<?php
// admin_logic.php
$file = 'products.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // Load existing data
    if (file_exists($file)) {
        $currentData = json_decode(file_get_contents($file), true) ?? [];
    } else {
        $currentData = [];
    }

    if ($action === 'add') {
        // Collect data from form
        $name = $_POST['name'];
        $price = $_POST['price'];
        $image = $_POST['image'] ?: 'default.png';

        // Create new product matching your JSON structure
        $newProduct = [
            "id" => "PROD_" . time(), // Generates a unique ID
            "name" => $name,
            "title" => $name, // For now, title is same as name
            "price_bdt" => (int)$price,
            "sizes" => ["S", "M", "L", "XL"],
            "colors" => ["Black", "White", "Blue"],
            "stock" => "Available",
            "image" => $image
        ];
        
        $currentData[] = $newProduct;
    } 
    
    elseif ($action === 'delete') {
        $idToDelete = $_POST['id'];
        
        // Filter the array to remove the ID
        $currentData = array_filter($currentData, function($item) use ($idToDelete) {
            return (string)$item['id'] !== (string)$idToDelete;
        });

        // Re-index array so it stays a clean list [0, 1, 2] in JSON
        $currentData = array_values($currentData);
    }

    // Save the updated array back to JSON
    file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));
    
    // Redirect back to admin panel
    header("Location: admin.php");
    exit();
}