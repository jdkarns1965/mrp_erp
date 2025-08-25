<?php
/**
 * Action Buttons Template
 * Reusable template for implementing collapsible action buttons in list views
 * 
 * Usage:
 * Include this file and call renderActionButtons() with appropriate parameters
 */

/**
 * Render action buttons for a list item
 * 
 * @param int $itemId The unique ID of the item
 * @param array $buttons Array of button configurations
 * @param array $itemData Optional data about the item for conditional buttons
 * @return string HTML for the action row
 */
function renderActionButtons($itemId, $buttons, $itemData = []) {
    ob_start();
    ?>
    <tr class="action-row" id="action-row-<?php echo $itemId; ?>">
        <td colspan="100%">
            <div class="actions-container">
                <button class="actions-toggle" onclick="toggleActions(<?php echo $itemId; ?>)" type="button">
                    <span class="toggle-text">Actions</span>
                    <span class="toggle-icon">▼</span>
                </button>
                <div class="action-buttons" id="actions-<?php echo $itemId; ?>" style="display: none;">
                    <?php foreach ($buttons as $button): ?>
                        <?php if (!isset($button['condition']) || $button['condition']): ?>
                            <a href="<?php echo $button['href']; ?>" 
                               class="btn-action <?php echo $button['class']; ?>" 
                               title="<?php echo $button['title']; ?>"
                               <?php if (isset($button['onclick'])): ?>
                                   onclick="<?php echo $button['onclick']; ?>"
                               <?php endif; ?>
                               <?php if (isset($button['target'])): ?>
                                   target="<?php echo $button['target']; ?>"
                               <?php endif; ?>>
                                <?php if (isset($button['icon'])): ?>
                                    <span class="icon"><?php echo $button['icon']; ?></span>
                                <?php endif; ?>
                                <span class="text"><?php echo $button['text']; ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Get standard action buttons for different entity types
 * 
 * @param string $entityType Type of entity (material, product, order, etc.)
 * @param int $itemId The ID of the item
 * @param array $itemData Data about the item for conditional buttons
 * @return array Array of button configurations
 */
function getStandardActionButtons($entityType, $itemId, $itemData = []) {
    $buttons = [];
    
    switch ($entityType) {
        case 'material':
            $buttons = [
                [
                    'href' => "view.php?id={$itemId}",
                    'class' => 'btn-view',
                    'title' => 'View Details',
                    'icon' => '👁️',
                    'text' => 'View'
                ],
                [
                    'href' => "edit.php?id={$itemId}",
                    'class' => 'btn-edit',
                    'title' => 'Edit Material',
                    'icon' => '✏️',
                    'text' => 'Edit'
                ],
                [
                    'href' => "../inventory/adjust.php?type=material&id={$itemId}",
                    'class' => 'btn-inventory',
                    'title' => 'Adjust Stock',
                    'icon' => '📦',
                    'text' => 'Stock'
                ],
                [
                    'href' => "../bom/index.php?material_id={$itemId}",
                    'class' => 'btn-usage',
                    'title' => 'View BOM Usage',
                    'icon' => '🔍',
                    'text' => 'Usage'
                ]
            ];
            
            // Add conditional reorder button
            if (isset($itemData['needs_reorder']) && $itemData['needs_reorder']) {
                $buttons[] = [
                    'href' => "../purchase/create.php?material_id={$itemId}",
                    'class' => 'btn-reorder',
                    'title' => 'Create Purchase Order',
                    'icon' => '🔄',
                    'text' => 'Reorder'
                ];
            }
            break;
            
        case 'product':
            $buttons = [
                [
                    'href' => "view.php?id={$itemId}",
                    'class' => 'btn-view',
                    'title' => 'View Details',
                    'text' => 'View'
                ],
                [
                    'href' => "edit.php?id={$itemId}",
                    'class' => 'btn-edit',
                    'title' => 'Edit Product',
                    'text' => 'Edit'
                ],
                [
                    'href' => "../bom/view.php?product_id={$itemId}",
                    'class' => 'btn-usage',
                    'title' => 'View BOM',
                    'text' => 'BOM'
                ],
                [
                    'href' => "../mrp/calculate.php?product_id={$itemId}",
                    'class' => 'btn-inventory',
                    'title' => 'Run MRP',
                    'text' => 'MRP'
                ]
            ];
            break;
            
        case 'production_order':
            $buttons = [
                [
                    'href' => "view.php?id={$itemId}",
                    'class' => 'btn-view',
                    'title' => 'View Details',
                    'text' => 'View'
                ],
                [
                    'href' => "operations.php?order_id={$itemId}",
                    'class' => 'btn-usage',
                    'title' => 'Manage Operations',
                    'icon' => '⚙️',
                    'text' => 'Operations'
                ]
            ];
            
            // Add status-specific buttons
            if (isset($itemData['status'])) {
                switch ($itemData['status']) {
                    case 'planned':
                        $buttons[] = [
                            'href' => "#",
                            'onclick' => "releaseOrder({$itemId}); return false;",
                            'class' => 'btn-start',
                            'title' => 'Release Order',
                            'icon' => '▶️',
                            'text' => 'Release'
                        ];
                        break;
                    case 'released':
                        $buttons[] = [
                            'href' => "#",
                            'onclick' => "startProduction({$itemId}); return false;",
                            'class' => 'btn-start',
                            'title' => 'Start Production',
                            'icon' => '🏭',
                            'text' => 'Start'
                        ];
                        break;
                    case 'in_progress':
                        $buttons[] = [
                            'href' => "#",
                            'onclick' => "completeOrder({$itemId}); return false;",
                            'class' => 'btn-complete',
                            'title' => 'Complete Order',
                            'icon' => '✅',
                            'text' => 'Complete'
                        ];
                        break;
                }
            }
            
            // Cancel button for non-completed orders
            if (isset($itemData['status']) && !in_array($itemData['status'], ['completed', 'cancelled'])) {
                $buttons[] = [
                    'href' => "#",
                    'onclick' => "if(confirm('Cancel this order?')) cancelOrder({$itemId}); return false;",
                    'class' => 'btn-cancel',
                    'title' => 'Cancel Order',
                    'icon' => '❌',
                    'text' => 'Cancel'
                ];
            }
            break;
            
        case 'customer_order':
            $buttons = [
                [
                    'href' => "view.php?id={$itemId}",
                    'class' => 'btn-view',
                    'title' => 'View Details',
                    'text' => 'View'
                ],
                [
                    'href' => "edit.php?id={$itemId}",
                    'class' => 'btn-edit',
                    'title' => 'Edit Order',
                    'text' => 'Edit'
                ],
                [
                    'href' => "../mrp/calculate.php?order_id={$itemId}",
                    'class' => 'btn-inventory',
                    'title' => 'Check Requirements',
                    'icon' => '📊',
                    'text' => 'MRP Check'
                ],
                [
                    'href' => "../production/create.php?order_id={$itemId}",
                    'class' => 'btn-start',
                    'title' => 'Create Production Order',
                    'icon' => '🏭',
                    'text' => 'Production',
                    'condition' => isset($itemData['can_produce']) && $itemData['can_produce']
                ]
            ];
            break;
            
        case 'bom':
            $buttons = [
                [
                    'href' => "view.php?id={$itemId}",
                    'class' => 'btn-view',
                    'title' => 'View BOM',
                    'text' => 'View'
                ],
                [
                    'href' => "edit.php?id={$itemId}",
                    'class' => 'btn-edit',
                    'title' => 'Edit BOM',
                    'text' => 'Edit'
                ],
                [
                    'href' => "copy.php?id={$itemId}",
                    'class' => 'btn-usage',
                    'title' => 'Copy BOM',
                    'icon' => '📋',
                    'text' => 'Copy'
                ],
                [
                    'href' => "#",
                    'onclick' => "validateBOM({$itemId}); return false;",
                    'class' => 'btn-inventory',
                    'title' => 'Validate BOM',
                    'icon' => '✓',
                    'text' => 'Validate'
                ]
            ];
            break;
    }
    
    return $buttons;
}

/**
 * Include required CSS and JavaScript files
 * Call this in the page header or before closing body tag
 */
function includeActionButtonsAssets() {
    ?>
    <!-- Action Buttons CSS -->
    <link rel="stylesheet" href="/mrp_erp/public/css/action-buttons.css">
    
    <!-- Action Buttons JavaScript -->
    <script src="/mrp_erp/public/js/action-buttons.js"></script>
    <?php
}

/**
 * Generate inline styles for custom button colors
 * 
 * @param string $buttonClass Class name for the button
 * @param string $color Primary color for the button
 * @param string $hoverColor Hover color for the button
 */
function generateCustomButtonStyle($buttonClass, $color, $hoverColor) {
    ?>
    <style>
        .btn-action.<?php echo $buttonClass; ?> {
            color: <?php echo $color; ?>;
        }
        .btn-action.<?php echo $buttonClass; ?>:hover {
            background-color: <?php echo $hoverColor; ?>;
            color: white;
            border-color: <?php echo $hoverColor; ?>;
        }
    </style>
    <?php
}