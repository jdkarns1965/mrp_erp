<?php
/**
 * Production Order Operations Tracking
 * Phase 2: Track progress of individual operations within production orders
 */

session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: index.php');
    exit;
}

// Handle operation status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operationId = $_POST['operation_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $quantityCompleted = $_POST['quantity_completed'] ?? 0;
    $quantityScrap = $_POST['quantity_scrap'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    $operator = $_POST['operator'] ?? '';
    
    try {
        $db->beginTransaction();
        
        if ($action === 'start') {
            $db->update("
                UPDATE production_order_operations 
                SET status = 'in_progress', 
                    actual_start_datetime = NOW(),
                    operator_name = ?
                WHERE id = ?
            ", [$operator, $operationId]);
            
        } elseif ($action === 'complete') {
            $db->update("
                UPDATE production_order_operations 
                SET status = 'completed', 
                    actual_end_datetime = NOW(),
                    quantity_completed = ?,
                    quantity_scrapped = ?,
                    notes = CONCAT(COALESCE(notes, ''), ?, '\n'),
                    operator_name = ?
                WHERE id = ?
            ", [$quantityCompleted, $quantityScrap, $notes, $operator, $operationId]);
            
            // Update production order progress
            $db->update("
                UPDATE production_orders po
                SET quantity_completed = (
                    SELECT MIN(quantity_completed) 
                    FROM production_order_operations 
                    WHERE production_order_id = po.id
                )
                WHERE id = (
                    SELECT production_order_id 
                    FROM production_order_operations 
                    WHERE id = ?
                )
            ", [$operationId]);
            
        } elseif ($action === 'setup_complete') {
            $db->update("
                UPDATE production_order_operations 
                SET setup_completed = TRUE
                WHERE id = ?
            ", [$operationId]);
        }
        
        $db->commit();
        header("Location: operations.php?id=$orderId&success=1");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// Get production order details
$productionOrder = $db->select("
    SELECT 
        po.*,
        p.product_code,
        p.name as product_name,
        co.customer_name,
        co.order_number as customer_order_number
    FROM production_orders po
    JOIN products p ON po.product_id = p.id
    LEFT JOIN customer_orders co ON po.customer_order_id = co.id
    WHERE po.id = ?
", [$orderId])[0] ?? null;

if (!$productionOrder) {
    header('Location: index.php');
    exit;
}

// Get operations for this production order
$operations = $db->select("
    SELECT 
        poo.*,
        pr.operation_description,
        pr.setup_time_minutes,
        pr.run_time_per_unit_seconds,
        wc.code as work_center_code,
        wc.name as work_center_name,
        wc.work_center_type,
        TIMESTAMPDIFF(MINUTE, poo.actual_start_datetime, poo.actual_end_datetime) as actual_duration_minutes,
        TIMESTAMPDIFF(MINUTE, poo.scheduled_start_datetime, poo.scheduled_end_datetime) as scheduled_duration_minutes,
        CASE 
            WHEN poo.status = 'completed' AND poo.actual_end_datetime > poo.scheduled_end_datetime THEN 'late'
            WHEN poo.status = 'completed' AND poo.actual_end_datetime <= poo.scheduled_end_datetime THEN 'on_time'
            WHEN poo.status = 'in_progress' AND NOW() > poo.scheduled_end_datetime THEN 'overdue'
            WHEN poo.status = 'in_progress' THEN 'in_progress'
            ELSE 'pending'
        END as performance_status
    FROM production_order_operations poo
    JOIN production_routes pr ON poo.route_id = pr.id
    JOIN work_centers wc ON poo.work_center_id = wc.id
    WHERE poo.production_order_id = ?
    ORDER BY poo.operation_sequence
", [$orderId]);

$success = $_GET['success'] ?? false;

?>

<style>
        .order-summary {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .summary-value {
            font-size: 1.125rem;
            color: #111827;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .operations-timeline {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .operation-card {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
            position: relative;
        }
        
        .operation-card:last-child {
            border-bottom: none;
        }
        
        .operation-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .operation-info {
            flex: 1;
        }
        
        .operation-sequence {
            background: #f3f4f6;
            color: #374151;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .operation-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .operation-details {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .operation-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-planned { background: #e5e7eb; color: #374151; }
        .status-ready { background: #dbeafe; color: #1d4ed8; }
        .status-in_progress { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .performance-late { color: #dc2626; font-weight: 600; }
        .performance-overdue { color: #dc2626; font-weight: 600; }
        .performance-on_time { color: #059669; font-weight: 600; }
        .performance-in_progress { color: #d97706; font-weight: 600; }
        
        .operation-progress {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .progress-item {
            text-align: center;
        }
        
        .progress-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2563eb;
        }
        
        .progress-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .operation-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-start { background: #059669; color: white; }
        .btn-complete { background: #dc2626; color: white; }
        .btn-setup { background: #f59e0b; color: white; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            margin: 5% auto;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        @media (max-width: 768px) {
            .operation-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .operation-actions {
                justify-content: center;
            }
        }
    </style>

<div class="container">
    <div class="card">
        <div class="card-header">
            Production Operations - <?= htmlspecialchars($productionOrder['order_number']) ?>
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">← Back to Production Orders</a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Operation updated successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Production Order Summary -->
        <div class="order-summary">
            <div class="summary-item">
                <span class="summary-label">Product</span>
                <span class="summary-value">
                    <?= htmlspecialchars($productionOrder['product_code']) ?>
                    <br><small style="font-weight: normal; color: #6b7280;">
                        <?= htmlspecialchars($productionOrder['product_name']) ?>
                    </small>
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Customer</span>
                <span class="summary-value">
                    <?= $productionOrder['customer_name'] ? htmlspecialchars($productionOrder['customer_name']) : 'Internal Order' ?>
                    <?php if ($productionOrder['customer_order_number']): ?>
                        <br><small style="font-weight: normal; color: #6b7280;">
                            <?= htmlspecialchars($productionOrder['customer_order_number']) ?>
                        </small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Quantity Ordered</span>
                <span class="summary-value"><?= number_format($productionOrder['quantity_ordered'], 2) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Quantity Completed</span>
                <span class="summary-value"><?= number_format($productionOrder['quantity_completed'], 2) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Progress</span>
                <span class="summary-value">
                    <?php 
                    $progressPercent = $productionOrder['quantity_ordered'] > 0 ? 
                        ($productionOrder['quantity_completed'] / $productionOrder['quantity_ordered']) * 100 : 0;
                    ?>
                    <?= round($progressPercent, 1) ?>%
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Status</span>
                <span class="summary-value">
                    <span class="status-badge status-<?= $productionOrder['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $productionOrder['status'])) ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Operations Timeline -->
        <div class="operations-timeline">
            <div style="padding: 1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Production Operations</h2>
                <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.875rem;">
                    Track the progress of each operation in the production process
                </p>
            </div>

            <?php if (empty($operations)): ?>
                <div style="padding: 2rem; text-align: center; color: #6b7280;">
                    No operations found for this production order. 
                    <a href="schedule.php">Schedule this order</a> to create operations.
                </div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="operation-card">
                        <div class="operation-header">
                            <div class="operation-info">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <span class="operation-sequence">Step <?= $operation['operation_sequence'] ?></span>
                                    <div class="operation-title"><?= htmlspecialchars($operation['operation_description']) ?></div>
                                </div>
                                <div class="operation-details">
                                    <strong><?= htmlspecialchars($operation['work_center_code']) ?></strong> - 
                                    <?= htmlspecialchars($operation['work_center_name']) ?>
                                    (<?= ucfirst($operation['work_center_type']) ?>)
                                </div>
                                <?php if ($operation['operator_name']): ?>
                                    <div class="operation-details">
                                        <strong>Operator:</strong> <?= htmlspecialchars($operation['operator_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="operation-status">
                                <span class="status-badge status-<?= $operation['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $operation['status'])) ?>
                                </span>
                                <?php if ($operation['performance_status'] !== 'pending'): ?>
                                    <span class="performance-<?= $operation['performance_status'] ?>">
                                        <?php
                                        switch ($operation['performance_status']) {
                                            case 'late': echo '⚠️ Late'; break;
                                            case 'overdue': echo '🚨 Overdue'; break;
                                            case 'on_time': echo '✅ On Time'; break;
                                            case 'in_progress': echo '🔄 In Progress'; break;
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Operation Progress -->
                        <div class="operation-progress">
                            <div class="progress-item">
                                <div class="progress-number"><?= number_format($operation['quantity_to_produce'], 0) ?></div>
                                <div class="progress-label">To Produce</div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-number"><?= number_format($operation['quantity_completed'], 0) ?></div>
                                <div class="progress-label">Completed</div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-number"><?= number_format($operation['quantity_scrapped'], 0) ?></div>
                                <div class="progress-label">Scrapped</div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-number">
                                    <?php if ($operation['scheduled_duration_minutes']): ?>
                                        <?= round($operation['scheduled_duration_minutes'] / 60, 1) ?>h
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                                <div class="progress-label">Scheduled Time</div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-number">
                                    <?php if ($operation['actual_duration_minutes']): ?>
                                        <?= round($operation['actual_duration_minutes'] / 60, 1) ?>h
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                                <div class="progress-label">Actual Time</div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-number">
                                    <?php if ($operation['setup_completed']): ?>
                                        ✅
                                    <?php else: ?>
                                        ❌
                                    <?php endif; ?>
                                </div>
                                <div class="progress-label">Setup</div>
                            </div>
                        </div>

                        <!-- Scheduled Times -->
                        <?php if ($operation['scheduled_start_datetime'] && $operation['scheduled_end_datetime']): ?>
                            <div style="margin-bottom: 1rem; padding: 0.75rem; background: #f3f4f6; border-radius: 6px; font-size: 0.875rem;">
                                <strong>Scheduled:</strong> 
                                <?= date('M j, Y g:i A', strtotime($operation['scheduled_start_datetime'])) ?> - 
                                <?= date('M j, Y g:i A', strtotime($operation['scheduled_end_datetime'])) ?>
                                
                                <?php if ($operation['actual_start_datetime']): ?>
                                    <br><strong>Actual Start:</strong> 
                                    <?= date('M j, Y g:i A', strtotime($operation['actual_start_datetime'])) ?>
                                <?php endif; ?>
                                
                                <?php if ($operation['actual_end_datetime']): ?>
                                    <br><strong>Actual End:</strong> 
                                    <?= date('M j, Y g:i A', strtotime($operation['actual_end_datetime'])) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Operation Actions -->
                        <div class="operation-actions">
                            <?php if ($operation['status'] === 'planned' || $operation['status'] === 'ready'): ?>
                                <?php if (!$operation['setup_completed']): ?>
                                    <button onclick="showSetupModal(<?= $operation['id'] ?>)" class="btn-action btn-setup">
                                        🔧 Complete Setup
                                    </button>
                                <?php endif; ?>
                                <button onclick="showStartModal(<?= $operation['id'] ?>)" class="btn-action btn-start">
                                    ▶️ Start Operation
                                </button>
                            <?php elseif ($operation['status'] === 'in_progress'): ?>
                                <button onclick="showCompleteModal(<?= $operation['id'] ?>, <?= $operation['quantity_to_produce'] ?>)" class="btn-action btn-complete">
                                    ✅ Complete Operation
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Notes -->
                        <?php if ($operation['notes']): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 6px 6px 0;">
                                <strong>Notes:</strong><br>
                                <?= nl2br(htmlspecialchars($operation['notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
    <!-- Setup Modal -->
    <div id="setupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('setupModal')">&times;</span>
                <h3 class="modal-title">Complete Setup</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="operation_id" id="setupOperationId">
                <input type="hidden" name="action" value="setup_complete">
                
                <div class="form-group">
                    <label for="setupOperator">Operator Name</label>
                    <input type="text" id="setupOperator" name="operator" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Complete Setup</button>
                    <button type="button" onclick="closeModal('setupModal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Start Modal -->
    <div id="startModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('startModal')">&times;</span>
                <h3 class="modal-title">Start Operation</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="operation_id" id="startOperationId">
                <input type="hidden" name="action" value="start">
                
                <div class="form-group">
                    <label for="startOperator">Operator Name</label>
                    <input type="text" id="startOperator" name="operator" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Start Operation</button>
                    <button type="button" onclick="closeModal('startModal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Complete Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('completeModal')">&times;</span>
                <h3 class="modal-title">Complete Operation</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="operation_id" id="completeOperationId">
                <input type="hidden" name="action" value="complete">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantityCompleted">Quantity Completed</label>
                        <input type="number" id="quantityCompleted" name="quantity_completed" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantityScrap">Quantity Scrapped</label>
                        <input type="number" id="quantityScrap" name="quantity_scrap" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="completeOperator">Operator Name</label>
                    <input type="text" id="completeOperator" name="operator" required>
                </div>
                
                <div class="form-group">
                    <label for="completeNotes">Notes (optional)</label>
                    <textarea id="completeNotes" name="notes" rows="3" placeholder="Any issues, observations, or comments..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Complete Operation</button>
                    <button type="button" onclick="closeModal('completeModal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showSetupModal(operationId) {
            document.getElementById('setupOperationId').value = operationId;
            document.getElementById('setupModal').style.display = 'block';
        }
        
        function showStartModal(operationId) {
            document.getElementById('startOperationId').value = operationId;
            document.getElementById('startModal').style.display = 'block';
        }
        
        function showCompleteModal(operationId, quantityToProduce) {
            document.getElementById('completeOperationId').value = operationId;
            document.getElementById('quantityCompleted').value = quantityToProduce;
            document.getElementById('completeModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['setupModal', 'startModal', 'completeModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>

<?php require_once '../../includes/footer.php'; ?>