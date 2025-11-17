<?php
/**
 * Gelato API Integration
 */

class FursonaPrints_Gelato_API {

    private $api_key;
    private $base_url;
    private $store_id;

    public function __construct() {
        // Get API credentials from WordPress options
        $this->api_key = get_option('fursonaprints_gelato_api_key', '');
        $this->store_id = get_option('fursonaprints_gelato_store_id', '');
        $this->base_url = 'https://ecommerce.gelatoapis.com/v1';

        // Log warning if credentials are not configured
        if (empty($this->api_key) || empty($this->store_id)) {
            error_log('FursonaPrints: Gelato API credentials not configured. Please configure in Settings > FursonaPrints');
        }
    }

    /**
     * Create product from template with AI generated image
     *
     * @param string $image_url URL of the AI generated pet portrait
     * @param string $title Product title
     * @param array $variants Product UIDs to create (optional)
     * @return array|WP_Error Response data or error
     */
    public function create_product_from_template($image_url, $title = 'Pet Portrait', $variants = []) {
        error_log("=== GELATO: Creating product ===");
        error_log("Image URL: {$image_url}");

        // Check if credentials are configured
        if (empty($this->api_key) || empty($this->store_id)) {
            $error_msg = 'Gelato API credentials not configured. Please configure in Settings > FursonaPrints';
            error_log("GELATO ERROR: {$error_msg}");
            return new WP_Error('gelato_config_error', $error_msg);
        }

        // Get template configuration from WordPress options
        $template_id = get_option('fursonaprints_gelato_template_id', '85d917c6-b1c1-4637-a724-f4f267a37c27');
        $layer_name = get_option('fursonaprints_gelato_layer_name', 'pet_portrait');

        // Default variants (A4 and A3) from WordPress options
        if (empty($variants)) {
            $variant_a4 = get_option('fursonaprints_gelato_variant_a4', 'flat_a4-8x12-inch_200-gsm-80lb-uncoated_4-0_ver');
            $variant_a3 = get_option('fursonaprints_gelato_variant_a3', 'flat_a3_200-gsm-80lb-uncoated_4-0_ver');
            $variants = [$variant_a4, $variant_a3];
        }

        error_log("Using Template ID: {$template_id}");
        error_log("Using Layer Name: {$layer_name}");
        error_log("Using Variants: " . implode(', ', $variants));

        $endpoint = "{$this->base_url}/stores/{$this->store_id}/products:create-from-template";

        $body = [
            'templateId' => $template_id,
            'title' => $title,
            'images' => [
                [
                    'layerName' => $layer_name,
                    'imageUrl' => $image_url
                ]
            ],
            'productUids' => $variants
        ];

        error_log("Request endpoint: {$endpoint}");
        error_log("Request body: " . json_encode($body, JSON_PRETTY_PRINT));

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'X-API-KEY' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log("GELATO ERROR: " . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        error_log("Response code: {$status_code}");
        error_log("Response body: " . $body);

        if ($status_code !== 200 && $status_code !== 201) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error';
            error_log("GELATO ERROR: {$error_msg}");
            return new WP_Error('gelato_error', $error_msg, ['status' => $status_code]);
        }

        error_log("SUCCESS: Product created with ID: " . ($data['id'] ?? 'unknown'));

        return $data;
    }

    /**
     * Get product details including mockups
     *
     * @param string $product_id Gelato product ID
     * @return array|WP_Error Product data or error
     */
    public function get_product($product_id) {
        error_log("=== GELATO: Getting product {$product_id} ===");

        // Check if credentials are configured
        if (empty($this->api_key) || empty($this->store_id)) {
            $error_msg = 'Gelato API credentials not configured. Please configure in Settings > FursonaPrints';
            error_log("GELATO ERROR: {$error_msg}");
            return new WP_Error('gelato_config_error', $error_msg);
        }

        $endpoint = "{$this->base_url}/stores/{$this->store_id}/products/{$product_id}";
        error_log("Request endpoint: {$endpoint}");

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'X-API-KEY' => $this->api_key
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            error_log("GELATO ERROR: " . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        error_log("Response code: {$status_code}");
        error_log("Response body: " . $body);

        if ($status_code !== 200) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Product not found';
            error_log("GELATO ERROR: {$error_msg}");
            return new WP_Error('gelato_error', $error_msg, ['status' => $status_code]);
        }

        return $data;
    }

    /**
     * Extract mockup URLs from product data
     *
     * @param array $product_data Product data from Gelato API
     * @return array Array of mockup URLs
     */
    public function get_mockup_urls($product_data) {
        $mockups = [];

        error_log("=== EXTRACTING MOCKUPS ===");
        error_log("Product data keys: " . implode(', ', array_keys($product_data ?? [])));

        if (isset($product_data['productImages'])) {
            error_log("productImages exists, type: " . gettype($product_data['productImages']));
            error_log("productImages content: " . json_encode($product_data['productImages']));
        } else {
            error_log("productImages NOT FOUND in response");
        }

        if (isset($product_data['productImages']) && is_array($product_data['productImages'])) {
            foreach ($product_data['productImages'] as $image) {
                // Gelato uses 'fileUrl' not 'url'
                $image_url = $image['fileUrl'] ?? $image['url'] ?? null;
                if ($image_url) {
                    $mockups[] = [
                        'url' => $image_url,
                        'variant' => $image['productUid'] ?? 'unknown',
                        'is_primary' => $image['isPrimary'] ?? false
                    ];
                }
            }
        }

        error_log("Found " . count($mockups) . " mockups");

        return $mockups;
    }
}
