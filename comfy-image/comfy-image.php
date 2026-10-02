<?php
/**
 * Plugin Name: Comfy Image — ComfyUI Integration
 * Description: Generate images inside Gutenberg using a remote ComfyUI instance.
 * Version:     0.1.3
 * Author:      HappyMonkey AI
 * Author URI:  https://happymonkey.ai/
 * License:     GPL-2.0+
 * Text Domain: comfy-image
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (! defined('COMFY_IMAGE_VERSION')) {
    define('COMFY_IMAGE_VERSION', '0.1.3');
}

// Activation: set default options if not present
function comfy_image_activate() {
    $defaults = array(
        'comfy_base_url' => 'http://127.0.0.1:8188',
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
    register_setting('comfy_image_settings', 'comfy_image_comfy_base_url', array('sanitize_callback' => 'comfy_image_validate_base_url'));
    register_setting('comfy_image_settings', 'comfy_image_gateway_api_token', array('sanitize_callback' => 'comfy_image_sanitize_gateway_api_token'));
    register_setting('comfy_image_settings', 'comfy_image_default_workflow_template', array('sanitize_callback' => 'comfy_image_sanitize_workflow_template'));
    register_setting('comfy_image_settings', 'comfy_image_auto_save_to_media', array('sanitize_callback' => 'absint'));
    register_setting('comfy_image_settings', 'comfy_image_max_image_size_mb', array('sanitize_callback' => 'absint'));
    register_setting('comfy_image_settings', 'comfy_image_allowed_roles');

    add_settings_section('comfy_image_main', __('Comfy Image Settings', 'comfy-image'), function() {
        echo '<p>' . esc_html__('Configure ComfyUI integration and media handling.', 'comfy-image') . '</p>';
    }, 'comfy_image_settings');

    add_settings_field('comfy_base_url', __('ComfyUI Base URL', 'comfy-image'), 'comfy_image_base_url_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('gateway_api_token', __('Gateway API token', 'comfy-image'), 'comfy_image_gateway_api_token_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('default_workflow_template', __('Default workflow template', 'comfy-image'), 'comfy_image_default_workflow_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('auto_save_to_media', __('Auto save to WP Media', 'comfy-image'), 'comfy_image_auto_save_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('max_image_size_mb', __('Max image size (MB)', 'comfy-image'), 'comfy_image_max_size_field', 'comfy_image_settings', 'comfy_image_main');
    add_settings_field('allowed_roles', __('Allowed roles', 'comfy-image'), 'comfy_image_allowed_roles_field', 'comfy_image_settings', 'comfy_image_main');
}

function comfy_image_sanitize_workflow_template($value) {
    if (! is_string($value) || trim($value) === '') {
        return '';
    }

    $workflow = json_decode($value, true);
    if (! is_array($workflow) || empty($workflow)) {
        add_settings_error('comfy_image_default_workflow_template', 'invalid_workflow_template', __('Enter a non-empty ComfyUI API workflow JSON object.', 'comfy-image'));
        return get_option('comfy_image_default_workflow_template', '');
    }

    if (strpos($value, '{{prompt}}') === false) {
        add_settings_error('comfy_image_default_workflow_template', 'missing_prompt_placeholder', __('The workflow must contain the {{prompt}} placeholder.', 'comfy-image'));
        return get_option('comfy_image_default_workflow_template', '');
    }

    return wp_json_encode($workflow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function comfy_image_base_url_field() {
    $v = esc_attr(get_option('comfy_image_comfy_base_url', ''));
    echo "<input type='text' name='comfy_image_comfy_base_url' value='" . $v . "' size='60' />";
}

function comfy_image_gateway_api_token_field() {
    if (defined('COMFY_IMAGE_GATEWAY_API_TOKEN')) {
        echo '<p class="description">' . esc_html__('A token is configured in wp-config.php and takes precedence over this setting.', 'comfy-image') . '</p>';
        return;
    }

    $configured = comfy_image_get_gateway_api_token() !== '';
    echo "<input type='password' name='comfy_image_gateway_api_token' value='' autocomplete='new-password' spellcheck='false' class='regular-text' />";
    echo '<p class="description">' . esc_html($configured ? __('A token is saved. Leave blank to keep it, or enter a replacement token.', 'comfy-image') : __('Paste the ComfyPress Gateway token. It is sent only from the WordPress server.', 'comfy-image')) . '</p>';
    echo '<label><input type="checkbox" name="comfy_image_clear_gateway_api_token" value="1" /> ' . esc_html__('Remove the saved token', 'comfy-image') . '</label>';
}

function comfy_image_default_workflow_field() {
    $v = esc_textarea(get_option('comfy_image_default_workflow_template', ''));
    $examples = array(
        'flux2-klein-4b' => array(
            'label' => __('FLUX.2 Klein 4B', 'comfy-image'),
            'file' => 'flux2-klein-4b.api.json',
            'requirements' => __('Requires flux-2-klein-base-4b.safetensors, qwen_3_4b.safetensors, and flux2-vae.safetensors.', 'comfy-image'),
        ),
        'z-image-turbo' => array(
            'label' => __('Z-Image Turbo', 'comfy-image'),
            'file' => 'z-image-turbo.api.json',
            'requirements' => __('Requires z_image_turbo_bf16.safetensors, qwen_3_4b.safetensors, and ae.safetensors.', 'comfy-image'),
        ),
        'kandinsky5-lite' => array(
            'label' => __('Kandinsky 5 Lite', 'comfy-image'),
            'file' => 'kandinsky5-lite.api.json',
            'requirements' => __('Requires kandinsky5lite_t2i.safetensors, qwen_2.5_vl_7b_fp8_scaled.safetensors, clip_l.safetensors, and ae.safetensors.', 'comfy-image'),
        ),
    );

    echo '<label for="comfy_image_workflow_example">' . esc_html__('Start from an example workflow:', 'comfy-image') . ' </label>';
    echo '<select id="comfy_image_workflow_example">';
    echo '<option value="">' . esc_html__('Choose an example…', 'comfy-image') . '</option>';
    foreach ($examples as $slug => $example) {
        $example_path = __DIR__ . '/examples/' . $example['file'];
        if (! is_readable($example_path)) {
            continue;
        }
        $example_json = file_get_contents($example_path);
        if ($example_json === false) {
            continue;
        }
        echo '<option value="' . esc_attr($slug) . '" data-template="' . esc_attr($example_json) . '" data-requirements="' . esc_attr($example['requirements']) . '">' . esc_html($example['label']) . '</option>';
    }
    echo '</select>';
    echo '<p id="comfy_image_example_requirements" class="description">' . esc_html__('Selecting an example replaces the workflow below. Its listed model files and nodes must be installed on your ComfyUI server.', 'comfy-image') . '</p>';
    echo "<textarea id='comfy_image_default_workflow_template' name='comfy_image_default_workflow_template' rows='12' style='width:100%;max-width:900px;resize:both;box-sizing:border-box;'>" . $v . "</textarea>";
    echo '<p class="description">' . esc_html__('Paste a ComfyUI API-format workflow JSON object and use {{prompt}} where the generated prompt should go.', 'comfy-image') . '</p>';
    echo '<script>(function(){var select=document.getElementById("comfy_image_workflow_example");var textarea=document.getElementById("comfy_image_default_workflow_template");var requirements=document.getElementById("comfy_image_example_requirements");if(select&&textarea){select.addEventListener("change",function(){var option=this.options[this.selectedIndex];if(option&&option.dataset.template){try{textarea.value=JSON.stringify(JSON.parse(option.dataset.template),null,2);textarea.dispatchEvent(new Event("input",{bubbles:true}));}catch(error){textarea.value=option.dataset.template;}}if(requirements&&option){requirements.textContent=option.dataset.requirements||"' . esc_js(__('Selecting an example replaces the workflow below. Its listed model files and nodes must be installed on your ComfyUI server.', 'comfy-image')) . '";}});}})();</script>';
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
    echo "<input type='hidden' name='comfy_image_allowed_roles[]' value='' />";
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
        <p><?php echo esc_html(sprintf(__('Version %s', 'comfy-image'), COMFY_IMAGE_VERSION)); ?> · <a href="<?php echo esc_url(plugins_url('readme.txt', __FILE__)); ?>"><?php esc_html_e('Setup guide', 'comfy-image'); ?></a> · <a href="https://happymonkey.ai/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('HappyMonkey AI', 'comfy-image'); ?></a> · <a href="https://docs.comfy.org/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('ComfyUI documentation', 'comfy-image'); ?></a></p>
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
        'rest_base' => untrailingslashit(rest_url('comfy-image/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'default_workflow_template' => get_option('comfy_image_default_workflow_template',''),
        'auto_save_to_media' => boolval(get_option('comfy_image_auto_save_to_media', 1)),
        'max_image_size_mb' => intval(get_option('comfy_image_max_image_size_mb', 10)),
    );
    wp_localize_script('comfy-image-block', 'ComfyImageSettings', $settings);

    wp_enqueue_script('comfy-image-block');
}

?>