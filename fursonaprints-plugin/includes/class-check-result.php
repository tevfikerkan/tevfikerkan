<?php
/**
 * Check AI Result Endpoint
 *
 * @package FursonaPrints
 * @version 0.1.1.3
 * @author The WP Clan
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/check-result', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $job_id = sanitize_text_field($request->get_param('job_id'));

            error_log("=== CHECK RESULT: job_id={$job_id} ===");

            // Replicate URL'sini kontrol et (eski sistem için)
            $replicate_url = get_option("ai_result_{$job_id}");

            error_log("Option value: " . ($replicate_url ? $replicate_url : 'NOT FOUND'));

            if (!$replicate_url) {
                error_log("Result PENDING for job_id: {$job_id}");
                return rest_ensure_response([
                    'status' => 'pending',
                    'image_url' => null,
                ]);
            }
            
            // WordPress'teki post'u bul
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
            
            // WP'ye yüklenmişse WP URL'sini kullan
            if (!empty($posts)) {
                $post_id = $posts[0]->ID;
                $attachment_id = get_post_meta($post_id, 'lowres_attachment_id', true);
                
                if ($attachment_id) {
                    $wp_image_url = wp_get_attachment_url($attachment_id);
                    
                    return rest_ensure_response([
                        'status' => 'ready',
                        'image_url' => $wp_image_url, // ✅ WordPress URL
                        'replicate_url' => $replicate_url, // Yedek
                    ]);
                }
            }
            
            // Henüz WP'ye yüklenmediyse Replicate URL'sini dön
            return rest_ensure_response([
                'status' => 'ready',
                'image_url' => $replicate_url, // Geçici
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});