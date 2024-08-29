<?php
/**
 * Plugin Name: Duplicate Plugin
 * Description: A plugin to duplicate posts and pages, including Contact Form 7 forms, with a settings page.
 * Version: 1.0
 * Author: Veli Korumaz
 * Text Domain: adp
 * Domain Path: /languages
 */

// Güvenlik için doğrudan erişimi önleyin
if (!defined('ABSPATH')) {
    exit;
}

// Metinleri çevirilebilir hale getirme fonksiyonu
function adp_load_textdomain() {
    load_plugin_textdomain('adp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'adp_load_textdomain');

// Duplicate post function
function adp_duplicate_post_as_draft($post_id) {
    $post = get_post($post_id);
    
    if (isset($post) && $post != null) {
        $new_post = array(
            'post_title'    => $post->post_title . ' (' . __('Copy', 'adp') . ')',
            'post_content'  => $post->post_content,
            'post_status'   => 'draft',
            'post_type'     => $post->post_type,
            'post_author'   => $post->post_author,
        );
        
        $new_post_id = wp_insert_post($new_post);
        
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
        }
        
        $meta_data = get_post_meta($post_id);
        foreach ($meta_data as $key => $value) {
            if ($key != '_wp_old_slug') {
                update_post_meta($new_post_id, $key, maybe_unserialize($value[0]));
            }
        }
        
        return $new_post_id;
    } else {
        wp_die(__('Post creation failed, could not find original post:', 'adp') . ' ' . $post_id);
    }
}

// Add duplicate link to post actions
function adp_duplicate_post_link($actions, $post) {
    $options = get_option('adp_duplicate_post_options');
    
    if (current_user_can('edit_posts') && isset($options['enable_duplicate']) && $options['enable_duplicate']) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=adp_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="' . esc_attr__('Duplicate this item', 'adp') . '" rel="permalink">' . __('Duplicate', 'adp') . '</a>';
    }
    return $actions;
}

add_filter('post_row_actions', 'adp_duplicate_post_link', 10, 2);
add_filter('page_row_actions', 'adp_duplicate_post_link', 10, 2);

// Add duplicate link to Contact Form 7 actions
function adp_duplicate_cf7_link($actions, $post) {
    $options = get_option('adp_duplicate_post_options');
    
    if ($post->post_type == 'wpcf7_contact_form' && current_user_can('edit_posts') && isset($options['enable_cf7_duplicate']) && $options['enable_cf7_duplicate']) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=adp_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="' . esc_attr__('Duplicate this form', 'adp') . '" rel="permalink">' . __('Duplicate', 'adp') . '</a>';
    }
    return $actions;
}

add_filter('wpcf7_admin_form_actions', 'adp_duplicate_cf7_link', 10, 2);

// Handle the duplicate post action
function adp_duplicate_post_action() {
    if (!isset($_GET['post']) || !isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], basename(__FILE__))) {
        return;
    }
    
    $post_id = absint($_GET['post']);
    $new_post_id = adp_duplicate_post_as_draft($post_id);
    
    wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
    exit;
}

add_action('admin_action_adp_duplicate_post_as_draft', 'adp_duplicate_post_action');

// Add duplicate settings page
function adp_duplicate_settings_page() {
    add_options_page(
        __('Duplicate Post Settings', 'adp'),
        __('Duplicate Post', 'adp'),
        'manage_options',
        'adp-duplicate-post',
        'adp_duplicate_settings_page_html'
    );
}

add_action('admin_menu', 'adp_duplicate_settings_page');

function adp_duplicate_settings_page_html() {
    ?>
    <div class="wrap">
        <h1><?php _e('Duplicate Post Settings', 'adp'); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('adp_duplicate_post_options');
            do_settings_sections('adp-duplicate-post');
            submit_button(__('Save Settings', 'adp'));
            ?>
        </form>
    </div>
    <?php
}

// Register and define the settings
function adp_duplicate_post_settings_init() {
    register_setting('adp_duplicate_post_options', 'adp_duplicate_post_options');

    add_settings_section(
        'adp_duplicate_post_section',
        __('Duplicate Post Settings', 'adp'),
        'adp_duplicate_post_section_callback',
        'adp-duplicate-post'
    );

    add_settings_field(
        'adp_duplicate_post_prefix',
        __('Title Prefix', 'adp'),
        'adp_duplicate_post_prefix_render',
        'adp-duplicate-post',
        'adp_duplicate_post_section'
    );

    add_settings_field(
        'adp_enable_duplicate',
        __('Enable Duplicate', 'adp'),
        'adp_enable_duplicate_render',
        'adp-duplicate-post',
        'adp_duplicate_post_section'
    );

    add_settings_field(
        'adp_enable_cf7_duplicate',
        __('Enable Contact Form 7 Duplicate', 'adp'),
        'adp_enable_cf7_duplicate_render',
        'adp-duplicate-post',
        'adp_duplicate_post_section'
    );
}

add_action('admin_init', 'adp_duplicate_post_settings_init');

function adp_duplicate_post_section_callback() {
    echo __('Configure the settings for the Duplicate Post plugin.', 'adp');
}

function adp_duplicate_post_prefix_render() {
    $options = get_option('adp_duplicate_post_options');
    ?>
    <input type='text' name='adp_duplicate_post_options[adp_duplicate_post_prefix]' value='<?php echo isset($options['adp_duplicate_post_prefix']) ? $options['adp_duplicate_post_prefix'] : ''; ?>'>
    <?php
}

function adp_enable_duplicate_render() {
    $options = get_option('adp_duplicate_post_options');
    ?>
    <input type='checkbox' name='adp_duplicate_post_options[enable_duplicate]' <?php checked(isset($options['enable_duplicate']) ? $options['enable_duplicate'] : 0); ?> value='1'>
    <?php
}

function adp_enable_cf7_duplicate_render() {
    $options = get_option('adp_duplicate_post_options');
    ?>
    <input type='checkbox' name='adp_duplicate_post_options[enable_cf7_duplicate]' <?php checked(isset($options['enable_cf7_duplicate']) ? $options['enable_cf7_duplicate'] : 0); ?> value='1'>
    <?php
}

// Apply the title prefix setting
function adp_apply_title_prefix($post_ID, $post) {
    if ($post->post_status == 'auto-draft') {
        return;
    }
    
    $options = get_option('adp_duplicate_post_options');
    $prefix = isset($options['adp_duplicate_post_prefix']) ? $options['adp_duplicate_post_prefix'] : '';
    
    if ($prefix && strpos($post->post_title, $prefix) === false) {
        $new_title = $prefix . $post->post_title;
        wp_update_post(array('ID' => $post_ID, 'post_title' => $new_title));
    }
}

add_action('wp_insert_post', 'adp_apply_title_prefix', 10, 2);
