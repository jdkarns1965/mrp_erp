<?php
/**
 * Documentation Maintenance Check Script
 * 
 * Run this script to check for common documentation issues:
 * - Broken internal links
 * - Missing files referenced in documentation
 * - Outdated status codes
 * 
 * Usage: php maintenance/check-documentation.php
 */

echo "🔍 MRP/ERP Documentation Health Check\n";
echo "=====================================\n\n";

$issues = [];
$warnings = [];

// Check if documentation files exist
$docFiles = [
    'USER_GUIDE.md',
    'QUICK_REFERENCE.md', 
    'CLAUDE.md',
    'includes/help-system.php'
];

echo "📁 Checking documentation files...\n";
foreach ($docFiles as $file) {
    if (!file_exists($file)) {
        $issues[] = "Missing documentation file: $file";
    } else {
        echo "✅ $file exists\n";
    }
}

// Check for references to non-existent PHP files
echo "\n🔗 Checking for references to missing files...\n";
$docContent = '';
foreach (['USER_GUIDE.md', 'QUICK_REFERENCE.md', 'CLAUDE.md'] as $file) {
    if (file_exists($file)) {
        $docContent .= file_get_contents($file);
    }
}

// Look for .php file references
if (preg_match_all('/([a-z-]+)\.php/', $docContent, $matches)) {
    $referencedFiles = array_unique($matches[0]);
    foreach ($referencedFiles as $file) {
        // Skip common files that might be referenced but not checked
        if (in_array($file, ['header.php', 'footer.php', 'database.php'])) {
            continue;
        }
        
        // Check if file exists in public directory
        $possiblePaths = [
            "public/$file",
            "public/*/$file", // Check subdirectories
        ];
        
        $found = false;
        if (file_exists("public/$file")) {
            $found = true;
        } else {
            // Check subdirectories
            $dirs = glob('public/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                if (file_exists("$dir/$file")) {
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            $issues[] = "Referenced file not found: $file";
        } else {
            echo "✅ $file found\n";
        }
    }
}

// Check for old status code patterns
echo "\n🏷️  Checking status codes...\n";
$oldStatusCodes = [
    'PND' => 'pending',
    'INP' => 'in_production', 
    'COM' => 'completed',
    'SHP' => 'shipped',
    'PLN' => 'planned',
    'REL' => 'released',
    'WIP' => 'in_progress',
    'HLD' => 'on_hold'
];

foreach ($oldStatusCodes as $old => $new) {
    if (strpos($docContent, $old) !== false) {
        $warnings[] = "Found old status code '$old' - should be '$new'";
    }
}

if (empty($warnings)) {
    echo "✅ No old status codes found\n";
}

// Check for common navigation patterns
echo "\n🧭 Checking navigation patterns...\n";
$navigationPatterns = [
    'MRP → Calculate' => 'MRP → Run MRP',
    'Inventory → Adjust' => 'Inventory → Receive/Issue',
    'Orders → View Orders' => '(Feature may not exist)',
];

foreach ($navigationPatterns as $old => $new) {
    if (strpos($docContent, $old) !== false) {
        $warnings[] = "Found old navigation: '$old' - should be '$new'";
    }
}

if (empty($warnings)) {
    echo "✅ Navigation patterns look current\n";
}

// Summary
echo "\n📊 Summary:\n";
echo "=========\n";

if (empty($issues) && empty($warnings)) {
    echo "🎉 All checks passed! Documentation appears healthy.\n";
} else {
    if (!empty($issues)) {
        echo "❌ Issues found (" . count($issues) . "):\n";
        foreach ($issues as $issue) {
            echo "   • $issue\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠️  Warnings (" . count($warnings) . "):\n";
        foreach ($warnings as $warning) {
            echo "   • $warning\n";
        }
    }
}

echo "\n💡 To fix issues:\n";
echo "   1. Update documentation files\n";
echo "   2. Test navigation paths in browser\n";
echo "   3. Verify status codes match system\n";
echo "   4. Run this check again\n\n";

echo "📅 Last checked: " . date('Y-m-d H:i:s') . "\n";
?>