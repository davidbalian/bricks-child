<?php
/**
 * Manages per-user account logos (upload, replace, remove).
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class UserLogoManager
{
    private const USER_META_KEY = '_account_logo_attachment_id';

    /**
     * Returns the current logo attachment ID for a user, or 0 if none.
     */
    public function getUserLogoAttachmentId(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $attachment_id = (int) get_user_meta($user_id, self::USER_META_KEY, true);

        return $attachment_id > 0 ? $attachment_id : 0;
    }

    /**
     * Returns the current logo URL for a user, or an empty string if none.
     */
    public function getUserLogoUrl(int $user_id, string $size = 'thumbnail'): string
    {
        $attachment_id = $this->getUserLogoAttachmentId($user_id);

        if ($attachment_id <= 0) {
            return '';
        }

        $url = wp_get_attachment_image_url($attachment_id, $size);

        return is_string($url) ? $url : '';
    }

    /**
     * Handles saving/replacing a logo for the current user using a file array from $_FILES.
     *
     * @param int   $user_id         The user ID.
     * @param array $file_array      Single file array from $_FILES (e.g. $_FILES['account_logo']).
     * @param int   $max_file_size   Max allowed file size in bytes.
     * @param array $allowed_mimes   Allowed MIME types.
     *
     * @return array{success:bool,message:string,logoUrl?:string}
     */
    public function saveUserLogoFromUpload(int $user_id, array $file_array, int $max_file_size, array $allowed_mimes): array
    {
        if ($user_id <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid user.',
            ];
        }

        if (empty($file_array['tmp_name']) || !is_uploaded_file($file_array['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'No file uploaded.',
            ];
        }

        if (!empty($file_array['error']) && $file_array['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Upload error. Please try again.',
            ];
        }

        if (!empty($file_array['size']) && $file_array['size'] > $max_file_size) {
            return [
                'success' => false,
                'message' => 'Image is too large.',
            ];
        }

        // Basic MIME/type check.
        $file_type = wp_check_filetype_and_ext(
            $file_array['tmp_name'],
            $file_array['name'],
            $allowed_mimes
        );

        if (empty($file_type['ext']) || empty($file_type['type'])) {
            return [
                'success' => false,
                'message' => 'Invalid file type.',
            ];
        }

        // Let WordPress handle the upload into the uploads directory.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ];

        $uploaded = wp_handle_upload($file_array, $overrides);

        if (!empty($uploaded['error'])) {
            return [
                'success' => false,
                'message' => 'Failed to store uploaded file.',
            ];
        }

        $file_url  = $uploaded['url'];
        $file_type = $uploaded['type'];
        $file_path = $uploaded['file'];

        // Create an attachment post.
        $attachment = [
            'post_mime_type' => $file_type,
            'post_title'     => sanitize_file_name(basename($file_path)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $user_id,
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attachment_id) || $attachment_id <= 0) {
            // Clean up the uploaded file if we failed to create the attachment.
            @unlink($file_path);

            return [
                'success' => false,
                'message' => 'Failed to create media attachment.',
            ];
        }

        // Generate attachment metadata (sizes, etc.).
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        if (!is_wp_error($attachment_data) && !empty($attachment_data)) {
            // Convert logo to WebP using the shared image optimization pipeline if available.
            if (function_exists('convert_to_webp_with_fallback')) {
                $webp_metadata = convert_to_webp_with_fallback($attachment_id, $attachment_data);
                if (is_array($webp_metadata)) {
                    $attachment_data = $webp_metadata;
                }
            }

            // Persist final metadata (WebP-aware when conversion ran successfully).
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }

        // Replace any existing logo.
        $this->replaceUserLogoAttachment($user_id, $attachment_id);

        $logo_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return [
            'success' => true,
            'message' => 'Logo updated successfully.',
            'logoUrl' => is_string($logo_url) ? $logo_url : $file_url,
        ];
    }

    /**
     * Removes the current logo for the given user (attachment + meta).
     *
     * @return bool True on success, false on failure.
     */
    public function removeUserLogo(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $existing_id = $this->getUserLogoAttachmentId($user_id);

        if ($existing_id > 0) {
            // Delete attachment and its files.
            wp_delete_attachment($existing_id, true);
        }

        return delete_user_meta($user_id, self::USER_META_KEY);
    }

    /**
     * Internal helper to swap the stored attachment ID and delete the old one.
     */
    private function replaceUserLogoAttachment(int $user_id, int $new_attachment_id): void
    {
        $old_id = $this->getUserLogoAttachmentId($user_id);

        update_user_meta($user_id, self::USER_META_KEY, $new_attachment_id);

        if ($old_id > 0 && $old_id !== $new_attachment_id) {
            wp_delete_attachment($old_id, true);
        }
    }
}


