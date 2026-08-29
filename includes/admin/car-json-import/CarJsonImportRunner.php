<?php
/**
 * Imports one validated AutoAgora car JSON row and its ZIP-contained images.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Car_Json_Import_Runner
{
    /**
     * @param array<string,mixed> $row
     * @param string $zip_path
     * @param int $author_id
     * @return array<string,mixed>|WP_Error
     */
    public static function importRow(array $row, string $zip_path, int $author_id)
    {
        if (empty($row['valid']) || empty($row['listing']) || !is_array($row['listing'])) {
            return new WP_Error('car_json_import_invalid_row', __('The selected row did not pass validation.', 'bricks-child'));
        }
        if (!get_userdata($author_id)) {
            return new WP_Error('car_json_import_author', __('The selected post author no longer exists.', 'bricks-child'));
        }

        $listing = $row['listing'];
        $existing_id = AutoAgora_Car_Json_Import_Validator::findExistingImport(
            (string) $listing['source_platform'],
            (string) $listing['source_id']
        );
        if ($existing_id > 0) {
            return array(
                'status'  => 'skipped',
                'post_id' => $existing_id,
                'message' => __('Source ID already imported.', 'bricks-child'),
            );
        }

        $title = sprintf('%d %s %s', (int) $listing['year'], $listing['make'], $listing['model']);
        $post_id = wp_insert_post(array(
            'post_type'    => 'car',
            'post_status'  => 'pending',
            'post_author'  => $author_id,
            'post_title'   => sanitize_text_field($title),
            'post_content' => wp_kses_post((string) $listing['description']),
        ), true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $attachment_ids = array();
        try {
            self::storeFields((int) $post_id, $listing);
            self::assignTaxonomy((int) $post_id, (string) $listing['make'], (string) $listing['model']);
            $attachment_ids = self::importImages((int) $post_id, $zip_path, (array) $listing['car_images']);
            self::updateField('car_images', $attachment_ids, (int) $post_id);

            update_post_meta($post_id, '_autoagora_import_source', sanitize_key((string) $listing['source_platform']));
            update_post_meta($post_id, '_autoagora_import_source_id', sanitize_text_field((string) $listing['source_id']));
            update_post_meta($post_id, '_autoagora_import_source_url', esc_url_raw((string) $listing['source_url']));
            update_post_meta($post_id, '_autoagora_imported_at', current_time('mysql', true));

            if (class_exists('ListingStateManager')) {
                ListingStateManager::assign_state((int) $post_id, ListingStateManager::STATE_ACTIVE);
            } else {
                self::updateField('listing_state', 'active', (int) $post_id);
            }

            do_action('acf/save_post', (int) $post_id);
            if (class_exists('Listing_Details_Badge_Manager')) {
                Listing_Details_Badge_Manager::update_badges_for_listing((int) $post_id);
            }

            return array(
                'status'      => 'imported',
                'post_id'     => (int) $post_id,
                'attachments' => count($attachment_ids),
                'message'     => __('Imported as a pending car listing.', 'bricks-child'),
            );
        } catch (Throwable $error) {
            foreach ($attachment_ids as $attachment_id) {
                wp_delete_attachment((int) $attachment_id, true);
            }
            wp_delete_post((int) $post_id, true);
            return new WP_Error('car_json_import_row_failed', $error->getMessage());
        }
    }

    /** @param array<string,mixed> $listing */
    private static function storeFields(int $post_id, array $listing): void
    {
        $fields = array(
            'make', 'model', 'year', 'mileage', 'price', 'engine_capacity',
            'fuel_type', 'transmission', 'body_type', 'drive_type',
            'exterior_color', 'interior_color', 'description', 'number_of_doors',
            'number_of_seats', 'motuntil', 'extras', 'vehiclehistory', 'hp',
            'numowners', 'isantique', 'availability', 'car_city', 'car_district',
            'car_latitude', 'car_longitude', 'car_address',
        );
        foreach ($fields as $field) {
            if (!array_key_exists($field, $listing) || $listing[$field] === null || $listing[$field] === '') {
                continue;
            }
            self::updateField($field, $listing[$field], $post_id);
        }
    }

    private static function updateField(string $field, $value, int $post_id): void
    {
        if (function_exists('update_field')) {
            update_field($field, $value, $post_id);
        } else {
            update_post_meta($post_id, $field, $value);
        }
    }

    private static function assignTaxonomy(int $post_id, string $make, string $model): void
    {
        if (!taxonomy_exists('car_make')) {
            throw new RuntimeException(__('The car_make taxonomy is not registered.', 'bricks-child'));
        }
        if (!AutoAgora_Car_Json_Import_Validator::taxonomyCatalogContains($make, $model)) {
            throw new RuntimeException(__('The make/model is not present in the taxonomy source files.', 'bricks-child'));
        }

        $make_term = get_term_by('name', $make, 'car_make');
        if (!$make_term || is_wp_error($make_term) || (int) $make_term->parent !== 0) {
            $inserted = wp_insert_term($make, 'car_make', array(
                'description' => 'Car make: ' . $make,
                'slug'        => sanitize_title($make),
            ));
            if (is_wp_error($inserted)) {
                throw new RuntimeException($inserted->get_error_message());
            }
            $make_id = (int) $inserted['term_id'];
        } else {
            $make_id = (int) $make_term->term_id;
        }

        $model_terms = get_terms(array(
            'taxonomy'   => 'car_make',
            'hide_empty' => false,
            'parent'     => $make_id,
            'name'       => $model,
        ));
        if (is_wp_error($model_terms)) {
            throw new RuntimeException($model_terms->get_error_message());
        }
        if (empty($model_terms)) {
            $inserted = wp_insert_term($model, 'car_make', array(
                'description' => $make . ' ' . $model,
                'slug'        => sanitize_title($make . '-' . $model),
                'parent'      => $make_id,
            ));
            if (is_wp_error($inserted)) {
                throw new RuntimeException($inserted->get_error_message());
            }
            $model_id = (int) $inserted['term_id'];
        } else {
            $model_id = (int) $model_terms[0]->term_id;
        }

        $assigned = wp_set_post_terms($post_id, array($make_id, $model_id), 'car_make', false);
        if (is_wp_error($assigned)) {
            throw new RuntimeException($assigned->get_error_message());
        }
    }

    /**
     * @param array<int,array<string,mixed>> $images
     * @return array<int,int>
     */
    private static function importImages(int $post_id, string $zip_path, array $images): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(__('The PHP ZIP extension is required.', 'bricks-child'));
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new RuntimeException(__('The import ZIP could not be reopened.', 'bricks-child'));
        }

        $attachment_ids = array();
        try {
            foreach ($images as $position => $image) {
                $archive_path = isset($image['path']) ? (string) $image['path'] : '';
                if ($archive_path === '') {
                    throw new RuntimeException(__('A validated image path is missing.', 'bricks-child'));
                }
                $stream = $zip->getStream($archive_path);
                if (!is_resource($stream)) {
                    throw new RuntimeException(sprintf(__('Could not read image from ZIP: %s.', 'bricks-child'), $archive_path));
                }

                $filename = sanitize_file_name(basename($archive_path));
                $temporary = wp_tempnam($filename);
                if (!$temporary) {
                    fclose($stream);
                    throw new RuntimeException(__('Could not create a temporary image file.', 'bricks-child'));
                }
                $destination = fopen($temporary, 'wb');
                if (!is_resource($destination)) {
                    fclose($stream);
                    @unlink($temporary);
                    throw new RuntimeException(__('Could not write a temporary image file.', 'bricks-child'));
                }
                stream_copy_to_stream($stream, $destination, AutoAgora_Car_Json_Import_Validator::MAX_IMAGE_BYTES + 1);
                fclose($stream);
                fclose($destination);

                $size = (int) filesize($temporary);
                if ($size <= 0 || $size > AutoAgora_Car_Json_Import_Validator::MAX_IMAGE_BYTES) {
                    @unlink($temporary);
                    throw new RuntimeException(sprintf(__('Image is empty or too large: %s.', 'bricks-child'), $archive_path));
                }
                $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($temporary) : '';
                $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif');
                if (!$mime || !in_array($mime, $allowed_mimes, true)) {
                    @unlink($temporary);
                    throw new RuntimeException(sprintf(__('File is not a supported image: %s.', 'bricks-child'), $archive_path));
                }

                $file_array = array(
                    'name'     => sprintf('%s-%02d-%s', $post_id, $position + 1, $filename),
                    'type'     => $mime,
                    'tmp_name' => $temporary,
                    'error'    => 0,
                    'size'     => $size,
                );
                $attachment_id = media_handle_sideload($file_array, $post_id, get_the_title($post_id));
                if (is_wp_error($attachment_id)) {
                    @unlink($temporary);
                    throw new RuntimeException($attachment_id->get_error_message());
                }
                $attachment_ids[] = (int) $attachment_id;
            }
        } catch (Throwable $error) {
            foreach ($attachment_ids as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
            throw $error;
        } finally {
            $zip->close();
        }

        if (empty($attachment_ids)) {
            throw new RuntimeException(__('No images were imported for the car.', 'bricks-child'));
        }
        return $attachment_ids;
    }
}
