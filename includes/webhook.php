<?php
// Register the webhook endpoint
function register_webhook_endpoint() {
    register_rest_route('wp-donate-lnbits/v1', '/webhook/', array(
        'methods'  => 'POST',
        'callback' => 'handle_webhook',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_webhook_endpoint');

function publish_donation($donation_details) {
    // Check if the payment hash is provided
    if (isset($donation_details->details->payment_hash)) {
        // Query to check if a post with the payment hash already exists
        $payment_hash = $donation_details->details->payment_hash;
		$args = array(
			'post_type' => 'donation',
			'post_status' => array('draft'),
			'meta_query' => array(
				array(
					'key' => '_payment_hash',
					'value' => $payment_hash,
					'compare' => '=',
				),
			),
			'fields' => 'ids', // Fetch only post IDs
		);

		$posts = get_posts($args);
		if ($posts) {
			foreach ($posts as $post_id) {
				 $post_data = array(
        		'ID' => $post_id,
        		'post_status' => 'publish',
    			);
    		$updated = wp_update_post($post_data);
			return True;
			}
		} else {
			return False;

		}

        }
	else {
		return False;
	}
    }

// Handle the incoming webhook data
function handle_webhook($request) {

    // Retrieve API settings from the admin
    $api_endpoint = get_option("lnc_btcdonate_api_endpoint");
    $api_key = get_option("lnc_btcdonate_api_key");

    // Get payment data via payemnt hash
    $data = $request->get_json_params();
    $payment_response = wp_remote_get($api_endpoint . "/api/v1/payments/".$data['payment_hash'], [
        "headers" => [
            "Content-Type" => "application/json",
            "X-API-KEY" => $api_key,

        ],
    ]);
    if (is_wp_error($payment_response)) {
        error_log("API Error: " . $payment_response->get_error_message());
    } else {
        // API call was successful
        $api_body = wp_remote_retrieve_body($payment_response);
        $decoded_response = json_decode($api_body);
        if ($decoded_response !== null) {
            $save_donation_result = publish_donation($decoded_response);
			if ($save_donation_result) {
				return new WP_REST_Response(array('message' => 'Donation published.'), 200);
			} else {
				return new WP_REST_Response(array('message' => 'Error processing webhook.'), 500);
			}
        }
    }
    return new WP_REST_Response(array('message' => 'Failed to publish donation.'), 500);
}
