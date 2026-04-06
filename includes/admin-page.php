<?php
/**
 * Admin Settings Page Template
 *
 * @package WP_Blocks_To_Category
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="wpbtc-container">
        <div class="wpbtc-notice" id="wpbtc-notice" style="display:none;"></div>

        <div class="wpbtc-settings-card">
            <h2><?php _e('General Settings', 'wp-blocks-to-category'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="remove_categories_on_block_removal">
                            <?php _e('Remove Categories on Block Removal', 'wp-blocks-to-category'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                id="remove_categories_on_block_removal"
                                name="remove_categories_on_block_removal"
                                value="1"
                                <?php checked($settings['remove_categories_on_block_removal'], true); ?>
                            />
                            <?php _e('Automatically remove assigned categories when the block is removed from a post', 'wp-blocks-to-category'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, if a post no longer contains a block, the categories that were assigned due to that block will be removed. Other categories will remain unchanged.', 'wp-blocks-to-category'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wpbtc-settings-card">
            <h2><?php _e('Block to Category Mappings', 'wp-blocks-to-category'); ?></h2>
            <p class="description">
                <?php _e('Select one or more categories for each block. When a post contains a block, it will automatically be assigned the selected categories.', 'wp-blocks-to-category'); ?>
            </p>

            <div class="wpbtc-filter-section">
                <input
                    type="text"
                    id="wpbtc-block-search"
                    class="regular-text"
                    placeholder="<?php esc_attr_e('Search blocks...', 'wp-blocks-to-category'); ?>"
                />
            </div>

            <div class="wpbtc-mappings-container">
                <?php if (empty($registered_blocks)) : ?>
                    <p><?php _e('No blocks found. Make sure you have the block editor enabled.', 'wp-blocks-to-category'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped wpbtc-mappings-table">
                        <thead>
                            <tr>
                                <th class="wpbtc-col-block"><?php _e('Block', 'wp-blocks-to-category'); ?></th>
                                <th class="wpbtc-col-name"><?php _e('Block Name', 'wp-blocks-to-category'); ?></th>
                                <th class="wpbtc-col-categories"><?php _e('Assigned Categories', 'wp-blocks-to-category'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="wpbtc-mappings-tbody">
                            <?php
                            // Sort blocks by title
                            $blocks_array = array();
                            foreach ($registered_blocks as $block_name => $block_type) {
                                // Get title or create a fallback from the last part of the slug
                                if (isset($block_type->title) && !empty($block_type->title)) {
                                    $title = $block_type->title;
                                } else {
                                    // Extract the last part after the slash and convert to title case
                                    $slug_parts = explode('/', $block_name);
                                    $last_part = end($slug_parts);
                                    $title = ucwords(str_replace(array('-', '_'), ' ', $last_part));
                                }

                                $blocks_array[] = array(
                                    'name' => $block_name,
                                    'title' => $title
                                );
                            }

                            usort($blocks_array, function($a, $b) {
                                return strcasecmp($a['title'], $b['title']);
                            });

                            foreach ($blocks_array as $block) :
                                $block_name = $block['name'];
                                $block_title = $block['title'];
                                $assigned_categories = isset($mappings[$block_name]) ? $mappings[$block_name] : array();
                            ?>
                                <tr class="wpbtc-mapping-row" data-block-name="<?php echo esc_attr($block_name); ?>" data-block-title="<?php echo esc_attr($block_title); ?>">
                                    <td class="wpbtc-col-block">
                                        <div class="wpbtc-block-icon">
                                            <span class="dashicons dashicons-block-default"></span>
                                        </div>
                                    </td>
                                    <td class="wpbtc-col-name">
                                        <strong><?php echo esc_html($block_title); ?></strong>
                                        <br>
                                        <code class="wpbtc-block-name"><?php echo esc_html($block_name); ?></code>
                                    </td>
                                    <td class="wpbtc-col-categories">
                                        <select
                                            name="wpbtc_mappings[<?php echo esc_attr($block_name); ?>][]"
                                            class="wpbtc-category-select"
                                            multiple="multiple"
                                            data-block="<?php echo esc_attr($block_name); ?>"
                                            style="width: 100%; min-height: 100px;"
                                        >
                                            <?php foreach ($categories as $category) : ?>
                                                <option
                                                    value="<?php echo esc_attr($category->term_id); ?>"
                                                    <?php selected(in_array($category->term_id, $assigned_categories)); ?>
                                                >
                                                    <?php echo esc_html($category->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">
                                            <?php
                                            $count = count($assigned_categories);
                                            if ($count > 0) {
                                                printf(
                                                    _n('%d category assigned', '%d categories assigned', $count, 'wp-blocks-to-category'),
                                                    $count
                                                );
                                            } else {
                                                _e('No categories assigned', 'wp-blocks-to-category');
                                            }
                                            ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="wpbtc-no-results" style="display:none;">
                        <p><?php _e('No blocks found matching your search.', 'wp-blocks-to-category'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpbtc-actions">
            <button type="button" class="button button-primary button-large" id="wpbtc-save-settings">
                <?php _e('Save Settings', 'wp-blocks-to-category'); ?>
            </button>
            <span class="spinner" id="wpbtc-spinner"></span>
        </div>

        <div class="wpbtc-settings-card wpbtc-bulk-process-card">
            <h2><?php _e('Process Existing Posts', 'wp-blocks-to-category'); ?></h2>
            <p class="description">
                <?php _e('Apply the current block-to-category mappings to all existing published posts. This is useful when you first install the plugin or change your mappings.', 'wp-blocks-to-category'); ?>
            </p>

            <?php
            $post_count = wp_count_posts('post')->publish;
            ?>

            <div class="wpbtc-bulk-process-info">
                <p>
                    <strong><?php printf(_n('%d published post', '%d published posts', $post_count, 'wp-blocks-to-category'), $post_count); ?></strong>
                </p>
                <p class="description">
                    <?php _e('This will process all published posts and assign categories based on the blocks they contain. Existing categories will be preserved.', 'wp-blocks-to-category'); ?>
                </p>
            </div>

            <div class="wpbtc-bulk-actions">
                <button type="button" class="button button-secondary button-large" id="wpbtc-process-existing">
                    <?php _e('Process All Existing Posts', 'wp-blocks-to-category'); ?>
                </button>
                <span class="spinner" id="wpbtc-process-spinner"></span>
            </div>

            <div class="wpbtc-progress-container" id="wpbtc-progress-container" style="display:none;">
                <div class="wpbtc-progress-bar">
                    <div class="wpbtc-progress-fill" id="wpbtc-progress-fill"></div>
                </div>
                <p class="wpbtc-progress-text" id="wpbtc-progress-text"></p>
            </div>
        </div>

        <div class="wpbtc-info-card">
            <h3><?php _e('How it works', 'wp-blocks-to-category'); ?></h3>
            <ol>
                <li><?php _e('Select categories for each block type above', 'wp-blocks-to-category'); ?></li>
                <li><?php _e('When a post is created or edited with those blocks, the selected categories will be automatically assigned', 'wp-blocks-to-category'); ?></li>
                <li><?php _e('Existing categories on the post are preserved (categories are added, not replaced)', 'wp-blocks-to-category'); ?></li>
                <li><?php _e('If enabled, categories can be automatically removed when blocks are removed from posts', 'wp-blocks-to-category'); ?></li>
            </ol>
        </div>

        <div class="card" style="margin-top:2em;">
            <h2><?php _e('Plugin Updates', 'wp-blocks-to-category'); ?></h2>
            <p>
                <?php _e('Current version:', 'wp-blocks-to-category'); ?>
                <strong>v<?php echo esc_html(WPBTC_VERSION); ?></strong>
                <?php if (isset($_GET['update_check'])) : ?>
                    <?php if (WPBTC_Updater::is_update_available()) : ?>
                        &mdash; <span style="color:#b32d2e;"><?php _e('Update available!', 'wp-blocks-to-category'); ?></span>
                        <a href="<?php echo esc_url(admin_url('update-core.php')); ?>"><?php _e('Go to Updates', 'wp-blocks-to-category'); ?></a>
                    <?php else : ?>
                        &mdash; <span style="color:#00a32a;"><?php _e('Up to date', 'wp-blocks-to-category'); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <input type="hidden" name="action" value="wpbtc_check_updates" />
                <?php wp_nonce_field('wpbtc_check_updates'); ?>
                <button type="submit" class="button"><?php _e('Check for Updates', 'wp-blocks-to-category'); ?></button>
            </form>
        </div>
    </div>
</div>
