<?php
/**
 * In-App Help System
 * Provides contextual help, tooltips, and user guidance throughout the application
 */

class HelpSystem {
    
    /**
     * Help content database - organized by page/context
     */
    private static $helpContent = [
        'dashboard' => [
            'title' => 'Dashboard Overview',
            'content' => 'The dashboard provides a real-time overview of your MRP system. Monitor key metrics, alerts, and production status at a glance.',
            'tips' => [
                'Check daily for low stock alerts',
                'Review production orders each morning',
                'Click any metric for detailed view'
            ]
        ],
        'materials' => [
            'title' => 'Materials Management',
            'content' => 'Manage raw materials, components, and supplies. Set reorder points to prevent stockouts.',
            'tips' => [
                'Use consistent material codes',
                'Set accurate reorder points',
                'Link suppliers for easy ordering'
            ]
        ],
        'products' => [
            'title' => 'Product Management',
            'content' => 'Define finished goods that you manufacture. Each product needs a BOM to define required materials.',
            'tips' => [
                'Create BOM after adding product',
                'Set realistic lead times',
                'Maintain safety stock levels'
            ]
        ],
        'bom' => [
            'title' => 'Bill of Materials',
            'content' => 'BOMs define the recipe for making products. List all materials and quantities needed.',
            'tips' => [
                'Include scrap percentages',
                'Review quantities carefully',
                'Update when designs change'
            ]
        ],
        'inventory' => [
            'title' => 'Inventory Control',
            'content' => 'Track current stock levels, make adjustments, and monitor transactions.',
            'tips' => [
                'Perform regular cycle counts',
                'Use lot numbers for traceability',
                'Document adjustment reasons'
            ]
        ],
        'orders' => [
            'title' => 'Customer Orders',
            'content' => 'Enter and track customer orders. These drive MRP calculations and production scheduling.',
            'tips' => [
                'Set realistic due dates',
                'Check material availability',
                'Update status promptly'
            ]
        ],
        'mrp' => [
            'title' => 'MRP Calculations',
            'content' => 'Calculate material requirements based on orders and BOMs. Identifies shortages and suggests ordering.',
            'tips' => [
                'Run daily or when orders change',
                'Review all shortages',
                'Consider lead times'
            ]
        ],
        'production' => [
            'title' => 'Production Scheduling',
            'content' => 'Schedule and track manufacturing orders. Use Gantt chart to visualize timeline.',
            'tips' => [
                'Check capacity before scheduling',
                'Update operation status regularly',
                'Monitor material availability'
            ]
        ],
        'mps' => [
            'title' => 'Master Production Schedule',
            'content' => 'Plan production quantities for each period. MPS drives MRP calculations and balances demand with capacity.',
            'tips' => [
                'Update weekly or when demand changes',
                'Consider work center capacity',
                'Run Enhanced MRP after changes'
            ]
        ]
    ];
    
    /**
     * Field-specific help tooltips
     */
    private static $fieldHelp = [
        'material_code' => 'Unique identifier for this material (e.g., MAT-001)',
        'reorder_point' => 'When inventory drops below this level, reorder the material',
        'safety_stock' => 'Minimum inventory to maintain as a buffer against stockouts',
        'lead_time' => 'Number of days from order placement to delivery',
        'scrap_percentage' => 'Expected waste percentage during production (0-100)',
        'lot_number' => 'Batch or lot identifier for traceability',
        'work_center' => 'Machine or station where work is performed',
        'setup_time' => 'Time required to prepare machine for production',
        'cycle_time' => 'Time to produce one unit',
        'due_date' => 'When the customer needs the order delivered',
        'priority' => 'Production priority (1=highest)',
        'unit_of_measure' => 'How this item is measured (EA, KG, M, etc.)',
        'bom_quantity' => 'Amount of this material needed per finished product',
        'order_quantity' => 'Number of products customer wants',
        'minimum_order_quantity' => 'Smallest quantity that can be ordered',
        'available_quantity' => 'Current stock on hand',
        'allocated_quantity' => 'Stock reserved for production orders',
        'on_order_quantity' => 'Stock currently on order from suppliers'
    ];
    
    /**
     * Render help icon with tooltip
     */
    public static function tooltip($fieldName, $customText = null) {
        $helpText = $customText ?: (self::$fieldHelp[$fieldName] ?? '');
        if (empty($helpText)) return '';
        
        return '<span class="help-tooltip" title="' . htmlspecialchars($helpText) . '">?</span>';
    }
    
    /**
     * Render contextual help panel for a page
     */
    public static function renderHelpPanel($context) {
        $help = self::$helpContent[$context] ?? null;
        if (!$help) return '';
        
        ob_start();
        ?>
        <div class="help-panel" id="help-panel">
            <div class="help-panel-header">
                <h3><?php echo htmlspecialchars($help['title']); ?></h3>
                <button class="help-close" onclick="toggleHelp()">&times;</button>
            </div>
            <div class="help-panel-content">
                <p><?php echo htmlspecialchars($help['content']); ?></p>
                <?php if (!empty($help['tips'])): ?>
                <h4>Quick Tips:</h4>
                <ul>
                    <?php foreach ($help['tips'] as $tip): ?>
                    <li><?php echo htmlspecialchars($tip); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render help button that toggles help panel
     */
    public static function renderHelpButton() {
        return '<button class="help-button" onclick="toggleHelp()" title="Get Help">?</button>';
    }
    
    /**
     * Render inline help text
     */
    public static function inlineHelp($text, $type = 'info') {
        switch($type) {
            case 'warning':
                $icon = '⚠️';
                break;
            case 'error':
                $icon = '❌';
                break;
            case 'success':
                $icon = '✓';
                break;
            default:
                $icon = 'ℹ️';
                break;
        }
        
        return sprintf(
            '<div class="inline-help %s-help">%s %s</div>',
            htmlspecialchars($type),
            $icon,
            htmlspecialchars($text)
        );
    }
    
    /**
     * Render workflow guide
     */
    public static function workflowGuide($steps) {
        ob_start();
        ?>
        <div class="workflow-guide">
            <h4>Step-by-Step Guide:</h4>
            <ol class="workflow-steps">
                <?php foreach ($steps as $index => $step): ?>
                <li class="workflow-step">
                    <span class="step-number"><?php echo $index + 1; ?></span>
                    <span class="step-text"><?php echo htmlspecialchars($step); ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get JavaScript for help system
     */
    public static function getHelpScript() {
        return <<<'JS'
        <script>
        function toggleHelp() {
            const panel = document.getElementById('help-panel');
            if (panel) {
                panel.classList.toggle('active');
                localStorage.setItem('helpPanelOpen', panel.classList.contains('active'));
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Restore help panel state
            const helpPanel = document.getElementById('help-panel');
            if (helpPanel && localStorage.getItem('helpPanelOpen') === 'true') {
                helpPanel.classList.add('active');
            }
            
            // Add keyboard shortcut for help (F1)
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F1') {
                    e.preventDefault();
                    toggleHelp();
                }
            });
            
            // Initialize all tooltips
            const tooltips = document.querySelectorAll('.help-tooltip');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert(this.title);
                });
            });
        });
        </script>
        JS;
    }
    
    /**
     * Get CSS for help system
     */
    public static function getHelpStyles() {
        return <<<'CSS'
        <style>
        /* Help System Styles */
        .help-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: transform 0.3s;
        }
        
        .help-button:hover {
            transform: scale(1.1);
            background: #0056b3;
        }
        
        .help-panel {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100%;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s;
            z-index: 999;
            overflow-y: auto;
        }
        
        .help-panel.active {
            right: 0;
        }
        
        .help-panel-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .help-panel-header h3 {
            margin: 0;
            color: #495057;
        }
        
        .help-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .help-panel-content {
            padding: 20px;
        }
        
        .help-panel-content h4 {
            color: #495057;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .help-panel-content ul {
            padding-left: 20px;
        }
        
        .help-panel-content li {
            margin-bottom: 8px;
            color: #6c757d;
        }
        
        .help-tooltip {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: #6c757d;
            color: white;
            border-radius: 50%;
            text-align: center;
            font-size: 12px;
            line-height: 16px;
            cursor: help;
            margin-left: 5px;
            vertical-align: middle;
        }
        
        .help-tooltip:hover {
            background: #495057;
        }
        
        .inline-help {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-help {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .warning-help {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .error-help {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .success-help {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .workflow-guide {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .workflow-steps {
            counter-reset: step-counter;
            padding-left: 0;
            list-style: none;
        }
        
        .workflow-step {
            position: relative;
            padding-left: 40px;
            margin-bottom: 15px;
        }
        
        .step-number {
            position: absolute;
            left: 0;
            top: 0;
            width: 28px;
            height: 28px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 28px;
            font-weight: bold;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .help-panel {
                width: 100%;
                right: -100%;
            }
            
            .help-button {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }
        }
        </style>
        CSS;
    }
}

/**
 * Helper function for quick tooltip generation
 */
function help_tooltip($field, $text = null) {
    return HelpSystem::tooltip($field, $text);
}

/**
 * Helper function for inline help
 */
function help_inline($text, $type = 'info') {
    return HelpSystem::inlineHelp($text, $type);
}
?>