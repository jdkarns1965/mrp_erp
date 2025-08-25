<?php
/**
 * Reusable Tailwind Form Components
 * Provides consistent styling across all forms in the MRP/ERP system
 */

class TailwindFormComponents {
    
    /**
     * Generate a professional page header with title, subtitle, and back button
     */
    public static function pageHeader($title, $subtitle = '', $backUrl = 'index.php', $backText = 'Back') {
        $backIcon = '<svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                     </svg>';
        
        return '
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">' . htmlspecialchars($title) . '</h1>
                    ' . ($subtitle ? '<p class="mt-1 text-sm text-gray-600">' . htmlspecialchars($subtitle) . '</p>' : '') . '
                </div>
                <a href="' . htmlspecialchars($backUrl) . '" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    ' . $backIcon . htmlspecialchars($backText) . '
                </a>
            </div>
        </div>';
    }
    
    /**
     * Generate error alert box
     */
    public static function errorAlert($errors) {
        if (empty($errors)) return '';
        
        $errorIcon = '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                      </svg>';
        
        $errorList = '';
        foreach ($errors as $error) {
            $errorList .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        
        return '
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">' . $errorIcon . '</div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">Please correct the following errors:</h3>
                    <ul class="mt-2 list-disc list-inside text-sm">' . $errorList . '</ul>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate success alert box
     */
    public static function successAlert($message) {
        if (empty($message)) return '';
        
        $successIcon = '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>';
        
        return '
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">' . $successIcon . '</div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">' . htmlspecialchars($message) . '</h3>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate info alert box
     */
    public static function infoAlert($title, $message) {
        $infoIcon = '<svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                       <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                     </svg>';
        
        return '
        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">' . $infoIcon . '</div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">' . htmlspecialchars($title) . '</h3>
                    <p class="text-sm">' . htmlspecialchars($message) . '</p>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Start a form section
     */
    public static function sectionStart($title) {
        return '
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
                <h2 class="text-lg font-medium text-gray-900">' . htmlspecialchars($title) . '</h2>
            </div>
            <div class="px-6 py-6 space-y-6">';
    }
    
    /**
     * End a form section
     */
    public static function sectionEnd() {
        return '
            </div>
        </div>';
    }
    
    /**
     * Generate a text input field
     */
    public static function textInput($name, $label, $value = '', $options = []) {
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '';
        $helpText = $options['help'] ?? '';
        $type = $options['type'] ?? 'text';
        $step = $options['step'] ?? '';
        $min = $options['min'] ?? '';
        $max = $options['max'] ?? '';
        
        $requiredMark = $required ? '<span class="text-red-500">*</span>' : '';
        $requiredAttr = $required ? 'required' : '';
        $stepAttr = $step ? 'step="' . $step . '"' : '';
        $minAttr = $min !== '' ? 'min="' . $min . '"' : '';
        $maxAttr = $max !== '' ? 'max="' . $max . '"' : '';
        
        $helpTextHtml = $helpText ? '<p class="mt-1 text-xs text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        return '
        <div>
            <label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700">
                ' . htmlspecialchars($label) . ' ' . $requiredMark . '
            </label>
            <input type="' . htmlspecialchars($type) . '" 
                   id="' . htmlspecialchars($name) . '" 
                   name="' . htmlspecialchars($name) . '" 
                   ' . $requiredAttr . '
                   ' . $stepAttr . '
                   ' . $minAttr . '
                   ' . $maxAttr . '
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                   placeholder="' . htmlspecialchars($placeholder) . '"
                   value="' . htmlspecialchars($value) . '">
            ' . $helpTextHtml . '
        </div>';
    }
    
    /**
     * Generate a textarea field
     */
    public static function textarea($name, $label, $value = '', $options = []) {
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '';
        $helpText = $options['help'] ?? '';
        $rows = $options['rows'] ?? 3;
        
        $requiredMark = $required ? '<span class="text-red-500">*</span>' : '';
        $requiredAttr = $required ? 'required' : '';
        $helpTextHtml = $helpText ? '<p class="mt-1 text-xs text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        return '
        <div>
            <label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700">
                ' . htmlspecialchars($label) . ' ' . $requiredMark . '
            </label>
            <textarea id="' . htmlspecialchars($name) . '" 
                      name="' . htmlspecialchars($name) . '" 
                      rows="' . $rows . '"
                      ' . $requiredAttr . '
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                      placeholder="' . htmlspecialchars($placeholder) . '">' . htmlspecialchars($value) . '</textarea>
            ' . $helpTextHtml . '
        </div>';
    }
    
    /**
     * Generate a select dropdown
     */
    public static function select($name, $label, $options, $selected = '', $config = []) {
        $required = $config['required'] ?? false;
        $helpText = $config['help'] ?? '';
        $emptyOption = $config['empty_option'] ?? 'Select...';
        
        $requiredMark = $required ? '<span class="text-red-500">*</span>' : '';
        $requiredAttr = $required ? 'required' : '';
        $helpTextHtml = $helpText ? '<p class="mt-1 text-xs text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        $optionsHtml = '';
        if ($emptyOption) {
            $optionsHtml .= '<option value="">' . htmlspecialchars($emptyOption) . '</option>';
        }
        
        foreach ($options as $value => $text) {
            $selectedAttr = ($selected == $value) ? 'selected' : '';
            $optionsHtml .= '<option value="' . htmlspecialchars($value) . '" ' . $selectedAttr . '>' . htmlspecialchars($text) . '</option>';
        }
        
        return '
        <div>
            <label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700">
                ' . htmlspecialchars($label) . ' ' . $requiredMark . '
            </label>
            <select id="' . htmlspecialchars($name) . '" 
                    name="' . htmlspecialchars($name) . '" 
                    ' . $requiredAttr . '
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                ' . $optionsHtml . '
            </select>
            ' . $helpTextHtml . '
        </div>';
    }
    
    /**
     * Generate a checkbox field
     */
    public static function checkbox($name, $label, $checked = false, $options = []) {
        $value = $options['value'] ?? '1';
        $helpText = $options['help'] ?? '';
        $checkedAttr = $checked ? 'checked' : '';
        $helpTextHtml = $helpText ? '<p class="text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        return '
        <div class="relative flex items-start">
            <div class="flex items-center h-5">
                <input type="checkbox" 
                       id="' . htmlspecialchars($name) . '" 
                       name="' . htmlspecialchars($name) . '" 
                       value="' . htmlspecialchars($value) . '"
                       ' . $checkedAttr . '
                       class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded">
            </div>
            <div class="ml-3 text-sm">
                <label for="' . htmlspecialchars($name) . '" class="font-medium text-gray-700">' . htmlspecialchars($label) . '</label>
                ' . $helpTextHtml . '
            </div>
        </div>';
    }
    
    /**
     * Generate currency input with dollar sign
     */
    public static function currencyInput($name, $label, $value = '', $options = []) {
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '0.00';
        $helpText = $options['help'] ?? '';
        
        $requiredMark = $required ? '<span class="text-red-500">*</span>' : '';
        $requiredAttr = $required ? 'required' : '';
        $helpTextHtml = $helpText ? '<p class="mt-1 text-xs text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        return '
        <div>
            <label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700">
                ' . htmlspecialchars($label) . ' ' . $requiredMark . '
            </label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">$</span>
                </div>
                <input type="number" 
                       id="' . htmlspecialchars($name) . '" 
                       name="' . htmlspecialchars($name) . '" 
                       step="0.01" 
                       min="0"
                       ' . $requiredAttr . '
                       class="block w-full rounded-md border-gray-300 pl-7 focus:border-primary focus:ring-primary sm:text-sm py-2 border"
                       placeholder="' . htmlspecialchars($placeholder) . '"
                       value="' . htmlspecialchars($value) . '">
            </div>
            ' . $helpTextHtml . '
        </div>';
    }
    
    /**
     * Generate input with suffix (like "units", "days", "%")
     */
    public static function inputWithSuffix($name, $label, $suffix, $value = '', $options = []) {
        $required = $options['required'] ?? false;
        $placeholder = $options['placeholder'] ?? '';
        $helpText = $options['help'] ?? '';
        $type = $options['type'] ?? 'number';
        $step = $options['step'] ?? '';
        $min = $options['min'] ?? '';
        $max = $options['max'] ?? '';
        
        $requiredMark = $required ? '<span class="text-red-500">*</span>' : '';
        $requiredAttr = $required ? 'required' : '';
        $stepAttr = $step ? 'step="' . $step . '"' : '';
        $minAttr = $min !== '' ? 'min="' . $min . '"' : '';
        $maxAttr = $max !== '' ? 'max="' . $max . '"' : '';
        $helpTextHtml = $helpText ? '<p class="mt-1 text-xs text-gray-500">' . htmlspecialchars($helpText) . '</p>' : '';
        
        return '
        <div>
            <label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700">
                ' . htmlspecialchars($label) . ' ' . $requiredMark . '
            </label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <input type="' . htmlspecialchars($type) . '" 
                       id="' . htmlspecialchars($name) . '" 
                       name="' . htmlspecialchars($name) . '" 
                       ' . $requiredAttr . '
                       ' . $stepAttr . '
                       ' . $minAttr . '
                       ' . $maxAttr . '
                       class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                       placeholder="' . htmlspecialchars($placeholder) . '"
                       value="' . htmlspecialchars($value) . '">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">' . htmlspecialchars($suffix) . '</span>
                </div>
            </div>
            ' . $helpTextHtml . '
        </div>';
    }
    
    /**
     * Generate form action buttons (Cancel/Submit)
     */
    public static function actionButtons($submitText = 'Save', $cancelUrl = 'index.php', $submitColor = 'primary') {
        $colorClasses = [
            'primary' => 'bg-primary hover:bg-primary-dark',
            'success' => 'bg-green-600 hover:bg-green-700',
            'danger' => 'bg-red-600 hover:bg-red-700'
        ];
        
        $buttonClass = $colorClasses[$submitColor] ?? $colorClasses['primary'];
        
        return '
        <div class="flex items-center justify-end gap-x-4">
            <a href="' . htmlspecialchars($cancelUrl) . '" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-md ' . $buttonClass . ' px-4 py-2.5 text-sm font-semibold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                ' . htmlspecialchars($submitText) . '
            </button>
        </div>';
    }
    
    /**
     * Generate a responsive grid container
     */
    public static function gridStart($columns = 2) {
        $gridClass = [
            1 => 'grid-cols-1',
            2 => 'grid-cols-1 gap-6 sm:grid-cols-2',
            3 => 'grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3',
            4 => 'grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4'
        ];
        
        return '<div class="grid ' . ($gridClass[$columns] ?? $gridClass[2]) . '">';
    }
    
    /**
     * End a grid container
     */
    public static function gridEnd() {
        return '</div>';
    }
}
?>