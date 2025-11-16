<?php
/**
 * Save AI Result Endpoint
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
    register_rest_route('myplugin/v1', '/save-result', [
        'methods' => 'POST',
        'callback' => function ($request) {
            // Debug: Gelen tüm verileri logla
            error_log('=== SAVE RESULT REQUEST ===');
            error_log('Headers: ' . print_r($request->get_headers(), true));
            error_log('Body: ' . $request->get_body());
            error_log('Params: ' . print_r($request->get_params(), true));

            // Görüntüdeki değişken isimleriyle eşleşme yapıldı
            $job_id           = sanitize_text_field($request->get_param('job_id'));
            $image_url        = esc_url_raw($request->get_param('image_url')); // Sonuç görseli URL'si
            $gender           = sanitize_text_field($request->get_param('gender'));
            $prompt           = sanitize_textarea_field($request->get_param('prompt'));

            // Görüntüdeki 'input_image' değişken adı kullanıldı
            $input_image_url  = esc_url_raw($request->get_param('input_image')); // Giriş görseli URL'si

            error_log("Parsed - job_id: {$job_id}, image_url: {$image_url}");

            if (!$job_id || !$image_url) {
                error_log('ERROR: Missing job_id or image_url');
                return new WP_REST_Response(['error' => 'Missing job_id or image_url'], 400);
            }

            // Gerekli dosyaları dahil et
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            // Yeni CPT oluştur
            $post_id = wp_insert_post([
                'post_type'   => 'ai_result',
                'post_title'  => 'AI Result - ' . $job_id,
                'post_status' => 'publish',
            ]);

            if (is_wp_error($post_id)) {
                return new WP_REST_Response(['error' => 'Failed to create post'], 500);
            }

            // Meta alanlarını kaydet (input_image_url ve prompt dahil)
            update_post_meta($post_id, 'job_id', $job_id);
            update_post_meta($post_id, 'lowres_url', $image_url);
            if ($gender) {
                update_post_meta($post_id, 'gender', $gender);
            }
            if ($prompt) {
                update_post_meta($post_id, 'prompt', $prompt);
            }
            // Yeni: Giriş görselinin URL'sini kaydet
            if ($input_image_url) {
                update_post_meta($post_id, 'input_image_url', $input_image_url);
            }


            // 1. Sonuç Görselini (lowres_url) indirip WordPress'e yükle
            $lowres_tmp = download_url($image_url);
            if (is_wp_error($lowres_tmp)) {
                return new WP_REST_Response(['error' => 'Result image download failed'], 500);
            }

            $lowres_file_array = [
                'name'     => basename($image_url),
                'tmp_name' => $lowres_tmp,
            ];

            $lowres_attachment_id = media_handle_sideload($lowres_file_array, $post_id);

            if (is_wp_error($lowres_attachment_id)) {
                @unlink($lowres_tmp); // temizle
                return new WP_REST_Response(['error' => 'Failed to sideload result image'], 500);
            }

            // Featured image olarak ata
            set_post_thumbnail($post_id, $lowres_attachment_id);
            update_post_meta($post_id, 'lowres_attachment_id', $lowres_attachment_id);
            
            // 2. Giriş Görseli (input_image URL) ID'sini bul
            $input_attachment_id = 0;
            if ($input_image_url) {
                // WordPress URL'sinden attachment ID'yi bul
                $found_id = attachment_url_to_postid($input_image_url);
                
                if ($found_id > 0) {
                    $input_attachment_id = $found_id;
                }
            }

            // Giriş Görseli ID'sini kaydet
            if ($input_attachment_id) {
                update_post_meta($post_id, 'input_attachment_id', $input_attachment_id);
            }

            // Eski sistemle uyumluluk için kayıt
            update_option("ai_result_{$job_id}", $image_url);

            error_log("SUCCESS: Saved job_id: {$job_id}, post_id: {$post_id}");

            // Create Gelato product with mockups
            $gelato = new FursonaPrints_Gelato_API();
            $product_title = 'Pet Portrait - ' . $job_id;

            // Use WordPress attachment URL for better reliability
            $gelato_image_url = wp_get_attachment_url($lowres_attachment_id);
            if (!$gelato_image_url) {
                $gelato_image_url = $image_url; // fallback to Replicate URL
            }

            error_log("Creating Gelato product with image: {$gelato_image_url}");

            $gelato_response = $gelato->create_product_from_template($gelato_image_url, $product_title);

            if (!is_wp_error($gelato_response) && isset($gelato_response['id'])) {
                $gelato_product_id = $gelato_response['id'];
                update_post_meta($post_id, 'gelato_product_id', $gelato_product_id);
                error_log("Gelato product created: {$gelato_product_id}");
            } else {
                $error_msg = is_wp_error($gelato_response) ? $gelato_response->get_error_message() : 'Unknown error';
                error_log("Gelato product creation failed: {$error_msg}");
                // Don't fail the request, just log the error
            }

            return rest_ensure_response([
                'success' => true,
                'post_id' => $post_id,
                'attachment_id' => $lowres_attachment_id,
                'input_attachment_id' => $input_attachment_id,
                'gelato_product_id' => isset($gelato_product_id) ? $gelato_product_id : null,
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});