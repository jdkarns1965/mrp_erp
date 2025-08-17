<?php

/**
 * UI Helper Functions
 * Provides reusable UI components and utilities
 */

/**
 * Generate an edit link for an entity
 * 
 * @param string $entityType The type of entity (material, product, etc.)
 * @param int $entityId The ID of the entity
 * @param string $linkText The text to display for the link (optional)
 * @param array $options Additional options (class, style, etc.)
 * @return string HTML for the edit link
 */
function renderEditLink($entityType, $entityId, $linkText = 'Edit', $options = []) {
    $baseUrl = "../{$entityType}s/edit.php";
    $url = $baseUrl . "?id=" . urlencode($entityId);
    
    $class = $options['class'] ?? 'edit-link';
    $style = $options['style'] ?? 'font-size: 0.875rem; margin-left: 0.5rem; color: #666; text-decoration: none;';
    $title = $options['title'] ?? "Edit this {$entityType}";
    
    return sprintf(
        '<a href="%s" class="%s" style="%s" title="%s">%s</a>',
        htmlspecialchars($url),
        htmlspecialchars($class),
        htmlspecialchars($style),
        htmlspecialchars($title),
        htmlspecialchars($linkText)
    );
}

/**
 * Generate entity name with optional edit link
 * 
 * @param string $entityType The type of entity (material, product, etc.)
 * @param int $entityId The ID of the entity
 * @param string $displayName The name to display
 * @param bool $showEditLink Whether to show the edit link
 * @param array $options Additional options
 * @return string HTML for entity name with optional edit link
 */
function renderEntityName($entityType, $entityId, $displayName, $showEditLink = true, $options = []) {
    $html = '<span class="entity-name">' . htmlspecialchars($displayName) . '</span>';
    
    if ($showEditLink) {
        $editOptions = array_merge([
            'class' => 'edit-link-small',
            'style' => 'font-size: 0.75rem; margin-left: 0.5rem; color: #666; text-decoration: none;',
        ], $options);
        
        $html .= renderEditLink($entityType, $entityId, '✏️', $editOptions);
    }
    
    return $html;
}