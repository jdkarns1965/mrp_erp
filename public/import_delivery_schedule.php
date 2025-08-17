<?php
session_start();
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();
$errors = [];
$results = [];

// Check if CSV file exists
$csvFile = __DIR__ . '/../data/delivery_schedule.csv';
$fileExists = file_exists($csvFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import']) && $fileExists) {
    try {
        $db->beginTransaction();
        
        // Read CSV file
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Could not open CSV file");
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        $results['headers'] = $headers;
        $results['rows'] = [];
        
        $rowCount = 0;
        $importedOrders = 0;
        $importedOrderDetails = 0;
        
        // Get existing UOM for pieces
        $uomResult = $db->selectOne("SELECT id FROM units_of_measure WHERE code = 'PC' OR code = 'EA' LIMIT 1");
        $defaultUomId = $uomResult['id'] ?? 4; // Default to PC
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowCount++;
            if ($rowCount > 100) break; // Limit to first 100 rows for safety
            
            // Create associative array with headers
            $data = array_combine($headers, $row);
            $results['rows'][] = $data;
            
            // Extract data based on your actual column names
            $partNumber = trim($data['Item Number'] ?? $data['Supplier Item'] ?? '');
            $customerLocation = trim($data['Ship-To Location'] ?? ''); // This is your customer
            $promisedDate = trim($data['Promised Date'] ?? '');
            $needByDate = trim($data['Need-By Date'] ?? '');
            $quantity = floatval($data['Quantity Ordered'] ?? 0);
            $quantityShipped = floatval($data['Quantity Received'] ?? 0); // "Received" by customer = shipped by you
            $poNumber = trim($data['PO Number'] ?? '');
            $itemDescription = trim($data['Item Description'] ?? '');
            $uom = trim($data['UOM'] ?? '');
            $organization = trim($data['Organization'] ?? '');
            
            // Determine customer name - use Ship-To Location or Organization
            $customerName = !empty($customerLocation) ? $customerLocation : $organization;
            if (empty($customerName)) {
                $customerName = 'Customer-' . $poNumber; // Fallback
            }
            
            // Map NIFCO plant locations to standard abbreviations
            $customerAbbrev = '';
            $cleanCustomerName = strtoupper(trim($customerName));
            if (strpos($cleanCustomerName, 'SHELBYVILLE') !== false || strpos($cleanCustomerName, 'SLB') !== false) {
                $customerAbbrev = 'SLB';
                $customerName = 'NIFCO Shelbyville, KY';
            } elseif (strpos($cleanCustomerName, 'CANAL WINCHESTER') !== false || 
                      strpos($cleanCustomerName, 'CWH') !== false || 
                      strpos($cleanCustomerName, 'CNL') !== false) {
                $customerAbbrev = 'CWH';
                $customerName = 'NIFCO Canal Winchester, OH';
            } elseif (strpos($cleanCustomerName, 'NIFCO') !== false) {
                // Generic NIFCO if we can't identify specific plant
                $customerAbbrev = 'NIFCO';
            } else {
                // For non-NIFCO customers, create abbreviation from name
                $customerAbbrev = substr(preg_replace('/[^A-Za-z0-9]/', '', $customerName), 0, 6);
            }
            
            // Skip empty rows
            if (empty($partNumber) || empty($customerName) || $quantity <= 0) {
                continue;
            }
            
            // Use Need-By Date as priority, fall back to Promised Date
            $deliveryDate = !empty($needByDate) ? $needByDate : $promisedDate;
            if (empty($deliveryDate)) {
                continue; // Skip if no date
            }
            
            // Convert date format if needed
            $deliveryDate = date('Y-m-d', strtotime($deliveryDate));
            
            // Handle blanket PO structure with duplicate release numbers
            $blanketPO = '';
            $releaseNumber = '';
            if (!empty($poNumber)) {
                if (strpos($poNumber, '-') !== false) {
                    $parts = explode('-', $poNumber, 2);
                    $blanketPO = $parts[0];
                    $releaseNumber = $parts[1];
                } else {
                    $blanketPO = $poNumber;
                    $releaseNumber = '001'; // Default release if no dash
                }
                
                // Create unique order number: PO-Release-Date-Customer to handle duplicate releases
                $orderNumber = $poNumber . '-' . date('Ymd', strtotime($deliveryDate)) . '-' . $customerAbbrev;
            } else {
                $orderNumber = 'IMP-' . date('Ymd') . '-' . str_pad($rowCount, 3, '0', STR_PAD_LEFT);
            }
            
            // Calculate outstanding quantity (ordered minus shipped)
            $outstandingQty = $quantity - $quantityShipped;
            if ($outstandingQty <= 0) {
                continue; // Skip if already fully shipped
            }
            
            // Build comprehensive notes
            $notes = [];
            if (!empty($itemDescription)) $notes[] = "Product: " . $itemDescription;
            if (!empty($organization)) $notes[] = "Customer Org: " . $organization;
            if (!empty($blanketPO)) $notes[] = "Blanket PO: " . $blanketPO;
            if (!empty($releaseNumber)) $notes[] = "Release: " . $releaseNumber;
            $notes[] = "Original PO: " . $poNumber; // Keep original PO for reference
            if ($quantityShipped > 0) $notes[] = "Already shipped: " . $quantityShipped . " of " . $quantity;
            if (!empty($promisedDate) && $promisedDate !== $deliveryDate) $notes[] = "Original promise date: " . $promisedDate;
            if (!empty($uom)) $notes[] = "UOM: " . $uom;
            $notesText = implode("; ", $notes);
            
            // Check if product exists, if not create it
            $product = $db->selectOne("SELECT id FROM products WHERE product_code = ?", [$partNumber], ['s']);
            
            if (!$product) {
                // Create new product
                $sql = "INSERT INTO products (product_code, name, description, category_id, uom_id, is_active) 
                        VALUES (?, ?, ?, 1, ?, 1)";
                $productId = $db->insert($sql, [
                    $partNumber,
                    !empty($itemDescription) ? $itemDescription : ($partNumber . ' - Manufactured Product'),
                    'Product imported from delivery schedule - manufactured item',
                    $defaultUomId
                ], ['s', 's', 's', 'i']);
            } else {
                $productId = $product['id'];
            }
            
            // Check if order already exists using the unique order number
            // This handles duplicate release numbers by including date and customer in the check
            $existingOrder = $db->selectOne(
                "SELECT id FROM customer_orders WHERE order_number = ?", 
                [$orderNumber], 
                ['s']
            );
            
            if (!$existingOrder) {
                // Create new customer order with blanket PO context
                $orderNotes = "Customer delivery commitment";
                if (!empty($blanketPO)) {
                    $orderNotes .= " - Blanket PO: " . $blanketPO;
                }
                if (!empty($releaseNumber)) {
                    $orderNotes .= " Release: " . $releaseNumber;
                }
                $orderNotes .= " - " . $notesText;
                
                $sql = "INSERT INTO customer_orders (order_number, customer_name, order_date, required_date, notes, status)
                        VALUES (?, ?, CURDATE(), ?, ?, 'confirmed')";
                $orderId = $db->insert($sql, [
                    $orderNumber,
                    $customerName,
                    $deliveryDate,
                    $orderNotes
                ], ['s', 's', 's', 's']);
                $importedOrders++;
            } else {
                $orderId = $existingOrder['id'];
            }
            
            // Add order detail with outstanding quantity (what still needs to be manufactured/shipped)
            $sql = "INSERT INTO customer_order_details (order_id, product_id, quantity, uom_id, notes)
                    VALUES (?, ?, ?, ?, ?)";
            $db->insert($sql, [
                $orderId,
                $productId,
                $outstandingQty, // Use outstanding quantity (ordered - already shipped)
                $defaultUomId,
                $notesText
            ], ['i', 'i', 'd', 'i', 's']);
            $importedOrderDetails++;
        }
        
        fclose($handle);
        $db->commit();
        
        $results['success'] = true;
        $results['stats'] = [
            'rows_processed' => $rowCount,
            'orders_created' => $importedOrders,
            'order_details_created' => $importedOrderDetails
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        $errors[] = 'Import failed: ' . $e->getMessage();
    }
}

// Preview mode - just read first few rows
$preview = [];
if ($fileExists && !isset($_POST['import'])) {
    try {
        $handle = fopen($csvFile, 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            $preview['headers'] = $headers;
            $preview['rows'] = [];
            
            // Read first 5 rows for preview
            for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== FALSE; $i++) {
                $preview['rows'][] = array_combine($headers, $row);
            }
            fclose($handle);
        }
    } catch (Exception $e) {
        $errors[] = 'Could not preview file: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Delivery Schedule - MRP/ERP System</title>
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/mrp_erp/public/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Import Delivery Schedule</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                CSV File Import
                <div style="float: right;">
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <div style="clear: both;"></div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!$fileExists): ?>
                <div class="alert alert-warning">
                    <h3>📁 CSV File Not Found</h3>
                    <p>Please place your <strong>delivery_schedule.csv</strong> file in the following directory:</p>
                    <code>/var/www/html/mrp_erp/data/delivery_schedule.csv</code>
                    
                    <h4>Expected CSV Format:</h4>
                    <p>Your CSV file should have these exact column names:</p>
                    <ul>
                        <li><strong>PO Number</strong> - Purchase order number</li>
                        <li><strong>Supplier Item</strong> - Supplier's item number</li>
                        <li><strong>Item Description</strong> - Description of the item</li>
                        <li><strong>Quantity Ordered</strong> - Total quantity ordered</li>
                        <li><strong>Quantity Received</strong> - Amount already received</li>
                        <li><strong>Promised Date</strong> - Supplier's promised delivery date</li>
                        <li><strong>Need-By Date</strong> - When you need the items</li>
                        <li><strong>Item Number</strong> - Your internal item number</li>
                        <li><strong>Supplier</strong> - Supplier name</li>
                        <li><strong>UOM</strong> - Unit of measure</li>
                        <li><strong>Ship-To Location</strong> - Delivery location</li>
                        <li><strong>Organization</strong> - Organization/division</li>
                    </ul>
                    
                    <p>Once you've placed the file, refresh this page to continue.</p>
                </div>
            
            <?php elseif (!empty($results) && $results['success']): ?>
                <div class="alert alert-success">
                    <h3>✅ Import Successful!</h3>
                    <ul>
                        <li><strong><?php echo $results['stats']['rows_processed']; ?></strong> rows processed</li>
                        <li><strong><?php echo $results['stats']['orders_created']; ?></strong> new orders created</li>
                        <li><strong><?php echo $results['stats']['order_details_created']; ?></strong> order line items imported</li>
                    </ul>
                    
                    <div class="btn-group mt-2">
                        <a href="index.php" class="btn btn-primary">View Dashboard</a>
                        <a href="mrp/" class="btn btn-success">Run MRP on Imported Orders</a>
                        <a href="orders/" class="btn btn-secondary">View Orders</a>
                    </div>
                </div>
                
            <?php elseif (!empty($preview)): ?>
                <div class="alert alert-info">
                    <h3>📋 File Found - Preview Data</h3>
                    <p>Found CSV file with <strong><?php echo count($preview['headers']); ?></strong> columns. Here's a preview of the first few rows:</p>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($preview['headers'] as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST">
                    <div class="alert alert-warning">
                        <h4>⚠️ Before Importing:</h4>
                        <ul>
                            <li>This will create new products for any part numbers not already in the system</li>
                            <li>This will create customer orders from your delivery schedule</li>
                            <li>Existing orders with the same order number will not be duplicated</li>
                            <li>Limited to first 100 rows for safety</li>
                        </ul>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="import" class="btn btn-primary">Import Data</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>