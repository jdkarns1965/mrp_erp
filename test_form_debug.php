<?php
if ($_POST) {
    echo "<h2>Form Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    exit;
}
?>
<form method="POST">
    <input type="hidden" name="test" value="form_working">
    <h3>Test Materials Array</h3>
    <input type="hidden" name="materials[0][material_id]" value="90006">
    <input type="hidden" name="materials[0][quantity_per]" value="0.1">
    <input type="hidden" name="materials[1][material_id]" value="20636">
    <input type="hidden" name="materials[1][quantity_per]" value="1">
    <button type="submit">Test Form Submission</button>
</form>