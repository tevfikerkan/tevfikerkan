<?php
/**
 * Get Gelato Product Mockups Endpoint
 */

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
                    'mockups' => []
                ]);
            }

            $post_id = $posts[0]->ID;

            // First, check if we have cached mockups from webhook
            $cached_mockups = get_post_meta($post_id, 'gelato_mockups', true);

            if (!empty($cached_mockups) && is_array($cached_mockups)) {
                error_log("Returning " . count($cached_mockups) . " cached mockups from webhook");
                return rest_ensure_response([
                    'status' => 'ready',
                    'mockups' => $cached_mockups,
                    'source' => 'webhook_cache'
                ]);
            }

            error_log("No cached mockups found, checking Gelato API...");

            $gelato_product_id = get_post_meta($post_id, 'gelato_product_id', true);

            if (!$gelato_product_id) {
                error_log("No Gelato product ID for post: {$post_id}");
                return rest_ensure_response([
                    'status' => 'pending',
                    'mockups' => []
                ]);
            }

            error_log("Found Gelato product ID: {$gelato_product_id}");

            // Get product from Gelato API
            $gelato = new FursonaPrints_Gelato_API();
            $product_data = $gelato->get_product($gelato_product_id);

            if (is_wp_error($product_data)) {
                error_log("Gelato API error: " . $product_data->get_error_message());
                return rest_ensure_response([
                    'status' => 'error',
                    'message' => $product_data->get_error_message(),
                    'mockups' => []
                ]);
            }

            // Extract mockups
            $mockups = $gelato->get_mockup_urls($product_data);

            // Cache mockups if found
            if (!empty($mockups)) {
                update_post_meta($post_id, 'gelato_mockups', $mockups);
                update_post_meta($post_id, 'gelato_mockups_updated_at', current_time('mysql'));
                error_log("Cached " . count($mockups) . " mockups to post meta");
            }

            error_log("Returning " . count($mockups) . " mockups from API");

            return rest_ensure_response([
                'status' => !empty($mockups) ? 'ready' : 'pending',
                'mockups' => $mockups,
                'product_id' => $gelato_product_id,
                'source' => 'api'
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});
