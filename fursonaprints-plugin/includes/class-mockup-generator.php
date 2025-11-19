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

        // Get saved mockups from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';
        $saved_mockups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");

        if (!empty($saved_mockups)) {
            error_log("Found " . count($saved_mockups) . " saved mockups in database");

            foreach ($saved_mockups as $index => $saved_mockup) {
                $coordinates = json_decode($saved_mockup->coordinates, true);

                if (!empty($coordinates) && count($coordinates) === 4) {
                    $mockup_url = $this->create_perspective_mockup(
                        $portrait_path,
                        $saved_mockup->image_url,
                        $coordinates,
                        $job_id,
                        $index
                    );

                    if ($mockup_url) {
                        $mockups[] = [
                            'url' => $mockup_url,
                            'variant' => sanitize_title($saved_mockup->mockup_name),
                            'name' => $saved_mockup->mockup_name,
                            'size' => 'Premium',
                            'is_primary' => $index === 0
                        ];
                    }
                }
            }
        }

        // Fallback: create simple mockups if no database mockups available
        if (empty($mockups)) {
            error_log("No database mockups found, creating simple fallback mockups");
            $mockup_url = $this->create_simple_mockup($portrait_path, $job_id, 'default', [
                'name' => 'Simple Frame',
                'size' => 'Standard'
            ]);

            if ($mockup_url) {
                $mockups[] = [
                    'url' => $mockup_url,
                    'variant' => 'simple_frame',
                    'name' => 'Simple Frame',
                    'size' => 'Standard',
                    'is_primary' => true
                ];
            }
        }

        error_log("Generated " . count($mockups) . " mockups");
        return $mockups;
    }

    /**
     * Create mockup with perspective transformation using saved coordinates
     */
    private function create_perspective_mockup($portrait_path, $mockup_image_url, $coordinates, $job_id, $index) {
        try {
            error_log("Creating perspective mockup with coordinates");

            // Load portrait
            $portrait = $this->load_image($portrait_path);
            if (!$portrait) {
                error_log("Failed to load portrait: {$portrait_path}");
                return false;
            }

            // Download mockup image
            $mockup_temp = download_url($mockup_image_url);
            if (is_wp_error($mockup_temp)) {
                error_log("Failed to download mockup image: " . $mockup_temp->get_error_message());
                imagedestroy($portrait);
                return false;
            }

            // Load mockup image
            $mockup = $this->load_image($mockup_temp);
            @unlink($mockup_temp); // Clean up temp file

            if (!$mockup) {
                error_log("Failed to load mockup image");
                imagedestroy($portrait);
                return false;
            }

            // Get dimensions
            $mockup_width = imagesx($mockup);
            $mockup_height = imagesy($mockup);
            $portrait_width = imagesx($portrait);
            $portrait_height = imagesy($portrait);

            // Calculate bounding box from coordinates
            $min_x = min($coordinates[0]['x'], $coordinates[1]['x'], $coordinates[2]['x'], $coordinates[3]['x']);
            $max_x = max($coordinates[0]['x'], $coordinates[1]['x'], $coordinates[2]['x'], $coordinates[3]['x']);
            $min_y = min($coordinates[0]['y'], $coordinates[1]['y'], $coordinates[2]['y'], $coordinates[3]['y']);
            $max_y = max($coordinates[0]['y'], $coordinates[1]['y'], $coordinates[2]['y'], $coordinates[3]['y']);

            $target_width = $max_x - $min_x;
            $target_height = $max_y - $min_y;

            // Resize portrait to fit the target area
            $resized_portrait = imagecreatetruecolor($target_width, $target_height);
            imagecopyresampled(
                $resized_portrait,
                $portrait,
                0, 0, 0, 0,
                $target_width,
                $target_height,
                $portrait_width,
                $portrait_height
            );

            // Composite resized portrait onto mockup at the target position
            imagecopy(
                $mockup,
                $resized_portrait,
                $min_x,
                $min_y,
                0,
                0,
                $target_width,
                $target_height
            );

            // Save the result
            $output_filename = "mockup_{$job_id}_{$index}.jpg";
            $output_path = $this->upload_dir . $output_filename;

            imagejpeg($mockup, $output_path, 90);

            // Cleanup
            imagedestroy($mockup);
            imagedestroy($portrait);
            imagedestroy($resized_portrait);

            // Return URL
            $upload = wp_upload_dir();
            $mockup_url = $upload['baseurl'] . '/mockups/' . $output_filename;

            error_log("Perspective mockup created: {$mockup_url}");
            return $mockup_url;

        } catch (Exception $e) {
            error_log("Perspective mockup error: " . $e->getMessage());
            return false;
        }
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
