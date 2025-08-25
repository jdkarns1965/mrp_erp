<?php
declare(strict_types=1);

/**
 * Server-Sent Events (SSE) Endpoint for Real-Time Updates
 * PHP 8.2 optimized for live dashboard updates
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable Nginx buffering

// Prevent script timeout
set_time_limit(0);
ignore_user_abort(true);

require_once '../../classes/Database.php';
require_once '../../classes/Material.php';
require_once '../../classes/Inventory.php';
require_once '../../includes/Enums/ProductionStatus.php';

use MRP\Enums\ProductionStatus;

// Flush output immediately
ob_implicit_flush(true);
ob_end_flush();

$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? '0';
$clientId = $_GET['client'] ?? uniqid('sse_', true);

/**
 * Send SSE message to client
 */
function sendEvent(string $event, mixed $data, ?string $id = null): void
{
    if ($id) {
        echo "id: {$id}\n";
    }
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Check for inventory alerts using generator for memory efficiency
 */
function checkInventoryAlerts(): \Generator
{
    $db = Database::getInstance()->getConnection();
    $material = new Material();
    $inventory = new Inventory();
    
    // Check materials below reorder point
    $belowReorder = $material->getBelowReorderPoint();
    if (!empty($belowReorder)) {
        yield ['type' => 'inventory_alert', 'severity' => 'warning', 'items' => $belowReorder];
    }
    
    // Check expiring inventory
    $expiring = $inventory->getExpiringInventory(7); // 7 days
    if (!empty($expiring)) {
        yield ['type' => 'expiry_alert', 'severity' => 'critical', 'items' => $expiring];
    }
}

/**
 * Stream production status updates
 */
function streamProductionUpdates(): \Generator
{
    $db = Database::getInstance()->getConnection();
    
    $query = "SELECT 
                po.id,
                po.order_number,
                po.status,
                po.progress_percentage,
                p.name as product_name,
                po.updated_at
              FROM production_orders po
              JOIN products p ON po.product_id = p.id
              WHERE po.status IN ('in_progress', 'released')
              ORDER BY po.updated_at DESC
              LIMIT 10";
    
    $result = $db->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $status = ProductionStatus::from($row['status']);
        $row['status_label'] = $status->label();
        $row['status_color'] = $status->color();
        yield $row;
    }
}

/**
 * Get dashboard statistics
 */
function getDashboardStats(): array
{
    $db = Database::getInstance()->getConnection();
    
    // Using match expression (PHP 8.2)
    $stats = [];
    
    // Active production orders
    $result = $db->query("SELECT COUNT(*) as count FROM production_orders WHERE status = 'in_progress'");
    $stats['active_production'] = $result->fetch_assoc()['count'];
    
    // Pending orders
    $result = $db->query("SELECT COUNT(*) as count FROM customer_orders WHERE status = 'pending'");
    $stats['pending_orders'] = $result->fetch_assoc()['count'];
    
    // Low stock items
    $result = $db->query("SELECT COUNT(*) as count FROM materials WHERE current_stock < reorder_point");
    $stats['low_stock'] = $result->fetch_assoc()['count'];
    
    // Today's shipments
    $result = $db->query("SELECT COUNT(*) as count FROM customer_orders WHERE status = 'shipped' AND DATE(updated_at) = CURDATE()");
    $stats['todays_shipments'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

// Send initial connection event
sendEvent('connected', ['client_id' => $clientId, 'timestamp' => time()]);

// Main event loop
$iteration = 0;
while (true) {
    try {
        // Send heartbeat every 30 seconds
        if ($iteration % 6 === 0) {
            sendEvent('heartbeat', ['timestamp' => time()]);
        }
        
        // Check inventory alerts every minute
        if ($iteration % 12 === 0) {
            foreach (checkInventoryAlerts() as $alert) {
                sendEvent('alert', $alert);
            }
        }
        
        // Stream production updates every 10 seconds
        if ($iteration % 2 === 0) {
            $updates = [];
            foreach (streamProductionUpdates() as $update) {
                $updates[] = $update;
            }
            if (!empty($updates)) {
                sendEvent('production_update', $updates);
            }
        }
        
        // Send dashboard stats every 30 seconds
        if ($iteration % 6 === 0) {
            sendEvent('dashboard_stats', getDashboardStats());
        }
        
        // Check connection
        if (connection_aborted()) {
            break;
        }
        
        sleep(5); // Check every 5 seconds
        $iteration++;
        
    } catch (\Exception $e) {
        sendEvent('error', ['message' => 'Update failed', 'timestamp' => time()]);
        error_log("SSE Error: " . $e->getMessage());
        sleep(10); // Back off on error
    }
}