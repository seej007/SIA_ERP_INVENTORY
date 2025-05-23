<?php
/**
 * Stock API
 * Handles stock operations with Odoo integration
 */

// Set headers to ensure JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include API utilities
require_once '../api/api_utils.php';

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];
error_log("Stock API called with method: $method");

try {
    // Initialize Odoo API
    $odoo = getOdooApi();
    if (!$odoo->isConnected()) {
        error_log("Odoo connection failed: " . $odoo->getLastError());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to connect to Odoo: ' . $odoo->getLastError()
        ]);
        exit;
    }

    switch ($method) {
        case 'GET':
            // Get stock information
            handleGetStock($odoo);
            break;
            
        case 'POST':
            // Process stock in/out
            handleUpdateStock($odoo);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
    }
} catch (Exception $e) {
    error_log("Exception in stock.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Handle GET request to retrieve stock information
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleGetStock($odoo) {
    try {
        error_log("Attempting to get stock information");
        
        // Check for history request
        if (isset($_GET['history']) && $_GET['history'] == 1) {
            getStockHistory($odoo);
            return;
        }
        
        // Check for specific product request
        if (isset($_GET['product_id'])) {
            getProductStock($odoo, intval($_GET['product_id']));
            return;
        }
        
        // Get all products with stock information
        $productIds = $odoo->search('product.product', []);
        
        if ($productIds === false) {
            error_log("Failed to get products: " . $odoo->getLastError());
            echo json_encode([
                'success' => true,
                'data' => ['stock' => []]
            ]);
            exit;
        }
        
        error_log("Retrieved " . count($productIds) . " products");
        
        // If no products found, return empty array
        if (empty($productIds)) {
            echo json_encode([
                'success' => true,
                'data' => ['stock' => []]
            ]);
            exit;
        }
        
        // Get product details
        $products = $odoo->read(
            'product.product',
            $productIds,
            ['id', 'name', 'categ_id', 'qty_available']
        );
        
        if ($products === false) {
            error_log("Failed to get product details: " . $odoo->getLastError());
            echo json_encode([
                'success' => true,
                'data' => ['stock' => []]
            ]);
            exit;
        }
        
        // Process products with their stock information
        $stockByProduct = [];
        foreach ($products as $product) {
            // Get category information
            $categoryName = 'N/A';
            $categoryId = 0;
            
            if (isset($product['categ_id']) && is_array($product['categ_id']) && count($product['categ_id']) >= 2) {
                $categoryId = $product['categ_id'][0];
                $categoryName = $product['categ_id'][1];
            }
            
            // Get stock quantity
            $stockQty = isset($product['qty_available']) ? $product['qty_available'] : 0;
            
            // Default min stock level
            $minStock = 5;
            
            $stockByProduct[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'quantity' => $stockQty,
                'category' => $categoryName,
                'category_id' => $categoryId,
                'min_stock' => $minStock,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
        
        // Send response
        echo json_encode([
            'success' => true,
            'data' => ['stock' => $stockByProduct]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Exception in handleGetStock: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving stock information: ' . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Get stock information for a specific product
 * 
 * @param OdooAPI $odoo Odoo API instance
 * @param int $productId Product ID
 */
function getProductStock($odoo, $productId) {
    try {
        // Get product details
        $product = $odoo->read('product.product', [$productId], ['name', 'default_code', 'categ_id', 'qty_available']);
        
        if ($product === false || empty($product)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to get product information: ' . $odoo->getLastError()
            ]);
            exit;
        }
        
        // Get stock quantity
        $stockQty = isset($product[0]['qty_available']) ? $product[0]['qty_available'] : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'product' => $product[0],
                'stock' => [
                    'quantity' => $stockQty
                ]
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Exception in getProductStock: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving product stock: ' . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Get stock movement history
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function getStockHistory($odoo) {
    try {
        // Filter by product if provided
        $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
        
        // Get product information if product_id is specified
        $productName = 'Unknown Product';
        if ($productId) {
            $product = $odoo->read('product.product', [$productId], ['name']);
            if ($product !== false && !empty($product)) {
                $productName = $product[0]['name'];
            }
        }
        
        // Get stock moves
        $domain = $productId ? [['product_id', '=', $productId]] : [];
        $moveIds = $odoo->search('stock.move', $domain, 0, 20);
        $history = [];
        
        if ($moveIds !== false && !empty($moveIds)) {
            $moves = $odoo->read(
                'stock.move',
                $moveIds,
                ['date', 'product_id', 'product_uom_qty', 'state', 'reference', 'create_uid', 'location_id', 'location_dest_id']
            );
            
            if ($moves !== false) {
                foreach ($moves as $move) {
                    if (isset($move['state']) && $move['state'] == 'done') {
                        // Default type is 'in'
                        $type = 'in';
                        
                        // Try to determine if it's an 'in' or 'out' movement
                        if (isset($move['reference'])) {
                            if (strpos(strtolower($move['reference']), 'out') !== false) {
                                $type = 'out';
                            }
                        }
                        
                        $history[] = [
                            'date' => $move['date'],
                            'product' => isset($move['product_id']) && is_array($move['product_id']) ? $move['product_id'][1] : $productName,
                            'product_id' => isset($move['product_id']) && is_array($move['product_id']) ? $move['product_id'][0] : $productId,
                            'quantity' => $move['product_uom_qty'],
                            'type' => $type,
                            'reference' => isset($move['reference']) ? $move['reference'] : '',
                            'user' => isset($move['create_uid']) && is_array($move['create_uid']) ? $move['create_uid'][1] : 'System'
                        ];
                    }
                }
            }
        }
        
        // Sort by date (newest first)
        usort($history, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        echo json_encode([
            'success' => true,
            'data' => ['history' => $history]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Exception in getStockHistory: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving stock history: ' . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Handle POST request to update stock
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleUpdateStock($odoo) {
    try {
        // Get posted data
        $input = file_get_contents('php://input');
        error_log("Stock update raw input: " . $input);
        
        $data = json_decode($input, true);
        
        // If JSON decoding failed, try getting from POST
        if ($data === null) {
            $data = $_POST;
        }
        
        error_log("Stock update processed data: " . print_r($data, true));
        
        // Validate required fields
        $requiredFields = ['product_id', 'quantity', 'action'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
                echo json_encode([
                    'success' => false,
                    'message' => "Field '$field' is required"
                ]);
                exit;
            }
        }
        
        $productId = intval($data['product_id']);
        $quantity = floatval($data['quantity']);
        $action = $data['action']; // 'in' or 'out'
        
        if ($quantity <= 0) {
            echo json_encode([
                'success' => false,
                'message' => "Quantity must be greater than zero"
            ]);
            exit;
        }
        
        if ($action !== 'in' && $action !== 'out') {
            echo json_encode([
                'success' => false,
                'message' => "Action must be 'in' or 'out'"
            ]);
            exit;
        }
        
        // Get product info
        $product = $odoo->read('product.product', [$productId], ['name', 'qty_available']);
        
        if ($product === false || empty($product)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to get product information: ' . $odoo->getLastError()
            ]);
            exit;
        }
        
        $productName = $product[0]['name'];
        $currentQty = isset($product[0]['qty_available']) ? floatval($product[0]['qty_available']) : 0;
        
        // Calculate new quantity
        $newQty = $action === 'in' ? ($currentQty + $quantity) : max(0, $currentQty - $quantity);
        
        // Prepare reference
        $reference = isset($data['reference']) ? $data['reference'] : '';
        if (isset($data['notes']) && !empty($data['notes'])) {
            $reference .= empty($reference) ? $data['notes'] : ' - ' . $data['notes'];
        }
        if (empty($reference)) {
            $reference = $action === 'in' ? 'Stock In' : 'Stock Out';
        }
        
        // Update product quantity directly
        $updateResult = $odoo->write('product.product', [$productId], [
            'qty_available' => $newQty
        ]);
        
        if ($updateResult === false) {
            error_log("Failed to update product quantity: " . $odoo->getLastError());
            
            // Try creating a stock move as a fallback
            $moveData = [
                'name' => $action === 'in' ? 'Stock In' : 'Stock Out',
                'product_id' => $productId,
                'product_uom_qty' => $quantity,
                'state' => 'done',
                'reference' => $reference . ' (Admin)'
            ];
            
            $moveId = $odoo->create('stock.move', $moveData);
            
            if ($moveId === false) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update stock: ' . $odoo->getLastError()
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => ['move_id' => $moveId],
                    'message' => 'Stock updated successfully via stock movement'
                ]);
                exit;
            }
        } else {
            // Also create a stock move for historical purposes
            $moveData = [
                'name' => $action === 'in' ? 'Stock In' : 'Stock Out',
                'product_id' => $productId,
                'product_uom_qty' => $quantity,
                'state' => 'done',
                'reference' => $reference . ' (Admin)'
            ];
            
            $moveId = $odoo->create('stock.move', $moveData);
            
            // Even if move creation fails, we've updated the product quantity
            echo json_encode([
                'success' => true,
                'data' => [
                    'product_id' => $productId,
                    'old_quantity' => $currentQty,
                    'new_quantity' => $newQty,
                    'move_id' => ($moveId !== false) ? $moveId : null
                ],
                'message' => 'Stock updated successfully'
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Exception in stock update: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error updating stock: ' . $e->getMessage()
        ]);
        exit;
    }
}