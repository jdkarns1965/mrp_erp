<?php
session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Material.php';

$db = Database::getInstance();
$materialModel = new Material();
$errors = [];
$results = [];

// Check if CSV file exists
$csvFile = __DIR__ . '/../data/materials.csv';
$fileExists = file_exists($csvFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import']) && $fileExists) {
    try {
        $db->beginTransaction();
        
        // Read CSV file
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Could not open CSV file");
        }
        
        // Read header row and handle BOM if present
        $headers = fgetcsv($handle);
        if (!empty($headers[0]) && substr($headers[0], 0, 3) === "\xEF\xBB\xBF") {
            $headers[0] = substr($headers[0], 3); // Remove BOM
        }
        
        $results['headers'] = $headers;
        $results['rows'] = [];
        
        $rowCount = 0;
        $importedMaterials = 0;
        $skippedMaterials = 0;
        
        // Get default UOM and category IDs
        $defaultUomResult = $db->selectOne("SELECT id FROM units_of_measure WHERE code = 'EA' OR code = 'PC' LIMIT 1");
        $defaultUomId = $defaultUomResult['id'] ?? 5; // EA is ID 5
        
        $defaultCategoryResult = $db->selectOne("SELECT id FROM material_categories WHERE name = 'Raw Materials' LIMIT 1");
        $defaultCategoryId = $defaultCategoryResult['id'] ?? 1;
        
        // Get or create NIFCO supplier
        $nifcoSupplier = $db->selectOne("SELECT id FROM suppliers WHERE name LIKE '%NIFCO%' OR name LIKE '%iStore%'");
        if (!$nifcoSupplier) {
            $sql = "INSERT INTO suppliers (code, name, contact_person, email, is_active) VALUES (?, ?, ?, ?, 1)";
            $supplierId = $db->insert($sql, ['NIFCO', 'NIFCO iStore', 'iStore Support', 'support@nifco.com'], ['s', 's', 's', 's']);
        } else {
            $supplierId = $nifcoSupplier['id'];
        }
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowCount++;
            if ($rowCount > 500) break; // Safety limit
            
            // Create associative array with headers
            $data = array_combine($headers, $row);
            $results['rows'][] = $data;
            
            // Extract data based on your CSV structure
            $partNumber = trim($data['Part Number'] ?? '');
            $description = trim($data['Description'] ?? '');
            $unitPrice = floatval($data['Unit Price'] ?? 0);
            $units = trim($data['Units'] ?? '');
            $leadTime = intval($data['Lead Time'] ?? 0);
            $minOrderQty = floatval($data['Min Order Qty'] ?? 0);
            $fixedLotMultiplier = floatval($data['Fixed Lot Multiplier'] ?? 1);
            
            // Skip empty rows
            if (empty($partNumber) || empty($description)) {
                continue;
            }
            
            // Determine material type based on description/part number
            $materialType = 'other'; // Default
            $descriptionUpper = strtoupper($description);
            $partNumberUpper = strtoupper($partNumber);
            
            if (strpos($descriptionUpper, 'RESIN') !== false || 
                strpos($descriptionUpper, 'POLYMER') !== false ||
                strpos($descriptionUpper, 'PLASTIC') !== false) {
                $materialType = 'resin';
            } elseif (strpos($descriptionUpper, 'INSERT') !== false ||
                      strpos($descriptionUpper, 'THREAD') !== false ||
                      strpos($descriptionUpper, 'METAL') !== false) {
                $materialType = 'insert';
            } elseif (strpos($descriptionUpper, 'BOX') !== false ||
                      strpos($descriptionUpper, 'PACKAGE') !== false ||
                      strpos($descriptionUpper, 'BAG') !== false ||
                      strpos($descriptionUpper, 'CONTAINER') !== false) {
                $materialType = 'packaging';
            } elseif (strpos($descriptionUpper, 'PROMOTER') !== false ||
                      strpos($descriptionUpper, 'ADHESIVE') !== false ||
                      strpos($descriptionUpper, 'CLEANER') !== false ||
                      strpos($descriptionUpper, 'SOLVENT') !== false) {
                $materialType = 'consumable';
            } elseif (strpos($descriptionUpper, 'COMPONENT') !== false ||
                      strpos($descriptionUpper, 'PART') !== false) {
                $materialType = 'component';
            }
            
            // Map units to UOM using actual database IDs
            $uomId = $defaultUomId;
            $unitsUpper = strtoupper($units);
            if (!empty($unitsUpper)) {
                $uomMapping = [
                    'KG' => 1, 'KILOGRAM' => 1, 'KILOGRAMS' => 1,
                    'G' => 2, 'GRAM' => 2, 'GRAMS' => 2,
                    'L' => 3, 'LITER' => 3, 'LITERS' => 3,
                    'PC' => 4, 'PIECE' => 4, 'PIECES' => 4, 'PCS' => 4,
                    'EA' => 5, 'EACH' => 5,
                    'BOX' => 6, 'BOXES' => 6,
                    'M' => 7, 'METER' => 7, 'METERS' => 7,
                    'LB' => 8, 'POUND' => 8, 'POUNDS' => 8, 'LBS' => 8,
                    'OZ' => 9, 'OUNCE' => 9, 'OUNCES' => 9
                ];
                
                foreach ($uomMapping as $unit => $id) {
                    if (strpos($unitsUpper, $unit) !== false) {
                        $uomId = $id;
                        break;
                    }
                }
            }
            
            // Check if material already exists
            if ($materialModel->codeExists($partNumber)) {
                $skippedMaterials++;
                continue;
            }
            
            // Create material (truncate long names to fit database column)
            $materialData = [
                'material_code' => substr($partNumber, 0, 30), // Material code max 30 chars
                'name' => substr($description, 0, 100), // Name max 100 chars
                'description' => 'Imported from NIFCO iStore: ' . $description, // Full description
                'category_id' => $defaultCategoryId,
                'material_type' => $materialType,
                'uom_id' => $uomId,
                'min_stock_qty' => 0,
                'max_stock_qty' => 0,
                'reorder_point' => $minOrderQty > 0 ? $minOrderQty : 0,
                'lead_time_days' => $leadTime,
                'default_supplier_id' => $supplierId,
                'cost_per_unit' => $unitPrice,
                'is_lot_controlled' => 1,
                'is_active' => 1
            ];
            
            $materialModel->create($materialData);
            $importedMaterials++;
        }
        
        fclose($handle);
        $db->commit();
        
        $results['success'] = true;
        $results['stats'] = [
            'rows_processed' => $rowCount,
            'materials_imported' => $importedMaterials,
            'materials_skipped' => $skippedMaterials
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
            if (!empty($headers[0]) && substr($headers[0], 0, 3) === "\xEF\xBB\xBF") {
                $headers[0] = substr($headers[0], 3);
            }
            $preview['headers'] = $headers;
            $preview['rows'] = [];
            
            for ($i = 0; $i < 10 && ($row = fgetcsv($handle)) !== FALSE; $i++) {
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
    <title>Import Materials - MRP/ERP System</title>
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/mrp_erp/public/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Import Materials from NIFCO iStore</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                Materials CSV Import
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
                    <h3>📁 Materials CSV File Not Found</h3>
                    <p>Please place your <strong>materials.csv</strong> file in the following directory:</p>
                    <code>/var/www/html/mrp_erp/data/materials.csv</code>
                    
                    <h4>Expected CSV Format:</h4>
                    <ul>
                        <li><strong>Part Number</strong> - Material/component part number</li>
                        <li><strong>Description</strong> - Material description</li>
                        <li><strong>Unit Price</strong> - Cost per unit</li>
                        <li><strong>Units</strong> - Unit of measure</li>
                        <li><strong>Lead Time</strong> - Lead time in days</li>
                        <li><strong>Min Order Qty</strong> - Minimum order quantity</li>
                        <li><strong>Fixed Lot Multiplier</strong> - Lot size multiplier</li>
                    </ul>
                </div>
            
            <?php elseif (!empty($results) && $results['success']): ?>
                <div class="alert alert-success">
                    <h3>✅ Materials Import Successful!</h3>
                    <ul>
                        <li><strong><?php echo $results['stats']['rows_processed']; ?></strong> rows processed</li>
                        <li><strong><?php echo $results['stats']['materials_imported']; ?></strong> materials imported</li>
                        <li><strong><?php echo $results['stats']['materials_skipped']; ?></strong> materials skipped (already exist)</li>
                    </ul>
                    
                    <div class="btn-group mt-2">
                        <a href="materials/" class="btn btn-primary">View Materials</a>
                        <a href="index.php" class="btn btn-secondary">Dashboard</a>
                    </div>
                </div>
                
            <?php elseif (!empty($preview)): ?>
                <div class="alert alert-info">
                    <h3>📋 Materials File Found - Preview Data</h3>
                    <p>Found materials CSV with <strong><?php echo count($preview['headers']); ?></strong> columns. Here's a preview:</p>
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
                    <div class="alert alert-info">
                        <h4>🔍 Import Logic:</h4>
                        <ul>
                            <li><strong>Material Types:</strong> Auto-detected from descriptions (resin, insert, packaging, consumable, component)</li>
                            <li><strong>Supplier:</strong> All materials assigned to NIFCO iStore</li>
                            <li><strong>Units:</strong> Mapped to system UOMs (KG, G, PC, EA, etc.)</li>
                            <li><strong>Costs:</strong> Unit Price imported as cost_per_unit</li>
                            <li><strong>Lead Time:</strong> Used for MRP calculations</li>
                            <li><strong>Duplicates:</strong> Skipped if material code already exists</li>
                        </ul>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="import" class="btn btn-primary">Import Materials</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>