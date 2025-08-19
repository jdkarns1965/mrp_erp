<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MRP/ERP System</title>
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/mrp_erp/public/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>MRP/ERP Manufacturing System</h1>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <?php 
                $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/mrp_erp/public';
                $current_uri = $_SERVER['REQUEST_URI'];
                ?>
                <li><a href="<?php echo $base_url; ?>/" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($current_uri, '/materials/') === false && strpos($current_uri, '/products/') === false ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="<?php echo $base_url; ?>/materials/" <?php echo strpos($current_uri, '/materials/') !== false ? 'class="active"' : ''; ?>>Materials</a></li>
                <li><a href="<?php echo $base_url; ?>/products/" <?php echo strpos($current_uri, '/products/') !== false ? 'class="active"' : ''; ?>>Products</a></li>
                <li><a href="<?php echo $base_url; ?>/bom/" <?php echo strpos($current_uri, '/bom/') !== false ? 'class="active"' : ''; ?>>BOM</a></li>
                <li><a href="<?php echo $base_url; ?>/inventory/" <?php echo strpos($current_uri, '/inventory/') !== false ? 'class="active"' : ''; ?>>Inventory</a></li>
                <li><a href="<?php echo $base_url; ?>/orders/" <?php echo strpos($current_uri, '/orders/') !== false ? 'class="active"' : ''; ?>>Orders</a></li>
                <li><a href="<?php echo $base_url; ?>/mrp/" <?php echo strpos($current_uri, '/mrp/') !== false ? 'class="active"' : ''; ?>>MRP</a></li>
                <li><a href="<?php echo $base_url; ?>/production/" <?php echo strpos($current_uri, '/production/') !== false ? 'class="active"' : ''; ?>>Production</a></li>
            </ul>
        </div>
    </nav>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="container">
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        </div>
    <?php endif; ?>