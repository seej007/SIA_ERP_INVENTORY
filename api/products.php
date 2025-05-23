<?php
require_once 'api_utils.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$method = $_SERVER['REQUEST_METHOD'];
error_log("Products API called with method: $method");

try {
    $odoo = getOdooApi();
    if (!$odoo->isConnected()) {
        error_log("Odoo connection failed: " . $odoo->getLastError());
        sendError('Failed to connect to Odoo: ' . $odoo->getLastError(), 500);
    }

    switch ($method) {
        case 'GET':
            handleGetProducts($odoo);
            break;
            
        case 'POST':
            handleCreateProduct($odoo);
            break;
            
        case 'PUT':
            handleUpdateProduct($odoo);
            break;
            
        case 'DELETE':
            handleDeleteProduct($odoo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Exception in products.php: " . $e->getMessage());
    sendError('An error occurred: ' . $e->getMessage(), 500);
}

function handleGetProducts($odoo) {
    error_log("Attempting to get products");
    
    $domain = [];
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $_GET['search'];
        $domain = [['name', 'ilike', $search]];
    }
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 100;
    $offset = ($page - 1) * $limit;
    
    $fields = ['id', 'name', 'description', 'list_price', 'standard_price', 'qty_available', 'categ_id'];
    
    try {
        $products = $odoo->searchRead('product.product', $domain, $fields, $offset, $limit);
        
        if ($products === false) {
            error_log("Error retrieving products: " . $odoo->getLastError());
            sendError('Failed to get products: ' . $odoo->getLastError(), 500);
            return;
        }
        
        $totalCount = $odoo->searchCount('product.product', $domain);
        if ($totalCount === false) {
            $totalCount = count($products);
        }
        
        $processedProducts = [];
        foreach ($products as $product) {
            $categoryId = 0;
            $categoryName = 'Uncategorized';
            
            if (isset($product['categ_id']) && is_array($product['categ_id']) && count($product['categ_id']) >= 2) {
                $categoryId = $product['categ_id'][0];
                $categoryName = $product['categ_id'][1];
            }
            
            $processedProducts[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'description' => isset($product['description']) ? $product['description'] : '',
                'price' => isset($product['list_price']) ? $product['list_price'] : 0,
                'cost' => isset($product['standard_price']) ? $product['standard_price'] : 0,
                'stock' => isset($product['qty_available']) ? $product['qty_available'] : 0,
                'category_id' => $categoryId,
                'category' => $categoryName
            ];
        }
        
        sendSuccess([
            'products' => $processedProducts,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    } catch (Exception $e) {
        error_log("Exception in handleGetProducts: " . $e->getMessage());
        sendError('Error retrieving products: ' . $e->getMessage(), 500);
    }
}

function handleCreateProduct($odoo) {
    try {
        $input = file_get_contents('php://input');
        error_log("Raw input data: $input");
        
        $data = json_decode($input, true);
        
        if ($data === null) {
            error_log("JSON decode failed, trying POST data");
            $data = $_POST;
            error_log("POST data: " . print_r($data, true));
        }
        
        error_log("Processed data: " . print_r($data, true));
        
        if (empty($data['name'])) {
            error_log("Missing required field: name");
            sendError("Product name is required");
            return;
        }
        
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            error_log("Invalid price format: " . (isset($data['price']) ? gettype($data['price']) . " - " . $data['price'] : "not set"));
            sendError("Please enter a valid sale price");
            return;
        }
        
        $price = floatval($data['price']);
        if ($price < 0) {
            error_log("Negative price value: $price");
            sendError("Sale price cannot be negative");
            return;
        }
        
        if (!isset($data['cost']) || !is_numeric($data['cost'])) {
            error_log("Invalid cost format: " . (isset($data['cost']) ? gettype($data['cost']) . " - " . $data['cost'] : "not set"));
            sendError("Please enter a valid cost price");
            return;
        }
        
        $cost = floatval($data['cost']);
        if ($cost < 0) {
            error_log("Negative cost value: $cost");
            sendError("Cost price cannot be negative");
            return;
        }
        
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            $data['category_id'] = 1;
        }
        
        $trackInventory = isset($data['track_inventory']) ? (bool)$data['track_inventory'] : true;
        
        $productData = [
            'name' => $data['name'],
            'categ_id' => intval($data['category_id']),
            'list_price' => $price,
            'standard_price' => $cost,
            'sale_ok' => true,
            'purchase_ok' => true
        ];
        
        if ($trackInventory) {
            $productData['tracking'] = 'none';
        }
        
        if (isset($data['description']) && !empty($data['description'])) {
            $productData['description'] = $data['description'];
        }
        
        error_log("Creating product in Odoo with data: " . print_r($productData, true));
        
        $templateId = $odoo->create('product.template', $productData);
        
        if ($templateId === false) {
            error_log("Failed to create product template: " . $odoo->getLastError());
            sendError('Failed to create product: ' . $odoo->getLastError());
            return;
        }
        
        error_log("Product template created successfully with ID: $templateId");
        
        $productIds = $odoo->search('product.product', [['product_tmpl_id', '=', $templateId]]);
        
        if ($productIds === false || empty($productIds)) {
            error_log("Failed to find product variant: " . $odoo->getLastError());
            sendError('Product template created but failed to find product variant');
            return;
        }
        
        $productId = $productIds[0];
        error_log("Found product variant with ID: $productId");
        
        if ($trackInventory && isset($data['initial_stock']) && floatval($data['initial_stock']) > 0) {
            $initialStock = floatval($data['initial_stock']);
            error_log("Setting initial stock: " . $initialStock);
            
            try {
                $stockUpdateResult = $odoo->write('product.product', [$productId], [
                    'qty_available' => $initialStock
                ]);
                
                error_log("Stock update result: " . ($stockUpdateResult ? "Success" : "Failed: " . $odoo->getLastError()));
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

function handleUpdateProduct($odoo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null) {
            parse_str($input, $data);
        }
        
        if (!isset($data['id']) || empty($data['id'])) {
            sendError("Product ID is required");
            return;
        }
        
        $productId = intval($data['id']);
        
        $productData = [];
        
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
        
        if (empty($productData)) {
            sendError("No fields to update");
            return;
        }
        
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

function handleDeleteProduct($odoo) {
    try {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            sendError("Product ID is required");
            return;
        }
        
        $productId = intval($_GET['id']);
        
        try {
            $product = $odoo->read('product.product', [$productId], ['product_tmpl_id']);
            
            if ($product !== false && !empty($product) && isset($product[0]['product_tmpl_id']) && is_array($product[0]['product_tmpl_id'])) {
                $templateId = $product[0]['product_tmpl_id'][0];
                $archiveResult = $odoo->write('product.template', [$templateId], ['active' => false]);
                
                if ($archiveResult) {
                    sendResponse(true, null, 'Product archived successfully');
                    return;
                }
            }
        } catch (Exception $e) {
            error_log("Exception trying to archive product: " . $e->getMessage());
        }
        
        $result = $odoo->unlink('product.product', [$productId]);
        
        if ($result === false) {
            error_log("Failed to delete product: " . $odoo->getLastError());
            
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
