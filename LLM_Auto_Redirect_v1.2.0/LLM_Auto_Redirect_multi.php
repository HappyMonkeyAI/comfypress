<?php
/**
 * Plugin Name:       LLM Auto Redirect
 * Description:       Uses an LLM to suggest intelligent redirects for 404 errors found by the Redirection plugin.
 * Version:           1.0.0
 * Author:            Gemini
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       llm-auto-redirect
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Main class for the plugin
class LLM_Auto_Redirect {

    public function __construct() {
        // Add the admin menu page
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        // Register plugin settings
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        // Handle the AJAX request for getting a suggestion
        add_action( 'wp_ajax_lar_get_llm_suggestion', [ $this, 'handle_llm_suggestion_ajax' ] );
        // Handle the AJAX request for creating a redirect
        add_action( 'wp_ajax_lar_create_redirect', [ $this, 'handle_create_redirect_ajax' ] );
    }

    /**
     * Add the admin page to the Tools menu.
     */
    public function add_admin_menu() {
        add_management_page(
            'LLM Auto Redirect',
            'LLM Auto Redirect',
            'manage_options',
            'llm-auto-redirect',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Register the settings fields for the admin page.
     */
    public function register_settings() {
        register_setting( 'lar_settings_group', 'lar_llm_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'ollama'
        ]);
        
        register_setting( 'lar_settings_group', 'lar_ollama_host', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'http://192.168.1.2:11434'
        ]);
        
        register_setting( 'lar_settings_group', 'lar_ollama_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'mistral-nemo:latest'
        ]);
        
        register_setting( 'lar_settings_group', 'lar_openai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting( 'lar_settings_group', 'lar_openrouter_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting( 'lar_settings_group', 'lar_gemini_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);

        add_settings_section(
            'lar_settings_section',
            'LLM Provider Settings',
            null,
            'llm-auto-redirect'
        );

        add_settings_field(
            'lar_llm_provider',
            'LLM Provider',
            [ $this, 'render_provider_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );

        add_settings_field(
            'lar_ollama_host',
            'Ollama Host URL',
            [ $this, 'render_host_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
        
        add_settings_field(
            'lar_ollama_model',
            'Ollama Model Name',
            [ $this, 'render_model_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
        
        add_settings_field(
            'lar_openai_api_key',
            'OpenAI API Key',
            [ $this, 'render_openai_key_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
        
        add_settings_field(
            'lar_openrouter_api_key',
            'OpenRouter API Key',
            [ $this, 'render_openrouter_key_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
        
        add_settings_field(
            'lar_gemini_api_key',
            'Gemini API Key',
            [ $this, 'render_gemini_key_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
    }

    /**
     * Render the HTML for the provider selection field.
     */
    public function render_provider_field() {
        $provider = get_option('lar_llm_provider', 'ollama');
        echo '<select name="lar_llm_provider" id="lar_llm_provider">';
        echo '<option value="ollama"' . selected($provider, 'ollama', false) . '>Ollama (Local)</option>';
        echo '<option value="openai"' . selected($provider, 'openai', false) . '>OpenAI</option>';
        echo '<option value="openrouter"' . selected($provider, 'openrouter', false) . '>OpenRouter</option>';
        echo '<option value="gemini"' . selected($provider, 'gemini', false) . '>Google Gemini</option>';
        echo '</select>';
    }

    /**
     * Render the HTML for the host input field.
     */
    public function render_host_field() {
        $host = get_option('lar_ollama_host', 'http://192.168.1.2:11434');
        echo '<input type="text" name="lar_ollama_host" value="' . esc_attr( $host ) . '" class="regular-text">';
        echo '<p class="description">URL of your Ollama instance (only for Ollama provider)</p>';
    }
    
    /**
     * Render the HTML for the model input field.
     */
    public function render_model_field() {
        $model = get_option('lar_ollama_model', 'mistral-nemo:latest');
        echo '<input type="text" name="lar_ollama_model" value="' . esc_attr( $model ) . '" class="regular-text">';
        echo '<p class="description">Name of the Ollama model to use (only for Ollama provider)</p>';
    }
    
    /**
     * Render the HTML for the OpenAI API key field.
     */
    public function render_openai_key_field() {
        $key = get_option('lar_openai_api_key', '');
        echo '<input type="password" name="lar_openai_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">OpenAI API key (only for OpenAI provider)</p>';
    }
    
    /**
     * Render the HTML for the OpenRouter API key field.
     */
    public function render_openrouter_key_field() {
        $key = get_option('lar_openrouter_api_key', '');
        echo '<input type="password" name="lar_openrouter_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">OpenRouter API key (only for OpenRouter provider)</p>';
    }
    
    /**
     * Render the HTML for the Gemini API key field.
     */
    public function render_gemini_key_field() {
        $key = get_option('lar_gemini_api_key', '');
        echo '<input type="password" name="lar_gemini_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">Google Gemini API key (only for Gemini provider)</p>';
    }
    
    /**
     * Render the main admin page.
     */
    public function render_admin_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'redirection_404';
        $redirect_table = $wpdb->prefix . 'redirection_items';
        
        $query = "SELECT DISTINCT l.url, l.created FROM {$log_table} l LEFT JOIN {$redirect_table} r ON l.url = r.url WHERE r.id IS NULL ORDER BY l.created DESC LIMIT 20";
        $results = $wpdb->get_results($query);
        ?>
        <div class="wrap">
            <h1>LLM Auto Redirect</h1>
            
            <!-- Settings Form -->
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'lar_settings_group' );
                    do_settings_sections( 'llm-auto-redirect' );
                    submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>Recent 404 Errors</h2>
            <p>Found <?php echo count($results); ?> recent 404 entries without redirects</p>
            
            <?php if ($results): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>404 URL</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><code><?php echo esc_html($row->url); ?></code></td>
                        <td><?php echo esc_html($row->created); ?></td>
                        <td>
                            <button class="button lar-suggest-btn" data-source-url="<?php echo esc_attr($row->url); ?>">Suggest</button>
                            <input type="text" class="lar-target-input" placeholder="Suggestion will appear here" style="width:300px;">
                            <button class="button button-primary lar-create-btn" disabled>Create</button>
                            <span class="lar-status"></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.lar-suggest-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const sourceUrl = this.dataset.sourceUrl;
                    const targetInput = this.parentNode.querySelector('.lar-target-input');
                    const createBtn = this.parentNode.querySelector('.lar-create-btn');
                    const status = this.parentNode.querySelector('.lar-status');
                    
                    this.disabled = true;
                    this.textContent = 'Loading...';
                    status.textContent = 'Asking LLM...';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'lar_get_llm_suggestion',
                            nonce: '<?php echo wp_create_nonce("lar_ajax_nonce"); ?>',
                            source_url: sourceUrl
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            targetInput.value = data.data.suggestion;
                            createBtn.disabled = false;
                            status.textContent = 'Suggestion received!';
                            status.style.color = 'green';
                        } else {
                            status.textContent = 'Error: ' + data.data;
                            status.style.color = 'red';
                        }
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.textContent = 'Suggest';
                    });
                });
            });
            
            document.querySelectorAll('.lar-create-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const sourceUrl = row.querySelector('.lar-suggest-btn').dataset.sourceUrl;
                    const targetUrl = row.querySelector('.lar-target-input').value;
                    const status = row.querySelector('.lar-status');
                    
                    if (!targetUrl) {
                        status.textContent = 'Please enter a target URL';
                        status.style.color = 'red';
                        return;
                    }
                    
                    this.disabled = true;
                    status.textContent = 'Creating redirect...';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'lar_create_redirect',
                            nonce: '<?php echo wp_create_nonce("lar_ajax_nonce"); ?>',
                            source_url: sourceUrl,
                            target_url: targetUrl
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            status.textContent = 'Redirect created!';
                            status.style.color = 'green';
                            setTimeout(() => row.remove(), 2000);
                        } else {
                            status.textContent = 'Error: ' + data.data;
                            status.style.color = 'red';
                            this.disabled = false;
                        }
                    });
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle the AJAX request to get a redirect suggestion from the LLM.
     */
    public function handle_llm_suggestion_ajax() {
        check_ajax_referer('lar_ajax_nonce', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }
        
        $source_url = isset($_POST['source_url']) ? sanitize_text_field(wp_unslash($_POST['source_url'])) : '';
        if ( empty($source_url) ) {
            wp_send_json_error( 'Source URL is missing.', 400 );
        }

        // --- Prepare the prompt for the LLM ---
        $site_url = get_site_url();
        // Get some top-level pages to help the LLM make a good choice
        $pages = get_pages(['parent' => 0]);
        $potential_targets = [
            $site_url . '/',
            get_permalink(get_option('page_for_posts')), // News/Blog page
        ];
        foreach ($pages as $page) {
            $potential_targets[] = get_permalink($page->ID);
        }
        // Add other key URLs
        $potential_targets[] = $site_url . '/news/';
        $potential_targets[] = $site_url . '/events/';

        $potential_targets = array_unique($potential_targets);
        $potential_targets_list = implode("\n", $potential_targets);

        $system_prompt = "You are an intelligent WordPress redirect assistant for the website {$site_url}. Your job is to analyze a 404 URL and a list of available pages on the website. Your goal is to find the most relevant page to redirect the 404 URL to. Prioritize top-level category pages like 'News' or 'Events' if the URL seems related to them. If no relevant page is found, you MUST return only the website's homepage URL. Your output must be a single, valid URL and nothing else. Do not add any explanation or formatting.";
        
        $user_prompt = "Analyze this 404 URL: {$site_url}{$source_url}\n\nHere is a list of potential target pages:\n{$potential_targets_list}\n\nWhat is the best redirect target? Return only the URL.";

        // --- Call Ollama API ---
        $ollama_host = get_option('lar_ollama_host', 'http://192.168.1.2:11434');
        $ollama_model = get_option('lar_ollama_model', 'mistral-nemo:latest');
        $api_url = rtrim($ollama_host, '/') . '/api/generate';

        $response = wp_remote_post($api_url, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'model' => $ollama_model,
                'prompt' => $system_prompt . "\n\n" . $user_prompt,
                'stream' => false
            ]),
            'timeout' => 30,
        ]);
        
        if ( is_wp_error($response) ) {
            $error_msg = $response->get_error_message();
            error_log('Ollama connection error: ' . $error_msg);
            wp_send_json_error( 'Cannot connect to Ollama at 192.168.5.157:11434. Error: ' . $error_msg, 500 );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Ollama HTTP error: ' . $response_code);
            wp_send_json_error( 'Ollama returned HTTP ' . $response_code, 500 );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ( isset($data['response']) ) {
            $suggested_url = trim($data['response']);
            // Basic URL validation
            if (filter_var($suggested_url, FILTER_VALIDATE_URL)) {
                 wp_send_json_success( ['suggestion' => $suggested_url] );
            } else {
                 wp_send_json_error( 'LLM returned an invalid URL format.', 500 );
            }
        } else {
            // Log the full error for debugging
            error_log('LLM Auto Redirect Error: ' . print_r($data, true));
            wp_send_json_error( 'Could not parse Ollama response.', 500 );
        }
    }

    /**
     * Handle the AJAX request to create the redirect in the Redirection plugin.
     */
    public function handle_create_redirect_ajax() {
        check_ajax_referer('lar_ajax_nonce', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }
        
        // Make sure the function from the Redirection plugin exists
        if ( ! class_exists( 'Red_Item' ) ) {
            wp_send_json_error( 'The Redirection plugin classes are not available.', 500 );
        }

        $source_url = isset($_POST['source_url']) ? sanitize_text_field(wp_unslash($_POST['source_url'])) : '';
        $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';

        if ( empty($source_url) || empty($target_url) ) {
            wp_send_json_error( 'Source or Target URL is missing.', 400 );
        }
        
        // Use the Redirection plugin's API to create the redirect
        $result = Red_Item::create( [
            'url'           => $source_url,
            'action_data'   => ['url' => $target_url],
            'match_type'    => 'url',
            'action_type'   => 'url',
            'action_code'   => 301,
            'group_id'      => 1,
        ] );

        if ($result && !is_wp_error($result)) {
            // Also, let's delete the 404 log entry for this URL
            global $wpdb;
            $wpdb->delete( "{$wpdb->prefix}redirection_404", [ 'url' => $source_url ] );
            wp_send_json_success( ['message' => 'Redirect created successfully!'] );
        } else {
            $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Unknown error';
            wp_send_json_error( 'Failed to create redirect: ' . $error_msg, 500 );
        }
    }
}

// Initialize the plugin
new LLM_Auto_Redirect();
