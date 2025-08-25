/**
 * Centralized Autocomplete Manager for MRP/ERP System
 * 
 * Provides a unified, configuration-driven approach to autocomplete functionality
 * across all pages and forms in the application.
 * 
 * Usage:
 * AutocompleteManager.init('products-search', '#searchInput');
 * AutocompleteManager.init('materials-form', '.material-input');
 */

// Prevent redeclaration if already loaded
if (typeof AutocompleteManager === 'undefined') {
    class AutocompleteManager {
    static instances = new Map();
    
    // Predefined configurations for common use cases
    static presets = {
        // Search page configurations with history
        'products-search': {
            apiUrl: 'search-api.php',
            displayField: 'code',
            valueField: 'code',
            showCategory: true,
            behavior: 'search-submit',
            placeholder: 'Search products by code or name...',
            minChars: 1,
            enableHistory: true,
            entityType: 'products',
            customTemplate: function(item, index, query) {
                const highlightedCode = this.highlightMatch(item.code, query);
                const highlightedName = this.highlightMatch(item.name, query);
                
                return `
                    <div class="autocomplete-item" data-index="${index}">
                        <div class="item-main">
                            <div class="item-code">${highlightedCode}</div>
                            <div class="item-name">${highlightedName}</div>
                        </div>
                        ${item.category ? `<span class="item-category">${item.category}</span>` : ''}
                    </div>
                `;
            }
        },
        
        'materials-search': {
            apiUrl: '../api/materials-search.php',
            displayField: 'code',
            valueField: 'code',
            showCategory: true,
            behavior: 'search-submit',
            placeholder: 'Search materials by code or name...',
            minChars: 1,
            enableHistory: true,
            entityType: 'materials',
            customTemplate: function(item, index, query) {
                const highlightedCode = this.highlightMatch(item.code, query);
                const highlightedName = this.highlightMatch(item.name, query);
                
                return `
                    <div class="autocomplete-item" data-index="${index}">
                        <div class="item-main">
                            <div class="item-code">${highlightedCode}</div>
                            <div class="item-name">${highlightedName}</div>
                        </div>
                        ${item.category ? `<span class="item-category">${item.category}</span>` : ''}
                    </div>
                `;
            }
        },
        
        // Form field configurations with history
        'materials-form': {
            apiUrl: '../api/materials-search.php',
            displayField: 'label',
            valueField: 'id',
            showCategory: true,
            behavior: 'form-field',
            placeholder: 'Search materials...',
            minChars: 1,
            hiddenField: true,
            autoPopulateFields: ['uom', 'cost'],
            enableHistory: true,
            entityType: 'materials'
        },
        
        'products-form': {
            apiUrl: '../api/products-search.php',
            displayField: 'label',
            valueField: 'id',
            showCategory: true,
            behavior: 'form-field',
            placeholder: 'Search products...',
            minChars: 1,
            hiddenField: true,
            enableHistory: true,
            entityType: 'products'
        },
        
        'categories-form': {
            apiUrl: '../api/categories-search.php',
            displayField: 'name',
            valueField: 'id',
            showCategory: false,
            behavior: 'form-field',
            placeholder: 'Search categories...',
            minChars: 1,
            hiddenField: true,
            enableHistory: true,
            entityType: 'categories'
        },
        
        'uom-form': {
            apiUrl: '../api/uom-search.php',
            displayField: 'label',
            valueField: 'id',
            showCategory: false,
            behavior: 'form-field',
            placeholder: 'Search units of measure...',
            minChars: 1,
            hiddenField: true,
            enableHistory: true,
            entityType: 'uom'
        },
        
        'suppliers-form': {
            apiUrl: '../api/suppliers-search.php',
            displayField: 'name',
            valueField: 'id',
            showCategory: false,
            behavior: 'form-field',
            placeholder: 'Search suppliers...',
            minChars: 1,
            hiddenField: true,
            enableHistory: true,
            entityType: 'suppliers'
        },
        
        'locations-form': {
            apiUrl: '../api/locations-search.php',
            displayField: 'label',
            valueField: 'id',
            showCategory: false,
            behavior: 'form-field',
            placeholder: 'Search locations...',
            minChars: 1,
            hiddenField: true,
            enableHistory: true,
            entityType: 'locations'
        },
        
        // BOM search configuration
        'bom-search': {
            apiUrl: '../api/bom-search.php',
            displayField: 'product_code',
            valueField: 'product_code',
            showCategory: true,
            behavior: 'search-submit',
            placeholder: 'Search BOMs by product code, name, or description...',
            minChars: 1,
            enableHistory: true,
            entityType: 'bom',
            customTemplate: function(item, index, query) {
                const highlightedCode = this.highlightMatch(item.product_code, query);
                const highlightedName = this.highlightMatch(item.product_name, query);
                const highlightedDesc = item.description ? this.highlightMatch(item.description, query) : '';
                
                return `
                    <div class="autocomplete-item" data-index="${index}">
                        <div class="item-main">
                            <div class="item-code">${highlightedCode} v${item.version}</div>
                            <div class="item-name">${highlightedName}</div>
                            ${highlightedDesc ? `<div class="item-description">${highlightedDesc}</div>` : ''}
                        </div>
                        <div class="item-meta">
                            <span class="item-status ${item.is_active ? 'active' : 'inactive'}">${item.is_active ? 'Active' : 'Inactive'}</span>
                            <span class="item-materials">${item.material_count} materials</span>
                        </div>
                    </div>
                `;
            }
        },
        
        // Inventory search configuration
        'inventory-search': {
            apiUrl: '../api/inventory-search.php',
            displayField: 'code',
            valueField: 'code',
            showCategory: true,
            behavior: 'search-submit',
            placeholder: 'Search inventory by item code, name, or lot number...',
            minChars: 1,
            enableHistory: true,
            entityType: 'inventory',
            customTemplate: function(item, index, query) {
                const highlightedCode = this.highlightMatch(item.code, query);
                const highlightedName = this.highlightMatch(item.name, query);
                const highlightedLot = item.lot_number ? this.highlightMatch(item.lot_number, query) : null;
                
                // Status indicator colors
                const statusColors = {
                    'normal': '#10b981',
                    'low_stock': '#f59e0b', 
                    'out_of_stock': '#ef4444',
                    'expiring': '#f97316',
                    'expired': '#991b1b'
                };
                
                return `
                    <div class="autocomplete-item" data-index="${index}">
                        <div class="item-main">
                            <div class="item-code">${highlightedCode}</div>
                            <div class="item-name">${highlightedName}</div>
                            ${highlightedLot ? `<div class="item-lot">Lot: ${highlightedLot}</div>` : ''}
                        </div>
                        <div class="item-meta">
                            <div class="item-status-row">
                                <span class="stock-indicator" style="background-color: ${statusColors[item.stock_status] || '#6b7280'};"></span>
                                <span class="item-type">${item.item_type}</span>
                                <span class="item-available">Available: ${item.available}</span>
                                ${item.location ? `<span class="item-location">${item.location}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }
        }
    };
    
    /**
     * Initialize autocomplete with preset or custom configuration
     * 
     * @param {string} preset - Preset name or 'custom'
     * @param {string|Element} selector - CSS selector or DOM element
     * @param {object} customConfig - Custom configuration (optional)
     * @returns {AutoComplete|Array} - AutoComplete instance(s)
     */
    static init(preset, selector, customConfig = {}) {
        const config = preset === 'custom' ? customConfig : {
            ...this.presets[preset],
            ...customConfig
        };
        
        if (!config) {
            console.error(`AutocompleteManager: Unknown preset "${preset}"`);
            return null;
        }
        
        // Handle multiple elements
        const elements = typeof selector === 'string' 
            ? document.querySelectorAll(selector)
            : [selector];
        
        const instances = [];
        
        elements.forEach(element => {
            if (!element) return;
            
            const instance = this.createInstance(element, config);
            if (instance) {
                instances.push(instance);
                this.instances.set(element, instance);
            }
        });
        
        return instances.length === 1 ? instances[0] : instances;
    }
    
    /**
     * Create autocomplete instance with behavior-specific configuration
     */
    static createInstance(element, config) {
        // Prepare the element
        this.prepareElement(element, config);
        
        // Create autocomplete options based on behavior
        const options = this.buildOptions(config, element);
        
        // Setup search history if enabled
        if (config.enableHistory && config.entityType && window.SearchHistoryManager) {
            this.setupSearchHistory(element, config);
        }
        
        // Create and return instance
        try {
            return new AutoComplete(element, options);
        } catch (error) {
            console.error('AutocompleteManager: Failed to create instance', error);
            return null;
        }
    }
    
    /**
     * Prepare DOM element for autocomplete
     */
    static prepareElement(element, config) {
        // Set placeholder
        if (config.placeholder) {
            element.placeholder = config.placeholder;
        }
        
        // Wrap in autocomplete wrapper if needed
        if (!element.parentElement.classList.contains('autocomplete-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'autocomplete-wrapper';
            element.parentElement.insertBefore(wrapper, element);
            wrapper.appendChild(element);
        }
        
        // Add hidden field for form fields
        if (config.hiddenField && config.behavior === 'form-field') {
            const hiddenFieldName = element.name ? element.name.replace(/(_search|_name)$/, '_id') : 'item_id';
            
            let hiddenField = element.parentElement.querySelector('input[type="hidden"]');
            if (!hiddenField) {
                hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = hiddenFieldName;
                element.parentElement.appendChild(hiddenField);
            }
        }
        
        // Set CSS classes
        element.classList.add('autocomplete-input');
        if (config.cssClass) {
            element.classList.add(config.cssClass);
        }
    }
    
    /**
     * Build AutoComplete options based on configuration
     */
    static buildOptions(config, element) {
        const options = {
            apiUrl: config.apiUrl,
            displayField: config.displayField,
            valueField: config.valueField,
            showCategory: config.showCategory,
            minChars: config.minChars || 1,
            debounceMs: config.debounceMs || 300,
            maxResults: config.maxResults || 10
        };
        
        // Add custom template if provided
        if (config.customTemplate) {
            options.customTemplate = (item, index, query) => {
                // Bind the template function to our class context so it can use our utility methods
                return config.customTemplate.call(this, item, index, query);
            };
        }
        
        // Add behavior-specific callbacks
        switch (config.behavior) {
            case 'search-submit':
                options.onSelect = (item, inputEl) => {
                    inputEl.value = item[config.displayField];
                    
                    // Add to search history if enabled
                    if (config.enableHistory && config.entityType && window.SearchHistoryManager) {
                        window.SearchHistoryManager.addToHistory(config.entityType, item);
                    }
                    
                    const form = inputEl.closest('form');
                    if (form) {
                        form.submit();
                    }
                };
                break;
                
            case 'form-field':
                options.onSelect = (item, inputEl) => {
                    // Update hidden field
                    const hiddenField = inputEl.parentElement.querySelector('input[type="hidden"]');
                    if (hiddenField) {
                        hiddenField.value = item[config.valueField];
                    }
                    
                    // Add to search history if enabled
                    if (config.enableHistory && config.entityType && window.SearchHistoryManager) {
                        window.SearchHistoryManager.addToHistory(config.entityType, item);
                    }
                    
                    // Auto-populate related fields
                    if (config.autoPopulateFields) {
                        this.autoPopulateFields(inputEl, item, config.autoPopulateFields);
                    }
                    
                    // Trigger change event
                    inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                };
                
                options.onClear = (inputEl) => {
                    const hiddenField = inputEl.parentElement.querySelector('input[type="hidden"]');
                    if (hiddenField) {
                        hiddenField.value = '';
                    }
                };
                break;
        }
        
        // Add custom callbacks if provided
        if (config.onSelect) {
            const originalOnSelect = options.onSelect;
            options.onSelect = (item, inputEl) => {
                if (originalOnSelect) originalOnSelect(item, inputEl);
                config.onSelect(item, inputEl);
            };
        }
        
        if (config.onClear) {
            const originalOnClear = options.onClear;
            options.onClear = (inputEl) => {
                if (originalOnClear) originalOnClear(inputEl);
                config.onClear(inputEl);
            };
        }
        
        return options;
    }
    
    /**
     * Auto-populate related fields based on selected item
     */
    static autoPopulateFields(inputEl, item, fieldMappings) {
        const container = inputEl.closest('.material-item, .form-row, .card') || inputEl.parentElement;
        
        fieldMappings.forEach(field => {
            if (item[field]) {
                // Find related select or input field
                let targetField = container.querySelector(`select[name*="${field}"], input[name*="${field}"]`);
                
                if (targetField && targetField.tagName === 'SELECT') {
                    // For select fields, find matching option
                    const option = Array.from(targetField.options).find(opt => 
                        opt.textContent.toLowerCase().includes(item[field].toLowerCase())
                    );
                    if (option) {
                        targetField.value = option.value;
                    }
                } else if (targetField) {
                    // For input fields, set value directly
                    targetField.value = item[field];
                }
            }
        });
    }
    
    /**
     * Initialize all autocomplete fields with data attributes on page load
     */
    static autoInit() {
        document.querySelectorAll('[data-autocomplete-preset]').forEach(element => {
            const preset = element.dataset.autocompletePreset;
            const customConfig = {};
            
            // Extract configuration from data attributes
            if (element.dataset.autocompleteApi) customConfig.apiUrl = element.dataset.autocompleteApi;
            if (element.dataset.autocompleteDisplay) customConfig.displayField = element.dataset.autocompleteDisplay;
            if (element.dataset.autocompleteValue) customConfig.valueField = element.dataset.autocompleteValue;
            if (element.dataset.autocompleteCategory) customConfig.showCategory = element.dataset.autocompleteCategory === 'true';
            if (element.dataset.autocompletePlaceholder) customConfig.placeholder = element.dataset.autocompletePlaceholder;
            
            this.init(preset, element, customConfig);
        });
    }
    
    /**
     * Get autocomplete instance for an element
     */
    static getInstance(element) {
        return this.instances.get(element);
    }
    
    /**
     * Destroy autocomplete instance
     */
    static destroy(element) {
        const instance = this.instances.get(element);
        if (instance && instance.destroy) {
            instance.destroy();
            this.instances.delete(element);
        }
    }
    
    /**
     * Update preset configuration
     */
    static updatePreset(presetName, config) {
        this.presets[presetName] = { ...this.presets[presetName], ...config };
    }
    
    /**
     * Add new preset
     */
    static addPreset(presetName, config) {
        this.presets[presetName] = config;
    }
    
    /**
     * Setup search history functionality for an element
     */
    static setupSearchHistory(element, config) {
        if (!window.SearchHistoryManager) return;
        
        const historyManager = window.SearchHistoryManager;
        
        // Create recent chips container
        const onChipClick = (item) => {
            // Handle recent chip clicks
            element.value = item[config.displayField] || item.label;
            
            // Trigger autocomplete selection behavior
            if (config.behavior === 'search-submit') {
                const form = element.closest('form');
                if (form) {
                    form.submit();
                }
            } else if (config.behavior === 'form-field') {
                const hiddenField = element.parentElement.querySelector('input[type="hidden"]');
                if (hiddenField) {
                    hiddenField.value = item[config.valueField] || item.id;
                }
                element.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        
        const onClearClick = (itemId) => {
            // Refresh the chips display after clearing
            console.log('Cleared item:', itemId);
        };
        
        // Create the chips container with a slight delay to ensure DOM is ready
        setTimeout(() => {
            historyManager.createRecentChipsContainer(element, config.entityType, onChipClick, onClearClick);
        }, 100);
        
        // Enhance autocomplete to show recent items on focus (empty input)
        element.addEventListener('focus', () => {
            if (!element.value.trim()) {
                this.showRecentItemsInDropdown(element, config);
            }
        });
        
        // Add search query to history when user submits without selecting
        const form = element.closest('form');
        if (form && config.behavior === 'search-submit') {
            form.addEventListener('submit', (e) => {
                const query = element.value.trim();
                if (query && query.length >= 2) {
                    historyManager.addSearchQuery(config.entityType, query);
                }
            });
        }
    }
    
    /**
     * Show recent items in autocomplete dropdown when input is empty
     */
    static showRecentItemsInDropdown(element, config) {
        const autocompleteInstance = this.getInstance(element);
        if (!autocompleteInstance || !window.SearchHistoryManager) return;
        
        const recentItems = window.SearchHistoryManager.getRecentItemsForDropdown(config.entityType);
        if (recentItems.length > 0) {
            // Set the suggestions to recent items and show dropdown
            autocompleteInstance.suggestions = recentItems;
            autocompleteInstance.showSuggestions();
        }
    }
    
    /**
     * Highlight matching text in search results
     */
    static highlightMatch(text, query) {
        if (!text || !query) return text;
        
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }
    
    /**
     * Escape special regex characters
     */
    static escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    } // End class AutocompleteManager

    // Make available globally
    window.AutocompleteManager = AutocompleteManager;
}

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AutocompleteManager !== 'undefined') {
        AutocompleteManager.autoInit();
    }
});