/**
 * Reusable Autocomplete Component for MRP/ERP System
 * 
 * Usage:
 * const autocomplete = new AutoComplete(inputElement, {
 *     apiUrl: 'api/materials-search.php',
 *     minChars: 1,
 *     displayField: 'label',
 *     valueField: 'value',
 *     onSelect: (item) => { console.log('Selected:', item); }
 * });
 */

class AutoComplete {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = {
            apiUrl: '',
            minChars: 1,
            debounceMs: 300,
            maxResults: 10,
            displayField: 'label',
            valueField: 'value',
            searchParam: 'q',
            placeholder: 'No results found',
            onSelect: null,
            onClear: null,
            customTemplate: null,
            showCategory: false,
            ...options
        };
        
        this.selectedIndex = -1;
        this.suggestions = [];
        this.debounceTimer = null;
        this.dropdown = null;
        
        this.init();
    }
    
    init() {
        if (!this.input) {
            console.error('AutoComplete: Input element not found');
            return;
        }
        
        // Create dropdown container
        this.createDropdown();
        
        // Add event listeners
        this.bindEvents();
        
        // Add CSS class for styling
        this.input.classList.add('autocomplete-input');
        this.input.setAttribute('autocomplete', 'off');
    }
    
    createDropdown() {
        // Wrap input in container if not already wrapped
        if (!this.input.parentElement.classList.contains('autocomplete-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'autocomplete-wrapper';
            this.input.parentElement.insertBefore(wrapper, this.input);
            wrapper.appendChild(this.input);
        }
        
        // Create dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'autocomplete-dropdown';
        this.input.parentElement.appendChild(this.dropdown);
    }
    
    bindEvents() {
        // Input events
        this.input.addEventListener('input', (e) => this.handleInput(e));
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.input.addEventListener('focus', (e) => this.handleFocus(e));
        this.input.addEventListener('blur', (e) => this.handleBlur(e));
        
        // Document events
        document.addEventListener('click', (e) => this.handleDocumentClick(e));
    }
    
    handleInput(e) {
        clearTimeout(this.debounceTimer);
        const query = e.target.value.trim();
        
        if (query.length < this.options.minChars) {
            this.hideDropdown();
            return;
        }
        
        this.debounceTimer = setTimeout(() => {
            this.fetchSuggestions(query);
        }, this.options.debounceMs);
    }
    
    handleKeydown(e) {
        const items = this.dropdown.querySelectorAll('.autocomplete-item');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelection();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelection();
                break;
                
            case 'Enter':
                if (this.selectedIndex >= 0 && this.selectedIndex < this.suggestions.length) {
                    e.preventDefault();
                    this.selectItem(this.selectedIndex);
                }
                break;
                
            case 'Escape':
                this.hideDropdown();
                break;
        }
    }
    
    handleFocus(e) {
        // Show dropdown if there are cached results
        if (this.suggestions.length > 0) {
            this.showDropdown();
        }
    }
    
    handleBlur(e) {
        // Delay hiding to allow for clicks on dropdown items
        setTimeout(() => {
            if (!this.dropdown.contains(document.activeElement)) {
                this.hideDropdown();
            }
        }, 150);
    }
    
    handleDocumentClick(e) {
        if (!this.input.parentElement.contains(e.target)) {
            this.hideDropdown();
        }
    }
    
    async fetchSuggestions(query) {
        if (!this.options.apiUrl) {
            console.error('AutoComplete: No API URL specified');
            return;
        }
        
        try {
            const url = `${this.options.apiUrl}?${this.options.searchParam}=${encodeURIComponent(query)}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            this.suggestions = await response.json();
            
            if (this.suggestions.length > 0) {
                this.showSuggestions();
            } else {
                this.showNoResults();
            }
            
        } catch (error) {
            console.error('AutoComplete fetch error:', error);
            this.hideDropdown();
        }
    }
    
    showSuggestions() {
        let html = '<div class="autocomplete-items">';
        
        this.suggestions.slice(0, this.options.maxResults).forEach((item, index) => {
            if (this.options.customTemplate) {
                html += this.options.customTemplate(item, index, this.input.value);
            } else {
                html += this.createDefaultTemplate(item, index);
            }
        });
        
        html += '</div>';
        this.dropdown.innerHTML = html;
        this.showDropdown();
        this.bindItemEvents();
    }
    
    createDefaultTemplate(item, index) {
        const displayValue = item[this.options.displayField] || item.label || item.name || '';
        const highlightedText = this.highlightMatch(displayValue, this.input.value);
        
        let template = `
            <div class="autocomplete-item" data-index="${index}">
                <div class="item-main">${highlightedText}</div>
        `;
        
        if (this.options.showCategory && item.category) {
            template += `<span class="item-category">${item.category}</span>`;
        }
        
        if (item.code && item.code !== displayValue) {
            template += `<div class="item-code">${this.highlightMatch(item.code, this.input.value)}</div>`;
        }
        
        template += '</div>';
        return template;
    }
    
    showNoResults() {
        this.dropdown.innerHTML = `<div class="autocomplete-no-results">${this.options.placeholder}</div>`;
        this.showDropdown();
    }
    
    showDropdown() {
        this.dropdown.style.display = 'block';
        this.selectedIndex = -1;
    }
    
    hideDropdown() {
        this.dropdown.style.display = 'none';
        this.selectedIndex = -1;
    }
    
    bindItemEvents() {
        const items = this.dropdown.querySelectorAll('.autocomplete-item');
        
        items.forEach((item, index) => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                this.selectItem(index);
            });
            
            item.addEventListener('mouseenter', () => {
                this.selectedIndex = index;
                this.updateSelection();
            });
        });
    }
    
    updateSelection() {
        const items = this.dropdown.querySelectorAll('.autocomplete-item');
        
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    selectItem(index) {
        if (index >= 0 && index < this.suggestions.length) {
            const selectedItem = this.suggestions[index];
            const displayValue = selectedItem[this.options.displayField] || selectedItem.label || selectedItem.name || '';
            
            this.input.value = displayValue;
            this.hideDropdown();
            
            // Set value attribute if different from display
            if (selectedItem[this.options.valueField] && this.options.valueField !== this.options.displayField) {
                this.input.setAttribute('data-value', selectedItem[this.options.valueField]);
            }
            
            // Trigger custom callback
            if (this.options.onSelect) {
                this.options.onSelect(selectedItem, this.input);
            }
            
            // Trigger change event
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    highlightMatch(text, query) {
        if (!text || !query) return text;
        
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }
    
    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // Public methods
    clear() {
        this.input.value = '';
        this.input.removeAttribute('data-value');
        this.hideDropdown();
        
        if (this.options.onClear) {
            this.options.onClear(this.input);
        }
    }
    
    setValue(value, display) {
        this.input.value = display || value;
        if (value !== display) {
            this.input.setAttribute('data-value', value);
        }
    }
    
    getValue() {
        return this.input.getAttribute('data-value') || this.input.value;
    }
    
    destroy() {
        if (this.dropdown) {
            this.dropdown.remove();
        }
        
        this.input.classList.remove('autocomplete-input');
        this.input.removeAttribute('autocomplete');
        
        // Remove event listeners would require keeping references
        // For now, just clear references
        this.input = null;
        this.dropdown = null;
        this.suggestions = [];
    }
}

// Auto-initialize autocomplete fields with data attributes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-autocomplete]').forEach(input => {
        const options = {
            apiUrl: input.dataset.autocomplete,
            minChars: parseInt(input.dataset.minChars) || 1,
            displayField: input.dataset.displayField || 'label',
            valueField: input.dataset.valueField || 'value',
            showCategory: input.dataset.showCategory === 'true'
        };
        
        new AutoComplete(input, options);
    });
});

// Make AutoComplete available globally
window.AutoComplete = AutoComplete;