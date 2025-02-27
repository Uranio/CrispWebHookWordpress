<?php
/**
 * Plugin Name: Crisp Web Hook Listener
 * Description: Example of a webhook handler for Crisp chat in WordPress.
 * Version: 1.4
 * Author: Leonardo Marte
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('crisp/v1', '/webhook/', [
        'methods'  => 'POST',
        'callback' => 'handle_crisp_webhook',
        'permission_callback' => '__return_true',
    ]);
});

// Function to handle the webhook request
function handle_crisp_webhook(WP_REST_Request $request) {
    // Retrieve the request body
    $inputJSON = $request->get_body();
    $input = json_decode($inputJSON, true);

    // Validate the JSON data
    if (!$input || !isset($input['data'])) {
        error_log("Error: Invalid JSON or missing data.");
        return new WP_REST_Response(['error' => 'Invalid data'], 400);
    }

    $data = $input['data'];
    $message = $data['content'] ?? 'No message';
    $session_id = $data['session_id'] ?? null;
    $website_id = $data['website_id'] ?? 'example-website-id';
    $from = $data['from'] ?? 'unknown';

    // Avoid loops: process only messages sent by the user
    if ($from !== 'user') {
        error_log("Message ignored: sent by '$from'.");
        return new WP_REST_Response(['status' => 'ignored', 'reason' => 'Message sent by ' . $from], 200);
    }

    // Ensure session ID is provided
    if (!$session_id) {
        error_log("Error: Missing Session ID.");
        return new WP_REST_Response(['error' => 'Session ID is required'], 400);
    }

    // Prepare the payload to send to the external API
    $payload = json_encode([
        'session_id' => $session_id,
        'website_id' => $website_id,
        'message' => $message,
    ]);

    error_log("📡 Sending payload: " . $payload);

    // Define the external API URL (replace with actual API endpoint)
    $api_url = 'http://example.com/webhook';
    
    // Configure the HTTP request options
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10, // 10-second timeout
        ]
    ];

    // Send the request (you can use curl or file_get_contents() if your provider permit it)
    $context = stream_context_create($options);
    $result = @file_get_contents($api_url, false, $context);
    $http_response_header = $http_response_header ?? [];

    // Handle errors in the API request
    if ($result === false) {
        error_log("❌ Error in API request using file_get_contents()");
        return new WP_REST_Response(['error' => 'API request failed'], 500);
    }

    // Extract HTTP response code
    preg_match('/HTTP\/\d+\.\d+ (\d+)/', $http_response_header[0] ?? '', $matches);
    $http_code = $matches[1] ?? 'UNKNOWN';

    error_log("✅ API Response: HTTP $http_code - $result");

    return new WP_REST_Response([
        'status' => 'success',
        'message_sent' => $message,
        'node_response' => json_decode($result, true),
        'http_code' => $http_code,
    ], 200);
}
