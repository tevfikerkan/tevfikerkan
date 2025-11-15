<?php
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/save-result', [
        'methods' => 'POST',
        'callback' => function ($request) {
            // Görüntüdeki değişken isimleriyle eşleşme yapıldı
            $job_id           = sanitize_text_field($request->get_param('job_id'));
            $image_url        = esc_url_raw($request->get_param('image_url')); // Sonuç görseli URL'si
            $gender           = sanitize_text_field($request->get_param('gender'));
            $prompt           = sanitize_textarea_field($request->get_param('prompt'));
            
            // Görüntüdeki 'input_image' değişken adı kullanıldı
            $input_image_url  = esc_url_raw($request->get_param('input_image')); // Giriş görseli URL'si

            if (!$job_id || !$image_url) {
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

            return rest_ensure_response([
                'success' => true,
                'post_id' => $post_id,
                'attachment_id' => $lowres_attachment_id,
                'input_attachment_id' => $input_attachment_id,
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});