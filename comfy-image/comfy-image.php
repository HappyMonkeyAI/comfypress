<?php
/**
 * Plugin Name: Comfy Image — ComfyUI Integration
 * Plugin URI:  https://example.com/comfy-image
 * Description: Generate images inside Gutenberg using a remote ComfyUI instance.
 * Version:     0.1.0
 * Author:      stephen
 * License:     GPL-2.0+
 * Text Domain: comfy-image
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (! defined('COMFY_IMAGE_VERSION')) {
    define('COMFY_IMAGE_VERSION', '0.1.0');
}

// Activation: set default options if not present
function comfy_image_activate() {
    $defaults = array(
        'comfy_base_url' => 'https://owned-schedules-practitioner-reflection.trycloudflare.com/',
        'default_workflow_template' => '',
        'auto_save_to_media' => 1,
        'max_image_size_mb' => 10,
        'allowed_roles' => array('editor','author'),
    );

    foreach ($defaults as $key => $val) {
        $option_name = 'comfy_image_' . $key;
        if (get_option($option_name) === false) {
            add_option($option_name, $val);
        }
    }
}
register_activation_hook(__FILE__, 'comfy_image_activate');

// Admin menu
add_action('admin_menu', 'comfy_image_admin_menu');
function comfy_image_admin_menu() {
    add_options_page(
        __('Comfy Image', 'comfy-image'),
        __('Comfy Image', 'comfy-image'),
        'manage_options',
        'comfy-image',
        'comfy_image_settings_page'
    );
}

// Register settings and fields
add_action('admin_init', 'comfy_image_settings_init');
function comfy_image_settings_init() {
    register_setting('comfy_image_settings', 'comfy_image_comfy_base_url', array('sanitize_callback' => 'esc_url_raw'));
    register_setting('comfy_image_settings', 'comfy_image_default_workflow_template', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('comfy_image_settings', 'comfy_image_auto_save_to_media', array('sanitize_callback' => 'absint'));
    register_setting('comfy_image_settings', 'comfy_image_max_image_size_mb', array('sanitize_callback' => 'absint'));
    register_setting('comfy_image_settings', 'comfy_image_allowed_roles');

    add_settings_section('comfy_image_main', __('Comfy Image Settings', 'comfy-image'), function() {
        echo '<p>' . esc_html__('Configure ComfyUI integration and media handling.', 'comfy-image') . '</p>';
    }, 'comfy_image_settings');

    add_settings_field('comfy_base_url', __('ComfyUI Base URL', 'comfy-image'), 'comfy_image_base_url_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('default_workflow_template', __('Default workflow template', 'comfy-image'), 'comfy_image_default_workflow_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('auto_save_to_media', __('Auto save to WP Media', 'comfy-image'), 'comfy_image_auto_save_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('max_image_size_mb', __('Max image size (MB)', 'comfy-image'), 'comfy_image_max_size_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('allowed_roles', __('Allowed roles', 'comfy-image'), 'comfy_image_allowed_roles_field', 'comfy_image_settings', 'comfy_image_main');
}

function comfy_image_base_url_field() {
    $v = esc_attr(get_option('comfy_image_comfy_base_url', ''));
    echo "<input type='text' name='comfy_image_comfy_base_url' value='" . $v . "' size='60' />";
}

function comfy_image_default_workflow_field() {
    $v = esc_textarea(get_option('comfy_image_default_workflow_template', ''));
    echo "<textarea name='comfy_image_default_workflow_template' rows='6' cols='60'>" . $v . "</textarea>";
}

function comfy_image_auto_save_field() {
    $v = get_option('comfy_image_auto_save_to_media', 1);
    $checked = $v ? "checked='checked'" : '';
    echo "<label><input type='checkbox' name='comfy_image_auto_save_to_media' value='1' $checked /> " . esc_html__('Save generated images into WP Media Library', 'comfy-image') . "</label>";
}

function comfy_image_max_size_field() {
    $v = esc_attr(get_option('comfy_image_max_image_size_mb', 10));
    echo "<input type='number' name='comfy_image_max_image_size_mb' value='" . $v . "' min='1' max='100' />";
}

function comfy_image_allowed_roles_field() {
    $roles = get_option('comfy_image_allowed_roles', array('editor','author'));
    if (!is_array($roles)) { $roles = array($roles); }
    global $wp_roles;
    if (! isset($wp_roles)) { $wp_roles = new WP_Roles(); }
    foreach ($wp_roles->roles as $role_key => $role_info) {
        $chk = in_array($role_key, $roles) ? "checked='checked'" : '';
        echo "<label style='display:block;margin-bottom:3px;'><input type='checkbox' name='comfy_image_allowed_roles[]' value='" . esc_attr($role_key) . "' $chk /> " . esc_html($role_info['name']) . "</label>";
    }
}

function comfy_image_settings_page() {
    if (! current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Comfy Image', 'comfy-image'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('comfy_image_settings');
            do_settings_sections('comfy_image_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Include endpoints and block registration
require_once __DIR__ . '/includes/endpoints.php';

// Enqueue block editor assets
add_action('enqueue_block_editor_assets', 'comfy_image_enqueue_editor_assets');
function comfy_image_enqueue_editor_assets() {
    $script_url = plugins_url('assets/block.js', __FILE__);

    // Register a script that relies on WordPress' editor globals
    wp_register_script(
        'comfy-image-block',
        $script_url,
        array('wp-blocks','wp-element','wp-components','wp-i18n','wp-editor','wp-data'),
        COMFY_IMAGE_VERSION
    );

    // Localize settings for the script
    $settings = array(
        'rest_base' => esc_url_raw(rest_url('comfy-image/v1')),
        'default_workflow_template' => get_option('comfy_image_default_workflow_template',''),
        'auto_save_to_media' => boolval(get_option('comfy_image_auto_save_to_media', 1)),
        'max_image_size_mb' => intval(get_option('comfy_image_max_image_size_mb', 10)),
    );
    wp_localize_script('comfy-image-block', 'ComfyImageSettings', $settings);

    wp_enqueue_script('comfy-image-block');
}

?>