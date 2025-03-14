<?php
/**
 * Bulk edit functionality for the OneClickContent Image Details plugin.
 *
 * Handles the bulk edit tab and related AJAX actions for editing and generating image metadata.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class OneClickContent_Images_Bulk_Edit
 *
 * Manages bulk editing and metadata generation in the Media Library.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Bulk_Edit {

    public function render_bulk_edit_tab() {
        ?>
        <div id="oneclick_images_bulk_edit" class="wrap">
            <h2><?php esc_html_e('Bulk Edit Image Metadata', 'oneclickcontent-images'); ?></h2>
            
            <!-- Usage Counter -->
            <div class="usage-info-section">
                <h2><?php esc_html_e('Your Usage', 'oneclickcontent-images'); ?></h2>
                <div id="usage_status" class="usage-summary">
                    <strong id="usage_count"><?php esc_html_e('Loading usage data...', 'oneclickcontent-images'); ?></strong>
                    <div class="progress">
                        <div 
                            id="usage_progress" 
                            class="progress-bar bg-success" 
                            role="progressbar" 
                            aria-valuenow="0" 
                            aria-valuemin="0" 
                            aria-valuemax="100" 
                            style="width: 0%;"
                        >
                            0%
                        </div>
                    </div>
                </div>

                <div class="bulk-edit-header">
                    <button id="generate-all-metadata" class="button button-primary button-hero">
                        <?php esc_html_e('Generate All Metadata', 'oneclickcontent-images'); ?>
                    </button>
                    <button id="stop-bulk-generation" class="button button-secondary" style="display:none;">
                        <?php esc_html_e('Stop Generation', 'oneclickcontent-images'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Click to generate metadata for all your images.', 'oneclickcontent-images'); ?></p>
                </div>

                <div id="bulk-generate-status" class="bulk-generate-status" style="display: none;">
                    <h3>Bulk Generation Progress</h3>
                    <div id="bulk-generate-progress-container" class="bulk-generate-progress-container">
                        <div id="bulk-generate-progress-bar" class="bulk-generate-progress-bar"></div>
                    </div>
                    <div id="bulk-generate-message" class="bulk-generate-message"></div>
                </div>
            </div>

            <!-- Modal for Bulk Generation Confirmation -->
            <div id="bulk-generate-modal" class="modal" style="display:none;">
                <div class="modal-content">
                    <h2><?php esc_html_e('Confirm Bulk Metadata Generation', 'oneclickcontent-images'); ?></h2>
                    <p><?php esc_html_e('Generate metadata for all images in your library? This may take some time.', 'oneclickcontent-images'); ?></p>
                    <div id="bulk-generate-warning" class="warning" style="display:none;">
                        <p><strong><?php esc_html_e('Warning:', 'oneclickcontent-images'); ?></strong> <?php esc_html_e('This will overwrite any existing image metadata.', 'oneclickcontent-images'); ?></p>
                    </div>
                    <div class="modal-buttons">
                        <button id="confirm-bulk-generate" class="button button-primary"><?php esc_html_e('Yes, Generate', 'oneclickcontent-images'); ?></button>
                        <button id="cancel-bulk-generate" class="button button-secondary"><?php esc_html_e('Cancel', 'oneclickcontent-images'); ?></button>
                    </div>
                </div>
            </div>

            <!-- DataTable for Bulk Editing -->
            <table id="image-metadata-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Thumbnail', 'oneclickcontent-images'); ?></th>
                        <th><?php esc_html_e('Title', 'oneclickcontent-images'); ?></th>
                        <th><?php esc_html_e('Alt Text', 'oneclickcontent-images'); ?></th>
                        <th><?php esc_html_e('Description', 'oneclickcontent-images'); ?></th>
                        <th><?php esc_html_e('Caption', 'oneclickcontent-images'); ?></th>
                        <th><?php esc_html_e('Actions', 'oneclickcontent-images'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Fetch image metadata for DataTables via AJAX with server-side pagination and search.
     *
     * @since 1.0.0
     * @return void
     */
    public function get_image_metadata() {
        check_ajax_referer('oneclick_images_bulk_edit', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
        }

        $draw = isset($_REQUEST['draw']) ? intval($_REQUEST['draw']) : 1;
        $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
        $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
        $search_value = isset($_REQUEST['search']['value']) ? sanitize_text_field($_REQUEST['search']['value']) : '';

        $page = $start / $length + 1;

        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $length,
            'paged'          => $page,
        );

        if (!empty($search_value)) {
            $args['s'] = $search_value;
        }

        $total_query = new WP_Query(array_merge($args, array('posts_per_page' => -1, 'fields' => 'ids')));
        $records_total = $total_query->found_posts;

        $query = new WP_Query($args);
        $records_filtered = $query->found_posts;

        $data = array();
        foreach ($query->posts as $post) {
            $thumbnail = wp_get_attachment_image($post->ID, 'thumbnail', false, array('style' => 'max-width: 50px;'));
            $data[] = array(
                'thumbnail'   => $thumbnail,
                'title'       => esc_html(get_the_title($post->ID)),
                'alt_text'    => esc_attr(get_post_meta($post->ID, '_wp_attachment_image_alt', true)),
                'description' => esc_textarea($post->post_content),
                'caption'     => esc_textarea($post->post_excerpt),
                'id'          => $post->ID,
            );
        }

        wp_send_json(array(
            'draw'            => $draw,
            'recordsTotal'    => $records_total,
            'recordsFiltered' => $records_filtered,
            'data'            => $data,
        ));
    }

    /**
     * Save edited metadata via AJAX.
     *
     * @since 1.0.0
     * @return void
     */
    public function save_bulk_metadata() {
        check_ajax_referer('oneclick_images_bulk_edit', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
        }

        $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $caption = isset($_POST['caption']) ? sanitize_textarea_field($_POST['caption']) : '';

        if (!$image_id) {
            wp_send_json_error('Invalid image ID.');
        }

        // Update the metadata
        update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
        wp_update_post([
            'ID'           => $image_id,
            'post_title'   => $title,
            'post_content' => $description,
            'post_excerpt' => $caption,
        ]);

        // Return the updated metadata
        $updated_data = [
            'id'          => $image_id,
            'title'       => $title,
            'alt_text'    => $alt_text,
            'description' => $description,
            'caption'     => $caption,
            // Optionally include thumbnail if your DataTable needs it
            'thumbnail'   => wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'thumbnail-preview']),
        ];

        wp_send_json_success($updated_data);
    }
}