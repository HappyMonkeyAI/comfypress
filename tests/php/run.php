<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['comfy_test_hooks'] = array();
$GLOBALS['comfy_test_routes'] = array();
$GLOBALS['comfy_test_options'] = array('comfy_image_comfy_base_url' => 'http://127.0.0.1:8188', 'comfy_image_allowed_roles' => array('editor', 'author'));
$GLOBALS['comfy_test_remote_response'] = null;
$GLOBALS['comfy_test_remote_get_response'] = null;
$GLOBALS['comfy_test_remote_args'] = null;
$GLOBALS['comfy_test_remote_calls'] = 0;
$GLOBALS['comfy_test_can_edit'] = true;
$GLOBALS['comfy_test_can_manage_options'] = false;
$GLOBALS['comfy_test_roles'] = array('editor');
$GLOBALS['comfy_test_user_id'] = 7;
$GLOBALS['comfy_test_transients'] = array();
$GLOBALS['comfy_test_transient_ttls'] = array();
$GLOBALS['comfy_test_can_upload'] = true;
$GLOBALS['comfy_test_attachment_error'] = false;
$GLOBALS['comfy_test_deleted_files'] = array();

class WP_REST_Request {
    private $json;
    private $params;

    public function __construct(array $json = array(), array $params = array()) {
        $this->json = $json;
        $this->params = $params;
    }

    public function get_json_params() { return $this->json; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}

class WP_REST_Response {
    private $data;
    private $status;

    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}

class WP_Error {
    private $message;

    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

class ComfyTestWpdb {
    public $dbname = 'comfypress_test';
    public $prefix = 'wp_';
    public $lock_result = 1;
    public $lock_calls = 0;
    public $release_calls = 0;

    public function prepare($query, ...$arguments) {
        foreach ($arguments as $argument) {
            $placeholder = is_int($argument) ? '%d' : '%s';
            $replacement = is_int($argument) ? (string) $argument : "'" . addslashes((string) $argument) . "'";
            $query = preg_replace('/' . preg_quote($placeholder, '/') . '/', $replacement, $query, 1);
        }
        return $query;
    }

    public function get_var($query) {
        if (strpos($query, 'GET_LOCK(') !== false) {
            $this->lock_calls++;
            return $this->lock_result;
        }
        if (strpos($query, 'RELEASE_LOCK(') !== false) {
            $this->release_calls++;
            return 1;
        }
        return null;
    }
}

$GLOBALS['wpdb'] = new ComfyTestWpdb();

function add_action($hook, $callback) { $GLOBALS['comfy_test_hooks'][$hook][] = $callback; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['comfy_test_routes'][$route] = $args; }
function current_user_can($capability) {
    if ($capability === 'manage_options') return $GLOBALS['comfy_test_can_manage_options'];
    return $capability === 'upload_files' ? $GLOBALS['comfy_test_can_upload'] : $GLOBALS['comfy_test_can_edit'];
}
function wp_get_current_user() { return (object) array('roles' => $GLOBALS['comfy_test_roles']); }
function get_current_user_id() { return $GLOBALS['comfy_test_user_id']; }
function get_transient($key) { return $GLOBALS['comfy_test_transients'][$key] ?? false; }
function set_transient($key, $value, $expiration) { $GLOBALS['comfy_test_transients'][$key] = $value; $GLOBALS['comfy_test_transient_ttls'][$key] = $expiration; return true; }
function get_option($key, $default = false) { return $GLOBALS['comfy_test_options'][$key] ?? $default; }
function esc_url_raw($url, $protocols = array('http', 'https')) {
    $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));
    return in_array($scheme, $protocols, true) ? $url : '';
}
function untrailingslashit($value) { return rtrim($value, '/'); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_file_name($value) { return preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $value); }
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_post($url, $args = array()) {
    $GLOBALS['comfy_test_remote_calls']++;
    $GLOBALS['comfy_test_remote_args'] = array('url' => $url, 'args' => $args);
    return $GLOBALS['comfy_test_remote_response'];
}
function wp_remote_get($url, $args = array()) {
    $GLOBALS['comfy_test_remote_calls']++;
    $GLOBALS['comfy_test_remote_args'] = array('url' => $url, 'args' => $args);
    return $GLOBALS['comfy_test_remote_get_response'];
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 0; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function wp_remote_retrieve_headers($response) { return $response['headers'] ?? array(); }
function wp_remote_retrieve_header($response, $header) { return $response['headers'][$header] ?? ''; }
function wp_handle_sideload() {}
function wp_upload_bits($name, $deprecated, $body) {
    $GLOBALS['comfy_test_upload'] = array('name' => $name, 'body' => $body);
    return array('file' => '/tmp/' . $name, 'url' => 'https://wordpress.test/uploads/' . rawurlencode($name));
}
function wp_check_filetype($file) { return array('type' => 'image/png'); }
function wp_insert_attachment($attachment, $file) {
    return $GLOBALS['comfy_test_attachment_error'] ? new WP_Error('insert_failed', 'private file detail') : 42;
}
function wp_generate_attachment_metadata($id, $file) { return array('sizes' => array()); }
function wp_update_attachment_metadata($id, $data) { return true; }
function wp_delete_file($file) { $GLOBALS['comfy_test_deleted_files'][] = $file; }

require __DIR__ . '/../../comfy-image/includes/endpoints.php';
foreach ($GLOBALS['comfy_test_hooks']['rest_api_init'] as $hook) {
    $hook();
}

$failures = array();
$checks = 0;
function check($condition, $message) {
    global $failures, $checks;
    $checks++;
    if (! $condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

$workflow_fixture_path = __DIR__ . '/../../comfy-image/examples/flux1-krea-dev.api.json';
$workflow_fixture_raw = file_exists($workflow_fixture_path) ? file_get_contents($workflow_fixture_path) : false;
$workflow_fixture = is_string($workflow_fixture_raw) ? json_decode($workflow_fixture_raw, true) : null;
check(is_array($workflow_fixture) && ! empty($workflow_fixture), 'example workflow fixture is a non-empty JSON object');
check(
    is_array($workflow_fixture) && isset($workflow_fixture['45']['inputs']['text']) && strpos($workflow_fixture['45']['inputs']['text'], '{{prompt}}') !== false,
    'example workflow fixture uses the ComfyUI API prompt template contract'
);

$submit_permission = $GLOBALS['comfy_test_routes']['/submit-workflow']['permission_callback'] ?? null;
check(is_callable($submit_permission), 'workflow route registers a permission callback');
check($submit_permission(), 'an allowed editor role with edit_posts can use the workflow route');
$GLOBALS['comfy_test_roles'] = array('contributor');
check(! $submit_permission(), 'unselected roles are denied even if they can edit posts');
$GLOBALS['comfy_test_options']['comfy_image_allowed_roles'] = array('author');
$GLOBALS['comfy_test_roles'] = array('editor');
check(! $submit_permission(), 'the administrator-selected role list narrows access');
$GLOBALS['comfy_test_roles'] = array('author');
check($submit_permission(), 'a role selected in settings is permitted');
$GLOBALS['comfy_test_options']['comfy_image_allowed_roles'] = array();
check(! $submit_permission(), 'an empty allow-list denies non-administrators');
$GLOBALS['comfy_test_options']['comfy_image_allowed_roles'] = array('editor', 'author');
$GLOBALS['comfy_test_roles'] = array('administrator');
$GLOBALS['comfy_test_can_manage_options'] = true;
check($submit_permission(), 'site administrators remain permitted');
$GLOBALS['comfy_test_can_manage_options'] = false;
$GLOBALS['comfy_test_can_edit'] = false;
check(! $submit_permission(), 'users without edit_posts are denied regardless of role');
$GLOBALS['comfy_test_can_edit'] = true;
$GLOBALS['comfy_test_roles'] = array('editor');

$GLOBALS['comfy_test_remote_response'] = array(
    'response' => array('code' => 200),
    'body' => '{"prompt_id":"prompt-123"}',
    'headers' => array('x-upstream-secret' => 'do-not-forward'),
);
$request = new WP_REST_Request(array(
    'workflow' => array('4' => array('inputs' => array('text' => '{{prompt}}', 'negative' => 'keep this text'))),
    'prompt' => 'blue cabin',
    'comfy_base_url' => 'http://untrusted.example',
));
$response = comfy_image_submit_workflow($request);
$data = $response->get_data();
$sent = json_decode($GLOBALS['comfy_test_remote_args']['args']['body'], true);
check($response->get_status() === 200, 'successful upstream submit returns HTTP 200');
check(($data['prompt_id'] ?? null) === 'prompt-123', 'submit returns prompt_id at the response top level');
check(! array_key_exists('comfy_raw_body', $data), 'submit response does not expose the raw upstream body');
check(! array_key_exists('comfy_headers', $data), 'submit response does not expose upstream headers');
check(($sent['prompt']['4']['inputs']['text'] ?? null) === 'blue cabin', 'submit substitutes the prompt placeholder in the workflow');
check(($sent['prompt']['4']['inputs']['negative'] ?? null) === 'keep this text', 'submit preserves unrelated workflow text');
check(($GLOBALS['comfy_test_remote_args']['args']['redirection'] ?? null) === 0, 'submit does not follow upstream redirects');
check(isset($GLOBALS['comfy_test_remote_args']['url']) && $GLOBALS['comfy_test_remote_args']['url'] === 'http://127.0.0.1:8188/prompt', 'workflow requests use the configured administrator endpoint, not a request-supplied URL');

$calls_before_missing_placeholder = $GLOBALS['comfy_test_remote_calls'];
$response = comfy_image_submit_workflow(new WP_REST_Request(array(
    'workflow' => array('4' => array('inputs' => array('text' => 'fixed text'))),
    'prompt' => 'blue cabin',
)));
check($response->get_status() === 400, 'submit rejects templates without an explicit prompt placeholder');
check($GLOBALS['comfy_test_remote_calls'] === $calls_before_missing_placeholder, 'invalid workflow templates are not sent upstream');

check(function_exists('comfy_image_validate_base_url'), 'base URL validation helper is available');
if (function_exists('comfy_image_validate_base_url')) {
    check(comfy_image_validate_base_url('http://127.0.0.1:8188') === 'http://127.0.0.1:8188', 'base URL validation permits local ComfyUI endpoints');
    check(comfy_image_validate_base_url('http://10.254.0.1:8188/comfy') === 'http://10.254.0.1:8188/comfy', 'base URL validation preserves a synthetic private-address endpoint and reverse-proxy path');
    check(comfy_image_validate_base_url('https://comfy.example/api') === 'https://comfy.example/api', 'base URL validation permits public HTTPS endpoints and path prefixes');
    check(comfy_image_validate_base_url('ftp://127.0.0.1:8188') === '', 'base URL validation rejects non-HTTP schemes');
    check(comfy_image_validate_base_url('http://user:pass@127.0.0.1:8188') === '', 'base URL validation rejects embedded credentials');
    check(comfy_image_validate_base_url('http://127.0.0.1:8188?target=internal') === '', 'base URL validation rejects query strings');
    check(comfy_image_validate_base_url('http://127.0.0.1:8188#fragment') === '', 'base URL validation rejects fragments');
}
check(function_exists('comfy_image_consume_generation_limit'), 'per-user generation limiter is available');
if (function_exists('comfy_image_consume_generation_limit')) {
    $GLOBALS['comfy_test_transients'] = array();
    $limit_passed = true;
    for ($i = 0; $i < 10; $i++) {
        $limit_passed = $limit_passed && comfy_image_consume_generation_limit(7);
    }
    check($limit_passed, 'generation limiter permits the first 10 requests in a window');
    $quota_state = $GLOBALS['comfy_test_transients']['comfy_image_generation_7'];
    check(is_array($quota_state) && $quota_state['count'] === 10, 'generation limiter stores a window count');
    check(! comfy_image_consume_generation_limit(7), 'generation limiter rejects the 11th request in a window');
    check(comfy_image_consume_generation_limit(8), 'generation limiter tracks users independently');
    $active_window_started_at = time() - 30;
    $GLOBALS['comfy_test_transients']['comfy_image_generation_8'] = array('window_started_at' => $active_window_started_at, 'count' => 3);
    check(comfy_image_consume_generation_limit(8), 'generation limiter permits requests inside an active fixed window');
    $continued_window = $GLOBALS['comfy_test_transients']['comfy_image_generation_8'];
    check(is_array($continued_window) && $continued_window['window_started_at'] === $active_window_started_at && $continued_window['count'] === 4, 'active-window requests preserve the original start time');
    check($GLOBALS['comfy_test_transient_ttls']['comfy_image_generation_8'] <= 30, 'active-window writes do not extend the fixed window expiry');
    $GLOBALS['comfy_test_transients']['comfy_image_generation_9'] = array('window_started_at' => time() - 61, 'count' => 10);
    check(comfy_image_consume_generation_limit(9), 'generation limiter resets an expired window');
    $reset_window = $GLOBALS['comfy_test_transients']['comfy_image_generation_9'];
    check(is_array($reset_window) && $reset_window['window_started_at'] >= time() - 1 && $reset_window['count'] === 1, 'expired-window reset starts a fresh count');
    check($GLOBALS['comfy_test_transient_ttls']['comfy_image_generation_9'] <= 60, 'fresh window expires no later than 60 seconds');
    $GLOBALS['comfy_test_transients']['comfy_image_generation_7'] = 10;
    $calls_before_limited_submit = $GLOBALS['comfy_test_remote_calls'];
    $limited_submit = comfy_image_submit_workflow(new WP_REST_Request(array(
        'workflow' => array('4' => array('inputs' => array('text' => '{{prompt}}'))),
        'prompt' => 'rate limited',
    )));
    check($limited_submit->get_status() === 429, 'workflow submit returns HTTP 429 when the per-user quota is exhausted');
    check($GLOBALS['comfy_test_remote_calls'] === $calls_before_limited_submit, 'rate-limited workflow submissions do not contact ComfyUI');
    $GLOBALS['comfy_test_transients'] = array();
}

$wpdb_lock_calls = $GLOBALS['wpdb']->lock_calls;
$wpdb_release_calls = $GLOBALS['wpdb']->release_calls;
$GLOBALS['wpdb']->lock_result = 0;
check(! comfy_image_consume_generation_limit(9), 'generation limiter fails closed when the database lock is unavailable');
check(! isset($GLOBALS['comfy_test_transients']['comfy_image_generation_9']), 'a busy database lock does not increment the quota');
check($GLOBALS['wpdb']->lock_calls === $wpdb_lock_calls + 1, 'generation limiter acquires a database lock before changing the counter');
check($GLOBALS['wpdb']->release_calls === $wpdb_release_calls, 'generation limiter does not release a lock it failed to acquire');

$GLOBALS['wpdb']->lock_result = 1;
$GLOBALS['comfy_test_transients']['comfy_image_generation_10'] = 10;
$wpdb_release_calls = $GLOBALS['wpdb']->release_calls;
check(! comfy_image_consume_generation_limit(10), 'generation limiter keeps rejecting an exhausted quota under lock');
check($GLOBALS['wpdb']->release_calls === $wpdb_release_calls + 1, 'generation limiter releases its lock after an exhausted quota check');

$GLOBALS['comfy_test_remote_response'] = array(
    'response' => array('code' => 503),
    'body' => '{"error":"backend unavailable","debug":"private detail"}',
    'headers' => array('x-debug' => 'private detail'),
);
$response = comfy_image_submit_workflow(new WP_REST_Request(array('workflow' => array('4' => array('inputs' => array('text' => '{{prompt}}'))), 'prompt' => 'test')));
$data = $response->get_data();
check($response->get_status() === 502, 'upstream server failure maps to HTTP 502');
check(isset($data['error']) && ! str_contains(json_encode($data), 'private detail'), 'upstream failure returns a bounded error without raw details');

$GLOBALS['comfy_test_remote_response'] = array(
    'response' => array('code' => 200),
    'body' => '<html>proxy failure</html>',
    'headers' => array(),
);
$response = comfy_image_submit_workflow(new WP_REST_Request(array('workflow' => array('4' => array('inputs' => array('text' => '{{prompt}}'))), 'prompt' => 'test')));
check($response->get_status() === 502, 'malformed upstream submit JSON maps to HTTP 502');
check(! str_contains(json_encode($response->get_data()), 'proxy failure'), 'malformed upstream response body is not returned');

$GLOBALS['comfy_test_remote_response'] = new WP_Error('http_request_failed', 'secret internal network detail');
$response = comfy_image_submit_workflow(new WP_REST_Request(array('workflow' => array('4' => array('inputs' => array('text' => '{{prompt}}'))), 'prompt' => 'test')));
check($response->get_status() === 502, 'transport failure maps to HTTP 502');
check(! str_contains(json_encode($response->get_data()), 'secret internal network detail'), 'transport errors do not disclose internal details');

$GLOBALS['comfy_test_remote_get_response'] = array(
    'response' => array('code' => 200),
    'body' => '{"prompt-123":{"outputs":{"9":{"images":[{"filename":"result.png","subfolder":"","type":"output"}]}}}}',
    'headers' => array(),
);
$status_request = new WP_REST_Request(array(), array('prompt_id' => 'prompt-123'));
$calls_before_bad_prompt_id = $GLOBALS['comfy_test_remote_calls'];
$bad_prompt_id_response = comfy_image_check_status(new WP_REST_Request(array(), array('prompt_id' => array('prompt-123'))));
check($bad_prompt_id_response->get_status() === 400, 'history endpoint rejects non-string prompt IDs');
check($GLOBALS['comfy_test_remote_calls'] === $calls_before_bad_prompt_id, 'invalid prompt IDs are rejected before network access');
$response = comfy_image_check_status($status_request);
check($response->get_status() === 200, 'successful history response preserves HTTP 200');
check(isset($response->get_data()['prompt-123']['outputs']['9']['images'][0]['filename']), 'history endpoint preserves the ComfyUI history response shape');

$GLOBALS['comfy_test_remote_get_response'] = array(
    'response' => array('code' => 500),
    'body' => '{"debug":"private history detail"}',
    'headers' => array(),
);
$response = comfy_image_check_status($status_request);
check($response->get_status() === 502, 'history upstream server failure maps to HTTP 502');
check(! str_contains(json_encode($response->get_data()), 'private history detail'), 'history error body is not returned');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');
$GLOBALS['comfy_test_remote_get_response'] = array(
    'response' => array('code' => 200),
    'body' => $png,
    'headers' => array('content-type' => 'image/png'),
);
$image_request = new WP_REST_Request(array(), array(
    'filename' => 'result.png',
    'subfolder' => 'previews/demo',
    'type' => 'output',
    'import' => '1',
));
$response = comfy_image_fetch_image($image_request);
check($response->get_status() === 200 && ($response->get_data()['attachment_id'] ?? null) === 42, 'image import returns a WordPress attachment');
check(($GLOBALS['comfy_test_remote_args']['args']['redirection'] ?? null) === 0, 'image import does not follow upstream redirects');
check(($GLOBALS['comfy_test_remote_args']['args']['limit_response_size'] ?? null) === 10485761, 'image import bounds the downloaded response size');
check(str_contains($GLOBALS['comfy_test_remote_args']['url'], 'subfolder=previews%2Fdemo'), 'image import preserves subfolder in the ComfyUI view request');

$GLOBALS['comfy_test_attachment_error'] = true;
$GLOBALS['comfy_test_deleted_files'] = array();
$response = comfy_image_fetch_image($image_request);
check($response->get_status() === 500, 'attachment-registration failure returns an error');
check($GLOBALS['comfy_test_deleted_files'] === array('/tmp/result.png'), 'attachment-registration failure removes the uploaded image file');
$GLOBALS['comfy_test_attachment_error'] = false;

$GLOBALS['comfy_test_can_upload'] = false;
$calls_before_upload_denial = $GLOBALS['comfy_test_remote_calls'];
$response = comfy_image_fetch_image($image_request);
check($response->get_status() === 403, 'image import requires upload_files capability');
check($GLOBALS['comfy_test_remote_calls'] === $calls_before_upload_denial, 'unauthorized image import does not fetch from ComfyUI');
$GLOBALS['comfy_test_can_upload'] = true;

$calls_before_bad_filename = $GLOBALS['comfy_test_remote_calls'];
$response = comfy_image_fetch_image(new WP_REST_Request(array(), array('filename' => '../secret.png', 'import' => '1')));
check($response->get_status() === 400, 'image import rejects path traversal in filenames');
check($GLOBALS['comfy_test_remote_calls'] === $calls_before_bad_filename, 'invalid image filename is rejected before network access');

$GLOBALS['comfy_test_remote_get_response']['body'] = str_repeat('x', 1024 * 1024 + 1);
$GLOBALS['comfy_test_options']['comfy_image_max_image_size_mb'] = 1;
$response = comfy_image_fetch_image($image_request);
check($response->get_status() === 413, 'image import enforces the configured byte limit');
$GLOBALS['comfy_test_options']['comfy_image_max_image_size_mb'] = 10;

$GLOBALS['comfy_test_remote_get_response'] = array(
    'response' => array('code' => 200),
    'body' => '<html>not an image</html>',
    'headers' => array('content-type' => 'image/png'),
);
$response = comfy_image_fetch_image($image_request);
check($response->get_status() === 415, 'image import verifies binary image data, not only the MIME header');

$GLOBALS['comfy_test_can_edit'] = false;
foreach (array('/submit-workflow', '/check-status/(?P<prompt_id>[A-Za-z0-9_-]{1,255})', '/fetch-image', '/upload-image') as $route) {
    $permission = $GLOBALS['comfy_test_routes'][$route]['permission_callback'] ?? null;
    check(is_callable($permission) && $permission() === false, $route . ' denies users without edit_posts capability');
}

if ($failures) {
    fwrite(STDERR, sprintf("%d/%d checks failed\n", count($failures), $checks));
    exit(1);
}

echo sprintf("PASS: %d checks\n", $checks);
