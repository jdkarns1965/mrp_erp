/**
 * Search History Manager for MRP/ERP System
 * 
 * Manages recent search history across different entity types using localStorage.
 * Provides functionality to store, retrieve, and display recent searches and selections.
 */

class SearchHistoryManager {
    constructor() {
        this.storageKey = 'mrp_search_history';
        this.maxHistoryItems = 8;
        this.maxHistoryAge = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds
    }

    /**
     * Get all history data from localStorage
     */
    getAllHistory() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : {};
        } catch (error) {
            console.warn('SearchHistoryManager: Failed to load history', error);
            return {};
        }
    }

    /**
     * Save history data to localStorage
     */
    saveHistory(historyData) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(historyData));
        } catch (error) {
            console.warn('SearchHistoryManager: Failed to save history', error);
        }
    }

    /**
     * Get recent items for a specific entity type
     */
    getRecentItems(entityType) {
        const history = this.getAllHistory();
        const entityHistory = history[entityType] || [];
        
        // Filter out expired items and sort by recency
        const now = Date.now();
        return entityHistory
            .filter(item => (now - item.timestamp) < this.maxHistoryAge)
            .sort((a, b) => b.timestamp - a.timestamp)
            .slice(0, this.maxHistoryItems);
    }

    /**
     * Add an item to search history
     */
    addToHistory(entityType, item) {
        if (!item || !item.id || !item.label) {
            return;
        }

        const history = this.getAllHistory();
        if (!history[entityType]) {
            history[entityType] = [];
        }

        const historyItem = {
            id: item.id,
            label: item.label,
            code: item.code || '',
            name: item.name || '',
            category: item.category || '',
            type: item.type || entityType,
            timestamp: Date.now(),
            searchType: 'selection' // vs 'query'
        };

        // Remove existing item if it exists
        history[entityType] = history[entityType].filter(h => h.id !== item.id);
        
        // Add to beginning
        history[entityType].unshift(historyItem);
        
        // Limit history size
        history[entityType] = history[entityType].slice(0, this.maxHistoryItems);
        
        this.saveHistory(history);
    }

    /**
     * Add a search query to history (for searches that don't result in selection)
     */
    addSearchQuery(entityType, query) {
        if (!query || query.trim().length < 2) {
            return;
        }

        const history = this.getAllHistory();
        if (!history[entityType]) {
            history[entityType] = [];
        }

        const historyItem = {
            id: `query_${Date.now()}`,
            label: query.trim(),
            code: '',
            name: query.trim(),
            category: '',
            type: entityType,
            timestamp: Date.now(),
            searchType: 'query'
        };

        // Remove existing similar query
        history[entityType] = history[entityType].filter(h => 
            h.searchType !== 'query' || h.label.toLowerCase() !== query.toLowerCase()
        );
        
        // Add to beginning
        history[entityType].unshift(historyItem);
        
        // Limit history size
        history[entityType] = history[entityType].slice(0, this.maxHistoryItems);
        
        this.saveHistory(history);
    }

    /**
     * Remove a specific item from history
     */
    removeFromHistory(entityType, itemId) {
        const history = this.getAllHistory();
        if (history[entityType]) {
            history[entityType] = history[entityType].filter(item => item.id !== itemId);
            this.saveHistory(history);
        }
    }

    /**
     * Clear all history for an entity type
     */
    clearEntityHistory(entityType) {
        const history = this.getAllHistory();
        delete history[entityType];
        this.saveHistory(history);
    }

    /**
     * Clear all search history
     */
    clearAllHistory() {
        try {
            localStorage.removeItem(this.storageKey);
        } catch (error) {
            console.warn('SearchHistoryManager: Failed to clear history', error);
        }
    }

    /**
     * Render recent search chips HTML
     */
    renderRecentChips(entityType, onChipClick, onClearClick) {
        const recentItems = this.getRecentItems(entityType);
        
        if (recentItems.length === 0) {
            return '';
        }

        // Limit items based on screen size (mobile-first approach)
        const isMobile = window.innerWidth <= 768;
        const maxItems = isMobile ? 3 : 6;
        const itemsToShow = recentItems.slice(0, maxItems);

        // Start collapsed on mobile by default for minimal space usage
        const containerClass = isMobile ? 'recent-searches-container collapsed' : 'recent-searches-container';
        
        let html = `<div class="${containerClass}">`;
        html += '<div class="recent-searches">';
        
        // On mobile, make it more compact with toggle option
        if (isMobile && recentItems.length > 0) {
            html += '<span class="recent-label">Recent:</span>';
            html += `<button class="recent-toggle" onclick="this.parentElement.parentElement.classList.toggle('collapsed'); this.textContent = this.textContent === '▲' ? '▼' : '▲';" title="Toggle recent searches">▲</button>`;
        } else {
            html += '<span class="recent-label">Recent:</span>';
        }
        
        itemsToShow.forEach(item => {
            const displayText = item.code || item.label;
            const isQuery = item.searchType === 'query';
            const icon = isQuery ? '🔍' : '📄';
            
            // More aggressive truncation on mobile
            const truncateLength = isMobile ? 12 : 20;
            
            html += `
                <span class="recent-chip ${isQuery ? 'recent-query' : 'recent-item'}" 
                      data-item-id="${item.id}"
                      data-item-data='${JSON.stringify(item)}'
                      title="${item.label}">
                    <span class="recent-icon">${icon}</span>
                    <span class="recent-text">${this.truncateText(displayText, truncateLength)}</span>
                    <button class="recent-remove" data-item-id="${item.id}" title="Remove from recent">×</button>
                </span>
            `;
        });
        
        // Show "Clear All" button and optionally a "Show More" indicator
        if (recentItems.length > maxItems) {
            html += `<button class="recent-clear-all" data-entity-type="${entityType}" title="Clear all recent searches">Clear All (+${recentItems.length - maxItems})</button>`;
        } else {
            html += `<button class="recent-clear-all" data-entity-type="${entityType}" title="Clear all recent searches">Clear All</button>`;
        }
        
        html += '</div></div>';
        
        return html;
    }

    /**
     * Bind events to recent search chips
     */
    bindRecentChipsEvents(container, entityType, onChipClick, onClearClick) {
        if (!container) return;

        // Chip click events
        container.querySelectorAll('.recent-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                if (e.target.classList.contains('recent-remove')) {
                    return; // Handle remove button separately
                }
                
                const itemData = JSON.parse(chip.dataset.itemData);
                if (onChipClick) {
                    onChipClick(itemData);
                }
            });
        });

        // Remove button events
        container.querySelectorAll('.recent-remove').forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const itemId = button.dataset.itemId;
                this.removeFromHistory(entityType, itemId);
                
                if (onClearClick) {
                    onClearClick(itemId);
                }
                
                // Re-render the chips
                this.updateRecentChipsDisplay(container, entityType, onChipClick, onClearClick);
            });
        });

        // Clear all button event
        const clearAllButton = container.querySelector('.recent-clear-all');
        if (clearAllButton) {
            clearAllButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.clearEntityHistory(entityType);
                
                if (onClearClick) {
                    onClearClick('all');
                }
                
                // Re-render the chips
                this.updateRecentChipsDisplay(container, entityType, onChipClick, onClearClick);
            });
        }
    }

    /**
     * Update the display of recent chips in a container
     */
    updateRecentChipsDisplay(container, entityType, onChipClick, onClearClick) {
        const html = this.renderRecentChips(entityType, onChipClick, onClearClick);
        container.innerHTML = html;
        this.bindRecentChipsEvents(container, entityType, onChipClick, onClearClick);
    }

    /**
     * Create and insert recent chips container after an input element
     */
    createRecentChipsContainer(inputElement, entityType, onChipClick, onClearClick) {
        const containerId = `recent-chips-${entityType}-${Date.now()}`;
        
        // Remove existing container if it exists
        const existingContainer = inputElement.parentElement.querySelector('.recent-searches-container');
        if (existingContainer) {
            existingContainer.remove();
        }
        
        // Create new container
        const container = document.createElement('div');
        container.id = containerId;
        container.className = 'recent-searches-wrapper';
        
        // Insert after the autocomplete wrapper
        const wrapper = inputElement.closest('.autocomplete-wrapper') || inputElement.parentElement;
        wrapper.parentElement.insertBefore(container, wrapper.nextSibling);
        
        // Render and bind events
        this.updateRecentChipsDisplay(container, entityType, onChipClick, onClearClick);
        
        return container;
    }

    /**
     * Truncate text to specified length
     */
    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) {
            return text;
        }
        return text.substring(0, maxLength - 3) + '...';
    }

    /**
     * Get recent items formatted for autocomplete dropdown
     */
    getRecentItemsForDropdown(entityType) {
        const recentItems = this.getRecentItems(entityType);
        return recentItems.map(item => ({
            ...item,
            isRecent: true,
            label: item.label,
            value: item.id
        }));
    }

    /**
     * Merge recent items with search results
     */
    mergeWithSearchResults(entityType, searchResults) {
        const recentItems = this.getRecentItemsForDropdown(entityType);
        
        // Filter out recent items that are already in search results
        const filteredRecent = recentItems.filter(recent => 
            !searchResults.some(result => result.id === recent.id)
        );
        
        // Return recent items first, then search results
        return [...filteredRecent, ...searchResults];
    }
}

// Create global instance
window.SearchHistoryManager = new SearchHistoryManager();