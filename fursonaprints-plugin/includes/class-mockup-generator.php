<?php
/**
 * Mockup Generator Class
 * Generates product mockups by compositing AI portraits onto mockup templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class FursonaPrints_Mockup_Generator {

    private $templates_dir;
    private $upload_dir;

    public function __construct() {
        $this->templates_dir = FURSONAPRINTS_PATH . 'assets/mockup-templates/';
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/mockups/';

        // Create mockups directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }

    /**
     * Generate mockups for a portrait
     *
     * @param string $portrait_path Path to AI portrait image
     * @param string $job_id Unique job ID for caching
     * @return array Array of mockup URLs
     */
    public function generate_mockups($portrait_path, $job_id) {
        error_log("=== GENERATING MOCKUPS ===");
        error_log("Portrait path: {$portrait_path}");
        error_log("Job ID: {$job_id}");

        $mockups = [];

        // Available mockup templates
        $templates = [
            'a4_framed' => [
                'name' => 'A4 Framed Poster',
                'size' => 'A4 (8x12")',
                'template' => 'a4-framed-poster.jpg',
                'insert_coords' => ['x' => 100, 'y' => 150, 'width' => 600, 'height' => 800]
            ],
            'a3_wall' => [
                'name' => 'A3 Wall Poster',
                'size' => 'A3 (12x16")',
                'template' => 'a3-wall-poster.jpg',
                'insert_coords' => ['x' => 150, 'y' => 200, 'width' => 800, 'height' => 1066]
            ]
        ];

        foreach ($templates as $key => $config) {
            $template_path = $this->templates_dir . $config['template'];

            // For now, create placeholder if template doesn't exist
            if (!file_exists($template_path)) {
                error_log("Template not found: {$template_path}, creating placeholder");
                $mockup_url = $this->create_simple_mockup($portrait_path, $job_id, $key, $config);
            } else {
                $mockup_url = $this->composite_mockup($portrait_path, $template_path, $job_id, $key, $config);
            }

            if ($mockup_url) {
                $mockups[] = [
                    'url' => $mockup_url,
                    'variant' => $key,
                    'name' => $config['name'],
                    'size' => $config['size'],
                    'is_primary' => $key === 'a4_framed'
                ];
            }
        }

        error_log("Generated " . count($mockups) . " mockups");
        return $mockups;
    }

    /**
     * Create simple mockup with border (fallback when template not available)
     */
    private function create_simple_mockup($portrait_path, $job_id, $variant, $config) {
        try {
            // Load portrait
            $portrait = $this->load_image($portrait_path);
            if (!$portrait) {
                error_log("Failed to load portrait: {$portrait_path}");
                return false;
            }

            $portrait_width = imagesx($portrait);
            $portrait_height = imagesy($portrait);

            // Create canvas with padding for frame
            $padding = 80;
            $canvas_width = $portrait_width + ($padding * 2);
            $canvas_height = $portrait_height + ($padding * 2);

            $canvas = imagecreatetruecolor($canvas_width, $canvas_height);

            // Background color (white)
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            // Draw frame border
            $frame_color = imagecolorallocate($canvas, 139, 105, 20); // Gold
            imagefilledrectangle($canvas, 0, 0, $canvas_width - 1, $canvas_height - 1, $frame_color);

            // Draw inner white border (mat)
            $mat_padding = 20;
            imagefilledrectangle(
                $canvas,
                $mat_padding,
                $mat_padding,
                $canvas_width - $mat_padding - 1,
                $canvas_height - $mat_padding - 1,
                $white
            );

            // Copy portrait to center
            imagecopy(
                $canvas,
                $portrait,
                $padding,
                $padding,
                0,
                0,
                $portrait_width,
                $portrait_height
            );

            // Save mockup
            $output_filename = "mockup_{$job_id}_{$variant}.jpg";
            $output_path = $this->upload_dir . $output_filename;

            imagejpeg($canvas, $output_path, 90);

            // Cleanup
            imagedestroy($canvas);
            imagedestroy($portrait);

            // Return URL
            $upload = wp_upload_dir();
            $mockup_url = $upload['baseurl'] . '/mockups/' . $output_filename;

            error_log("Simple mockup created: {$mockup_url}");
            return $mockup_url;

        } catch (Exception $e) {
            error_log("Mockup generation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Composite portrait onto mockup template
     */
    private function composite_mockup($portrait_path, $template_path, $job_id, $variant, $config) {
        try {
            $portrait = $this->load_image($portrait_path);
            $template = $this->load_image($template_path);

            if (!$portrait || !$template) {
                error_log("Failed to load images for compositing");
                return false;
            }

            $coords = $config['insert_coords'];

            // Resize portrait to fit
            $resized_portrait = imagecreatetruecolor($coords['width'], $coords['height']);
            imagecopyresampled(
                $resized_portrait,
                $portrait,
                0, 0, 0, 0,
                $coords['width'],
                $coords['height'],
                imagesx($portrait),
                imagesy($portrait)
            );

            // Composite onto template
            imagecopy(
                $template,
                $resized_portrait,
                $coords['x'],
                $coords['y'],
                0,
                0,
                $coords['width'],
                $coords['height']
            );

            // Save
            $output_filename = "mockup_{$job_id}_{$variant}.jpg";
            $output_path = $this->upload_dir . $output_filename;

            imagejpeg($template, $output_path, 90);

            // Cleanup
            imagedestroy($template);
            imagedestroy($portrait);
            imagedestroy($resized_portrait);

            // Return URL
            $upload = wp_upload_dir();
            $mockup_url = $upload['baseurl'] . '/mockups/' . $output_filename;

            error_log("Composite mockup created: {$mockup_url}");
            return $mockup_url;

        } catch (Exception $e) {
            error_log("Composite mockup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load image from path (supports JPEG, PNG)
     */
    private function load_image($path) {
        $info = getimagesize($path);

        if (!$info) {
            return false;
        }

        switch ($info['mime']) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            default:
                return false;
        }
    }
}
