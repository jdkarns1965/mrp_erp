<?php
/**
 * Modular Search Component for MRP/ERP System
 * 
 * Provides a consistent, drop-in search interface across all entity pages.
 * Ensures UI consistency while maintaining full functionality.
 */

/**
 * Render a standardized search component using Tailwind CSS
 * 
 * @param array $config Configuration array with the following keys:
 *   - 'entity' (required): Entity type for autocomplete preset (e.g., 'materials', 'products')
 *   - 'placeholder' (optional): Placeholder text for search input
 *   - 'current_search' (optional): Current search value to populate input
 *   - 'show_filters' (optional): Array of additional filter options
 *   - 'clear_url' (optional): URL for clear button (defaults to current page)
 *   - 'search_icon' (optional): Whether to show search icon (default: true)
 * 
 * @return string HTML for the search component using Tailwind classes
 */
function renderSearchComponent($config = []) {
    // Set defaults
    $defaults = [
        'entity' => 'items',
        'placeholder' => 'Search...',
        'current_search' => $_GET['search'] ?? '',
        'show_filters' => [],
        'clear_url' => 'index.php',
        'search_icon' => true,
        'form_method' => 'GET',
        'form_id' => 'searchForm'
    ];
    
    $config = array_merge($defaults, $config);
    
    // Generate autocomplete preset name
    $preset = $config['entity'] . '-search';
    
    ob_start();
    ?>
    <!-- Tailwind-based Search Component -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 mb-6">
        <form method="<?php echo htmlspecialchars($config['form_method']); ?>" 
              id="<?php echo htmlspecialchars($config['form_id']); ?>" 
              class="flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
              
            <!-- Search Input Section -->
            <div class="flex-1">
                <div class="relative">
                    <?php if ($config['search_icon']): ?>
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <?php endif; ?>
                    
                    <input type="text" 
                           name="search" 
                           id="searchInput"
                           placeholder="<?php echo htmlspecialchars($config['placeholder']); ?>"
                           value="<?php echo htmlspecialchars($config['current_search']); ?>"
                           data-autocomplete-preset="<?php echo htmlspecialchars($preset); ?>"
                           autocomplete="off"
                           class="w-full <?php echo $config['search_icon'] ? 'pl-10 pr-4' : 'px-4'; ?> py-3 border border-gray-300 rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
            </div>
            
            <!-- Search Controls -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                <!-- Search Buttons -->
                <div class="flex gap-3">
                    <button type="submit" 
                            class="inline-flex items-center justify-center px-4 py-3 min-w-[5rem] text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 hover:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Search
                    </button>
                    
                    <?php if (!empty($config['current_search']) || !empty($_GET)): ?>
                    <a href="<?php echo htmlspecialchars($config['clear_url']); ?>" 
                       class="inline-flex items-center justify-center px-4 py-3 min-w-[4rem] text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Clear
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Additional Filters -->
                <?php if (!empty($config['show_filters'])): ?>
                <div class="flex flex-wrap items-center gap-4">
                    <?php foreach ($config['show_filters'] as $filter): ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" 
                               name="<?php echo htmlspecialchars($filter['name']); ?>" 
                               value="<?php echo htmlspecialchars($filter['value']); ?>"
                               <?php echo !empty($_GET[$filter['name']]) ? 'checked' : ''; ?>
                               <?php echo !empty($filter['onchange']) ? 'onchange="' . htmlspecialchars($filter['onchange']) . '"' : ''; ?>
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                        <span class="text-sm text-gray-700 select-none">
                            <?php echo htmlspecialchars($filter['label']); ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get standardized search component CSS
 * Now using pure Tailwind classes, minimal custom CSS needed
 */
function getSearchComponentCSS() {
    return '
<style>
/* Minimal custom CSS for autocomplete integration */
.autocomplete-dropdown {
    top: 100%;
    margin-top: 0.25rem;
    z-index: 1000;
}

/* Recent search chips integration */
.recent-searches-wrapper {
    margin-top: 0.5rem;
}
</style>';
}

/**
 * Include required JavaScript for search functionality
 * Call this once per page to include the required scripts
 */
function includeSearchComponentJS() {
    echo '<script src="../js/autocomplete.js"></script>' . "\n";
    echo '<script src="../js/search-history-manager.js"></script>' . "\n";
    echo '<script src="../js/autocomplete-manager.js"></script>' . "\n";
    echo '<link rel="stylesheet" href="../css/autocomplete.css">' . "\n";
}
?>