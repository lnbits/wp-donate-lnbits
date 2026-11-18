<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process a submitted donation form.
 * Redirects to the payment page on success, returns an error message otherwise.
 */
function lnc_btcdonate_process_donation()
{
    if (
        !isset($_POST["lnc_btcdonate_nonce_field"]) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST["lnc_btcdonate_nonce_field"])),
            "lnc_btcdonate_nonce"
        )
    ) {
        return "Your session has expired, please try again.";
    }

    // Retrieve API settings from the admin
    $api_endpoint = untrailingslashit(get_option("lnc_btcdonate_api_endpoint"));
    $api_key = get_option("lnc_btcdonate_api_key");
    $api_lnwallet = get_option("lnc_btcdonate_api_wallet");
    $api_createpost = get_option("lnc_btcdonate_api_createpost");

    if (empty($api_endpoint) || empty($api_key) || empty($api_lnwallet)) {
        return "Donations are not configured yet.";
    }

    // Get defaults
    $site_name = get_bloginfo("name");
    $base_url = home_url();
    $request_uri = isset($_SERVER["REQUEST_URI"]) ? wp_unslash($_SERVER["REQUEST_URI"]) : "/";
    $redirect_url = esc_url_raw(
        add_query_arg("donated", "true", remove_query_arg("donated", $base_url . $request_uri))
    ) . "#donateform";

    // Retrieve form data
    $donation_amount = isset($_POST["donation_amount"]) ? sanitize_text_field(wp_unslash($_POST["donation_amount"])) : "";
    $donor_name = isset($_POST["donor_name"]) ? sanitize_text_field(wp_unslash($_POST["donor_name"])) : "";
    $donor_comment = isset($_POST["donor_comment"]) ? sanitize_textarea_field(wp_unslash($_POST["donor_comment"])) : "";
    $selected_currency = isset($_POST["donor_currency"]) ? strtoupper(sanitize_text_field(wp_unslash($_POST["donor_currency"]))) : "";

    $donor_name = mb_substr($donor_name, 0, 100);
    $donor_comment = mb_substr($donor_comment, 0, 250);

    // Only accept currencies enabled by the admin
    $enabled_currencies = array_map("trim", explode(",", (string) get_option("lnc_btcdonate_currency_options")));
    if (!in_array($selected_currency, $enabled_currencies, true) || !array_key_exists($selected_currency, lnc_btcdonate_get_currency_list())) {
        return "Please select a valid currency.";
    }

    if (!is_numeric($donation_amount) || (float) $donation_amount <= 0) {
        return "Please enter a valid amount.";
    }

    // Convert amount to sat if not sat
    $amount_sats = 0;
    if ($selected_currency === "SAT") {
        $amount_sats = (int) $donation_amount;
    } else {
        $api_response = wp_remote_post(
            $api_endpoint . "/api/v1/conversion",
            [
                "body" => wp_json_encode([
                    "from_" => $selected_currency,
                    "amount" => (float) $donation_amount,
                    "to" => "sat",
                ]),
                "headers" => [
                    "Content-Type" => "application/json",
                ],
                "timeout" => 15,
            ]
        );

        if (is_wp_error($api_response)) {
            error_log("WP Donate LNbits API Error: " . $api_response->get_error_message());
        } else {
            $decoded_response = json_decode(wp_remote_retrieve_body($api_response));
            if (is_object($decoded_response) && isset($decoded_response->sats)) {
                $amount_sats = (int) $decoded_response->sats;
            }
        }
    }

    if ($amount_sats <= 0) {
        return "Could not determine the donation amount, please try again.";
    }

    // Set donation title once amount sats is known
    if (mb_strlen($donor_name) > 2) {
        $donor_title = $donor_name . " (" . $amount_sats . " sats)";
    } else {
        $donor_title = "Anonymous (" . $amount_sats . " sats)";
    }

    // Create payments
    $charge_api_data = [
        "lnbitswallet" => $api_lnwallet,
        "description" => "Donation " . $site_name,
        "completelink" => $redirect_url,
        "completelinktext" => "Thanks, go back to " . $site_name,
        "time" => 1440,
        "amount" => $amount_sats,
    ];

    // If a post is needed, the webhook is as well, so add to charge data.
    if ($api_createpost) {
        $charge_api_data["webhook"] = rest_url("wp-donate-lnbits/v1/webhook");
    }

    $charge_api_response = wp_remote_post(
        $api_endpoint . "/satspay/api/v1/charge",
        [
            "body" => wp_json_encode($charge_api_data),
            "headers" => [
                "Content-Type" => "application/json",
                "X-API-KEY" => $api_key,
            ],
            "timeout" => 15,
        ]
    );

    if (is_wp_error($charge_api_response)) {
        error_log("WP Donate LNbits API Error: " . $charge_api_response->get_error_message());
        return "Could not create the payment, please try again.";
    }

    $decoded_charge_response = json_decode(wp_remote_retrieve_body($charge_api_response));
    if (
        !is_object($decoded_charge_response) ||
        !isset($decoded_charge_response->id) ||
        !preg_match('/^[A-Za-z0-9_-]+$/', (string) $decoded_charge_response->id)
    ) {
        return "Could not create the payment, please try again.";
    }

    $charge_id = (string) $decoded_charge_response->id;

    if ($api_createpost && isset($decoded_charge_response->payment_hash)) {
        // Create post in concept, it gets published by the webhook after payment
        $post_id = wp_insert_post([
            "post_title" => $donor_title,
            "post_content" => $donor_comment,
            "post_type" => "donation",
            "post_status" => "draft",
        ], true);

        if (!is_wp_error($post_id) && $post_id) {
            update_post_meta(
                $post_id,
                "_payment_hash",
                sanitize_text_field($decoded_charge_response->payment_hash)
            );
        }
    }

    $payment_url = $api_endpoint . "/satspay/" . rawurlencode($charge_id);

    if (headers_sent()) {
        return "<script>window.location = " . wp_json_encode(esc_url_raw($payment_url)) . ";</script>" .
            '<p><a href="' . esc_url($payment_url) . '">Continue to payment</a></p>';
    }

    wp_redirect(esc_url_raw($payment_url));
    exit();
}

// Add Form Shortcode
function lnc_btcdonate_shortcode()
{
    ob_start();

    // Handle form submission
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["lnc_btcdonate_nonce_field"])) {
        $result = lnc_btcdonate_process_donation();
        if (strpos($result, "<script>") === 0) {
            // Redirect markup, built from escaped values only
            echo $result;
            return ob_get_clean();
        }
        echo '<div class="alert alert-warning" id="donateform">' . esc_html($result) . '</div>';
    }

    // Check if the 'donated' parameter is present in the URL
    $is_paid = isset($_GET["donated"]) && $_GET["donated"] === "true";

    // If 'paid' is true, display a thank you message
    if ($is_paid) { ?>
        <div style="display: flex;justify-content: center;padding: 10px;" id="donateform"><b>Thanks for your donation!</b></div>
        <?php } else {
        // Retrieve selected currency options
        $selected_currencies = get_option("lnc_btcdonate_currency_options");
        $selected_currencies = explode(",", (string) $selected_currencies);
        $selected_currencies = array_map("trim", $selected_currencies);
        ?>
<form action="" method="post">
  <?php wp_nonce_field("lnc_btcdonate_nonce", "lnc_btcdonate_nonce_field"); ?>
           <div class="form-row" id="donateform">
              <div class="form-group col-md-12">
                 <input placeholder="Name" type="text" class="form-control" id="donor-name" name="donor_name" maxlength="100" />
              </div>
           </div>
           <div class="form-row">
              <div class="form-group col-md-8">
              <input type="number" min="0.01" step=".01" placeholder="0" class="form-control" id="donation-amount" name="donation_amount" required />
              </div>
              <div class="form-group col-md-4">
                 <select id="donor-currency" class="form-control" name="donor_currency">
                    <?php foreach ($selected_currencies as $code): ?>
                    <?php $name = lnc_btcdonate_get_currency_list()[$code] ?? ""; ?>
                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)</option>
                    <?php endforeach; ?>
                 </select>
              </div>
           </div>
           <div class="form-row">
              <div class="form-group col-md-12">
                 <textarea id="donor-comment" placeholder="Comment..." class="form-control" name="donor_comment" maxlength="250"></textarea>
              </div>
           </div>
           <div class="form-row">
              <div class="form-group col-md-12">
                 <button type="submit" name="lnc_btcdonate_submit" value="1" class="btn btn-primary">Donate!</button>
              </div>
           </div>
        </form>
    <?php }
    $form_content = ob_get_clean();

    /**
     * Filter the custom payment form output.
     *
     * @param string $form_content The default form content.
     */
    $form_content = apply_filters("lnc_btcdonate_output", $form_content);
    return $form_content;
}

// Register the shortcode
add_shortcode("wp-donate-lnbits-form", "lnc_btcdonate_shortcode");

function lnc_btcdonate_publish_donations($atts)
{
    ob_start();

    $custom_query = new WP_Query([
        "post_type" => "donation",
        "post_status" => "publish",
        "posts_per_page" => 100,
        "no_found_rows" => true,
    ]);

    if ($custom_query->have_posts()):
        while ($custom_query->have_posts()):
            $custom_query->the_post();
            ?>
            <div class="custom-post-type-item">
                <h3><?php echo esc_html(get_the_title()); ?></h3>
                <div class="content"><?php echo wp_kses_post(wpautop(get_the_content())); ?></div>
            </div>
            <?php
        endwhile;
        wp_reset_postdata();
    else:
        echo "No custom post type items found.";
    endif;

    return ob_get_clean();
}

add_shortcode("wp-donate-lnbits-donations", "lnc_btcdonate_publish_donations");
