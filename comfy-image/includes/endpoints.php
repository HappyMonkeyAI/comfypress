<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Helper to get configured ComfyUI base URL (no trailing slash)
 */
function comfy_image_get_base_url() {
    $url = get_option('comfy_image_comfy_base_url', '');
    $url = trim($url);
    $url = rtrim($url, " /\t\n\r");
    return esc_url_raw($url);
}

add_action('rest_api_init', function () {
    register_rest_route('comfy-image/v1', '/submit-workflow', array(
        'methods' => 'POST',
        'callback' => 'comfy_image_submit_workflow',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('comfy-image/v1', '/check-status/(?P<prompt_id>[A-Za-z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'comfy_image_check_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('comfy-image/v1', '/fetch-image', array(
        'methods' => 'GET',
        'callback' => 'comfy_image_fetch_image',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    // Upload endpoint (stub) - file uploads are supported later
    register_rest_route('comfy-image/v1', '/upload-image', array(
        'methods' => 'POST',
        'callback' => 'comfy_image_upload_image',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
});

/**
 * Submit a workflow JSON to ComfyUI /prompt
 * Expects JSON body: { workflow: {...}, prompt: "optional prompt" }
 */
function comfy_image_submit_workflow( WP_REST_Request $request ) {
    $params = $request->get_json_params();

    if (empty($params) || ! is_array($params)) {
        return new WP_REST_Response(array('error' => 'Invalid JSON body'), 400);
    }

    $workflow = isset($params['workflow']) ? $params['workflow'] : null;
    $prompt = isset($params['prompt']) ? sanitize_text_field($params['prompt']) : null;

    if (! $workflow) {
        return new WP_REST_Response(array('error' => 'Missing workflow'), 400);
    }

    // If prompt provided and workflow is an array/object, try to inject prompt into workflow if node exists.
    if ($prompt && is_array($workflow)) {
        // Best-effort: if there is a node with key 'prompt' in params, set it.
        array_walk_recursive($workflow, function (&$v, $k) use ($prompt) {
            if ($k === 'prompt' && empty($v)) {
                $v = $prompt;
            }
        });
    }

    $body = wp_json_encode($workflow);
    if ($body === false) {
        return new WP_REST_Response(array('error' => 'Failed to encode workflow'), 500);
    }

    $base = comfy_image_get_base_url();
    if (empty($base)) {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL not configured'), 500);
    }

    $endpoint = untrailingslashit($base) . '/prompt';

    $response = wp_remote_post($endpoint, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => $body,
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('error' => $response->get_error_message()), 502);
    }

    $code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);

    $decoded = json_decode($resp_body, true);
    if ($decoded === null) {
        // Return raw body if JSON decode fails
        return new WP_REST_Response(array('status_code' => $code, 'body' => $resp_body), $code);
    }

    return new WP_REST_Response($decoded, $code);
}

/**
 * Check workflow status via ComfyUI /history/{prompt_id}
 */
function comfy_image_check_status( WP_REST_Request $request ) {
    $prompt_id = $request->get_param('prompt_id');
    $prompt_id = sanitize_text_field($prompt_id);
    if (empty($prompt_id)) {
        return new WP_REST_Response(array('error' => 'Missing prompt_id'), 400);
    }

    $base = comfy_image_get_base_url();
    if (empty($base)) {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL not configured'), 500);
    }

    $endpoint = untrailingslashit($base) . '/history/' . rawurlencode($prompt_id);
    $response = wp_remote_get($endpoint, array('timeout' => 20));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('error' => $response->get_error_message()), 502);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    if ($decoded === null) {
        return new WP_REST_Response(array('status_code' => $code, 'body' => $body), $code);
    }

    return new WP_REST_Response($decoded, $code);
}

/**
 * Fetch image: return a view URL for ComfyUI `view` endpoint (does not proxy image binary)
 * Query param: filename
 */
function comfy_image_fetch_image( WP_REST_Request $request ) {
    $filename = $request->get_param('filename');
    $filename = sanitize_text_field($filename);
    if (empty($filename)) {
        return new WP_REST_Response(array('error' => 'Missing filename'), 400);
    }

    $base = comfy_image_get_base_url();
    if (empty($base)) {
        return new WP_REST_Response(array('error' => 'ComfyUI base URL not configured'), 500);
    }

    $view_url = untrailingslashit($base) . '/view?filename=' . rawurlencode($filename);

    return new WP_REST_Response(array('view_url' => $view_url), 200);
}

/**
 * Upload image stub: intended to accept multipart file and forward to ComfyUI /upload/image.
 * For now this returns 501 until full multipart forwarding is implemented.
 */
function comfy_image_upload_image( WP_REST_Request $request ) {
    return new WP_REST_Response(array('error' => 'Upload endpoint not implemented yet'), 501);
}

?>