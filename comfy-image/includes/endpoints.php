<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Helper to get configured ComfyUI base URL (no trailing slash)
 */
function comfy_image_get_base_url() {
    return comfy_image_validate_base_url(get_option('comfy_image_comfy_base_url', ''));
}

function comfy_image_get_gateway_api_token() {
    $token = defined('COMFY_IMAGE_GATEWAY_API_TOKEN')
        ? constant('COMFY_IMAGE_GATEWAY_API_TOKEN')
        : get_option('comfy_image_gateway_api_token', '');

    return is_string($token) ? trim($token) : '';
}

function comfy_image_get_gateway_auth_headers($headers = array()) {
    if (! is_array($headers)) {
        $headers = array();
    }

    $token = comfy_image_get_gateway_api_token();
    if ($token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }

    return $headers;
}

function comfy_image_sanitize_gateway_api_token($value) {
    $existing = get_option('comfy_image_gateway_api_token', '');
    if (defined('COMFY_IMAGE_GATEWAY_API_TOKEN')) {
        return is_string($existing) ? $existing : '';
    }

    if (isset($_POST['comfy_image_clear_gateway_api_token']) && (string) $_POST['comfy_image_clear_gateway_api_token'] === '1') {
        return '';
    }

    if (! is_string($value)) {
        return is_string($existing) ? $existing : '';
    }

    $value = trim($value);
    if ($value === '') {
        return is_string($existing) ? $existing : '';
    }

    if (! preg_match('/\\Acpwg_[A-Za-z0-9_-]{20,160}\\z/', $value)) {
        add_settings_error('comfy_image_gateway_api_token', 'invalid_gateway_token', __('Enter a valid ComfyPress Gateway token or leave the field blank to keep the saved token.', 'comfy-image'));
        return is_string($existing) ? $existing : '';
    }

    return $value;
}

/**
 * Accept HTTP(S) ComfyUI origins, including private/LAN hosts, without embedded credentials.
 */
function comfy_image_validate_base_url($url) {
    if (! is_string($url) || trim($url) === '') {
        return '';
    }

    $url = trim($url);
    $parts = parse_url($url);
    if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    if (! in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return '';
    }

    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }

    if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
        return '';
    }

    if (preg_match('/[\s\\\\]/', $parts['host'])) {
        return '';
    }

    $url = esc_url_raw($url, array('http', 'https'));
    return $url ? rtrim($url, '/') : '';
}

/**
 * Replace explicit template markers, leaving unrelated positive/negative prompt text untouched.
 */
function comfy_image_replace_prompt_placeholder($value, $prompt, &$replaced) {
    if (is_string($value)) {
        if (strpos($value, '{{prompt}}') !== false) {
            $replaced = true;
            return str_replace('{{prompt}}', $prompt, $value);
        }
        return $value;
    }

    if (is_array($value)) {
        foreach ($value as $key => $child) {
            $value[$key] = comfy_image_replace_prompt_placeholder($child, $prompt, $replaced);
        }
        return $value;
    }

    if (is_object($value)) {
        foreach ($value as $key => $child) {
            $value->$key = comfy_image_replace_prompt_placeholder($child, $prompt, $replaced);
        }
    }

    return $value;
}

function comfy_image_compare_unsigned_decimal_strings($value, $maximum) {
    $value = ltrim((string) $value, '0');
    $maximum = ltrim((string) $maximum, '0');
    $value = $value === '' ? '0' : $value;
    $maximum = $maximum === '' ? '0' : $maximum;

    if (strlen($value) !== strlen($maximum)) {
        return strlen($value) < strlen($maximum) ? -1 : 1;
    }

    return strcmp($value, $maximum);
}

/**
 * Validate and normalize an override to a safe JSON numeric literal.
 */
function comfy_image_parse_numeric_override($name, $value) {
    if ($name === 'seed' || $name === 'steps') {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $digits = trim((string) $value);
        if (! preg_match('/\\A[0-9]+\\z/', $digits)) {
            return false;
        }

        $normalized = ltrim($digits, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = $name === 'seed' ? '18446744073709551615' : '4096';
        if (comfy_image_compare_unsigned_decimal_strings($normalized, $maximum) > 0) {
            return false;
        }
        if ($name === 'steps' && $normalized === '0') {
            return false;
        }

        return $normalized;
    }

    if ($name === 'cfg') {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return false;
        }

        $raw = is_float($value) ? json_encode($value) : trim((string) $value);
        if (! is_string($raw) || ! preg_match('/\\A(?:[0-9]+(?:\\.[0-9]{1,2})?|\\.[0-9]{1,2})\\z/', $raw)) {
            return false;
        }

        $number = (float) $raw;
        if (! is_finite($number) || $number < 0 || $number > 100) {
            return false;
        }

        return json_encode($number);
    }

    return false;
}

/**
 * Replace exact numeric markers so ComfyUI receives JSON numbers, never strings.
 */
function comfy_image_replace_exact_placeholder($value, $marker, $replacement, &$replaced) {
    if (is_string($value)) {
        if ($value === $marker) {
            $replaced = true;
            return $replacement;
        }
        return $value;
    }

    if (is_array($value)) {
        foreach ($value as $key => $child) {
            $value[$key] = comfy_image_replace_exact_placeholder($child, $marker, $replacement, $replaced);
        }
        return $value;
    }

    if (is_object($value)) {
        foreach ($value as $key => $child) {
            $value->$key = comfy_image_replace_exact_placeholder($child, $marker, $replacement, $replaced);
        }
    }

    return $value;
}

function comfy_image_contains_exact_placeholder($value, $marker) {
    if (is_string($value)) {
        return $value === $marker;
    }
    if (! is_array($value) && ! is_object($value)) {
        return false;
    }

    foreach ((array) $value as $child) {
        if (comfy_image_contains_exact_placeholder($child, $marker)) {
            return true;
        }
    }

    return false;
}

function comfy_image_contains_template_marker($value, $marker) {
    if (is_string($value)) {
        return strpos($value, $marker) !== false;
    }
    if (! is_array($value) && ! is_object($value)) {
        return false;
    }

    foreach ((array) $value as $child) {
        if (comfy_image_contains_template_marker($child, $marker)) {
            return true;
        }
    }

    return false;
}

/**
 * Apply the configured role allow-list in addition to the edit_posts capability gate.
 * Administrators with edit_posts retain access so the configured feature remains manageable.
 */
function comfy_image_user_can_generate() {
    if (! current_user_can('edit_posts')) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    $user = wp_get_current_user();
    $user_roles = is_object($user) && isset($user->roles) && is_array($user->roles) ? $user->roles : array();
    $allowed_roles = get_option('comfy_image_allowed_roles', array('editor', 'author'));
    if (! is_array($allowed_roles)) {
        return false;
    }

    foreach ($user_roles as $role) {
        if (is_string($role) && in_array($role, $allowed_roles, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Allow at most ten workflow submissions per authenticated user in a fixed 60-second window.
 * A database advisory lock serializes the transient read/modify/write across PHP workers.
 */
function comfy_image_consume_generation_limit($user_id) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return false;
    }

    global $wpdb;
    if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_var')) {
        return false;
    }

    $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    $database = isset($wpdb->dbname) ? (string) $wpdb->dbname : '';
    $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
    $lock_context = $database . ':' . $prefix . ':' . $blog_id . ':' . $user_id;
    $lock_name = 'comfy_' . substr(hash('sha256', $lock_context), 0, 48);
    $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 1));
    if ((string) $lock_acquired !== '1') {
        return false;
    }

    try {
        $key = 'comfy_image_generation_' . $user_id;
        $counter = get_transient($key);
        $now = time();

        if (is_array($counter) && isset($counter['window_started_at'], $counter['count']) && is_numeric($counter['window_started_at']) && is_numeric($counter['count'])) {
            $window_started_at = (int) $counter['window_started_at'];
            $count = (int) $counter['count'];
        } elseif (is_numeric($counter)) {
            // Preserve an older scalar counter during the one-time rollout to this window shape.
            $window_started_at = $now;
            $count = (int) $counter;
        } else {
            $window_started_at = $now;
            $count = 0;
        }

        if ($window_started_at < 1 || $window_started_at > $now || $now >= $window_started_at + 60) {
            $window_started_at = $now;
            $count = 0;
        }

        if ($count >= 10) {
            return false;
        }

        $next_state = array(
            'window_started_at' => $window_started_at,
            'count' => $count + 1,
        );
        $expires_in = max(1, $window_started_at + 60 - $now);
        return set_transient($key, $next_state, $expires_in) !== false;
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

add_action('rest_api_init', function () {
    register_rest_route('comfy-image/v1', '/submit-workflow', array(
        'methods' => 'POST',
        'callback' => 'comfy_image_submit_workflow',
        'permission_callback' => function () {
            return comfy_image_user_can_generate();
        }
    ));

    register_rest_route('comfy-image/v1', '/check-status/(?P<prompt_id>[A-Za-z0-9_-]{1,255})', array(
        'methods' => 'GET',
        'callback' => 'comfy_image_check_status',
        'permission_callback' => function () {
            return comfy_image_user_can_generate();
        }
    ));

    register_rest_route('comfy-image/v1', '/fetch-image', array(
        'methods' => 'GET',
        'callback' => 'comfy_image_fetch_image',
        'permission_callback' => function () {
            return comfy_image_user_can_generate();
        }
    ));

    // Upload endpoint (stub) - file uploads are supported later
    register_rest_route('comfy-image/v1', '/upload-image', array(
        'methods' => 'POST',
        'callback' => 'comfy_image_upload_image',
        'permission_callback' => function () {
            return comfy_image_user_can_generate();
        }
    ));
});

/**
 * Submit a workflow JSON to ComfyUI /prompt.
 * Expects: { workflow: {...}, prompt: "...", seed?: "...", steps?: "...", cfg?: "..." }.
 * Numeric overrides require exact-value workflow markers and are validated before any ComfyUI request.
 */
function comfy_image_submit_workflow( WP_REST_Request $request ) {
    $params = $request->get_json_params();

    if (empty($params) || ! is_array($params)) {
        return new WP_REST_Response(array('error' => 'Invalid JSON body'), 400);
    }

    if (! isset($params['workflow']) || (! is_array($params['workflow']) && ! is_object($params['workflow'])) || empty((array) $params['workflow'])) {
        return new WP_REST_Response(array('error' => 'Missing workflow'), 400);
    }

    $prompt = isset($params['prompt']) && is_string($params['prompt']) ? sanitize_text_field($params['prompt']) : '';
    if ($prompt === '') {
        return new WP_REST_Response(array('error' => 'A prompt is required'), 400);
    }

    $workflow = $params['workflow'];

    $numeric_tokens = array();
    foreach (array('seed', 'steps', 'cfg') as $name) {
        $marker = '{{' . $name . '}}';
        $has_override = array_key_exists($name, $params)
            && $params[$name] !== null
            && (! is_string($params[$name]) || trim($params[$name]) !== '');

        if (! $has_override) {
            if (comfy_image_contains_template_marker($workflow, $marker)) {
                return new WP_REST_Response(array('error' => 'A value is required for the ' . $name . ' workflow marker'), 400);
            }
            continue;
        }

        $numeric_value = comfy_image_parse_numeric_override($name, $params[$name]);
        if ($numeric_value === false) {
            return new WP_REST_Response(array('error' => 'Invalid ' . $name . ' override'), 400);
        }

        if (! comfy_image_contains_exact_placeholder($workflow, $marker)) {
            return new WP_REST_Response(array('error' => 'Workflow template must contain an exact ' . $marker . ' marker for this override'), 400);
        }

        $workflow_source = wp_json_encode(array('workflow' => $workflow, 'prompt' => $prompt));
        if ($workflow_source === false) {
            return new WP_REST_Response(array('error' => 'Failed to encode workflow'), 500);
        }

        try {
            do {
                $token = '__COMFY_IMAGE_NUM_' . bin2hex(random_bytes(16)) . '__';
            } while (strpos($workflow_source, $token) !== false);
        } catch (Throwable $error) {
            return new WP_REST_Response(array('error' => 'Unable to prepare numeric workflow values'), 500);
        }

        $marker_replaced = false;
        $workflow = comfy_image_replace_exact_placeholder($workflow, $marker, $token, $marker_replaced);
        if (! $marker_replaced) {
            return new WP_REST_Response(array('error' => 'Unable to apply the ' . $name . ' override'), 500);
        }
        $numeric_tokens[] = array('token' => $token, 'value' => $numeric_value);
    }

    $prompt_replaced = false;
    $workflow = comfy_image_replace_prompt_placeholder($workflow, $prompt, $prompt_replaced);
    if (! $prompt_replaced) {
        return new WP_REST_Response(array('error' => 'Workflow template must contain a {{prompt}} placeholder'), 400);
    }

    // Wrap workflow into the ComfyUI expected envelope: { prompt: <workflow> }
    $body = wp_json_encode(array('prompt' => $workflow));
    if ($body === false) {
        return new WP_REST_Response(array('error' => 'Failed to encode workflow'), 500);
    }

    foreach ($numeric_tokens as $numeric_token) {
        $encoded_token = wp_json_encode($numeric_token['token']);
        $replacement_count = 0;
        $body = str_replace($encoded_token, $numeric_token['value'], $body, $replacement_count);
        if ($replacement_count < 1) {
            return new WP_REST_Response(array('error' => 'Unable to encode numeric workflow value'), 500);
        }
    }

    $base = comfy_image_get_base_url();
    if (empty($base)) {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL is missing or invalid'), 500);
    }

    if (! comfy_image_consume_generation_limit(get_current_user_id())) {
        return new WP_REST_Response(array('error' => 'Generation limit reached. Try again in a minute.'), 429);
    }

    $endpoint = untrailingslashit($base) . '/prompt';

    $response = wp_remote_post($endpoint, array(
        'headers' => comfy_image_get_gateway_auth_headers(array('Content-Type' => 'application/json')),
        'body' => $body,
        'timeout' => 30,
        'redirection' => 0,
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('error' => 'Unable to contact ComfyUI'), 502);
    }

    $code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);
    $decoded = json_decode($resp_body, true);

    if ($code < 200 || $code >= 300) {
        $status = ($code >= 400 && $code < 500) ? $code : 502;
        return new WP_REST_Response(array(
            'error' => 'ComfyUI rejected the workflow',
            'upstream_status' => $code,
        ), $status);
    }

    if (! is_array($decoded)) {
        return new WP_REST_Response(array('error' => 'ComfyUI returned an invalid response'), 502);
    }

    return new WP_REST_Response($decoded, 200);
}

/**
 * Check workflow status via ComfyUI /history/{prompt_id}
 */
function comfy_image_check_status( WP_REST_Request $request ) {
    $prompt_id = $request->get_param('prompt_id');
    if (! is_string($prompt_id) || ! preg_match('/\A[A-Za-z0-9_-]{1,255}\z/', $prompt_id)) {
        return new WP_REST_Response(array('error' => 'Invalid prompt_id'), 400);
    }
    $prompt_id = sanitize_text_field($prompt_id);
    if (empty($prompt_id)) {
        return new WP_REST_Response(array('error' => 'Missing prompt_id'), 400);
    }

    $base = comfy_image_get_base_url();
    if (empty($base)) {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL not configured'), 500);
    }

    $endpoint = untrailingslashit($base) . '/history/' . rawurlencode($prompt_id);
    $response = wp_remote_get($endpoint, array(
        'headers' => comfy_image_get_gateway_auth_headers(),
        'timeout' => 20,
        'redirection' => 0,
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('error' => 'Unable to contact ComfyUI'), 502);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
        $status = ($code >= 400 && $code < 500) ? $code : 502;
        return new WP_REST_Response(array(
            'error' => 'ComfyUI could not return workflow history',
            'upstream_status' => $code,
        ), $status);
    }

    if (! is_array($decoded)) {
        return new WP_REST_Response(array('error' => 'ComfyUI returned invalid workflow history'), 502);
    }

    return new WP_REST_Response($decoded, 200);
}

/**
 * Fetch image: return a view URL for ComfyUI `view` endpoint (does not proxy image binary)
 * Query param: filename
 */
function comfy_image_fetch_image( WP_REST_Request $request ) {
    $filename = $request->get_param('filename');
    if (! is_string($filename) || $filename === '' || strlen($filename) > 255 || strpos($filename, '/') !== false || strpos($filename, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $filename) || $filename === '.' || $filename === '..') {
        return new WP_REST_Response(array('error' => 'Invalid image filename'), 400);
    }

    $subfolder = $request->get_param('subfolder');
    $subfolder = is_string($subfolder) ? $subfolder : '';
    if (strlen($subfolder) > 512 || strpos($subfolder, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $subfolder)) {
        return new WP_REST_Response(array('error' => 'Invalid image subfolder'), 400);
    }
    if ($subfolder !== '') {
        foreach (explode('/', $subfolder) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return new WP_REST_Response(array('error' => 'Invalid image subfolder'), 400);
            }
        }
    }

    $type = $request->get_param('type');
    $type = is_string($type) ? $type : 'output';
    if (! in_array($type, array('output', 'input', 'temp'), true)) {
        return new WP_REST_Response(array('error' => 'Invalid image type'), 400);
    }

    $base = comfy_image_get_base_url();
    if ($base === '') {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL is missing or invalid'), 500);
    }

    $query = array('filename' => $filename, 'type' => $type);
    if ($subfolder !== '') {
        $query['subfolder'] = $subfolder;
    }
    $view_url = untrailingslashit($base) . '/view?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $import = in_array($request->get_param('import'), array('1', 1, true, 'true'), true);
    if (! $import) {
        return new WP_REST_Response(array('view_url' => $view_url), 200);
    }

    if (! current_user_can('upload_files')) {
        return new WP_REST_Response(array('error' => 'You are not allowed to add media.'), 403);
    }

    $max_mb = (int) get_option('comfy_image_max_image_size_mb', 10);
    $max_mb = min(100, max(1, $max_mb));
    $max_bytes = $max_mb * 1024 * 1024;
    $resp = wp_remote_get($view_url, array(
        'headers' => comfy_image_get_gateway_auth_headers(),
        'timeout' => 40,
        'redirection' => 0,
        'limit_response_size' => $max_bytes + 1,
    ));
    if (is_wp_error($resp)) {
        return new WP_REST_Response(array('error' => 'Unable to fetch image from ComfyUI.'), 502);
    }

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return new WP_REST_Response(array('error' => 'Unable to fetch image from ComfyUI.'), $code >= 400 && $code < 500 ? $code : 502);
    }

    $body = wp_remote_retrieve_body($resp);
    $size_bytes = strlen($body);
    if ($size_bytes === 0) {
        return new WP_REST_Response(array('error' => 'ComfyUI returned an empty image.'), 502);
    }
    if ($size_bytes > $max_bytes) {
        return new WP_REST_Response(array('error' => 'Image exceeds the configured maximum size.'), 413);
    }

    $content_type = strtolower(trim(explode(';', (string) wp_remote_retrieve_header($resp, 'content-type'))[0]));
    if ($content_type === 'image/jpg') {
        $content_type = 'image/jpeg';
    }
    $extensions = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif');
    $image_info = @getimagesizefromstring($body);
    if (! isset($extensions[$content_type]) || ! is_array($image_info) || ($image_info['mime'] ?? '') !== $content_type) {
        return new WP_REST_Response(array('error' => 'ComfyUI did not return a supported image.'), 415);
    }

    if (! function_exists('wp_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    $base_name = sanitize_file_name($filename);
    $extension = $extensions[$content_type];
    if (! preg_match('/\.' . preg_quote($extension, '/') . '$/i', $base_name)) {
        $base_name .= '.' . $extension;
    }

    $upload = wp_upload_bits($base_name, null, $body);
    if (! is_array($upload) || ! empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
        return new WP_REST_Response(array('error' => 'Unable to save image to the media library.'), 500);
    }

    $file_path = $upload['file'];
    $file_url = $upload['url'];
    $attachment = array(
        'post_mime_type' => $content_type,
        'post_title' => sanitize_text_field(pathinfo($base_name, PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
    );
    $attach_id = wp_insert_attachment($attachment, $file_path);
    if (is_wp_error($attach_id) || ! $attach_id) {
        wp_delete_file($file_path);
        return new WP_REST_Response(array('error' => 'Unable to register image in the media library.'), 500);
    }

    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    if (is_array($attach_data)) {
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    return new WP_REST_Response(array('attachment_id' => $attach_id, 'url' => $file_url, 'mime' => $content_type), 200);
}

/**
 * Upload image stub: intended to accept multipart file and forward to ComfyUI /upload/image.
 * For now this returns 501 until full multipart forwarding is implemented.
 */
function comfy_image_upload_image( WP_REST_Request $request ) {
    return new WP_REST_Response(array('error' => 'Upload endpoint not implemented yet'), 501);
}

?>