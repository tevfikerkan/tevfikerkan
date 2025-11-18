<?php
// 1. CPT Tanımı (ai_result)
add_action('init', function () {
    register_post_type('ai_result', [
        'labels' => [
            'name' => 'AI Results',
            'singular_name' => 'AI Result',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New AI Result',
            'edit_item' => 'Edit AI Result',
            'new_item' => 'New AI Result',
            'view_item' => 'View AI Result',
            'search_items' => 'Search AI Results',
            'not_found' => 'No AI Results found',
            'not_found_in_trash' => 'No AI Results found in Trash',
            'menu_name' => 'AI Results'
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-format-image',
        'supports' => ['title', 'thumbnail'],
        'capability_type' => 'post',
        'has_archive' => false,
        'rewrite' => false
    ]);
});

// 2. Meta Kutusu Tanımı ve Alanların Eklenmesi
add_action('add_meta_boxes', function () {
    add_meta_box(
        'ai_result_fields',
        'AI Result Details',
        function ($post) {
            // Güvenlik için nonce alanını ekle
            wp_nonce_field('ai_result_save_meta', 'ai_result_nonce');
            
            $fields = [
                'job_id' => 'Job ID',
                'lowres_url' => 'Low-Res Image URL',
                'hd_url' => 'HD Image URL',
                'order_id' => 'Order ID',
                'customer_email' => 'Customer Email',
                'gender' => 'Gender',
                'status' => 'Status',
                'lowres_attachment_id' => 'Low-Res Attachment ID',
                'input_image_url' => 'Input Image URL (Original)',
                'input_attachment_id' => 'Input Attachment ID',
                'gelato_product_id' => 'Gelato Product ID',
            ];

            // Prompt için ayrı bir textarea kullanılması daha uygundur.
            $prompt_value = esc_textarea(get_post_meta($post->ID, 'prompt', true));
            echo "<p><label for='prompt'><strong>Prompt:</strong></label><br />";
            echo "<textarea id='prompt' name='prompt' style='width:100%' rows='4'>{$prompt_value}</textarea></p>";

            // Diğer alanları döngüye al
            foreach ($fields as $key => $label) {
                $value = esc_attr(get_post_meta($post->ID, $key, true));
                echo "<p><label for='{$key}'><strong>{$label}:</strong></label><br />";
                echo "<input type='text' id='{$key}' name='{$key}' value='{$value}' style='width:100%' /></p>";
            }

            // Gelato Mockups (JSON, read-only)
            $mockups_data = get_post_meta($post->ID, 'gelato_mockups', true);
            $mockups_json = $mockups_data ? json_encode($mockups_data, JSON_PRETTY_PRINT) : '';
            $mockups_updated = get_post_meta($post->ID, 'gelato_mockups_updated_at', true);
            echo "<p><label for='gelato_mockups'><strong>Gelato Mockups (Read-Only):</strong></label>";
            if ($mockups_updated) {
                echo " <em>Last updated: {$mockups_updated}</em>";
            }
            echo "<br />";
            echo "<textarea id='gelato_mockups' style='width:100%; font-family: monospace;' rows='6' readonly>{$mockups_json}</textarea></p>";
        },
        'ai_result',
        'normal',
        'default'
    );
});

// 3. Meta Veri Kaydetme İşlemi
add_action('save_post_ai_result', function ($post_id) {
    // Güvenlik kontrolleri
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['ai_result_nonce']) || !wp_verify_nonce($_POST['ai_result_nonce'], 'ai_result_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields_to_sanitize_text = [
        'job_id', 'lowres_url', 'hd_url',
        'order_id', 'customer_email', 'gender',
        'status', 'lowres_attachment_id',
        'input_image_url',
        'input_attachment_id',
        'gelato_product_id'
    ];
    
    // Prompt için ayrı sanitization
    if (isset($_POST['prompt'])) {
        update_post_meta($post_id, 'prompt', sanitize_textarea_field($_POST['prompt']));
    }

    foreach ($fields_to_sanitize_text as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'lowres_attachment_id' || $field === 'input_attachment_id') {
                // Attachment ID'ler için tam sayıya çevir
                update_post_meta($post_id, $field, (int)$_POST[$field]);
            } elseif ($field === 'lowres_url' || $field === 'input_image_url') {
                // URL'ler için daha güvenli sanitization kullan
                update_post_meta($post_id, $field, esc_url_raw($_POST[$field]));
            } else {
                // Diğer metin alanları
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
});