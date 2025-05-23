<?php
require_once 'api_utils.php';

// Prevent PHP errors from being output directly
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log the request method and data
$method = $_SERVER['REQUEST_METHOD'];
error_log("Products API called with method: $method");

// Initialize Odoo API
$odoo = getOdooApi();
if (!$odoo->isConnected()) {
    error_log("Odoo connection failed: " . $odoo->getLastError());
    sendError('Failed to connect to Odoo: ' . $odoo->getLastError(), 500);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Get products
            handleGetProducts($odoo);
            break;
            
        case 'POST':
            // Create a product
            handleCreateProduct($odoo);
            break;
            
        case 'PUT':
            // Update a product
            handleUpdateProduct($odoo);
            break;
            
        case 'DELETE':
            // Delete a product
            handleDeleteProduct($odoo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Exception in products.php: " . $e->getMessage());
    sendError('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle GET request to retrieve products
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleGetProducts($odoo) {
    error_log("Attempting to get products");
    
    // Handle pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    try {
        // Get product IDs first
        $productIds = $odoo->search('product.product', [], $offset, $limit);
        
        if ($productIds === false) {
            error_log("Error retrieving product IDs: " . $odoo->getLastError());
            // Return empty list on error
            sendResponse(true, [
                'products' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => 0
                ]
            ]);
            return;
        }
        
        error_log("Retrieved " . count($productIds) . " product IDs");
        
        // If no product IDs found, return empty list
        if (empty($productIds)) {
            sendResponse(true, [
                'products' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => 0
                ]
            ]);
            return;
        }
        
        // Get product details with qty_available field to get accurate stock numbers
        $products = $odoo->read(
            'product.product',
            $productIds,
            ['id', 'name', 'categ_id', 'list_price', 'standard_price', 'qty_available']
        );
        
        if ($products === false) {
            error_log("Error retrieving product details: " . $odoo->getLastError());
            // Return empty list on error
            sendResponse(true, [
                'products' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => 0
                ]
            ]);
            return;
        }
        
        error_log("Retrieved " . count($products) . " product details");
        
        // Count total products for pagination
        $totalCount = $odoo->searchCount('product.product', []);
        
        if ($totalCount === false) {
            $totalCount = count($products);
        }
        
        $totalPages = ceil($totalCount / $limit);
        
        error_log("Total products: $totalCount, Total pages: $totalPages");
        
        // Map products to the format expected by the frontend
        $mappedProducts = [];
        foreach ($products as $index => $product) {
            error_log("Processing product: " . json_encode($product));
            
            // Get actual stock from Odoo instead of generating it
            $stock = isset($product['qty_available']) ? $product['qty_available'] : 0;
            $minStock = 5; // Default min stock
            
            $categoryName = 'N/A';
            $categoryId = 0;
            
            if (isset($product['categ_id']) && is_array($product['categ_id']) && count($product['categ_id']) >= 2) {
                $categoryId = $product['categ_id'][0];
                $categoryName = $product['categ_id'][1];
            }
            
            $mappedProducts[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'category' => $categoryName,
                'category_id' => $categoryId,
                'price' => isset($product['list_price']) ? $product['list_price'] : 0,
                'cost' => isset($product['standard_price']) ? $product['standard_price'] : 0,
                'stock' => $stock,
                'min_stock' => $minStock
            ];
        }
        
        // Send response
        sendResponse(true, [
            'products' => $mappedProducts,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'pages' => $totalPages
            ]
        ]);
    } catch (Exception $e) {
        error_log("Exception in handleGetProducts: " . $e->getMessage());
        sendError('Error retrieving products: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle POST request to create a product
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleCreateProduct($odoo) {
    try {
        // Get posted data
        $input = file_get_contents('php://input');
        error_log("Raw input data: $input");
        
        $data = json_decode($input, true);
        
        // If JSON decoding failed, try getting from POST
        if ($data === null) {
            error_log("JSON decode failed, trying POST data");
            $data = $_POST;
            error_log("POST data: " . print_r($data, true));
        }
        
        error_log("Processed data: " . print_r($data, true));
        
        // Validate required fields
        $requiredFields = ['name', 'category_id', 'price', 'cost'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                error_log("Missing required field: $field");
                sendError("Field '$field' is required");
                return;
            }
        }
        
        // Check if inventory tracking is enabled
        $trackInventory = isset($data['track_inventory']) ? (bool)$data['track_inventory'] : false;
        
        // Create the product with the correct is_storable field
        $productData = [
            'name' => $data['name'],
            'categ_id' => intval($data['category_id']),
            'list_price' => floatval($data['price']),
            'standard_price' => floatval($data['cost']),
            'sale_ok' => true,
            'purchase_ok' => true,
            'is_storable' => $trackInventory  // This is the key field we need to set!
        ];
        
        // Add optional fields
        if (isset($data['description']) && !empty($data['description'])) {
            $productData['description'] = $data['description'];
        }
        
        error_log("Creating product in Odoo with data: " . print_r($productData, true));
        
        // Create product template
        $templateId = $odoo->create('product.template', $productData);
        
        if ($templateId === false) {
            error_log("Failed to create product template: " . $odoo->getLastError());
            sendError('Failed to create product: ' . $odoo->getLastError());
            return;
        }
        
        error_log("Product template created successfully with ID: $templateId");
        
        // Find the product variant that was created
        $productIds = $odoo->search('product.product', [['product_tmpl_id', '=', $templateId]]);
        
        if ($productIds === false || empty($productIds)) {
            error_log("Failed to find product variant: " . $odoo->getLastError());
            sendError('Product template created but failed to find product variant');
            return;
        }
        
        $productId = $productIds[0];
        error_log("Found product variant with ID: $productId");
        
        // Handle initial stock if provided and tracking is enabled
        if ($trackInventory && isset($data['initial_stock']) && floatval($data['initial_stock']) > 0) {
            $initialStock = floatval($data['initial_stock']);
            error_log("Setting initial stock: " . $initialStock);
            
            try {
                // Find the warehouse
                $warehouseIds = $odoo->search('stock.warehouse', [], 0, 1);
                if ($warehouseIds !== false && !empty($warehouseIds)) {
                    $warehouseId = $warehouseIds[0];
                    
                    // Find the stock location for this warehouse
                    $warehouse = $odoo->read('stock.warehouse', [$warehouseId], ['lot_stock_id']);
                    if ($warehouse !== false && !empty($warehouse) && isset($warehouse[0]['lot_stock_id']) && is_array($warehouse[0]['lot_stock_id'])) {
                        $locationId = $warehouse[0]['lot_stock_id'][0];
                        
                        // Create new stock quant
                        $stockData = [
                            'product_id' => $productId,
                            'location_id' => $locationId,
                            'inventory_quantity' => $initialStock,
                            'quantity' => $initialStock
                        ];
                        
                        $quantId = $odoo->create('stock.quant', $stockData);
                        error_log("Creating stock quant result: " . ($quantId !== false ? "Success with ID $quantId" : "Failed: " . $odoo->getLastError()));
                    }
                }
            } catch (Exception $e) {
                error_log("Exception setting initial stock: " . $e->getMessage());
            }
        }
        
        sendResponse(true, ['product_id' => $productId], 'Product created successfully');
    } catch (Exception $e) {
        error_log("Exception in product creation: " . $e->getMessage());
        sendError('Error creating product: ' . $e->getMessage());
    }
}

/**
 * Handle PUT request to update a product
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleUpdateProduct($odoo) {
    try {
        // Get posted data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // If JSON decoding failed, try getting from PUT
        if ($data === null) {
            parse_str($input, $data);
        }
        
        // Check if product ID is provided
        if (!isset($data['id']) || empty($data['id'])) {
            sendError("Product ID is required");
            return;
        }
        
        $productId = intval($data['id']);
        
        // Prepare product data
        $productData = [];
        
        // Add fields that can be updated
        if (isset($data['name']) && !empty($data['name'])) {
            $productData['name'] = $data['name'];
        }
        
        if (isset($data['category_id']) && !empty($data['category_id'])) {
            $productData['categ_id'] = intval($data['category_id']);
        }
        
        if (isset($data['price'])) {
            $productData['list_price'] = floatval($data['price']);
        }
        
        if (isset($data['cost'])) {
            $productData['standard_price'] = floatval($data['cost']);
        }
        
        if (isset($data['description'])) {
            $productData['description'] = $data['description'];
        }
        
        // Check if there are fields to update
        if (empty($productData)) {
            sendError("No fields to update");
            return;
        }
        
        // Update product
        $result = $odoo->write('product.product', [$productId], $productData);
        
        if ($result === false) {
            sendError('Failed to update product: ' . $odoo->getLastError());
            return;
        }
        
        sendResponse(true, null, 'Product updated successfully');
    } catch (Exception $e) {
        sendError('Error updating product: ' . $e->getMessage());
    }
}

/**
 * Handle DELETE request to delete a product
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleDeleteProduct($odoo) {
    try {
        // Check if product ID is provided
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            sendError("Product ID is required");
            return;
        }
        
        $productId = intval($_GET['id']);
        
        try {
            // First try to get the product template ID
            $product = $odoo->read('product.product', [$productId], ['product_tmpl_id']);
            
            if ($product !== false && !empty($product) && isset($product[0]['product_tmpl_id']) && is_array($product[0]['product_tmpl_id'])) {
                $templateId = $product[0]['product_tmpl_id'][0];
                
                // Try to archive the product instead of deleting it
                // This is safer and avoids the constraints errors
                $archiveResult = $odoo->write('product.template', [$templateId], ['active' => false]);
                
                if ($archiveResult) {
                    sendResponse(true, null, 'Product archived successfully');
                    return;
                }
            }
        } catch (Exception $e) {
            error_log("Exception trying to archive product: " . $e->getMessage());
            // Continue to deletion attempt
        }
        
        // If archiving failed, try regular deletion
        $result = $odoo->unlink('product.product', [$productId]);
        
        if ($result === false) {
            error_log("Failed to delete product: " . $odoo->getLastError());
            
            // If deletion fails, try to archive the product as a fallback
            try {
                $archiveResult = $odoo->write('product.product', [$productId], ['active' => false]);
                if ($archiveResult) {
                    sendResponse(true, null, 'Product archived successfully');
                    return;
                }
            } catch (Exception $e) {
                error_log("Exception in fallback archive: " . $e->getMessage());
            }
            
            sendError('Failed to delete product: ' . $odoo->getLastError());
            return;
        }
        
        sendResponse(true, null, 'Product deleted successfully');
    } catch (Exception $e) {
        error_log("Exception in product deletion: " . $e->getMessage());
        sendError('Error deleting product: ' . $e->getMessage());
    }
}
