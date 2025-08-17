<?php
echo "<h1>System Verification</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once __DIR__ . '/../classes/Database.php';
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if tables exist
    $tables = $db->select("SHOW TABLES");
    echo "<p>Found " . count($tables) . " tables in database:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        $tableName = reset($table); // Get first value from array
        echo "<li>$tableName</li>";
    }
    echo "</ul>";
    
    if (count($tables) == 0) {
        echo "<p style='color: red;'>❌ No tables found. Schema needs to be imported.</p>";
        echo "<p><a href='import_schema.php'>Import Schema</a></p>";
    } else {
        echo "<p style='color: green;'>✅ Database schema looks good!</p>";
        echo "<p><a href='simple_sample_data.php'>Create Sample Data</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Go to Dashboard</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
h1, h2 { color: #333; }
p { margin: 0.5rem 0; }
ul { margin: 0.5rem 0; padding-left: 2rem; }
a { color: #2563eb; text-decoration: none; padding: 0.5rem 1rem; background: #f0f9ff; border-radius: 0.25rem; display: inline-block; margin: 0.25rem; }
a:hover { background: #dbeafe; text-decoration: none; }
</style>