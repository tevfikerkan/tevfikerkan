<?php
/**
 * Get Product Mockups Endpoint
 * Now generates mockups locally instead of using Gelato API
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

            // Check if we have generated mockups cached
            $cached_mockups = get_post_meta($post_id, 'generated_mockups', true);

            if (!empty($cached_mockups) && is_array($cached_mockups)) {
                error_log("Returning " . count($cached_mockups) . " cached mockups");
                return rest_ensure_response([
                    'status' => 'ready',
                    'mockups' => $cached_mockups,
                    'source' => 'cache'
                ]);
            }

            error_log("No cached mockups found, generating new mockups...");

            // Get the portrait image path
            $image_url = get_post_meta($post_id, 'lowres_url', true);

            if (!$image_url) {
                error_log("No image URL for post: {$post_id}");
                return rest_ensure_response([
                    'status' => 'pending',
                    'mockups' => [],
                    'message' => 'Portrait not ready yet'
                ]);
            }

            // Convert URL to local path
            $upload_dir = wp_upload_dir();
            $portrait_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);

            if (!file_exists($portrait_path)) {
                error_log("Portrait file not found: {$portrait_path}");
                return rest_ensure_response([
                    'status' => 'error',
                    'mockups' => [],
                    'message' => 'Portrait file not found'
                ]);
            }

            // Generate mockups
            $generator = new FursonaPrints_Mockup_Generator();
            $mockups = $generator->generate_mockups($portrait_path, $job_id);

            // Cache the generated mockups
            if (!empty($mockups)) {
                update_post_meta($post_id, 'generated_mockups', $mockups);
                update_post_meta($post_id, 'mockups_generated_at', current_time('mysql'));
                error_log("Cached " . count($mockups) . " generated mockups");
            }

            error_log("Returning " . count($mockups) . " newly generated mockups");

            return rest_ensure_response([
                'status' => !empty($mockups) ? 'ready' : 'error',
                'mockups' => $mockups,
                'source' => 'generated'
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});
