<?php
/**
 * Plugin Name:       LLM Auto Redirect
 * Description:       Uses an LLM to suggest intelligent redirects for 404 errors found by the Redirection plugin.
 * Version:           1.2.0
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
        // Handle bulk processing
        add_action( 'wp_ajax_lar_bulk_process', [ $this, 'handle_bulk_process_ajax' ] );
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
        
        register_setting( 'lar_settings_group', 'lar_openai_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo'
        ]);
        
        register_setting( 'lar_settings_group', 'lar_openrouter_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting( 'lar_settings_group', 'lar_openrouter_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'meta-llama/llama-3.3-70b-instruct:free'
        ]);
        
        register_setting( 'lar_settings_group', 'lar_gemini_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting( 'lar_settings_group', 'lar_gemini_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gemini-pro'
        ]);

        register_setting( 'lar_settings_group', 'lar_ignore_patterns', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
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
            'lar_openai_model',
            'OpenAI Model',
            [ $this, 'render_openai_model_field' ],
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
            'lar_openrouter_model',
            'OpenRouter Model',
            [ $this, 'render_openrouter_model_field' ],
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
        
        add_settings_field(
            'lar_gemini_model',
            'Gemini Model',
            [ $this, 'render_gemini_model_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );

        add_settings_field(
            'lar_ignore_patterns',
            'Ignore URL Patterns',
            [ $this, 'render_ignore_patterns_field' ],
            'llm-auto-redirect',
            'lar_settings_section'
        );
    }

    public function render_provider_field() {
        $provider = get_option('lar_llm_provider', 'ollama');
        echo '<select name="lar_llm_provider">';
        echo '<option value="ollama"' . selected($provider, 'ollama', false) . '>Ollama (Local)</option>';
        echo '<option value="openai"' . selected($provider, 'openai', false) . '>OpenAI</option>';
        echo '<option value="openrouter"' . selected($provider, 'openrouter', false) . '>OpenRouter</option>';
        echo '<option value="gemini"' . selected($provider, 'gemini', false) . '>Google Gemini</option>';
        echo '</select>';
    }

    public function render_host_field() {
        $host = get_option('lar_ollama_host', 'http://192.168.1.2:11434');
        echo '<input type="text" name="lar_ollama_host" value="' . esc_attr( $host ) . '" class="regular-text">';
        echo '<p class="description">URL of your Ollama instance</p>';
    }
    
    public function render_model_field() {
        $model = get_option('lar_ollama_model', 'mistral-nemo:latest');
        echo '<input type="text" name="lar_ollama_model" value="' . esc_attr( $model ) . '" class="regular-text">';
        echo '<p class="description">Name of the Ollama model to use</p>';
    }
    
    public function render_openai_key_field() {
        $key = get_option('lar_openai_api_key', '');
        echo '<input type="password" name="lar_openai_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">OpenAI API key</p>';
    }
    
    public function render_openai_model_field() {
        $model = get_option('lar_openai_model', 'gpt-3.5-turbo');
        echo '<select name="lar_openai_model">';
        echo '<option value="gpt-3.5-turbo"' . selected($model, 'gpt-3.5-turbo', false) . '>GPT-3.5 Turbo</option>';
        echo '<option value="gpt-4"' . selected($model, 'gpt-4', false) . '>GPT-4</option>';
        echo '<option value="gpt-4-turbo"' . selected($model, 'gpt-4-turbo', false) . '>GPT-4 Turbo</option>';
        echo '</select>';
    }
    
    public function render_openrouter_key_field() {
        $key = get_option('lar_openrouter_api_key', '');
        echo '<input type="password" name="lar_openrouter_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">OpenRouter API key</p>';
    }
    
    public function render_openrouter_model_field() {
        $model = get_option('lar_openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free');
        echo '<select name="lar_openrouter_model">';
        echo '<option value="meta-llama/llama-3.3-70b-instruct:free"' . selected($model, 'meta-llama/llama-3.3-70b-instruct:free', false) . '>Llama 3.3 70B (Free)</option>';
        echo '<option value="microsoft/phi-3-mini-128k-instruct:free"' . selected($model, 'microsoft/phi-3-mini-128k-instruct:free', false) . '>Phi-3 Mini (Free)</option>';
        echo '<option value="microsoft/phi-3-medium-128k-instruct:free"' . selected($model, 'microsoft/phi-3-medium-128k-instruct:free', false) . '>Phi-3 Medium (Free)</option>';
        echo '<option value="google/gemma-2-9b-it:free"' . selected($model, 'google/gemma-2-9b-it:free', false) . '>Gemma 2 9B (Free)</option>';
        echo '</select>';
    }
    
    public function render_gemini_key_field() {
        $key = get_option('lar_gemini_api_key', '');
        echo '<input type="password" name="lar_gemini_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
        echo '<p class="description">Google Gemini API key</p>';
    }
    
    public function render_gemini_model_field() {
        $model = get_option('lar_gemini_model', 'gemini-pro');
        echo '<select name="lar_gemini_model">';
        echo '<option value="gemini-pro"' . selected($model, 'gemini-pro', false) . '>Gemini Pro</option>';
        echo '<option value="gemini-1.5-pro"' . selected($model, 'gemini-1.5-pro', false) . '>Gemini 1.5 Pro</option>';
        echo '<option value="gemini-1.5-flash"' . selected($model, 'gemini-1.5-flash', false) . '>Gemini 1.5 Flash</option>';
        echo '</select>';
    }

    public function render_ignore_patterns_field() {
        $patterns = get_option('lar_ignore_patterns', '');
        echo '<textarea name="lar_ignore_patterns" rows="5" cols="50" placeholder="wp-admin&#10;wp-login.php&#10;*.css&#10;*.js">' . esc_textarea($patterns) . '</textarea>';
        echo '<p class="description">One pattern per line. Use * for wildcards. Built-in spam filters are always active.</p>';
    }

    private function should_ignore_url($url) {
        // Built-in patterns to ignore
        $builtin_patterns = [
            'wp-admin', 'wp-login.php', 'xmlrpc.php', 'wp-config.php',
            '*.css', '*.js', '*.png', '*.jpg', '*.gif', '*.ico', '*.pdf',
            '*.php', '*.asp', '*.jsp'
        ];
        
        // User-defined patterns
        $user_patterns = array_filter(array_map('trim', explode("\n", get_option('lar_ignore_patterns', ''))));
        $all_patterns = array_merge($builtin_patterns, $user_patterns);
        
        foreach ($all_patterns as $pattern) {
            if (fnmatch($pattern, $url) || strpos($url, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Render the main admin page.
     */
    public function render_admin_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'redirection_404';
        $redirect_table = $wpdb->prefix . 'redirection_items';
        
        // Get all results first, then filter
        $all_query = "SELECT DISTINCT l.url, l.created FROM {$log_table} l LEFT JOIN {$redirect_table} r ON l.url = r.url WHERE r.id IS NULL ORDER BY l.created DESC";
        $all_results = $wpdb->get_results($all_query);
        
        // Filter out ignored URLs
        $filtered_results = array_filter($all_results, function($row) {
            return !$this->should_ignore_url($row->url);
        });
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $total_items = count($filtered_results);
        $total_pages = ceil($total_items / $per_page);
        $results = array_slice($filtered_results, $offset, $per_page);
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
            <p>Showing <?php echo count($results); ?> of <?php echo $total_items; ?> 404 entries without redirects (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)</p>
            
            <?php if ($total_items > 0): ?>
            <p>
                <button id="lar-bulk-process" class="button button-secondary">Bulk Process All <?php echo $total_items; ?> URLs</button>
                <span id="lar-bulk-status"></span>
            </p>
            <?php endif; ?>
            
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
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ]);
                    echo $page_links;
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
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
            
            // Bulk process handler
            const bulkBtn = document.getElementById('lar-bulk-process');
            if (bulkBtn) {
                bulkBtn.addEventListener('click', function() {
                    if (!confirm('Process all 404s automatically? This may take several minutes.')) return;
                    
                    this.disabled = true;
                    this.textContent = 'Processing...';
                    const status = document.getElementById('lar-bulk-status');
                    status.textContent = 'Starting bulk process...';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'lar_bulk_process',
                            nonce: '<?php echo wp_create_nonce("lar_ajax_nonce"); ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            status.textContent = `Completed! Processed ${data.data.processed} URLs, created ${data.data.created} redirects.`;
                            status.style.color = 'green';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            status.textContent = 'Error: ' + data.data;
                            status.style.color = 'red';
                            this.disabled = false;
                            this.textContent = 'Bulk Process All';
                        }
                    });
                });
            }
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

        $suggestion = $this->get_llm_suggestion($source_url);
        
        if ($suggestion) {
            wp_send_json_success(['suggestion' => $suggestion]);
        } else {
            wp_send_json_error('Failed to get suggestion from LLM.');
        }
    }

    private function get_llm_suggestion($source_url) {
        // Get provider and settings
        $provider = get_option('lar_llm_provider', 'ollama');
        
        // Prepare the prompt
        $site_url = get_site_url();
        $pages = get_pages(['parent' => 0]);
        $potential_targets = [$site_url . '/'];
        foreach ($pages as $page) {
            $potential_targets[] = get_permalink($page->ID);
        }
        $potential_targets[] = $site_url . '/news/';
        $potential_targets[] = $site_url . '/events/';
        $potential_targets = array_unique($potential_targets);
        $potential_targets_list = implode("\n", $potential_targets);

        $system_prompt = "You are a WordPress redirect assistant for {$site_url}. Analyze a 404 URL and suggest the most relevant redirect target from the available pages. Return only the URL, nothing else.";
        $user_prompt = "404 URL: {$site_url}{$source_url}\n\nAvailable pages:\n{$potential_targets_list}\n\nBest redirect target:";

        // Call the appropriate LLM provider
        switch ($provider) {
            case 'ollama':
                $response = $this->call_ollama($system_prompt, $user_prompt);
                break;
            case 'openai':
                $response = $this->call_openai($system_prompt, $user_prompt);
                break;
            case 'openrouter':
                $response = $this->call_openrouter($system_prompt, $user_prompt);
                break;
            case 'gemini':
                $response = $this->call_gemini($system_prompt, $user_prompt);
                break;
            default:
                return false;
        }

        if (is_wp_error($response)) {
            return false;
        }

        $suggested_url = trim($response);
        return filter_var($suggested_url, FILTER_VALIDATE_URL) ? $suggested_url : false;
    }

    private function call_ollama($system_prompt, $user_prompt) {
        $host = get_option('lar_ollama_host', 'http://192.168.1.2:11434');
        $model = get_option('lar_ollama_model', 'mistral-nemo:latest');
        $api_url = rtrim($host, '/') . '/api/generate';

        $response = wp_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $model,
                'prompt' => $system_prompt . "\n\n" . $user_prompt,
                'stream' => false
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ollama_error', 'Cannot connect to Ollama at ' . $host . '. Error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['response'])) {
            return $data['response'];
        }
        
        return new WP_Error('ollama_parse', 'Could not parse Ollama response');
    }

    private function call_openai($system_prompt, $user_prompt) {
        $api_key = get_option('lar_openai_api_key');
        $model = get_option('lar_openai_model', 'gpt-3.5-turbo');
        if (empty($api_key)) {
            return new WP_Error('openai_key', 'OpenAI API key not set');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'max_tokens' => 100
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('openai_error', 'OpenAI API failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return new WP_Error('openai_parse', 'Could not parse OpenAI response');
    }

    private function call_openrouter($system_prompt, $user_prompt) {
        $api_key = get_option('lar_openrouter_api_key');
        $model = get_option('lar_openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free');
        if (empty($api_key)) {
            return new WP_Error('openrouter_key', 'OpenRouter API key not set');
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => 'LLM Auto Redirect'
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt]
                ]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('openrouter_error', 'OpenRouter API failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Log response for debugging
        error_log('OpenRouter Response Code: ' . $response_code);
        error_log('OpenRouter Response Body: ' . $body);
        
        if ($response_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'HTTP ' . $response_code;
            return new WP_Error('openrouter_http', 'OpenRouter API error: ' . $error_msg);
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return new WP_Error('openrouter_parse', 'Could not parse OpenRouter response. Response: ' . substr($body, 0, 200));
    }

    private function call_gemini($system_prompt, $user_prompt) {
        $api_key = get_option('lar_gemini_api_key');
        $model = get_option('lar_gemini_model', 'gemini-pro');
        if (empty($api_key)) {
            return new WP_Error('gemini_key', 'Gemini API key not set');
        }

        $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [[
                    'parts' => [['text' => $user_prompt]]
                ]],
                'systemInstruction' => [
                    'parts' => [['text' => $system_prompt]]
                ]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('gemini_error', 'Gemini API failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new WP_Error('gemini_parse', 'Could not parse Gemini response');
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

    public function handle_bulk_process_ajax() {
        check_ajax_referer('lar_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.', 403);
        }
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'redirection_404';
        $redirect_table = $wpdb->prefix . 'redirection_items';
        
        // Get all 404s without redirects
        $all_query = "SELECT DISTINCT l.url FROM {$log_table} l LEFT JOIN {$redirect_table} r ON l.url = r.url WHERE r.id IS NULL";
        $all_results = $wpdb->get_results($all_query);
        
        // Filter out ignored URLs
        $urls_to_process = array_filter($all_results, function($row) {
            return !$this->should_ignore_url($row->url);
        });
        
        $processed = 0;
        $created = 0;
        
        foreach ($urls_to_process as $row) {
            $suggestion = $this->get_llm_suggestion($row->url);
            if ($suggestion && $suggestion !== home_url('/')) {
                // Create redirect
                if (class_exists('Red_Item')) {
                    $result = Red_Item::create([
                        'url' => $row->url,
                        'action_data' => ['url' => $suggestion],
                        'match_type' => 'url',
                        'action_type' => 'url',
                        'action_code' => 301,
                        'group_id' => 1,
                    ]);
                    
                    if ($result && !is_wp_error($result)) {
                        $wpdb->delete("{$wpdb->prefix}redirection_404", ['url' => $row->url]);
                        $created++;
                    }
                }
            }
            $processed++;
        }
        
        wp_send_json_success(['processed' => $processed, 'created' => $created]);
    }
}

// Initialize the plugin
new LLM_Auto_Redirect();
