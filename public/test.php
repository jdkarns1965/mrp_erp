<?php
echo "<h1>PHP Test</h1>";
echo "<p>PHP is working!</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test database connection
try {
    require_once '../classes/Database.php';
    $db = Database::getInstance();
    $result = $db->selectOne("SELECT 1 as test");
    echo "<p style='color: green;'>Database connection: SUCCESS</p>";
    echo "<p>Database test result: " . $result['test'] . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection: FAILED</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='index.php'>Go to Main Dashboard</a></p>";
?>