<?php
/**
 * Gantt Chart View for Production Scheduling
 * Phase 2: Visual production schedule and capacity planning
 */

session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/Database.php';
require_once '../../classes/ProductionScheduler.php';

$db = Database::getInstance();
$scheduler = new ProductionScheduler();

// Get date range (default to current week + 2 weeks ahead)
$startDate = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : new DateTime();
$endDate = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : (clone $startDate)->add(new DateInterval('P21D'));

// Ensure we don't go too far back or forward (for performance)
$maxRange = new DateInterval('P90D'); // 90 days
if ($endDate->diff($startDate) > $maxRange) {
    $endDate = (clone $startDate)->add($maxRange);
}

// Get work center capacity data
$capacityData = $scheduler->getWorkCenterCapacity($startDate, $endDate);

// Get production schedule data
$scheduleData = $scheduler->getProductionSchedule($startDate, $endDate);

// Group data by work center
$workCenters = [];
$schedule = [];

foreach ($capacityData as $capacity) {
    $wcId = $capacity['id'];
    if (!isset($workCenters[$wcId])) {
        $workCenters[$wcId] = [
            'id' => $wcId,
            'code' => $capacity['code'],
            'name' => $capacity['name'],
            'type' => $capacity['work_center_type']
        ];
    }
}

foreach ($scheduleData as $operation) {
    $wcId = $operation['work_center_code']; // Using code as key for display
    if (!isset($schedule[$wcId])) {
        $schedule[$wcId] = [];
    }
    $schedule[$wcId][] = $operation;
}

// Generate date range for calendar
$dateRange = [];
$currentDate = clone $startDate;
while ($currentDate <= $endDate) {
    $dateRange[] = $currentDate->format('Y-m-d');
    $currentDate->add(new DateInterval('P1D'));
}

?>

<style>
        .gantt-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .gantt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .gantt-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        /* Top horizontal scrollbar */
        .gantt-top-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            height: 20px;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .gantt-top-scroll-content {
            height: 1px;
            min-width: 1200px;
        }
        
        /* Main gantt scroll area */
        .gantt-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 600px;
            position: relative;
        }
        
        .gantt-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }
        
        .gantt-table th,
        .gantt-table td {
            border: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        
        /* Sticky header row */
        .gantt-table thead {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #f9fafb;
        }
        
        .work-center-header {
            width: 200px;
            background: #f3f4f6;
            padding: 1rem;
            font-weight: 600;
            position: sticky;
            left: 0;
            z-index: 30;
        }
        
        /* Make date headers sticky too */
        .gantt-table thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            z-index: 20;
        }
        
        /* Work center header in sticky header needs higher z-index */
        .gantt-table thead .work-center-header {
            z-index: 30;
        }
        
        .date-header {
            min-width: 120px;
            padding: 0.5rem;
            text-align: center;
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .gantt-cell {
            min-width: 120px;
            height: 80px;
            padding: 2px;
            position: relative;
            background: #fafafa;
        }
        
        .gantt-operation {
            background: #3b82f6;
            color: white;
            padding: 2px 4px;
            margin: 1px 0;
            border-radius: 3px;
            font-size: 0.75rem;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            position: relative;
        }
        
        .operation-planned { background: #6b7280; }
        .operation-ready { background: #3b82f6; }
        .operation-in_progress { background: #f59e0b; }
        .operation-completed { background: #059669; }
        .operation-cancelled { background: #dc2626; }
        
        .priority-urgent { border-left: 4px solid #dc2626; }
        .priority-high { border-left: 4px solid #ea580c; }
        .priority-normal { border-left: 4px solid #6b7280; }
        .priority-low { border-left: 4px solid #9ca3af; }
        
        .capacity-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #e5e7eb;
        }
        
        .capacity-bar {
            height: 100%;
            background: #059669;
            transition: width 0.3s ease;
        }
        
        .capacity-over { background: #dc2626; }
        .capacity-high { background: #f59e0b; }
        .capacity-normal { background: #059669; }
        
        .legend {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        /* Modal for operation details */
        .operation-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            cursor: pointer;
        }
        
        .operation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            min-width: 350px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            cursor: default;
        }
        
        .operation-modal-header {
            padding: 1rem;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .operation-modal-title {
            font-weight: 600;
            font-size: 1rem;
            color: #111827;
        }
        
        .operation-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .operation-modal-close:hover {
            background: #e5e7eb;
            color: #111827;
        }
        
        .operation-modal-body {
            padding: 1rem;
        }
        
        .operation-detail-row {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .operation-detail-label {
            font-weight: 600;
            color: #6b7280;
            width: 120px;
            font-size: 0.875rem;
        }
        
        .operation-detail-value {
            flex: 1;
            color: #111827;
            font-size: 0.875rem;
        }
        
        .operation-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-planned { background: #e5e7eb; color: #6b7280; }
        .status-ready { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #fed7aa; color: #c2410c; }
        .status-completed { background: #bbf7d0; color: #14532d; }
        
        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-urgent { background: #fee2e2; color: #991b1b; }
        .priority-high { background: #fed7aa; color: #c2410c; }
        .priority-normal { background: #e5e7eb; color: #4b5563; }
        .priority-low { background: #f3f4f6; color: #6b7280; }
        
        .date-navigation {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .today-marker {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .weekend {
            background: rgba(156, 163, 175, 0.1);
        }
        
        /* Navigation aids */
        .gantt-navigation {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        
        .nav-button {
            padding: 0.25rem 0.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: background-color 0.2s;
        }
        
        .nav-button:hover {
            background: #2563eb;
        }
        
        .nav-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .timeline-overview {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }
        
        .timeline-indicator {
            width: 100px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            position: relative;
            cursor: pointer;
        }
        
        .timeline-current {
            height: 100%;
            background: #3b82f6;
            border-radius: 4px;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        @media (max-width: 768px) {
            .operation-modal {
                min-width: 90%;
                max-width: 90%;
                margin: 1rem;
            }
            
            .gantt-controls {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .date-navigation {
                flex-direction: column;
            }
            
            .legend {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .gantt-navigation {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            
            .timeline-overview {
                margin-left: 0;
                justify-content: center;
            }
            
            .nav-button {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            
            .gantt-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .work-center-header {
                width: 150px;
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            
            .date-header {
                min-width: 100px;
                font-size: 0.75rem;
            }
            
            .gantt-cell {
                min-width: 100px;
                height: 60px;
            }
            
            .gantt-operation {
                font-size: 0.625rem;
                padding: 1px 2px;
            }
            
            /* Make mobile scrolling smoother */
            .gantt-scroll {
                -webkit-overflow-scrolling: touch;
            }
            
            .gantt-top-scroll {
                -webkit-overflow-scrolling: touch;
            }
        }
        
        @media (max-width: 480px) {
            .work-center-header {
                width: 120px;
                padding: 0.25rem;
            }
            
            .date-header {
                min-width: 80px;
                padding: 0.25rem;
            }
            
            .gantt-cell {
                min-width: 80px;
                height: 50px;
            }
            
            .gantt-table {
                min-width: 800px; /* Reduced from 1200px for smaller screens */
            }
            
            .gantt-top-scroll-content {
                min-width: 800px;
            }
            
            .timeline-indicator {
                width: 60px;
            }
            
            .nav-button {
                font-size: 0.75rem;
                padding: 0.375rem;
            }
        }
    </style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            Production Gantt Chart
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">← Back to Production Orders</a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <div class="gantt-container">
            <div class="gantt-header">
                <div>
                    <h3>Production Schedule</h3>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                        Showing schedule from <?= $startDate->format('M j, Y') ?> to <?= $endDate->format('M j, Y') ?>
                    </p>
                </div>
                
                <div class="gantt-controls">
                    <div class="date-navigation">
                        <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                            <label for="start_date" style="font-size: 0.875rem;">From:</label>
                            <input 
                                type="date" 
                                id="start_date" 
                                name="start_date" 
                                value="<?= $startDate->format('Y-m-d') ?>"
                                style="padding: 0.25rem;">
                            
                            <label for="end_date" style="font-size: 0.875rem;">To:</label>
                            <input 
                                type="date" 
                                id="end_date" 
                                name="end_date" 
                                value="<?= $endDate->format('Y-m-d') ?>"
                                style="padding: 0.25rem;">
                            
                            <button type="submit" class="btn btn-primary btn-sm">Update</button>
                        </form>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?start_date=<?= (new DateTime())->format('Y-m-d') ?>&end_date=<?= (new DateTime())->add(new DateInterval('P7D'))->format('Y-m-d') ?>" 
                           class="btn btn-secondary btn-sm">This Week</a>
                        <a href="?start_date=<?= (new DateTime())->format('Y-m-d') ?>&end_date=<?= (new DateTime())->add(new DateInterval('P30D'))->format('Y-m-d') ?>" 
                           class="btn btn-secondary btn-sm">Next 30 Days</a>
                    </div>
                </div>
            </div>
            
            <!-- Top horizontal scrollbar -->
            <div class="gantt-top-scroll" id="topScroll">
                <div class="gantt-top-scroll-content"></div>
            </div>
            
            <!-- Navigation aids -->
            <div class="gantt-navigation">
                <button class="nav-button" onclick="jumpToToday()" title="Jump to today's column">📍 Jump to Today</button>
                <button class="nav-button" onclick="scrollGantt('left')" title="Scroll left by 2 columns">← Scroll Left</button>
                <button class="nav-button" onclick="scrollGantt('right')" title="Scroll right by 2 columns">Scroll Right →</button>
                <button class="nav-button" onclick="showHelp()" title="Show keyboard shortcuts">❓ Help</button>
                
                <div class="timeline-overview">
                    <span>Timeline:</span>
                    <div class="timeline-indicator" onclick="jumpToPosition(event)" title="Click to jump to any position">
                        <div class="timeline-current" id="timelineCurrent"></div>
                    </div>
                    <span id="timelineLabel"><?= $startDate->format('M j') ?> - <?= $endDate->format('M j') ?></span>
                </div>
            </div>
            
            <div class="gantt-scroll" id="mainScroll">
                <table class="gantt-table">
                    <thead>
                        <tr>
                            <th class="work-center-header">Work Center</th>
                            <?php foreach ($dateRange as $date): ?>
                                <?php 
                                $dateObj = new DateTime($date);
                                $isToday = $date === date('Y-m-d');
                                $isWeekend = in_array($dateObj->format('w'), ['0', '6']); // Sunday = 0, Saturday = 6
                                $classes = [];
                                if ($isToday) $classes[] = 'today-marker';
                                if ($isWeekend) $classes[] = 'weekend';
                                ?>
                                <th class="date-header <?= implode(' ', $classes) ?>" data-date="<?= $date ?>">
                                    <div><?= $dateObj->format('D') ?></div>
                                    <div><?= $dateObj->format('M j') ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workCenters)): ?>
                            <tr>
                                <td class="work-center-header">No Work Centers</td>
                                <?php foreach ($dateRange as $date): ?>
                                    <td class="gantt-cell">
                                        <em style="color: #6b7280; font-size: 0.75rem;">No data</em>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workCenters as $workCenter): ?>
                                <tr>
                                    <td class="work-center-header">
                                        <div style="font-weight: 600;"><?= htmlspecialchars($workCenter['code']) ?></div>
                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                            <?= htmlspecialchars($workCenter['name']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">
                                            <?= ucfirst($workCenter['type']) ?>
                                        </div>
                                    </td>
                                    
                                    <?php foreach ($dateRange as $date): ?>
                                        <?php
                                        $dateObj = new DateTime($date);
                                        $isToday = $date === date('Y-m-d');
                                        $isWeekend = in_array($dateObj->format('w'), ['0', '6']);
                                        $classes = ['gantt-cell'];
                                        if ($isToday) $classes[] = 'today-marker';
                                        if ($isWeekend) $classes[] = 'weekend';
                                        
                                        // Get operations for this work center on this date
                                        $dailyOperations = [];
                                        $wcCode = $workCenter['code'];
                                        if (isset($schedule[$wcCode])) {
                                            foreach ($schedule[$wcCode] as $operation) {
                                                $opStartDate = date('Y-m-d', strtotime($operation['scheduled_start_datetime']));
                                                $opEndDate = date('Y-m-d', strtotime($operation['scheduled_end_datetime']));
                                                
                                                // Check if operation spans this date
                                                if ($date >= $opStartDate && $date <= $opEndDate) {
                                                    $dailyOperations[] = $operation;
                                                }
                                            }
                                        }
                                        
                                        // Calculate capacity utilization for this date
                                        $utilization = 0;
                                        foreach ($capacityData as $capacity) {
                                            if ($capacity['id'] == $workCenter['id'] && $capacity['date'] === $date) {
                                                $utilization = $capacity['utilization_percentage'] ?? 0;
                                                break;
                                            }
                                        }
                                        ?>
                                        <td class="<?= implode(' ', $classes) ?>">
                                            <?php foreach ($dailyOperations as $operation): ?>
                                                <div class="gantt-operation operation-<?= $operation['operation_status'] ?> priority-<?= $operation['priority_level'] ?>"
                                                     data-operation='<?= json_encode($operation) ?>'
                                                     onclick="showOperationDetails(this)">
                                                    <?= htmlspecialchars($operation['order_number']) ?>
                                                    <br><small><?= htmlspecialchars($operation['product_code']) ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (!$isWeekend): ?>
                                                <div class="capacity-indicator">
                                                    <div class="capacity-bar <?= $utilization > 100 ? 'capacity-over' : ($utilization > 80 ? 'capacity-high' : 'capacity-normal') ?>"
                                                         style="width: <?= min($utilization, 100) ?>%"
                                                         title="Capacity: <?= round($utilization, 1) ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div style="font-weight: 600; margin-right: 1rem;">Legend:</div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <div class="legend-item">
                    <div class="legend-color operation-planned"></div>
                    <span>Planned</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color operation-ready"></div>
                    <span>Ready</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color operation-in_progress"></div>
                    <span>In Progress</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color operation-completed"></div>
                    <span>Completed</span>
                </div>
            </div>
            
            <div style="border-left: 1px solid #e5e7eb; padding-left: 1rem; margin-left: 1rem;">
                <div style="font-weight: 600; margin-bottom: 0.5rem;">Priority:</div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #dc2626;"></div>
                        <span>Urgent</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #ea580c;"></div>
                        <span>High</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #6b7280;"></div>
                        <span>Normal</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #9ca3af;"></div>
                        <span>Low</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Operation Details Modal -->
<div id="operationModalOverlay" class="operation-modal-overlay">
    <div class="operation-modal" onclick="event.stopPropagation()">
        <div class="operation-modal-header">
            <div class="operation-modal-title" id="modalTitle">Operation Details</div>
            <button class="operation-modal-close" onclick="closeOperationModal()">×</button>
        </div>
        <div class="operation-modal-body" id="modalBody">
            <!-- Content will be inserted here -->
        </div>
    </div>
</div>

<script>
        // Enhanced Gantt Chart functionality
        let isScrolling = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            const topScroll = document.getElementById('topScroll');
            const mainScroll = document.getElementById('mainScroll');
            
            // Synchronize horizontal scrolling between top scrollbar and main content
            topScroll.addEventListener('scroll', function() {
                if (!isScrolling) {
                    isScrolling = true;
                    mainScroll.scrollLeft = topScroll.scrollLeft;
                    updateTimelineIndicator();
                    requestAnimationFrame(() => isScrolling = false);
                }
            });
            
            mainScroll.addEventListener('scroll', function() {
                if (!isScrolling) {
                    isScrolling = true;
                    topScroll.scrollLeft = mainScroll.scrollLeft;
                    updateTimelineIndicator();
                    requestAnimationFrame(() => isScrolling = false);
                }
            });
            
            // Initialize timeline indicator
            updateTimelineIndicator();
            
            // Find today's column and add data attributes for navigation
            const dateHeaders = document.querySelectorAll('.date-header');
            const today = new Date().toISOString().split('T')[0];
            let todayColumnIndex = -1;
            
            dateHeaders.forEach((header, index) => {
                const headerDate = header.getAttribute('data-date') || extractDateFromHeader(header);
                if (headerDate === today) {
                    todayColumnIndex = index;
                    header.setAttribute('data-is-today', 'true');
                }
            });
            
            // Store today's position for navigation
            window.todayColumnIndex = todayColumnIndex;
        });
        
        function extractDateFromHeader(header) {
            // Extract date from header text (fallback if data-date not set)
            const text = header.textContent.trim();
            const lines = text.split('\n');
            if (lines.length >= 2) {
                const monthDay = lines[1].trim();
                const year = new Date().getFullYear();
                return new Date(`${monthDay} ${year}`).toISOString().split('T')[0];
            }
            return null;
        }
        
        function jumpToToday() {
            if (window.todayColumnIndex >= 0) {
                const columnWidth = 120; // min-width of date columns
                const workCenterWidth = 200;
                const scrollPosition = (window.todayColumnIndex * columnWidth) - 200; // Offset for visibility
                
                document.getElementById('mainScroll').scrollLeft = Math.max(0, scrollPosition);
                document.getElementById('topScroll').scrollLeft = Math.max(0, scrollPosition);
                updateTimelineIndicator();
            } else {
                alert('Today is not visible in the current date range.');
            }
        }
        
        function scrollGantt(direction) {
            const scrollAmount = 240; // Scroll by 2 columns
            const mainScroll = document.getElementById('mainScroll');
            const topScroll = document.getElementById('topScroll');
            
            const currentScroll = mainScroll.scrollLeft;
            const newScroll = direction === 'left' 
                ? Math.max(0, currentScroll - scrollAmount)
                : currentScroll + scrollAmount;
            
            mainScroll.scrollLeft = newScroll;
            topScroll.scrollLeft = newScroll;
            updateTimelineIndicator();
        }
        
        function jumpToPosition(event) {
            const indicator = event.currentTarget;
            const rect = indicator.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const percentage = clickX / rect.width;
            
            const mainScroll = document.getElementById('mainScroll');
            const topScroll = document.getElementById('topScroll');
            const maxScroll = mainScroll.scrollWidth - mainScroll.clientWidth;
            const newScroll = maxScroll * percentage;
            
            mainScroll.scrollLeft = newScroll;
            topScroll.scrollLeft = newScroll;
            updateTimelineIndicator();
        }
        
        function updateTimelineIndicator() {
            const mainScroll = document.getElementById('mainScroll');
            const timelineCurrent = document.getElementById('timelineCurrent');
            
            if (mainScroll && timelineCurrent) {
                const scrollPercentage = mainScroll.scrollLeft / (mainScroll.scrollWidth - mainScroll.clientWidth);
                timelineCurrent.style.width = (scrollPercentage * 100) + '%';
            }
        }
        
        function showHelp() {
            alert(`Gantt Chart Navigation Help:

🖱️ Mouse Controls:
• Use top scrollbar or main area to scroll horizontally
• Click timeline indicator to jump to any position
• Click operations for detailed information

⌨️ Keyboard Shortcuts:
• Home - Jump to today
• Ctrl + ← - Scroll left
• Ctrl + → - Scroll right

📱 Mobile:
• Swipe to scroll horizontally
• Tap navigation buttons
• Responsive design optimized for touch`);
        }
        
        function showOperationDetails(element) {
            const operation = JSON.parse(element.dataset.operation);
            
            // Update modal title
            document.getElementById('modalTitle').innerHTML = `${operation.order_number} - ${operation.product_code}`;
            
            // Format dates nicely
            const startDate = new Date(operation.scheduled_start_datetime);
            const endDate = new Date(operation.scheduled_end_datetime);
            const duration = Math.round(operation.duration_minutes / 60 * 10) / 10;
            
            // Build modal content with better formatting
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Product:</div>
                    <div class="operation-detail-value">${operation.product_name}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Operation:</div>
                    <div class="operation-detail-value">${operation.operation_description}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Work Center:</div>
                    <div class="operation-detail-value">${operation.work_center_code} - ${operation.work_center_name}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Start Time:</div>
                    <div class="operation-detail-value">${startDate.toLocaleDateString()} ${startDate.toLocaleTimeString()}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">End Time:</div>
                    <div class="operation-detail-value">${endDate.toLocaleDateString()} ${endDate.toLocaleTimeString()}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Duration:</div>
                    <div class="operation-detail-value">${duration} hours</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Priority:</div>
                    <div class="operation-detail-value">
                        <span class="priority-badge priority-${operation.priority_level}">
                            ${operation.priority_level.toUpperCase()}
                        </span>
                    </div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Status:</div>
                    <div class="operation-detail-value">
                        <span class="operation-status-badge status-${operation.operation_status}">
                            ${operation.operation_status.replace('_', ' ')}
                        </span>
                    </div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Order Number:</div>
                    <div class="operation-detail-value">${operation.order_number}</div>
                </div>
                
                <div class="operation-detail-row">
                    <div class="operation-detail-label">Sequence:</div>
                    <div class="operation-detail-value">#${operation.operation_sequence}</div>
                </div>
            `;
            
            // Show modal
            document.getElementById('operationModalOverlay').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        function closeOperationModal() {
            document.getElementById('operationModalOverlay').style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
        
        // Close modal when clicking overlay
        document.getElementById('operationModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOperationModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOperationModal();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT') return; // Don't interfere with form inputs
            
            switch(e.key) {
                case 'Home':
                    e.preventDefault();
                    jumpToToday();
                    break;
                case 'ArrowLeft':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        scrollGantt('left');
                    }
                    break;
                case 'ArrowRight':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        scrollGantt('right');
                    }
                    break;
            }
        });
    </script>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>