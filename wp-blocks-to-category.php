<?php
/**
 * Plugin Name: WP Blocks to Category
 * Plugin URI: https://github.com/dataforge/wp-blocks-to-category
 * Description: Automatically assign categories to posts based on the blocks they contain. Configure block-to-category mappings in Settings > WP Blocks to Category.
 * Version: 1.0.3
 * Author: Dataforge
 * Text Domain: wp-blocks-to-category
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI: https://github.com/dataforge/wp-blocks-to-category
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPBTC_VERSION', '1.0.3');
define('WPBTC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPBTC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPBTC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class WP_Blocks_To_Category {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Option name for storing block-category mappings
     */
    const OPTION_MAPPINGS = 'wpbtc_block_category_mappings';

    /**
     * Option name for storing settings
     */
    const OPTION_SETTINGS = 'wpbtc_settings';

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Post save hook
        add_action('save_post', array($this, 'on_post_save'), 10, 3);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_wpbtc_save_mappings', array($this, 'ajax_save_mappings'));
        add_action('wp_ajax_wpbtc_get_blocks', array($this, 'ajax_get_blocks'));
        add_action('wp_ajax_wpbtc_process_existing_posts', array($this, 'ajax_process_existing_posts'));

        // Plugin action links
        add_filter('plugin_action_links_' . WPBTC_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }

    /**
     * Add admin menu under Settings
     */
    public function add_admin_menu() {
        add_options_page(
            __('WP Blocks to Category', 'wp-blocks-to-category'),
            __('WP Blocks to Category', 'wp-blocks-to-category'),
            'manage_options',
            'wp-blocks-to-category',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Add Settings link to plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=wp-blocks-to-category'),
            __('Settings', 'wp-blocks-to-category')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wpbtc_settings_group', self::OPTION_MAPPINGS);
        register_setting('wpbtc_settings_group', self::OPTION_SETTINGS);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ('settings_page_wp-blocks-to-category' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'wpbtc-admin-style',
            WPBTC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPBTC_VERSION
        );

        wp_enqueue_script(
            'wpbtc-admin-script',
            WPBTC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WPBTC_VERSION,
            true
        );

        wp_localize_script('wpbtc-admin-script', 'wpbtc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbtc_ajax_nonce')
        ));
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-blocks-to-category'));
        }

        // Get current mappings and settings
        $mappings = get_option(self::OPTION_MAPPINGS, array());
        $settings = get_option(self::OPTION_SETTINGS, array(
            'remove_categories_on_block_removal' => false
        ));

        // Get all registered blocks
        $registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

        // Add embed variations
        $embed_variations = $this->get_embed_variations();
        foreach ($embed_variations as $variation_slug => $variation_name) {
            $block_name = 'core/embed:' . $variation_slug;
            $registered_blocks[$block_name] = (object) array(
                'name' => $block_name,
                'title' => $variation_name . ' Embed'
            );
        }

        // Get all categories
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        // Sort mappings alphabetically by block name
        uksort($mappings, 'strnatcasecmp');

        include WPBTC_PLUGIN_DIR . 'includes/admin-page.php';
    }

    /**
     * Get a list of common oEmbed provider variations for the core/embed block
     */
    private function get_embed_variations() {
        return array(
            'animoto' => 'Animoto',
            'bluesky' => 'Bluesky',
            'cloudup' => 'Cloudup',
            'collegehumor' => 'CollegeHumor',
            'crowdsignal' => 'Crowdsignal',
            'dailymotion' => 'Dailymotion',
            'facebook' => 'Facebook',
            'flickr' => 'Flickr',
            'imgur' => 'Imgur',
            'instagram' => 'Instagram',
            'issuu' => 'Issuu',
            'kickstarter' => 'Kickstarter',
            'meetup-com' => 'Meetup.com',
            'mixcloud' => 'Mixcloud',
            'pinterest' => 'Pinterest',
            'pocket-casts' => 'Pocket Casts',
            'reddit' => 'Reddit',
            'reverbnation' => 'ReverbNation',
            'screencast' => 'Screencast',
            'scribd' => 'Scribd',
            'slideshare' => 'Slideshare',
            'smugmug' => 'SmugMug',
            'soundcloud' => 'SoundCloud',
            'spotify' => 'Spotify',
            'ted' => 'TED',
            'tiktok' => 'TikTok',
            'tumblr' => 'Tumblr',
            'videopress' => 'VideoPress',
            'vimeo' => 'Vimeo',
            'wolfram' => 'Wolfram',
            'wordpress' => 'WordPress',
            'wordpress-tv' => 'WordPress.tv',
            'youtube' => 'YouTube',
        );
    }

    /**
     * AJAX handler to save mappings
     */
    public function ajax_save_mappings() {
        check_ajax_referer('wpbtc_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-blocks-to-category')));
            return;
        }

        $mappings = isset($_POST['mappings']) ? json_decode(stripslashes($_POST['mappings']), true) : array();
        $remove_categories = isset($_POST['remove_categories_on_block_removal']) ? (bool) $_POST['remove_categories_on_block_removal'] : false;

        update_option(self::OPTION_MAPPINGS, $mappings);
        update_option(self::OPTION_SETTINGS, array(
            'remove_categories_on_block_removal' => $remove_categories
        ));

        wp_send_json_success(array('message' => __('Settings saved successfully', 'wp-blocks-to-category')));
    }

    /**
     * AJAX handler to get all blocks
     */
    public function ajax_get_blocks() {
        check_ajax_referer('wpbtc_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-blocks-to-category')));
            return;
        }

        $registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $blocks = array();

        foreach ($registered_blocks as $block_name => $block_type) {
            $blocks[] = array(
                'name' => $block_name,
                'title' => isset($block_type->title) ? $block_type->title : $block_name
            );
        }

        // Add embed variations
        $embed_variations = $this->get_embed_variations();
        foreach ($embed_variations as $variation_slug => $variation_name) {
            $blocks[] = array(
                'name' => 'core/embed:' . $variation_slug,
                'title' => $variation_name . ' Embed'
            );
        }

        wp_send_json_success($blocks);
    }

    /**
     * Handle post save event
     */
    public function on_post_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Only process posts (not pages or custom post types by default, but can be extended)
        if ($post->post_type !== 'post') {
            return;
        }

        // Process categories for this post
        $this->process_post_categories($post_id, $post);
    }

    /**
     * Recursively extract block names from parsed blocks
     */
    private function extract_block_names($blocks) {
        $block_names = array();

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            $block_name = $block['blockName'];

            // Handle core/embed variations
            if ($block_name === 'core/embed' && !empty($block['attrs']['providerNameSlug'])) {
                $block_names[] = 'core/embed:' . $block['attrs']['providerNameSlug'];
            } else {
                $block_names[] = $block_name;
            }

            // Check for inner blocks
            if (!empty($block['innerBlocks'])) {
                $inner_block_names = $this->extract_block_names($block['innerBlocks']);
                $block_names = array_merge($block_names, $inner_block_names);
            }
        }

        return array_unique($block_names);
    }

    /**
     * Get plugin mappings
     */
    public static function get_mappings() {
        return get_option(self::OPTION_MAPPINGS, array());
    }

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option(self::OPTION_SETTINGS, array(
            'remove_categories_on_block_removal' => false
        ));
    }

    /**
     * AJAX handler to process existing posts
     */
    public function ajax_process_existing_posts() {
        check_ajax_referer('wpbtc_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-blocks-to-category')));
            return;
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10; // Process 10 posts at a time

        // Get posts
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        );

        $query = new WP_Query($args);
        $total_posts = $query->found_posts;
        $processed = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post = get_post($post_id);

                // Process this post using the same logic as on_post_save
                $this->process_post_categories($post_id, $post);
                $processed++;
            }
            wp_reset_postdata();
        }

        $total = wp_count_posts('post')->publish;
        $completed = $offset + $processed;
        $remaining = $total - $completed;

        wp_send_json_success(array(
            'processed' => $processed,
            'total' => $total,
            'completed' => $completed,
            'remaining' => $remaining,
            'continue' => $remaining > 0,
            'message' => sprintf(
                __('Processed %d of %d posts...', 'wp-blocks-to-category'),
                $completed,
                $total
            )
        ));
    }

    /**
     * Process post categories based on blocks
     * Extracted from on_post_save for reusability
     */
    private function process_post_categories($post_id, $post) {
        // Get the post content
        $content = $post->post_content;

        // Parse blocks from content
        $blocks = parse_blocks($content);
        $block_names = $this->extract_block_names($blocks);

        // Get current mappings
        $mappings = get_option(self::OPTION_MAPPINGS, array());
        $settings = get_option(self::OPTION_SETTINGS, array(
            'remove_categories_on_block_removal' => false
        ));

        // Get current post categories
        $current_categories = wp_get_post_categories($post_id);

        // Collect all categories that should be assigned based on blocks
        $categories_to_assign = array();

        foreach ($block_names as $block_name) {
            if (isset($mappings[$block_name]) && is_array($mappings[$block_name])) {
                $categories_to_assign = array_merge($categories_to_assign, $mappings[$block_name]);
            }
        }

        // Remove duplicates
        $categories_to_assign = array_unique($categories_to_assign);

        // Merge with current categories (additive approach)
        $new_categories = array_unique(array_merge($current_categories, $categories_to_assign));

        // Handle category removal if enabled
        if ($settings['remove_categories_on_block_removal']) {
            // Find categories that were assigned by blocks but those blocks are no longer present
            $categories_from_all_mapped_blocks = array();
            foreach ($mappings as $mapped_block => $mapped_categories) {
                if (!in_array($mapped_block, $block_names)) {
                    // This block is not in the content anymore
                    $categories_from_all_mapped_blocks = array_merge($categories_from_all_mapped_blocks, $mapped_categories);
                }
            }

            // Remove these categories from the post
            $categories_from_all_mapped_blocks = array_unique($categories_from_all_mapped_blocks);
            $new_categories = array_diff($new_categories, $categories_from_all_mapped_blocks);
        }

        // Update post categories if changed
        if ($new_categories !== $current_categories) {
            wp_set_post_categories($post_id, $new_categories);
        }
    }
}

/**
 * Initialize the plugin
 */
function wpbtc_init() {
    return WP_Blocks_To_Category::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'wpbtc_init');

// Initialize updater
require_once WPBTC_PLUGIN_DIR . 'includes/class-wpbtc-updater.php';
WPBTC_Updater::init();
