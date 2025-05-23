<?php
/**
 * API utilities for the Odoo integration
 */

// Include the OdooAPI class
if (file_exists(__DIR__ . '/OdooAPI.php')) {
    require_once __DIR__ . '/OdooAPI.php';
} else {
    // Look in parent directory if not found here
    require_once __DIR__ . '/../API/OdooAPI.php';
}

// Only define these functions if they don't already exist
if (!function_exists('getOdooApi')) {
    /**
     * Get Odoo API instance
     * 
     * @return OdooAPI Instance of OdooAPI
     */
    function getOdooApi() {
        // Load configuration
        $config = include __DIR__ . '/config.php';
        
        // Create Odoo API instance
        $odoo = new OdooAPI(
            $config['odoo']['url'],
            $config['odoo']['db'],
            $config['odoo']['username'],
            $config['odoo']['apikey']
        );
        
        return $odoo;
    }
}

if (!function_exists('sendSuccess')) {
    /**
     * Helper function to send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     */
    function sendSuccess($data, $message = '') {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message
        ]);
        exit;
    }
}

if (!function_exists('sendError')) {
    /**
     * Helper function to send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    function sendError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
}

if (!function_exists('sendResponse')) {
    /**
     * Helper function to send standard response
     * 
     * @param bool $success Whether the request was successful
     * @param mixed $data Response data
     * @param string $message Response message
     * @param int $code HTTP status code
     */
    function sendResponse($success, $data, $message = '', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message
        ]);
        exit;
    }
}