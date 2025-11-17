<?php
/**
 * Gelato Webhook Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/gelato-webhook', [
        'methods' => ['POST', 'GET'], // POST for Gelato, GET for testing
        'callback' => 'fursonaprints_gelato_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function fursonaprints_gelato_webhook_handler($request) {
    error_log('=== GELATO WEBHOOK RECEIVED ===');

    $method = $request->get_method();
    error_log("Request method: {$method}");

    // Handle GET requests (for testing)
    if ($method === 'GET') {
        return rest_ensure_response([
            'status' => 'ok',
            'message' => 'Gelato webhook endpoint is active',
            'endpoint' => home_url('/wp-json/myplugin/v1/gelato-webhook'),
            'instructions' => 'Configure this URL in your Gelato dashboard for store_product_updated events'
        ]);
    }

    // Log headers
    $headers = $request->get_headers();
    error_log('Webhook Headers: ' . print_r($headers, true));

    // Log body
    $body = $request->get_body();
    error_log('Webhook Body: ' . $body);

    // Parse JSON
    $data = json_decode($body, true);
    error_log('Webhook Data: ' . print_r($data, true));

    if (!$data) {
        error_log('WEBHOOK ERROR: Invalid JSON');
        return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
    }

    // Extract event type and product ID
    $event_type = $data['eventType'] ?? $data['event'] ?? $data['type'] ?? 'unknown';
    $product_id = $data['productId'] ?? $data['product_id'] ?? $data['id'] ?? null;

    error_log("Event Type: {$event_type}");
    error_log("Product ID: {$product_id}");

    if (!$product_id) {
        error_log('WEBHOOK ERROR: No product ID found');
        return new WP_REST_Response(['error' => 'No product ID'], 400);
    }

    // Find the post with this Gelato product ID
    $posts = get_posts([
        'post_type' => 'ai_result',
        'meta_query' => [
            [
                'key' => 'gelato_product_id',
                'value' => $product_id,
            ]
        ],
        'posts_per_page' => 1,
    ]);

    if (empty($posts)) {
        error_log("WEBHOOK: No post found for Gelato product ID: {$product_id}");
        return new WP_REST_Response(['message' => 'Product not found in database'], 404);
    }

    $post_id = $posts[0]->ID;
    error_log("Found post ID: {$post_id}");

    // Get fresh product data from Gelato API
    $gelato = new FursonaPrints_Gelato_API();
    $product_data = $gelato->get_product($product_id);

    if (is_wp_error($product_data)) {
        error_log('WEBHOOK ERROR: Failed to fetch product from Gelato API');
        return new WP_REST_Response(['error' => 'Failed to fetch product'], 500);
    }

    // Extract and save mockups
    $mockups = $gelato->get_mockup_urls($product_data);

    if (!empty($mockups)) {
        error_log("WEBHOOK: Saving " . count($mockups) . " mockups to post {$post_id}");
        update_post_meta($post_id, 'gelato_mockups', $mockups);
        update_post_meta($post_id, 'gelato_mockups_updated_at', current_time('mysql'));

        return rest_ensure_response([
            'success' => true,
            'post_id' => $post_id,
            'mockups_count' => count($mockups),
            'message' => 'Mockups saved successfully'
        ]);
    } else {
        error_log("WEBHOOK: No mockups found yet for product {$product_id}");
        return rest_ensure_response([
            'success' => false,
            'post_id' => $post_id,
            'message' => 'No mockups available yet'
        ]);
    }
}
