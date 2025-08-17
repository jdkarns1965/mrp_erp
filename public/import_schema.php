<?php
echo "<h1>Import Database Schema</h1>";

try {
    require_once __DIR__ . '/../classes/Database.php';
    $db = Database::getInstance();
    
    // Read and execute the schema file
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $schema = file_get_contents($schemaFile);
    
    // Split into individual statements
    $statements = explode(';', $schema);
    $executed = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        // Skip database creation statements since we're already connected
        if (stripos($statement, 'DROP DATABASE') !== false || 
            stripos($statement, 'CREATE DATABASE') !== false ||
            stripos($statement, 'USE ') !== false) {
            continue;
        }
        
        try {
            $db->getConnection()->query($statement);
            $executed++;
        } catch (Exception $e) {
            echo "<p style='color: orange;'>Warning: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ Schema imported successfully! Executed $executed statements.</p>";
    echo "<p><a href='verify_setup.php'>Verify Setup</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error importing schema: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
h1 { color: #333; }
p { margin: 0.5rem 0; }
a { color: #2563eb; text-decoration: none; padding: 0.5rem 1rem; background: #f0f9ff; border-radius: 0.25rem; display: inline-block; margin: 0.25rem; }
a:hover { background: #dbeafe; text-decoration: none; }
</style>