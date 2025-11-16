<?php
/**
 * Get Gelato Product Mockups Endpoint
 *
 * @package FursonaPrints
 * @version 0.1.1.4
 * @author The WP Clan
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/get-mockups', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $job_id = sanitize_text_field($request->get_param('job_id'));

            error_log("=== GET MOCKUPS: job_id={$job_id} ===");

            if (!$job_id) {
                return rest_ensure_response([
                    'error' => 'Missing job_id'
                ], 400);
            }

            // Find the post
            $posts = get_posts([
                'post_type' => 'ai_result',
                'meta_query' => [
                    [
                        'key' => 'job_id',
                        'value' => $job_id,
                    ]
                ],
                'posts_per_page' => 1,
            ]);

            if (empty($posts)) {
                error_log("No post found for job_id: {$job_id}");
                return rest_ensure_response([
                    'status' => 'not_found',
                    'message' => 'AI result post not found. n8n may not have saved the result yet.',
                    'job_id' => $job_id,
                    'mockups' => []
                ]);
            }

            $post_id = $posts[0]->ID;
            error_log("Found post ID: {$post_id}");

            $gelato_product_id = get_post_meta($post_id, 'gelato_product_id', true);

            if (!$gelato_product_id) {
                error_log("No Gelato product ID for post: {$post_id}");

                // Check if save-result was called
                $lowres_url = get_post_meta($post_id, 'lowres_url', true);
                $has_image = !empty($lowres_url);

                return rest_ensure_response([
                    'status' => 'pending',
                    'message' => 'Gelato product not created yet. Still processing...',
                    'post_id' => $post_id,
                    'has_image' => $has_image,
                    'mockups' => []
                ]);
            }

            error_log("Found Gelato product ID: {$gelato_product_id}");

            // Get product from Gelato API
            $gelato = new FursonaPrints_Gelato_API();
            $product_data = $gelato->get_product($gelato_product_id);

            if (is_wp_error($product_data)) {
                $error_msg = $product_data->get_error_message();
                error_log("Gelato API error: " . $error_msg);
                return rest_ensure_response([
                    'status' => 'error',
                    'message' => 'Gelato API error: ' . $error_msg,
                    'product_id' => $gelato_product_id,
                    'mockups' => []
                ]);
            }

            // Extract mockups
            $mockups = $gelato->get_mockup_urls($product_data);

            error_log("Returning " . count($mockups) . " mockups");

            if (empty($mockups)) {
                error_log("WARNING: Gelato returned product but no mockups found");
                return rest_ensure_response([
                    'status' => 'pending',
                    'message' => 'Product created but mockups not ready yet. Gelato is still generating them.',
                    'product_id' => $gelato_product_id,
                    'product_data' => $product_data, // Return full data for debugging
                    'mockups' => []
                ]);
            }

            return rest_ensure_response([
                'status' => 'ready',
                'message' => 'Mockups ready!',
                'mockups' => $mockups,
                'product_id' => $gelato_product_id
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});
