<?php
session_start();
require_once '../../includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Customer Orders
            <div style="float: right;">
                <a href="create.php" class="btn btn-primary">Create Order</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="alert alert-info">
            No orders found. Create your first customer order to get started with MRP calculations.
        </div>
        
        <div class="btn-group">
            <a href="create.php" class="btn btn-primary">Create First Order</a>
            <a href="../" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>