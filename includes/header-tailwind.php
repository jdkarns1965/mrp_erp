<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MRP/ERP Manufacturing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        'primary-dark': '#1d4ed8',
                        'primary-light': '#3b82f6'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/mrp_erp/public/css/autocomplete.css">
</head>
<body class="h-full bg-gray-50 flex flex-col">
    <!-- Navigation Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo/Brand -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-bold text-gray-900">MRP/ERP</h1>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <?php 
                    $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/mrp_erp/public';
                    $current_uri = $_SERVER['REQUEST_URI'];
                    $nav_items = [
                        ['url' => '/', 'label' => 'Dashboard', 'check' => basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($current_uri, '/materials/') === false && strpos($current_uri, '/products/') === false],
                        ['url' => '/materials/', 'label' => 'Materials', 'check' => strpos($current_uri, '/materials/') !== false],
                        ['url' => '/products/', 'label' => 'Products', 'check' => strpos($current_uri, '/products/') !== false],
                        ['url' => '/bom/', 'label' => 'BOM', 'check' => strpos($current_uri, '/bom/') !== false],
                        ['url' => '/inventory/', 'label' => 'Inventory', 'check' => strpos($current_uri, '/inventory/') !== false],
                        ['url' => '/orders/', 'label' => 'Orders', 'check' => strpos($current_uri, '/orders/') !== false],
                        ['url' => '/mrp/', 'label' => 'MRP', 'check' => strpos($current_uri, '/mrp/') !== false],
                        ['url' => '/mps/', 'label' => 'MPS', 'check' => strpos($current_uri, '/mps/') !== false],
                        ['url' => '/production/', 'label' => 'Production', 'check' => strpos($current_uri, '/production/') !== false],
                        ['url' => '/documents/', 'label' => 'Documents', 'check' => strpos($current_uri, '/documents/') !== false]
                    ];
                    
                    foreach ($nav_items as $item): ?>
                        <a href="<?php echo $base_url . $item['url']; ?>" 
                           class="<?php echo $item['check'] ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700'; ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                            <?php echo $item['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Manual Link & Mobile Menu Button -->
                <div class="flex items-center space-x-4">
                    <a href="#" onclick="openManual(); return false;" class="text-gray-500 hover:text-gray-700 flex items-center space-x-1 text-sm font-medium transition-colors duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span>Manual</span>
                    </a>
                    
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="md:hidden bg-white p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Panel -->
        <div id="mobile-menu" class="md:hidden hidden">
            <div class="fixed inset-0 z-50">
                <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="mobile-menu-overlay"></div>
                <div class="fixed right-0 top-0 h-full w-64 bg-white shadow-xl transform transition-transform duration-300 ease-in-out">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Navigation</h2>
                        <button id="mobile-menu-close" class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <nav class="mt-4 space-y-1 px-4">
                        <?php foreach ($nav_items as $item): ?>
                            <a href="<?php echo $base_url . $item['url']; ?>" 
                               class="<?php echo $item['check'] ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> block px-3 py-2 rounded-md text-base font-medium">
                                <?php echo $item['label']; ?>
                            </a>
                        <?php endforeach; ?>
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <a href="#" onclick="openManual(); return false;" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 flex items-center px-3 py-2 rounded-md text-base font-medium">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                Manual
                            </a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Main Content Container -->
    <main class="flex-1">
        <!-- Content will be inserted here -->

<script>
    function openManual() {
        const manualUrl = '<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/mrp_erp/MRP_SYSTEM_MANUAL.html';
        window.open(manualUrl, 'mrpManual', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,location=no,directories=no,status=no,menubar=no');
    }
    
    // Mobile menu toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');

        function openMobileMenu() {
            mobileMenu.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', openMobileMenu);
        }
        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', closeMobileMenu);
        }
        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        }

        // Close mobile menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && mobileMenu && !mobileMenu.classList.contains('hidden')) {
                closeMobileMenu();
            }
        });

        // Close mobile menu when clicking on navigation links
        const mobileNavLinks = mobileMenu ? mobileMenu.querySelectorAll('nav a') : [];
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
    });
</script>