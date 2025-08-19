<?php
/**
 * MRP System Validation Script
 * Comprehensive testing of Phase 1 functionality
 * Tests all major workflows and edge cases
 */

require_once 'classes/Database.php';
require_once 'classes/MRP.php';
require_once 'classes/Material.php';
require_once 'classes/Product.php';
require_once 'classes/Inventory.php';
require_once 'classes/BOM.php';

class MRPValidator {
    private $db;
    private $mrp;
    private $material;
    private $product;
    private $inventory;
    private $bom;
    private $testResults = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mrp = new MRP();
        $this->material = new Material();
        $this->product = new Product();
        $this->inventory = new Inventory();
        $this->bom = new BOM();
    }
    
    public function runAllTests() {
        echo "=== MRP SYSTEM VALIDATION TESTS ===\n";
        echo "Starting comprehensive testing at " . date('Y-m-d H:i:s') . "\n\n";
        
        // Test 1: Database connectivity and basic operations
        $this->testDatabaseConnectivity();
        
        // Test 2: Basic CRUD operations
        $this->testBasicCRUDOperations();
        
        // Test 3: BOM explosion functionality
        $this->testBOMExplosion();
        
        // Test 4: Inventory calculations
        $this->testInventoryCalculations();
        
        // Test 5: MRP calculation workflows
        $this->testMRPCalculations();
        
        // Test 6: Edge cases and error handling
        $this->testEdgeCases();
        
        // Test 7: Performance testing
        $this->testPerformance();
        
        // Display results summary
        $this->displayResults();
    }
    
    private function testDatabaseConnectivity() {
        echo "1. Testing Database Connectivity...\n";
        
        try {
            // Test basic connection
            $result = $this->db->select("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'mrp_erp'");
            $tableCount = $result[0]['table_count'];
            
            if ($tableCount >= 15) {
                $this->addResult('Database Connectivity', 'PASS', "Found $tableCount tables");
            } else {
                $this->addResult('Database Connectivity', 'FAIL', "Only found $tableCount tables");
            }
            
            // Test foreign key constraints
            $constraints = $this->db->select("
                SELECT COUNT(*) as constraint_count 
                FROM information_schema.table_constraints 
                WHERE table_schema = 'mrp_erp' AND constraint_type = 'FOREIGN KEY'
            ");
            
            $constraintCount = $constraints[0]['constraint_count'];
            if ($constraintCount >= 10) {
                $this->addResult('Foreign Key Constraints', 'PASS', "Found $constraintCount constraints");
            } else {
                $this->addResult('Foreign Key Constraints', 'FAIL', "Only found $constraintCount constraints");
            }
            
        } catch (Exception $e) {
            $this->addResult('Database Connectivity', 'FAIL', $e->getMessage());
        }
    }
    
    private function testBasicCRUDOperations() {
        echo "2. Testing Basic CRUD Operations...\n";
        
        try {
            // Test material operations
            $materials = $this->material->all();
            if (count($materials) >= 10) {
                $this->addResult('Material CRUD', 'PASS', "Retrieved " . count($materials) . " materials");
            } else {
                $this->addResult('Material CRUD', 'FAIL', "Only found " . count($materials) . " materials");
            }
            
            // Test product operations
            $products = $this->product->all();
            if (count($products) >= 4) {
                $this->addResult('Product CRUD', 'PASS', "Retrieved " . count($products) . " products");
            } else {
                $this->addResult('Product CRUD', 'FAIL', "Only found " . count($products) . " products");
            }
            
            // Test specific material retrieval
            $material = $this->material->find(1);
            if ($material && $material['material_code'] === 'RES-001') {
                $this->addResult('Material Find', 'PASS', "Found material RES-001");
            } else {
                $this->addResult('Material Find', 'FAIL', "Could not find material RES-001");
            }
            
        } catch (Exception $e) {
            $this->addResult('Basic CRUD Operations', 'FAIL', $e->getMessage());
        }
    }
    
    private function testBOMExplosion() {
        echo "3. Testing BOM Explosion Functionality...\n";
        
        try {
            // Test simple BOM explosion (Product 1 - simple product)
            $bomExplosion = $this->bom->explodeBOM(1, 10);
            if (!empty($bomExplosion)) {
                $this->addResult('Simple BOM Explosion', 'PASS', "Exploded BOM for 10 units of PROD-001");
                
                // Verify quantities are correct (should need 1.2 kg of ABS for 10 containers)
                $expectedABS = 10 * 0.120 * 1.05; // with 5% scrap
                $actualABS = $bomExplosion[0]['total_required'];
                
                if (abs($actualABS - $expectedABS) < 0.01) {
                    $this->addResult('BOM Quantity Calculation', 'PASS', "Correct quantity: $actualABS kg");
                } else {
                    $this->addResult('BOM Quantity Calculation', 'FAIL', "Expected $expectedABS, got $actualABS");
                }
            } else {
                $this->addResult('Simple BOM Explosion', 'FAIL', "No BOM explosion results");
            }
            
            // Test complex BOM explosion (Product 3 - multi-material)
            $complexBOM = $this->bom->explodeBOM(3, 5);
            if (count($complexBOM) >= 5) {
                $this->addResult('Complex BOM Explosion', 'PASS', "Exploded complex BOM with " . count($complexBOM) . " materials");
            } else {
                $this->addResult('Complex BOM Explosion', 'FAIL', "Complex BOM explosion incomplete");
            }
            
        } catch (Exception $e) {
            $this->addResult('BOM Explosion', 'FAIL', $e->getMessage());
        }
    }
    
    private function testInventoryCalculations() {
        echo "4. Testing Inventory Calculations...\n";
        
        try {
            // Test available quantity calculation
            $availableABS = $this->inventory->getAvailableQuantity('material', 1);
            if ($availableABS > 800) {
                $this->addResult('Inventory Available Qty', 'PASS', "ABS available: $availableABS kg");
            } else {
                $this->addResult('Inventory Available Qty', 'FAIL', "ABS availability issue: $availableABS kg");
            }
            
            // Test below reorder point detection
            $belowReorder = $this->material->getBelowReorderPoint();
            if (count($belowReorder) >= 3) {
                $this->addResult('Below Reorder Detection', 'PASS', count($belowReorder) . " materials below reorder");
            } else {
                $this->addResult('Below Reorder Detection', 'WARN', "Only " . count($belowReorder) . " materials below reorder");
            }
            
            // Test expiring inventory
            $expiring = $this->inventory->getExpiringInventory(30);
            if (count($expiring) >= 2) {
                $this->addResult('Expiring Inventory', 'PASS', count($expiring) . " items expiring soon");
            } else {
                $this->addResult('Expiring Inventory', 'WARN', "Only " . count($expiring) . " items expiring");
            }
            
        } catch (Exception $e) {
            $this->addResult('Inventory Calculations', 'FAIL', $e->getMessage());
        }
    }
    
    private function testMRPCalculations() {
        echo "5. Testing MRP Calculation Workflows...\n";
        
        try {
            // Test MRP for standard order (Order 1)
            $mrpResult1 = $this->mrp->runMRP(1);
            if ($mrpResult1['success']) {
                $this->addResult('Standard MRP Calculation', 'PASS', "Order 1 processed successfully");
                
                // Check if requirements were generated
                if (count($mrpResult1['requirements']) > 0) {
                    $this->addResult('MRP Requirements Generation', 'PASS', count($mrpResult1['requirements']) . " requirements generated");
                } else {
                    $this->addResult('MRP Requirements Generation', 'FAIL', "No requirements generated");
                }
                
                // Check summary calculation
                $summary = $mrpResult1['summary'];
                if (isset($summary['total_materials']) && $summary['total_materials'] > 0) {
                    $this->addResult('MRP Summary Calculation', 'PASS', "Summary generated correctly");
                } else {
                    $this->addResult('MRP Summary Calculation', 'FAIL', "Summary calculation failed");
                }
            } else {
                $this->addResult('Standard MRP Calculation', 'FAIL', $mrpResult1['error']);
            }
            
            // Test MRP for large order (Order 3)
            $mrpResult3 = $this->mrp->runMRP(3);
            if ($mrpResult3['success']) {
                $this->addResult('Large Order MRP', 'PASS', "Order 3 processed successfully");
                
                // Check for material shortages
                $shortages = 0;
                foreach ($mrpResult3['requirements'] as $req) {
                    if ($req['net_requirement'] > 0) {
                        $shortages++;
                    }
                }
                
                if ($shortages > 0) {
                    $this->addResult('Shortage Detection', 'PASS', "$shortages materials have shortages");
                } else {
                    $this->addResult('Shortage Detection', 'WARN', "No shortages detected in large order");
                }
            } else {
                $this->addResult('Large Order MRP', 'FAIL', $mrpResult3['error']);
            }
            
            // Test purchase order suggestions
            $poSuggestions = $this->mrp->generatePurchaseOrderSuggestions(3);
            if (count($poSuggestions) > 0) {
                $this->addResult('PO Suggestions', 'PASS', count($poSuggestions) . " PO suggestions generated");
            } else {
                $this->addResult('PO Suggestions', 'WARN', "No PO suggestions generated");
            }
            
        } catch (Exception $e) {
            $this->addResult('MRP Calculations', 'FAIL', $e->getMessage());
        }
    }
    
    private function testEdgeCases() {
        echo "6. Testing Edge Cases and Error Handling...\n";
        
        try {
            // Test MRP with non-existent order
            $invalidMRP = $this->mrp->runMRP(999);
            if (!$invalidMRP['success']) {
                $this->addResult('Invalid Order Handling', 'PASS', "Correctly handled invalid order");
            } else {
                $this->addResult('Invalid Order Handling', 'FAIL', "Should have failed for invalid order");
            }
            
            // Test BOM explosion with no BOM
            $noBOM = $this->bom->explodeBOM(999, 10);
            if (empty($noBOM)) {
                $this->addResult('No BOM Handling', 'PASS', "Correctly handled product with no BOM");
            } else {
                $this->addResult('No BOM Handling', 'FAIL', "Should return empty for product with no BOM");
            }
            
            // Test inventory with non-existent item
            $noInventory = $this->inventory->getAvailableQuantity('material', 999);
            if ($noInventory === 0) {
                $this->addResult('No Inventory Handling', 'PASS', "Correctly returned 0 for non-existent item");
            } else {
                $this->addResult('No Inventory Handling', 'FAIL', "Should return 0 for non-existent item");
            }
            
            // Test zero quantity MRP
            $zeroBOM = $this->bom->explodeBOM(1, 0);
            if (empty($zeroBOM)) {
                $this->addResult('Zero Quantity Handling', 'PASS', "Correctly handled zero quantity");
            } else {
                $this->addResult('Zero Quantity Handling', 'FAIL', "Should return empty for zero quantity");
            }
            
        } catch (Exception $e) {
            $this->addResult('Edge Cases', 'FAIL', $e->getMessage());
        }
    }
    
    private function testPerformance() {
        echo "7. Testing Performance...\n";
        
        try {
            // Test MRP calculation performance
            $startTime = microtime(true);
            $this->mrp->runMRP(1);
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            if ($duration < 1000) { // Less than 1 second
                $this->addResult('MRP Performance', 'PASS', "MRP calculation: " . round($duration, 2) . "ms");
            } else {
                $this->addResult('MRP Performance', 'WARN', "MRP calculation slow: " . round($duration, 2) . "ms");
            }
            
            // Test large BOM explosion performance
            $startTime = microtime(true);
            $this->bom->explodeBOM(3, 100);
            $endTime = microtime(true);
            $bomDuration = ($endTime - $startTime) * 1000;
            
            if ($bomDuration < 500) { // Less than 0.5 seconds
                $this->addResult('BOM Performance', 'PASS', "BOM explosion: " . round($bomDuration, 2) . "ms");
            } else {
                $this->addResult('BOM Performance', 'WARN', "BOM explosion slow: " . round($bomDuration, 2) . "ms");
            }
            
        } catch (Exception $e) {
            $this->addResult('Performance Testing', 'FAIL', $e->getMessage());
        }
    }
    
    private function addResult($test, $status, $message) {
        $this->testResults[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message,
            'timestamp' => date('H:i:s')
        ];
        
        $statusColor = $status === 'PASS' ? "\033[32m" : ($status === 'WARN' ? "\033[33m" : "\033[31m");
        echo "  [$statusColor$status\033[0m] $test: $message\n";
    }
    
    private function displayResults() {
        echo "\n=== TEST RESULTS SUMMARY ===\n";
        
        $passed = 0;
        $warned = 0;
        $failed = 0;
        
        foreach ($this->testResults as $result) {
            switch ($result['status']) {
                case 'PASS':
                    $passed++;
                    break;
                case 'WARN':
                    $warned++;
                    break;
                case 'FAIL':
                    $failed++;
                    break;
            }
        }
        
        $total = count($this->testResults);
        echo "Total Tests: $total\n";
        echo "\033[32mPassed: $passed\033[0m\n";
        echo "\033[33mWarnings: $warned\033[0m\n";
        echo "\033[31mFailed: $failed\033[0m\n\n";
        
        if ($failed === 0) {
            echo "\033[32m✓ ALL CRITICAL TESTS PASSED\033[0m\n";
            echo "Phase 1 MRP system is ready for production!\n";
        } else {
            echo "\033[31m✗ CRITICAL ISSUES FOUND\033[0m\n";
            echo "Please address failed tests before production deployment.\n";
        }
        
        echo "\nValidation completed at " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the validation
try {
    $validator = new MRPValidator();
    $validator->runAllTests();
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>