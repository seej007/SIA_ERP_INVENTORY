<?php
/**
 * Categories API
 * Handles product category operations
 */

// Include API utilities
require_once 'api_utils.php';

// Set headers to ensure proper response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Log the request
error_log("Categories API called with method: " . $_SERVER['REQUEST_METHOD']);

// Initialize Odoo API
$odoo = getOdooApi();

// Check connection
if (!$odoo->isConnected()) {
    sendError('Failed to connect to Odoo: ' . $odoo->getLastError(), 500);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get all product categories - use search first, then read
        $categoryIds = $odoo->search('product.category', []);
        
        if ($categoryIds === false) {
            // If we can't get categories, return an empty array
            sendSuccess(['categories' => []]);
        }
        
        if (empty($categoryIds)) {
            // If no categories found, return empty array
            sendSuccess(['categories' => []]);
        }
        
        // Read category details
        $categories = $odoo->read('product.category', $categoryIds, ['id', 'name', 'parent_id']);
        
        if ($categories === false) {
            // If read fails, return empty array
            sendSuccess(['categories' => []]);
        }
        
        // Process categories for the frontend
        $mappedCategories = [];
        foreach ($categories as $category) {
            $mappedCategories[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'parent_id' => isset($category['parent_id']) && is_array($category['parent_id']) ? $category['parent_id'][0] : 0,
                'parent_name' => isset($category['parent_id']) && is_array($category['parent_id']) ? $category['parent_id'][1] : ''
            ];
        }
        
        // Return the categories
        sendSuccess(['categories' => $mappedCategories]);
    } catch (Exception $e) {
        error_log("Exception in categories GET: " . $e->getMessage());
        sendError('Error retrieving categories: ' . $e->getMessage(), 500);
    }
}

// Handle unsupported methods
sendError('Method not allowed', 405);