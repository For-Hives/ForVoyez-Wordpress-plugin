<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Renderer
{
    public static function render_image_item($image)
    {
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $is_analyzed = get_post_meta($image->ID, '_forvoyez_analyzed', true);
        $disabled_class = $is_analyzed ? 'forvoyez-analyzed' : '';
        $all_complete = !empty($image_alt) && !empty($image->post_title) && !empty($image->post_excerpt);
        ?>
        <div class="forvoyez-image-item <?php echo $disabled_class; ?>" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <input type="checkbox" class="forvoyez-image-checkbox" value="<?php echo esc_attr($image->ID); ?>">
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
            <div class="forvoyez-metadata-icons">
                <?php if ($all_complete) : ?>
                    <span class="dashicons dashicons-yes-alt" title="All metadata complete" style="color: green;"></span>
                <?php else : ?>
                    <?php if (empty($image_alt)) : ?>
                        <span class="dashicons dashicons-editor-textcolor" title="Missing Alt Text"></span>
                    <?php endif; ?>
                    <?php if (empty($image->post_title)) : ?>
                        <span class="dashicons dashicons-heading" title="Missing Title"></span>
                    <?php endif; ?>
                    <?php if (empty($image->post_excerpt)) : ?>
                        <span class="dashicons dashicons-editor-quote" title="Missing Caption"></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="forvoyez-action-buttons">
                <?php if (!$is_analyzed): ?>
                    <button class="forvoyez-analyze-button" title="Analyze with ForVoyez">
                        <span class="dashicons dashicons-upload"></span>
                    </button>
                <?php endif; ?>
                <button class="forvoyez-see-more" title="See Details">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
            </div>
            <div class="forvoyez-loader"></div>
            <div class="forvoyez-image-details" style="display: none;">
                <p><strong>Title:</strong> <?php echo esc_html($image->post_title ?: 'Not set'); ?></p>
                <p><strong>Alt Text:</strong> <?php echo esc_html($image_alt ?: 'Not set'); ?></p>
                <p><strong>Caption:</strong> <?php echo esc_html($image->post_excerpt ?: 'Not set'); ?></p>
            </div>
        </div>
        <?php
    }

    public static function display_filters($total_images, $displayed_images)
    {
        $per_page = isset($_GET['per_page']) ? abs((int)$_GET['per_page']) : 25;
        $filter = isset($_GET['filter']) ? $_GET['filter'] : array();
        ?>
        <div class="forvoyez-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="forvoyez-auto-alt-text">
                <div class="forvoyez-filter-row">
                    <div class="forvoyez-filter-group">
                        <label>Items per page:
                            <select name="per_page">
                                <option value="25" <?php selected($per_page, 25); ?>>25</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                                <option value="-1" <?php selected($per_page, -1); ?>>All</option>
                            </select>
                        </label>
                    </div>
                    <div class="forvoyez-filter-group">
                        <label><input type="checkbox" name="filter[]" value="alt" <?php checked(in_array('alt', $filter)); ?>> Missing Alt</label>
                        <label><input type="checkbox" name="filter[]" value="title" <?php checked(in_array('title', $filter)); ?>> Missing Title</label>
                        <label><input type="checkbox" name="filter[]" value="caption" <?php checked(in_array('caption', $filter)); ?>> Missing Caption</label>
                    </div>
                    <div class="forvoyez-filter-group">
                        <input type="submit" value="Apply Filters" class="button">
                    </div>
                </div>
                <div class="forvoyez-filter-row">
                    <div class="forvoyez-displayed-images">
                        Images Displayed: <strong><?php echo $displayed_images; ?></strong>
                        /<?php echo $total_images; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}