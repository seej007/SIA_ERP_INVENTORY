<?php
require_once 'api_utils.php';

$odoo = getOdooApi();
if (!$odoo->isConnected()) {
    sendError('Failed to connect to Odoo: ' . $odoo->getLastError(), 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dashboard = [];
    
    $productIds = $odoo->search('product.product', []);
    $totalProducts = ($productIds !== false) ? count($productIds) : 0;
    $dashboard['totalProducts'] = $totalProducts;
    
    $totalStock = 0;
    $lowStockItems = 0;
    $lowStockProducts = [];
    
    if ($totalProducts > 0 && $productIds !== false) {
        $products = $odoo->read('product.product', $productIds, [
            'id', 'name', 'qty_available', 'type'
        ]);
        
        if ($products !== false) {
            foreach ($products as $product) {
                $stockQty = isset($product['qty_available']) ? (int)$product['qty_available'] : 0;
                $totalStock += $stockQty;
                
                if ($stockQty <= 5 && $stockQty > 0) {
                    $lowStockItems++;
                    $lowStockProducts[] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'stock' => $stockQty
                    ];
                }
            }
        }
    }
    
    $dashboard['totalStock'] = $totalStock;
    $dashboard['lowStockItems'] = $lowStockItems;
    $dashboard['lowStockProducts'] = $lowStockProducts;
    
    $pendingOrders = 0;
    
    $orderIds = $odoo->search('sale.order', []);
    if ($orderIds !== false) {
        $orders = $odoo->read('sale.order', $orderIds, ['id', 'state']);
        if ($orders !== false) {
            foreach ($orders as $order) {
                if (isset($order['state']) && in_array($order['state'], ['draft', 'sent', 'sale'])) {
                    $pendingOrders++;
                }
            }
        }
    } else {
        $purchaseOrderIds = $odoo->search('purchase.order', []);
        if ($purchaseOrderIds !== false) {
            $purchases = $odoo->read('purchase.order', $purchaseOrderIds, ['id', 'state']);
            if ($purchases !== false) {
                foreach ($purchases as $purchase) {
                    if (isset($purchase['state']) && in_array($purchase['state'], ['draft', 'sent', 'purchase'])) {
                        $pendingOrders++;
                    }
                }
            }
        }
    }
    
    $dashboard['pendingOrders'] = $pendingOrders;
    
    $recentActivities = [];
    
    $moveIds = $odoo->search('stock.move', [], 0, 10);
    if ($moveIds !== false && !empty($moveIds)) {
        $moves = $odoo->read('stock.move', $moveIds, [
            'date', 'product_id', 'product_uom_qty', 'reference', 'create_uid', 'state', 'origin'
        ]);
        
        if ($moves !== false) {
            foreach ($moves as $move) {
                if (isset($move['state']) && $move['state'] == 'done') {
                    $action = 'Stock Movement';
                    if (isset($move['reference'])) {
                        if (strpos(strtolower($move['reference']), 'out') !== false) {
                            $action = 'Stock Out';
                        } elseif (strpos(strtolower($move['reference']), 'in') !== false) {
                            $action = 'Stock In';
                        }
                    }
                    
                    $recentActivities[] = [
                        'date' => isset($move['date']) ? $move['date'] : date('Y-m-d H:i:s'),
                        'product' => isset($move['product_id']) && is_array($move['product_id']) ? 
                                    $move['product_id'][1] : 'Unknown Product',
                        'action' => $action,
                        'quantity' => isset($move['product_uom_qty']) ? $move['product_uom_qty'] : 0,
                        'user' => isset($move['create_uid']) && is_array($move['create_uid']) ? 
                                $move['create_uid'][1] : 'System'
                    ];
                }
            }
        }
    }
    
    if (empty($recentActivities)) {
        $pickingIds = $odoo->search('stock.picking', [], 0, 10);
        if ($pickingIds !== false && !empty($pickingIds)) {
            $pickings = $odoo->read('stock.picking', $pickingIds, [
                'date', 'name', 'partner_id', 'state', 'scheduled_date', 'origin', 'create_uid'
            ]);
            
            if ($pickings !== false) {
                foreach ($pickings as $picking) {
                    if (isset($picking['state']) && $picking['state'] == 'done') {
                        $action = 'Stock Movement';
                        if (isset($picking['name'])) {
                            if (strpos(strtolower($picking['name']), 'out') !== false) {
                                $action = 'Stock Out';
                            } elseif (strpos(strtolower($picking['name']), 'in') !== false) {
                                $action = 'Stock In';
                            }
                        }
                        
                        $recentActivities[] = [
                            'date' => isset($picking['date']) ? $picking['date'] : 
                                    (isset($picking['scheduled_date']) ? $picking['scheduled_date'] : date('Y-m-d H:i:s')),
                            'product' => isset($picking['origin']) ? $picking['origin'] : 
                                       (isset($picking['name']) ? $picking['name'] : 'Stock Movement'),
                            'action' => $action,
                            'quantity' => 0,
                            'user' => isset($picking['create_uid']) && is_array($picking['create_uid']) ? 
                                    $picking['create_uid'][1] : 'System'
                        ];
                    }
                }
            }
        }
    }
    
    if (!empty($recentActivities)) {
        usort($recentActivities, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }
    
    $dashboard['recentActivities'] = $recentActivities;
    
    sendResponse(true, $dashboard);
} else {
    sendError('Method not allowed', 405);
}
