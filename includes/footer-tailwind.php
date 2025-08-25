    </main>
    
    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
                <div class="mb-2 md:mb-0">
                    &copy; <?php echo date('Y'); ?> MRP/ERP Manufacturing System. All rights reserved.
                </div>
                <div class="flex space-x-6">
                    <a href="#" onclick="openManual(); return false;" class="hover:text-gray-700 transition-colors duration-200">User Manual</a>
                    <a href="/mrp_erp/public/verify_setup.php" class="hover:text-gray-700 transition-colors duration-200">System Status</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Load any additional JavaScript files if needed -->
    <?php if (isset($include_autocomplete) && $include_autocomplete): ?>
        <script src="/mrp_erp/public/js/autocomplete.js"></script>
        <script src="/mrp_erp/public/js/autocomplete-manager.js"></script>
    <?php endif; ?>
    
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>