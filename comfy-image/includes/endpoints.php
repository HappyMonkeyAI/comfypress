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

    // If import=1 is present, download the image and insert into WP Media
    $import = $request->get_param('import');
    if ($import && ($import === '1' || $import === 1 || $import === true || $import === 'true')) {
        // Fetch the image binary from ComfyUI
        $resp = wp_remote_get($view_url, array('timeout' => 40));
        if (is_wp_error($resp)) {
            return new WP_REST_Response(array('error' => $resp->get_error_message()), 502);
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return new WP_REST_Response(array('error' => 'Failed to fetch image from ComfyUI', 'status' => $code), $code);
        }

        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) {
            return new WP_REST_Response(array('error' => 'Empty image body received'), 502);
        }

        // Size check
        $size_bytes = strlen($body);
        $max_mb = intval(get_option('comfy_image_max_image_size_mb', 10));
        $max_bytes = $max_mb * 1024 * 1024;
        if ($max_bytes > 0 && $size_bytes > $max_bytes) {
            return new WP_REST_Response(array('error' => 'Image exceeds maximum allowed size', 'size_bytes' => $size_bytes, 'max_bytes' => $max_bytes), 413);
        }

        // Content type check
        $content_type = wp_remote_retrieve_header($resp, 'content-type');
        if ($content_type) {
            $content_type = strtolower(trim(explode(';', $content_type)[0]));
        }
        $allowed = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif');
        if (empty($content_type) || ! array_key_exists($content_type, $allowed)) {
            return new WP_REST_Response(array('error' => 'Unsupported or missing image MIME type', 'mime' => $content_type), 415);
        }

        // Prepare filename and save via wp_upload_bits
        $base_name = basename($filename);
        $base_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $base_name);
        $ext = $allowed[$content_type];
        // Ensure extension
        if (! preg_match('/\.' . preg_quote($ext, '/') . '$/i', $base_name)) {
            $base_name .= '.' . $ext;
        }

        // Include required files for media handling
        if (! function_exists('wp_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Use wp_upload_bits to write the file
        $upload = wp_upload_bits( $base_name, null, $body );
        if ( isset($upload['error']) && $upload['error'] ) {
            return new WP_REST_Response(array('error' => 'Failed to write uploaded file', 'details' => $upload['error']), 500);
        }

        $file_path = $upload['file'];
        $file_url = $upload['url'];

        // Prepare attachment
        $wp_filetype = wp_check_filetype( $file_path );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'] ?: $content_type,
            'post_title' => sanitize_text_field( pathinfo( $base_name, PATHINFO_FILENAME ) ),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $file_path );
        if ( is_wp_error($attach_id) ) {
            return new WP_REST_Response(array('error' => 'Failed to insert attachment', 'details' => $attach_id->get_error_message()), 500);
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // Return attachment info
        return new WP_REST_Response(array('attachment_id' => $attach_id, 'url' => $file_url, 'mime' => $content_type), 200);
    }

    // Default: return view URL
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