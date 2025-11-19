<?php
function pet_photo_gender_form_shortcode() {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    ob_start();
    ?>
    <form id="pet-form" enctype="multipart/form-data" method="post">
        <label for="pet-photo"><?php echo esc_html__('Upload your pet photo:', 'fursonaprints'); ?></label><br>
        <input type="file" id="pet-photo" name="pet_photo" accept="image/*" required><br><br>

        <label for="pet-gender"><?php echo esc_html__('Select gender:', 'fursonaprints'); ?></label><br>
        <select id="pet-gender" name="pet_gender" required>
            <option value=""><?php echo esc_html__('-- Please select --', 'fursonaprints'); ?></option>
            <option value="male"><?php echo esc_html__('Male', 'fursonaprints'); ?></option>
            <option value="female"><?php echo esc_html__('Female', 'fursonaprints'); ?></option>
            <option value="not_important"><?php echo esc_html__('Not Important', 'fursonaprints'); ?></option>
        </select><br><br>

        <input type="submit" name="submit_pet_form" value="<?php echo esc_attr__('Submit', 'fursonaprints'); ?>">
    </form>
    <?php

    if (isset($_POST['submit_pet_form'])) {
        if (!empty($_FILES['pet_photo']['tmp_name']) && !empty($_POST['pet_gender'])) {
            $gender = sanitize_text_field($_POST['pet_gender']);
            $file = $_FILES['pet_photo'];
            $temp_path = $file['tmp_name'];

            // Görsel kontrolü
            $image_info = getimagesize($temp_path);
            if (!$image_info) {
                echo '<p style="color:red;">' . esc_html__('Invalid image file.', 'fursonaprints') . '</p>';
                return ob_get_clean();
            }

            $original_width  = $image_info[0];
            $original_height = $image_info[1];
            $extension = image_type_to_extension($image_info[2], false);

            // Görsel formatını kontrol et
            switch ($extension) {
                case 'jpeg':
                case 'jpg':
                    $image = imagecreatefromjpeg($temp_path);
                    break;
                case 'png':
                    $image = imagecreatefrompng($temp_path);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($temp_path);
                    break;
                default:
                    echo '<p style="color:red;">' . esc_html__('Unsupported image format.', 'fursonaprints') . '</p>';
                    return ob_get_clean();
            }

            // Görseli resize et (max 1024px)
            $max_dim = 1024;
            $scale = min($max_dim / $original_width, $max_dim / $original_height, 1);
            $new_width = (int)($original_width * $scale);
            $new_height = (int)($original_height * $scale);

            $resized_image = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
            imagedestroy($image);

            // JPG olarak kaydet
            $jpg_temp = tempnam(sys_get_temp_dir(), 'petphoto_') . '.jpg';
            imagejpeg($resized_image, $jpg_temp, 90);
            imagedestroy($resized_image);

            // WordPress media library'ye yükle
            $file_array = [
                'name'     => uniqid('pet_') . '.jpg',
                'tmp_name' => $jpg_temp,
            ];

            $attachment_id = media_handle_sideload($file_array, 0);
            if (is_wp_error($attachment_id)) {
                echo '<p style="color:red;">' . esc_html__('Upload failed.', 'fursonaprints') . '</p>';
                @unlink($jpg_temp);
                return ob_get_clean();
            }

            // Görsel URL'sini al
            $image_src = wp_get_attachment_image_src($attachment_id, 'medium_large');
            $image_url = $image_src ? $image_src[0] : wp_get_attachment_url($attachment_id);

            // URL erişilebilir mi kontrol et
            $check = wp_remote_head($image_url);
            if (is_wp_error($check) || wp_remote_retrieve_response_code($check) !== 200) {
                echo '<p style="color:red;">' . esc_html__('Image not accessible.', 'fursonaprints') . '</p>';
                @unlink($jpg_temp);
                return ob_get_clean();
            }

            // n8n webhook'una gönder
            $webhook_url = 'https://thewpclan.app.n8n.cloud/webhook/fursona-preview';
            $response = wp_remote_post($webhook_url, [
                'method'  => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode([
                    'image_url' => $image_url,
                    'gender'    => $gender,
                ]),
                'timeout' => 20,
            ]);

            @unlink($jpg_temp);

            // n8n yanıtını kontrol et
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);

                if (!empty($json['job_id'])) {
                    $job_id = ltrim(trim($json['job_id']), '=');

                    // Hemen yönlendir
                    echo "<script>
                        window.location.href = '" . home_url('/ai-result/?job_id=') . "{$job_id}';
                    </script>";

                } else {
                    echo '<p style="color:red;">' . esc_html__('Job ID could not be retrieved. Please try again.', 'fursonaprints') . '</p>';
                }
            } else {
                $error_message = $response->get_error_message();
                echo '<p style="color:red;">' . sprintf(esc_html__('Connection error: %s', 'fursonaprints'), esc_html($error_message)) . '</p>';
            }

        } else {
            echo '<p style="color:red;">' . esc_html__('Please fill in all fields.', 'fursonaprints') . '</p>';
        }
    }

    return ob_get_clean();
}
add_shortcode('pet_form', 'pet_photo_gender_form_shortcode');