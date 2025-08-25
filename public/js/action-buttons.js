/**
 * Action Buttons JavaScript
 * Handles the collapsible action buttons under list items
 */

class ActionButtonsManager {
    constructor() {
        this.activeRow = null;
        this.init();
    }

    init() {
        // Bind event listeners
        this.bindToggleButtons();
        this.bindOutsideClick();
        this.bindKeyboardShortcuts();
        this.bindTableRowHover();
    }

    bindToggleButtons() {
        // Use event delegation for dynamically added content
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.actions-toggle');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                const container = toggleBtn.closest('.actions-container');
                const rowId = container?.closest('.action-row')?.id;
                
                if (rowId) {
                    const materialId = rowId.replace('action-row-', '');
                    this.toggleActions(materialId);
                }
            }
        });
    }

    toggleActions(itemId) {
        const actionsDiv = document.getElementById('actions-' + itemId);
        const toggleBtn = document.querySelector('#action-row-' + itemId + ' .actions-toggle');
        const actionRow = document.getElementById('action-row-' + itemId);
        
        if (!actionsDiv || !toggleBtn) return;

        const isCurrentlyOpen = actionsDiv.style.display === 'flex';
        
        // Close all other open action menus
        this.closeAllActions();
        
        if (!isCurrentlyOpen) {
            // Open this action menu
            actionsDiv.style.display = 'flex';
            toggleBtn.classList.add('expanded');
            actionRow?.classList.add('active');
            this.activeRow = itemId;
            
            // Ensure the action row is visible
            this.ensureVisible(actionRow);
            
            // Focus first action button for accessibility
            setTimeout(() => {
                const firstButton = actionsDiv.querySelector('.btn-action:not(:disabled)');
                if (firstButton) {
                    firstButton.focus();
                }
            }, 100);
        } else {
            // Close this action menu
            this.activeRow = null;
        }
    }

    closeAllActions() {
        // Close all action buttons
        document.querySelectorAll('.action-buttons').forEach(div => {
            div.style.display = 'none';
        });
        
        // Remove expanded class from all toggle buttons
        document.querySelectorAll('.actions-toggle').forEach(btn => {
            btn.classList.remove('expanded');
        });
        
        // Remove active class from all action rows
        document.querySelectorAll('.action-row').forEach(row => {
            row.classList.remove('active');
        });
        
        this.activeRow = null;
    }

    bindOutsideClick() {
        document.addEventListener('click', (event) => {
            // Close actions when clicking outside
            if (!event.target.closest('.actions-container') && 
                !event.target.closest('.action-buttons')) {
                this.closeAllActions();
            }
        });
    }

    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Close on Escape key
            if (e.key === 'Escape') {
                this.closeAllActions();
            }
            
            // Navigate with arrow keys when actions are open
            if (this.activeRow !== null) {
                const actionsDiv = document.getElementById('actions-' + this.activeRow);
                if (!actionsDiv) return;
                
                const buttons = Array.from(actionsDiv.querySelectorAll('.btn-action:not(:disabled)'));
                const currentIndex = buttons.indexOf(document.activeElement);
                
                if (e.key === 'ArrowRight' && currentIndex < buttons.length - 1) {
                    e.preventDefault();
                    buttons[currentIndex + 1].focus();
                } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
                    e.preventDefault();
                    buttons[currentIndex - 1].focus();
                }
            }
        });
    }

    bindTableRowHover() {
        // Add hover effect to main table rows that have action rows
        document.querySelectorAll('tbody tr:not(.action-row)').forEach(row => {
            row.addEventListener('mouseenter', () => {
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.classList.contains('action-row')) {
                    nextRow.classList.add('hover-preview');
                }
            });
            
            row.addEventListener('mouseleave', () => {
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.classList.contains('action-row')) {
                    nextRow.classList.remove('hover-preview');
                }
            });
        });
    }

    ensureVisible(element) {
        if (!element) return;
        
        const rect = element.getBoundingClientRect();
        const viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
        
        // Check if element is below viewport
        if (rect.bottom > viewHeight) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        // Check if element is above viewport
        else if (rect.top < 0) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Helper method to add loading state to buttons
    setButtonLoading(button, isLoading = true) {
        if (isLoading) {
            button.classList.add('loading');
            button.disabled = true;
        } else {
            button.classList.remove('loading');
            button.disabled = false;
        }
    }

    // Helper method to show confirmation before action
    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // Method to dynamically add action buttons
    addActionButton(itemId, buttonConfig) {
        const actionsDiv = document.getElementById('actions-' + itemId);
        if (!actionsDiv) return;
        
        const button = document.createElement('a');
        button.className = `btn-action ${buttonConfig.class || 'btn-default'}`;
        button.href = buttonConfig.href || '#';
        button.title = buttonConfig.title || '';
        
        if (buttonConfig.onclick) {
            button.onclick = buttonConfig.onclick;
        }
        
        const span = document.createElement('span');
        span.className = 'text';
        span.textContent = buttonConfig.text;
        
        button.appendChild(span);
        actionsDiv.appendChild(button);
    }

    // Method to update button visibility based on conditions
    updateButtonVisibility(itemId, conditions) {
        const actionsDiv = document.getElementById('actions-' + itemId);
        if (!actionsDiv) return;
        
        Object.keys(conditions).forEach(buttonClass => {
            const button = actionsDiv.querySelector(`.${buttonClass}`);
            if (button) {
                button.style.display = conditions[buttonClass] ? '' : 'none';
            }
        });
    }
}

// Global functions for backward compatibility
function toggleActions(itemId) {
    if (window.actionButtonsManager) {
        window.actionButtonsManager.toggleActions(itemId);
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.actionButtonsManager = new ActionButtonsManager();
    });
} else {
    window.actionButtonsManager = new ActionButtonsManager();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ActionButtonsManager;
}