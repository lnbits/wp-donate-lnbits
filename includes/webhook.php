<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
function lnc_btcdonate_register_webhook_endpoint() {
    register_rest_route('wp-donate-lnbits/v1', '/webhook/', array(
        'methods'  => 'POST',
        'callback' => 'lnc_btcdonate_handle_webhook',
        // The endpoint is public by design (called by LNbits). The payload is
        // never trusted: the payment status is always verified against LNbits.
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'lnc_btcdonate_register_webhook_endpoint');

/**
 * Publish the draft donation post that belongs to a verified, paid payment.
 */
function lnc_btcdonate_publish_donation($payment_hash) {
    $posts = get_posts(array(
        'post_type'   => 'donation',
        'post_status' => array('draft'),
        'meta_query'  => array(
            array(
                'key'     => '_payment_hash',
                'value'   => $payment_hash,
                'compare' => '=',
            ),
        ),
        'fields'      => 'ids',
        'numberposts' => 1,
    ));

    if (empty($posts)) {
        return false;
    }

    $updated = wp_update_post(array(
        'ID'          => (int) $posts[0],
        'post_status' => 'publish',
    ), true);

    return !is_wp_error($updated) && $updated;
}

/**
 * Extract the payment hash from the webhook payload.
 * Satspay posts the charge as a JSON-encoded string inside a JSON body, so
 * accept both a decoded object and a JSON string.
 */
function lnc_btcdonate_get_webhook_payment_hash($request) {
    $data = $request->get_json_params();

    if (is_string($data)) {
        $data = json_decode($data, true);
    }

    if (!is_array($data) || !isset($data['payment_hash']) || !is_string($data['payment_hash'])) {
        return null;
    }

    $payment_hash = strtolower(trim($data['payment_hash']));

    // A payment hash is always a 32 byte hex string. Rejecting anything else
    // prevents path manipulation of the LNbits API request below.
    if (!preg_match('/^[0-9a-f]{64}$/', $payment_hash)) {
        return null;
    }

    return $payment_hash;
}

// Handle the incoming webhook data
function lnc_btcdonate_handle_webhook($request) {
    if (!get_option('lnc_btcdonate_api_createpost')) {
        return new WP_REST_Response(array('message' => 'Donation posts are disabled.'), 200);
    }

    $payment_hash = lnc_btcdonate_get_webhook_payment_hash($request);
    if ($payment_hash === null) {
        return new WP_REST_Response(array('message' => 'Invalid payload.'), 400);
    }

    // Retrieve API settings from the admin
    $api_endpoint = untrailingslashit(get_option('lnc_btcdonate_api_endpoint'));
    $api_key = get_option('lnc_btcdonate_api_key');

    // Never trust the webhook payload: look the payment up at LNbits.
    $payment_response = wp_remote_get($api_endpoint . '/api/v1/payments/' . rawurlencode($payment_hash), array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-API-KEY'    => $api_key,
        ),
        'timeout' => 15,
    ));

    if (is_wp_error($payment_response)) {
        error_log('WP Donate LNbits API Error: ' . $payment_response->get_error_message());
        return new WP_REST_Response(array('message' => 'Failed to verify payment.'), 502);
    }

    if (wp_remote_retrieve_response_code($payment_response) !== 200) {
        return new WP_REST_Response(array('message' => 'Failed to verify payment.'), 502);
    }

    $payment = json_decode(wp_remote_retrieve_body($payment_response));

    // Only publish when LNbits confirms this exact payment was paid.
    if (
        !is_object($payment) ||
        empty($payment->paid) || $payment->paid !== true ||
        !isset($payment->details->payment_hash) ||
        strtolower($payment->details->payment_hash) !== $payment_hash
    ) {
        return new WP_REST_Response(array('message' => 'Payment not paid.'), 400);
    }

    if (lnc_btcdonate_publish_donation($payment_hash)) {
        return new WP_REST_Response(array('message' => 'Donation published.'), 200);
    }

    return new WP_REST_Response(array('message' => 'No pending donation found.'), 404);
}
