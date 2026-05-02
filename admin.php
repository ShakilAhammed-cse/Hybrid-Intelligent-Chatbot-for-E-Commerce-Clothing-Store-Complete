<?php
// Load products to display in the table
$file = 'products.json';
$products = [];
if (file_exists($file)) {
    $products = json_decode(file_get_contents($file), true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hobe Shop - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Product Management</h2>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">View Shop</a>
        </div>
        
        <div class="card p-4 mb-4 shadow-sm">
            <h4 class="mb-3">Add New Product</h4>
            <form action="admin_logic.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Product Name / Title</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Classic T-Shirt" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Price (BDT)</label>
                        <input type="number" name="price" class="form-control" placeholder="1200" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Image Filename</label>
                        <input type="text" name="image" class="form-control" placeholder="product-x.png">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Add Product</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card p-4 shadow-sm">
            <h4 class="mb-3">Current Inventory</h4>
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Image</th>
                        <th>Name/Title</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="5" class="text-center">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $product): ?>
                        <tr>
                            <td>
                                <img src="assets/<?= htmlspecialchars($product['image'] ?? 'default.png') ?>" 
                                     alt="img" class="product-img" 
                                     onerror="this.src='https://via.placeholder.com/50'">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($product['name'] ?? 'No Name') ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($product['title'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($product['price_bdt'] ?? '0') ?> BDT</td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($product['stock'] ?? 'Available') ?></span></td>
                            <td>
                                <form action="admin_logic.php" method="POST" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>