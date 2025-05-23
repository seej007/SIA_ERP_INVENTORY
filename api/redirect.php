<?php
/**
 * Redirect script for backward compatibility
 * Redirects requests from old uppercase API folder to new lowercase api folder
 */

// Check if we've already been redirected to prevent loops
if (isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS'] == 200) {
    // We've already been redirected, so don't redirect again
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Redirect loop detected',
        'error' => 'ERR_TOO_MANY_REDIRECTS'
    ]);
    exit;
}

// Get the current script name without the full path
$requestedScript = basename($_SERVER['SCRIPT_FILENAME']);

// Build the new URL with the same query string
$newUrl = '../api/' . $requestedScript . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

// Redirect to the new location
header("Location: $newUrl");
exit;
