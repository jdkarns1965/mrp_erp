<?php
/**
 * Helper functions for working with database enum fields
 */

require_once __DIR__ . '/../classes/Database.php';

/**
 * Get enum values from a database column
 * @param string $table Table name
 * @param string $column Column name
 * @return array Array of enum values with keys and display labels
 */
function getEnumValues($table, $column) {
    $db = Database::getInstance();
    
    // Query to get column definition
    $query = "SHOW COLUMNS FROM `$table` WHERE Field = ?";
    $result = $db->select($query, [$column]);
    
    if (empty($result)) {
        return [];
    }
    
    $type = $result[0]['Type'];
    
    // Extract enum values from the type definition
    if (preg_match('/^enum\((.*)\)$/', $type, $matches)) {
        $enumStr = $matches[1];
        // Parse the enum values
        preg_match_all("/'([^']+)'/", $enumStr, $values);
        
        $enumValues = [];
        foreach ($values[1] as $value) {
            // Convert value to display label
            $label = formatEnumLabel($value);
            $enumValues[$value] = $label;
        }
        
        return $enumValues;
    }
    
    return [];
}

/**
 * Convert enum value to display label
 * @param string $value Enum value (e.g., 'base_resin')
 * @return string Display label (e.g., 'Base Resin')
 */
function formatEnumLabel($value) {
    // Special cases for better formatting
    $specialCases = [
        'base_resin' => 'Base Resin',
        'uom' => 'UOM',
        'bom' => 'BOM',
        'mrp' => 'MRP',
        'wip' => 'WIP',
        'in_progress' => 'In Progress',
        'in_production' => 'In Production',
        'on_hold' => 'On Hold',
        'po_suggestion' => 'PO Suggestion',
        'prod_suggestion' => 'Production Suggestion',
        'existing_po' => 'Existing PO',
        'net-change' => 'Net Change',
        'raw_material' => 'Raw Material',
        'finished_goods' => 'Finished Goods',
    ];
    
    // Check for special cases
    $lowerValue = strtolower($value);
    if (isset($specialCases[$lowerValue])) {
        return $specialCases[$lowerValue];
    }
    
    // General formatting: replace underscores with spaces and capitalize words
    $label = str_replace('_', ' ', $value);
    $label = ucwords($label);
    
    return $label;
}

/**
 * Generate HTML select options from enum values
 * @param array $enumValues Array of enum values from getEnumValues()
 * @param string $selectedValue Currently selected value
 * @param bool $includeEmpty Include empty option
 * @return string HTML options
 */
function generateEnumOptions($enumValues, $selectedValue = '', $includeEmpty = true) {
    $html = '';
    
    if ($includeEmpty) {
        $html .= '<option value="">Select...</option>' . "\n";
    }
    
    foreach ($enumValues as $value => $label) {
        $selected = ($selectedValue === $value) ? ' selected' : '';
        $html .= sprintf(
            '                        <option value="%s"%s>%s</option>' . "\n",
            htmlspecialchars($value),
            $selected,
            htmlspecialchars($label)
        );
    }
    
    return $html;
}