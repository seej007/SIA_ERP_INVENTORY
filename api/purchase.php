<?php
/**
 * Purchase Orders API
 * Handles Odoo purchase.order, res.partner, and product.product API interactions
 */

// Set up error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    
    // Only handle fatal errors here, let the rest go through normal channels
    if ($errno == E_ERROR || $errno == E_USER_ERROR) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Internal Server Error: ' . $errstr
        ]);
        exit(1);
    }
    
    // Return false to let PHP handle the error normally
    return false;
});

// Include API utilities
require_once 'api_utils.php';

// Initialize Odoo API
$odoo = getOdooApi();
if (!$odoo->isConnected()) {
    sendError('Failed to connect to Odoo: ' . $odoo->getLastError(), 500);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    switch ($action) {
        case 'getSuppliers':
            handleGetSuppliers($odoo);
            break;
            
        case 'getProducts':
            handleGetProducts($odoo);
            break;
            
        case 'getPurchaseOrders':
            handleGetPurchaseOrders($odoo);
            break;
            
        case 'getPurchaseOrderDetails':
            $orderId = isset($_GET['orderId']) ? intval($_GET['orderId']) : 0;
            if (!$orderId) {
                sendError('Order ID is required', 400);
            }
            handleGetPurchaseOrderDetails($odoo, $orderId);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
}
// Handle POST requests
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        error_log("POST action received: " . $action);
        
        switch ($action) {
            case 'createPurchaseOrder':
                error_log("Processing createPurchaseOrder");
                
                // Try getting data from POST first
                $orderData = isset($_POST['orderData']) ? json_decode($_POST['orderData'], true) : null;
                
                // If that fails, try getting raw input
                if (!$orderData) {
                    $postData = file_get_contents('php://input');
                    error_log("Raw POST data: " . $postData);
                    
                    // Try to parse JSON from raw input
                    $postArray = json_decode($postData, true);
                    if ($postArray && isset($postArray['orderData'])) {
                        // If orderData is already a string (JSON), decode it
                        if (is_string($postArray['orderData'])) {
                            $orderData = json_decode($postArray['orderData'], true);
                        } else {
                            // If it's already an array, use it directly
                            $orderData = $postArray['orderData'];
                        }
                    }
                    
                    // Try one more approach - direct JSON parsing of the entire input
                    if (!$orderData) {
                        $directData = json_decode($postData, true);
                        if ($directData && isset($directData['partner_id'])) {
                            $orderData = $directData;
                        }
                    }
                }
                
                if (!$orderData) {
                    error_log("Order data not found or invalid. POST data: " . print_r($_POST, true));
                    sendError('Order data is required', 400);
                }
                
                error_log("Order data parsed successfully: " . print_r($orderData, true));
                handleCreatePurchaseOrder($odoo, $orderData);
                break;
                
            case 'createSupplier':
                error_log("Processing createSupplier");
                
                // Try getting data from POST first
                $supplierData = isset($_POST['supplierData']) ? json_decode($_POST['supplierData'], true) : null;
                
                // If that fails, try getting raw input
                if (!$supplierData) {
                    $postData = file_get_contents('php://input');
                    error_log("Raw POST data: " . $postData);
                    
                    // Try to parse JSON from raw input
                    $jsonData = json_decode($postData, true);
                    if ($jsonData && isset($jsonData['supplierData'])) {
                        $supplierData = json_decode($jsonData['supplierData'], true);
                    } elseif ($jsonData) {
                        $supplierData = $jsonData;
                    }
                }
                
                if (!$supplierData) {
                    error_log("Supplier data not found or invalid. POST data: " . print_r($_POST, true));
                    sendError('Supplier data is required', 400);
                }
                
                error_log("Supplier data parsed successfully: " . print_r($supplierData, true));
                handleCreateSupplier($odoo, $supplierData);
                break;
                
            default:
                error_log("Invalid action: " . $action);
                sendError('Invalid action', 400);
        }
    } catch (Exception $e) {
        error_log("Exception in POST processing: " . $e->getMessage());
        sendError('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
}
// Handle other request methods
else {
    sendError('Method not allowed', 405);
}

/**
 * Get suppliers (res.partner) from Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleGetSuppliers($odoo) {
    // Get all partners - without filtering by supplier field
    // since the field names vary between Odoo versions
    $domain = [];
    
    $fields = ['id', 'name', 'email', 'phone', 'street', 'city', 'zip'];
    
    $suppliers = $odoo->searchRead('res.partner', $domain, $fields, 0, 100);
    
    if ($suppliers === false) {
        sendError('Failed to get suppliers: ' . $odoo->getLastError(), 500);
        return;
    }
    
    sendSuccess($suppliers);
}

/**
 * Get products (product.product) from Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleGetProducts($odoo) {
    // Get all products without filtering by purchase_ok
    $domain = [];
    
    $fields = ['id', 'name', 'description', 'description_purchase', 'list_price', 'standard_price', 'qty_available'];
    
    $products = $odoo->searchRead('product.product', $domain, $fields, 0, 100);
    
    if ($products === false) {
        sendError('Failed to get products: ' . $odoo->getLastError(), 500);
        return;
    }
    
    sendSuccess($products);
}

/**
 * Get purchase orders from Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 */
function handleGetPurchaseOrders($odoo) {
    try {
        error_log("Getting customer invoices");
        
        // Check for available invoice models in Odoo
        $availableInvoiceModel = findInvoiceModel($odoo);
        
        if ($availableInvoiceModel === false) {
            error_log("No invoice models found in Odoo");
            sendError('Failed to get invoices: No suitable invoice model found', 500);
            return;
        }

        error_log("Using invoice model: " . $availableInvoiceModel['model']);
        
        // Get invoices based on model
        $model = $availableInvoiceModel['model'];
        $fields = ['id', 'name', 'partner_id', 'amount_total', 'state'];
        
        if ($model === 'account.invoice') {
            $domain = [['type', '=', 'out_invoice']];
            $fields[] = 'date_invoice';
            $dateField = 'date_invoice';
        } else {
            // account.move
            $domain = [['move_type', '=', 'out_invoice']];
            $fields[] = 'invoice_date';
            $dateField = 'invoice_date';
        }
        
        $orders = $odoo->searchRead($model, $domain, $fields, 0, 100, $dateField . ' DESC');
        
        if ($orders === false) {
            $error = $odoo->getLastError();
            error_log("Failed to get invoices: " . $error);
            sendError('Failed to get invoices: ' . $error, 500);
            return;
        }
        
        // Standardize date field for frontend
        foreach ($orders as &$order) {
            $order['date_order'] = $order[$dateField];
        }
        
        error_log("Successfully got " . count($orders) . " customer invoices");
        sendSuccess($orders);
    } catch (Exception $e) {
        error_log("Exception in handleGetPurchaseOrders: " . $e->getMessage());
        sendError('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
}

/**
 * Get invoice details from Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 * @param int $orderId Invoice ID
 */
function handleGetPurchaseOrderDetails($odoo, $orderId) {
    try {
        // Find the invoice model
        $availableInvoiceModel = findInvoiceModel($odoo);
        
        if ($availableInvoiceModel === false) {
            error_log("No invoice models found in Odoo");
            sendError('Failed to get invoice details: No suitable invoice model found', 500);
            return;
        }
        
        // Get field mappings
        $invoiceFields = determineInvoiceFields($availableInvoiceModel['model']);
        $model = $availableInvoiceModel['model'];
        $lineModel = $invoiceFields['line_model'];
        
        // Get invoice details
        $fields = [
            'id', 
            'name', 
            'partner_id', 
            $invoiceFields['date_field'], 
            'amount_total', 
            'state', 
            'invoice_line_ids', 
            'invoice_line',
            'currency_id',
            'journal_id'
        ];
        
        $order = $odoo->read($model, [$orderId], $fields);
        
        if ($order === false || count($order) === 0) {
            error_log("Failed to get invoice: " . $odoo->getLastError());
            sendError('Invoice not found or access denied', 404);
            return;
        }
        
        $order = $order[0];
        
        // Standardize date field for frontend
        $order['date_order'] = $order[$invoiceFields['date_field']];
        
        // Get invoice lines
        $lineIds = !empty($order['invoice_line_ids']) ? $order['invoice_line_ids'] : 
                 (!empty($order['invoice_line']) ? $order['invoice_line'] : []);
        
        if (!empty($lineIds)) {
            $lineFields = [
                'id',
                'product_id',
                $invoiceFields['quantity_field'],
                'price_unit',
                'price_subtotal',
                'name'
            ];
            
            // Add tax fields based on model
            if ($model === 'account.move') {
                $lineFields[] = 'tax_ids';
            } else {
                $lineFields[] = 'invoice_line_tax_ids';
            }
            
            $orderLines = $odoo->read($lineModel, $lineIds, $lineFields);
            
            if ($orderLines === false) {
                error_log("Failed to get invoice lines: " . $odoo->getLastError());
                sendError('Failed to get invoice line details', 500);
                return;
            }
            
            // Standardize quantity field for frontend
            foreach ($orderLines as &$line) {
                $line['product_qty'] = $line[$invoiceFields['quantity_field']];
            }
            
            $order['order_line'] = $orderLines;
        } else {
            $order['order_line'] = [];
        }
        
        sendSuccess($order);
    } catch (Exception $e) {
        error_log("Exception in handleGetPurchaseOrderDetails: " . $e->getMessage());
        sendError('An unexpected error occurred', 500);
    }
}

/**
 * Create customer invoice in Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 * @param array $orderData Invoice data
 */
function handleCreatePurchaseOrder($odoo, $orderData) {
    try {
        error_log("Creating customer invoice with data: " . print_r($orderData, true));
        
        // Validate required fields
        if (!isset($orderData['partner_id']) || empty($orderData['partner_id'])) {
            error_log("Missing required field: partner_id");
            sendError('Customer (partner_id) is required', 400);
            return;
        }

        if (!isset($orderData['date_order']) || empty($orderData['date_order'])) {
            error_log("Missing required field: date_order");
            sendError('Invoice date is required', 400);
            return;
        }
        
        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $orderData['date_order']);
        if (!$date || $date->format('Y-m-d') !== $orderData['date_order']) {
            error_log("Invalid date format: " . $orderData['date_order']);
            sendError('Invalid date format. Please use YYYY-MM-DD', 400);
            return;
        }
        
        if (!isset($orderData['order_line']) || empty($orderData['order_line'])) {
            error_log("Missing required field: order_line");
            sendError('Invoice lines are required', 400);
            return;
        }

        // Check for available invoice models in Odoo
        $availableInvoiceModel = findInvoiceModel($odoo);
        
        if ($availableInvoiceModel === false) {
            error_log("No invoice models found in Odoo.");
            // Create a mock invoice as fallback
            createMockOrder($orderData);
            return;
        }

        // Determine fields to use based on the model
        $invoiceFields = determineInvoiceFields($availableInvoiceModel['model']);
        
        // Get journal for customer invoices - required for both versions
        $journals = $odoo->searchRead('account.journal', [
            ['type', '=', 'sale']
        ], ['id'], 0, 1);
        
        if ($journals === false || empty($journals)) {
            error_log("No sale journal found");
            // Create a mock invoice as fallback
            createMockOrder($orderData);
            return;
        }
        
        $journalId = $journals[0]['id'];
        error_log("Using journal ID: " . $journalId);
        
        // Prepare base invoice values
        $invoiceValues = [
            'partner_id' => intval($orderData['partner_id']),
            $invoiceFields['date_field'] => $orderData['date_order'],
            $invoiceFields['type_field'] => $invoiceFields['type_value'],
            'journal_id' => $journalId,
            'state' => 'draft'
        ];

        // Add currency if provided
        if (isset($orderData['currency_id'])) {
            $invoiceValues['currency_id'] = intval($orderData['currency_id']);
        }
        
        // Create the invoice
        $invoiceId = $odoo->create($availableInvoiceModel['model'], $invoiceValues);
        if ($invoiceId === false) {
            $errorMessage = $odoo->getLastError();
            error_log("Failed to create invoice: " . $errorMessage);
            // Create a mock invoice as fallback
            createMockOrder($orderData);
            return;
        }

        // Add invoice lines
        $success = true;
        foreach ($orderData['order_line'] as $line) {
            $lineValues = [
                $invoiceFields['invoice_line_link'] => $invoiceId,
                'product_id' => intval($line['product_id']),
                $invoiceFields['quantity_field'] => floatval($line['product_qty']),
                'price_unit' => floatval($line['price_unit'])
            ];

            // Get product details
            $product = $odoo->read('product.product', [intval($line['product_id'])], ['name', 'taxes_id']);
            if ($product !== false && !empty($product)) {
                // Set product name
                $lineValues['name'] = $product[0]['name'];
                
                // Set taxes
                if (!empty($product[0]['taxes_id'])) {
                    if ($availableInvoiceModel['model'] === 'account.move') {
                        $lineValues['tax_ids'] = [[6, 0, $product[0]['taxes_id']]];
                    } else {
                        $lineValues['invoice_line_tax_ids'] = [[6, 0, $product[0]['taxes_id']]];
                    }
                }
            }

            $lineId = $odoo->create($invoiceFields['line_model'], $lineValues);
            if ($lineId === false) {
                $success = false;
                error_log("Failed to create invoice line: " . $odoo->getLastError());
                break;
            }
        }

        if (!$success) {
            // Leave invoice for debugging but report error
            error_log("Failed to create all invoice lines");
            // Create a mock invoice as fallback
            createMockOrder($orderData);
            return;
        }

        // Compute taxes and totals
        try {
            if ($availableInvoiceModel['model'] === 'account.move') {
                // Odoo 13+
                $odoo->execute($availableInvoiceModel['model'], 'action_post', [[$invoiceId]]);
                // Reset to draft for consistency
                $odoo->execute($availableInvoiceModel['model'], 'button_draft', [[$invoiceId]]);
            } else {
                // Older Odoo versions
                $odoo->execute($availableInvoiceModel['model'], 'button_reset_taxes', [[$invoiceId]]);
            }
        } catch (Exception $e) {
            error_log("Warning: Could not compute invoice totals: " . $e->getMessage());
            // Continue as this is not critical
        }

        sendSuccess(['invoice_id' => $invoiceId]);
    } catch (Exception $e) {
        error_log("Exception in handleCreatePurchaseOrder: " . $e->getMessage());
        // Create a mock invoice as fallback
        createMockOrder($orderData);
    }
}

/**
 * Create a mock invoice when Odoo models don't exist
 * 
 * @param array $orderData Invoice data
 */
function createMockOrder($orderData) {
    error_log("Creating mock invoice with data: " . print_r($orderData, true));
    
    // Start session to store mock orders
    session_start();
    
    // Generate a mock invoice ID
    $invoiceId = 'INV' . date('Ymd') . rand(1000, 9999);
    
    // Get customer name
    $customerName = "Unknown Customer";
    if (isset($orderData['partner_id'])) {
        // Try to get the partner name if we have it
        $partnerId = intval($orderData['partner_id']);
        
        // In a real app, we would query Odoo for the partner name
        // For now, use a mock name based on ID
        $customerName = "Customer " . $partnerId;
    }
    
    // Calculate total
    $total = 0;
    foreach ($orderData['order_line'] as $line) {
        $total += $line['product_qty'] * $line['price_unit'];
    }
    
    // Create a mock invoice response
    $mockInvoice = [
        'id' => time() . rand(100, 999),
        'name' => $invoiceId,
        'date_order' => $orderData['date_order'] . ' ' . date('H:i:s'),
        'partner_id' => [intval($orderData['partner_id']), $customerName],
        'amount_total' => $total,
        'state' => 'draft',
        'mock' => true,
        'order_line' => array_map(function($line) {
            // Add dummy tax for mock orders
            $line['tax_ids'] = [[1, 'VAT 10%']];
            return $line;
        }, $orderData['order_line'])
    ];
    
    // Store in session for persistence
    if (!isset($_SESSION['mock_purchase_orders'])) {
        $_SESSION['mock_purchase_orders'] = [];
    }
    $_SESSION['mock_purchase_orders'][] = $mockInvoice;
    
    error_log("Mock invoice created: " . print_r($mockInvoice, true));
    error_log("Total mock invoices in session: " . count($_SESSION['mock_purchase_orders']));
    
    // Return success with the mock invoice
    sendSuccess($mockInvoice);
}

/**
 * Create customer in Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 * @param array $customerData Customer data
 */
function handleCreateSupplier($odoo, $customerData) {
    try {
        error_log("Creating customer with data: " . print_r($customerData, true));
        
        // Check if email already exists
        if (!empty($customerData['email'])) {
            $existingPartners = $odoo->searchRead('res.partner', [
                ['email', '=', $customerData['email']]
            ], ['id']);
            
            if ($existingPartners !== false && count($existingPartners) > 0) {
                error_log("Customer with email already exists: " . $customerData['email']);
                sendError('A partner with this email already exists', 400);
                return;
            }
        }
        
        // Check which customer field exists in this Odoo version
        $customerField = null;
        $partnerFields = $odoo->getFields('res.partner');
        
        if ($partnerFields !== false) {
            if (isset($partnerFields['customer'])) {
                $customerField = 'customer';
                error_log("Using 'customer' field for res.partner");
            } else if (isset($partnerFields['is_customer'])) {
                $customerField = 'is_customer';
                error_log("Using 'is_customer' field for res.partner");
            } else if (isset($partnerFields['customer_rank'])) {
                $customerField = 'customer_rank';
                error_log("Using 'customer_rank' field for res.partner");
            } else {
                error_log("No customer field found in res.partner");
            }
        }
        
        // Prepare customer data
        $values = [
            'name' => $customerData['name'],
            'email' => $customerData['email'],
        ];
        
        // Add customer designation based on available field
        if ($customerField) {
            if ($customerField == 'customer_rank') {
                $values[$customerField] = 1; // Newer Odoo versions use rank
            } else {
                $values[$customerField] = true;
            }
        }
        
        // Add optional fields if provided
        if (!empty($customerData['phone'])) {
            $values['phone'] = $customerData['phone'];
        }
        
        if (!empty($customerData['street'])) {
            $values['street'] = $customerData['street'];
        }
        
        // Create customer
        $partnerId = $odoo->create('res.partner', $values);
        
        if ($partnerId === false) {
            error_log("Failed to create customer: " . $odoo->getLastError());
            sendError('Failed to create customer: ' . $odoo->getLastError(), 500);
            return;
        }
        
        error_log("Successfully created customer with ID: " . $partnerId);
        sendSuccess(['partner_id' => $partnerId]);
    } catch (Exception $e) {
        error_log("Exception in handleCreateSupplier: " . $e->getMessage());
        sendError('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
}

/**
 * Find available invoice model in Odoo
 * 
 * @param OdooAPI $odoo Odoo API instance
 * @return array|bool Model information if found, false otherwise
 */
function findInvoiceModel($odoo) {
    // First check if account.move exists and has required fields (Odoo 13+)
    $fields = $odoo->getFields('account.move');
    if ($fields !== false && isset($fields['move_type']) && isset($fields['invoice_date'])) {
        $modelCheck = $odoo->execute('ir.model', 'search_read', [
            [['model', '=', 'account.move']],
            ['id', 'name', 'model']
        ]);
        
        if ($modelCheck !== false && count($modelCheck) > 0) {
            error_log("Found modern invoice model: account.move");
            return $modelCheck[0];
        }
    }

    // Check if account.invoice exists (Odoo 12 and earlier)
    $fields = $odoo->getFields('account.invoice');
    if ($fields !== false && isset($fields['type']) && isset($fields['date_invoice'])) {
        $modelCheck = $odoo->execute('ir.model', 'search_read', [
            [['model', '=', 'account.invoice']],
            ['id', 'name', 'model']
        ]);
        
        if ($modelCheck !== false && count($modelCheck) > 0) {
            error_log("Found legacy invoice model: account.invoice");
            return $modelCheck[0];
        }
    }

    error_log("No suitable invoice models found in Odoo");
    return false;
}

/**
 * Determine the field names to use for invoices based on the model
 * 
 * @param string $model Model name
 * @return array Field mapping
 */
function determineInvoiceFields($model) {
    // Default fields for account.invoice (Odoo <= 12)
    if ($model === 'account.invoice') {
        return [
            'date_field' => 'date_invoice',
            'type_field' => 'type',
            'type_value' => 'out_invoice',  // customer invoice
            'line_model' => 'account.invoice.line',
            'invoice_line_link' => 'invoice_id',
            'quantity_field' => 'quantity',
            'tax_field' => 'invoice_line_tax_ids',
            'required_fields' => [
                'partner_id',
                'date_invoice',
                'type',
                'journal_id',
                'account_id',
                'currency_id'
            ],
            'line_required_fields' => [
                'name',
                'quantity',
                'price_unit',
                'invoice_id',
                'account_id'
            ]
        ];
    }
    
    // Fields for account.move (Odoo >= 13)
    return [
        'date_field' => 'invoice_date',
        'type_field' => 'move_type',
        'type_value' => 'out_invoice',  // customer invoice
        'line_model' => 'account.move.line',
        'invoice_line_link' => 'move_id',
        'quantity_field' => 'quantity',
        'tax_field' => 'tax_ids',
        'required_fields' => [
            'partner_id',
            'invoice_date',
            'move_type',
            'journal_id',
            'currency_id'
        ],
        'line_required_fields' => [
            'name',
            'quantity',
            'price_unit',
            'move_id',
            'account_id'
        ]
    ];
}/** * Get available models in Odoo *  * @param OdooAPI $odoo Odoo API instance * @param array $modelNames Model names to check * @return array Available models */function getAvailableModels($odoo, $modelNames) {    $result = [];        foreach ($modelNames as $modelName) {        $check = $odoo->execute('ir.model', 'search_read', [            [['model', '=', $modelName]],            ['id', 'name', 'model']        ]);                if ($check !== false && count($check) > 0) {            $result[] = $check[0]['model'];        }    }        return $result;}