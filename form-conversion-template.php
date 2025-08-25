<?php
/* TAILWIND FORM CONVERSION TEMPLATE
 * Use this as a reference for converting old forms to new TailwindFormComponents
 */

// 1. Include the components file
require_once '../../includes/tailwind-form-components.php';

// 2. Replace page header
echo TailwindFormComponents::pageHeader(
    'Page Title',
    'Optional subtitle'
);

// 3. Replace error handling
echo TailwindFormComponents::errorAlert($errors);

// 4. Wrap form in proper structure
?>
<form method="POST" class="space-y-8">
    <?php echo TailwindFormComponents::sectionStart('Section Name'); ?>
    
    <?php echo TailwindFormComponents::gridStart(2); ?>
        <?php echo TailwindFormComponents::textInput(
            'field_name',
            'Field Label',
            $_POST['field_name'] ?? '',
            [
                'required' => true,
                'placeholder' => 'Example...',
                'help' => 'Helper text'
            ]
        ); ?>
    <?php echo TailwindFormComponents::gridEnd(); ?>
    
    <?php echo TailwindFormComponents::sectionEnd(); ?>
    
    <?php echo TailwindFormComponents::actionButtons('Save Changes'); ?>
</form>
<?php

echo "Template created at: form-conversion-template.php";
