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

        // Priority 1: Get saved mockups from database (coordinate-finder)
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

        // Priority 2: Scan templates directory for config files (if no database mockups)
        if (empty($mockups)) {
            error_log("No database mockups found, scanning templates directory");
            $templates = $this->scan_templates();

            if (!empty($templates)) {
                foreach ($templates as $key => $config) {
                    error_log("Processing template: {$key}");

                    $template_path = $this->templates_dir . $config['template_dir'] . '/' . $config['file'];

                    if (!file_exists($template_path)) {
                        error_log("Template file not found: {$template_path}");
                        continue;
                    }

                    $mockup_url = $this->composite_mockup($portrait_path, $template_path, $job_id, $key, $config);

                    if ($mockup_url) {
                        $mockups[] = [
                            'url' => $mockup_url,
                            'variant' => $key,
                            'name' => $config['name'],
                            'size' => $config['size'] ?? 'Standard',
                            'is_primary' => $config['is_primary'] ?? false
                        ];
                    }
                }
            }
        }

        // Priority 3: Fallback to simple framed mockup if nothing else available
        if (empty($mockups)) {
            error_log("No templates found, using simple fallback");
            $mockup_url = $this->create_simple_mockup($portrait_path, $job_id, 'simple_frame', [
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
     * Scan templates directory for config.json files
     *
     * @return array Array of template configurations
     */
    private function scan_templates() {
        $templates = [];

        if (!is_dir($this->templates_dir)) {
            error_log("Templates directory not found: {$this->templates_dir}");
            return $templates;
        }

        $subdirs = glob($this->templates_dir . '*', GLOB_ONLYDIR);

        foreach ($subdirs as $dir) {
            $config_file = $dir . '/config.json';

            if (!file_exists($config_file)) {
                continue;
            }

            $config_json = file_get_contents($config_file);
            $config = json_decode($config_json, true);

            if (!$config || !isset($config['name']) || !isset($config['file'])) {
                error_log("Invalid config in: {$config_file}");
                continue;
            }

            $variant_key = $config['variant'] ?? basename($dir);
            $config['template_dir'] = basename($dir);

            $templates[$variant_key] = $config;
            error_log("Loaded template: {$variant_key} from {$dir}");
        }

        return $templates;
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

            $insert_area = $config['insert_area'] ?? $config['insert_coords'] ?? null;

            if (!$insert_area) {
                error_log("No insert_area defined in config");
                return false;
            }

            // Check if perspective transformation is needed
            if (isset($config['perspective']) && $config['perspective'] === true) {
                $resized_portrait = $this->apply_perspective_transform($portrait, $insert_area);
            } else {
                // Standard resize
                $resized_portrait = imagecreatetruecolor($insert_area['width'], $insert_area['height']);

                // Preserve transparency for PNG
                imagealphablending($resized_portrait, false);
                imagesavealpha($resized_portrait, true);

                imagecopyresampled(
                    $resized_portrait,
                    $portrait,
                    0, 0, 0, 0,
                    $insert_area['width'],
                    $insert_area['height'],
                    imagesx($portrait),
                    imagesy($portrait)
                );
            }

            if (!$resized_portrait) {
                error_log("Failed to resize portrait");
                imagedestroy($template);
                imagedestroy($portrait);
                return false;
            }

            // Composite onto template
            imagecopy(
                $template,
                $resized_portrait,
                $insert_area['x'],
                $insert_area['y'],
                0,
                0,
                $insert_area['width'],
                $insert_area['height']
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
     * Apply perspective transformation to portrait (for curved surfaces like mugs)
     */
    private function apply_perspective_transform($portrait, $insert_area) {
        $src_width = imagesx($portrait);
        $src_height = imagesy($portrait);

        $dst_width = $insert_area['width'];
        $dst_height = $insert_area['height'];

        // Create destination image
        $transformed = imagecreatetruecolor($dst_width, $dst_height);
        imagealphablending($transformed, false);
        imagesavealpha($transformed, true);

        // Apply cylindrical wrap effect (for mug curvature)
        // This creates a subtle barrel distortion
        $curvature = $insert_area['curvature'] ?? 0.15; // Default curvature factor

        for ($y = 0; $y < $dst_height; $y++) {
            for ($x = 0; $x < $dst_width; $x++) {
                // Calculate normalized coordinates (-1 to 1)
                $nx = ($x / $dst_width) * 2 - 1;
                $ny = ($y / $dst_height) * 2 - 1;

                // Apply cylindrical projection
                $r = sqrt($nx * $nx + $ny * $ny);

                if ($r > 1.0) {
                    continue; // Outside bounds
                }

                // Apply curvature
                $theta = atan2($ny, $nx);
                $curved_r = $r * (1 + $curvature * $r * $r);

                // Map back to source coordinates
                $src_x = (($curved_r * cos($theta) + 1) / 2) * $src_width;
                $src_y = (($curved_r * sin($theta) + 1) / 2) * $src_height;

                // Bounds check
                if ($src_x >= 0 && $src_x < $src_width && $src_y >= 0 && $src_y < $src_height) {
                    $color = imagecolorat($portrait, (int)$src_x, (int)$src_y);
                    imagesetpixel($transformed, $x, $y, $color);
                }
            }
        }

        return $transformed;
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
