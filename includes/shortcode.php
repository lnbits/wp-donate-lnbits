<?php

// Add Form Shortcode
function lnc_btcdonate_shortcode()
{
    ob_start();

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
        if (
            !isset($_POST["lnc_btcdonate_nonce_field"]) ||
            !wp_verify_nonce(
                $_POST["lnc_btcdonate_nonce_field"],
                "lnc_btcdonate_nonce"
            )
        ) {
            error_log("Nonce verification failed, handle the error as needed");
            // Nonce verification failed, handle the error as needed
            wp_redirect($_SERVER["REQUEST_URI"]);
            exit();
        }

        // Retrieve API settings from the admin
        $api_endpoint = get_option("lnc_btcdonate_api_endpoint");
        $api_key = get_option("lnc_btcdonate_api_key");
        $api_lnwallet = get_option("lnc_btcdonate_api_wallet");
        $api_createpost = get_option("lnc_btcdonate_api_createpost");

        // Get defaults
        $site_name = get_bloginfo("name");
        $base_url = home_url();
        $redirect_url =
            add_query_arg("", "", $base_url . $_SERVER["REQUEST_URI"]) .
            "?donated=true#donateform";

        // Retrieve form data
        $donation_amount = sanitize_text_field($_POST["donation_amount"]);
        $donor_name = sanitize_text_field($_POST["donor_name"]);
        $donor_comment = esc_html($_POST["donor_comment"]);
        $selected_currency = sanitize_text_field($_POST["donor_currency"]);

        // Convert amount to sat if not sat
        if ($selected_currency == "SAT") {
            $amount_sats = intval($donation_amount);
        } else {
            $conversion_api_data = [
                "from_" => $selected_currency,
                "amount" => $donation_amount,
                "to" => "sat",
            ];

            // Make API call
            $api_response = wp_remote_post(
                $api_endpoint . "/api/v1/conversion",
                [
                    "body" => json_encode($conversion_api_data),
                    "headers" => [
                        "Content-Type" => "application/json",
                    ],
                ]
            );

            // Check if the API call was successful
            if (is_wp_error($api_response)) {
                error_log("API Error: " . $api_response->get_error_message());
            } else {
                // API call was successful
                $api_body = wp_remote_retrieve_body($api_response);
                $decoded_response = json_decode($api_body);
                if ($decoded_response !== null) {
                    $amount_sats = $decoded_response->sats;
                    error_log($amount_sats);
                }
            }
        }

        // Set donation title once amount sats is known
        if (strlen($donor_name) > 2) {
            $donar_title = $donor_name . " (" . $amount_sats . " sats)";
        } else {
            $donar_title = "Anonymous (" . $amount_sats . " sats)";
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
            $charge_api_data["webhook"] =
                $base_url . "/wp-json/wp-donate-lnbits/v1/webhook";
        }

        // Make API call
        $charge_api_response = wp_remote_post(
            $api_endpoint . "/satspay/api/v1/charge",
            [
                "body" => json_encode($charge_api_data),
                "headers" => [
                    "Content-Type" => "application/json",
                    "X-API-KEY" => $api_key,
                ],
            ]
        );
        if (is_wp_error($charge_api_response)) {
            error_log(
                "API Error: " . $charge_api_response->get_error_message()
            );
        } else {
            // API call was successful
            $charge_api_body = wp_remote_retrieve_body($charge_api_response);
            $decoded_charge_response = json_decode($charge_api_body);
            if ($decoded_charge_response !== null) {
                $charge_id = $decoded_charge_response->id;
                error_log(
                    "Payment hash to process: " .
                        $decoded_charge_response->payment_hash
                );
                if ($api_createpost) {
                    // Create post in concept
                    $post_data = [
                        "post_title" => $donar_title,
                        "post_content" => $donor_comment,
                        "post_type" => "donation",
                        "post_status" => "draft",
                    ];

                    // Insert the post and Save payment hash as post meta
                    $post_id = wp_insert_post($post_data);
                    if (!is_wp_error($post_id)) {
                        error_log(
                            "Payment processing: " .
                                $decoded_charge_response->payment_hash
                        );
                        update_post_meta(
                            $post_id,
                            "_payment_hash",
                            $decoded_charge_response->payment_hash
                        );
                    }
                }

                if (headers_sent()) {
                    $redirect =
                        "<script>window.location = '" .
                        $api_endpoint .
                        "/satspay/" .
                        $charge_id .
                        "';</script>";
                    echo $redirect;
                } else {
                    wp_redirect($api_endpoint . "/satspay/" . $charge_id);
                    exit();
                }
            }
        }
    }

    // Check if the 'donated' parameter is present in the URL
    $is_paid = isset($_GET["donated"]) && $_GET["donated"] === "true";

    // If 'paid' is true, display a thank you message
    if ($is_paid) { ?>
        <div style="display: flex;justify-content: center;padding: 10px;" id="donateform"><b>Thanks for your donation!</b></div>
        <?php } else {
        // Retrieve selected currency options
        $selected_currencies = get_option("lnc_btcdonate_currency_options");
        $selected_currencies = explode(",", $selected_currencies);
        $selected_currencies = array_map("trim", $selected_currencies);

        // Your shortcode logic here
        ?>
<form action="" method="post">
  <?php wp_nonce_field("lnc_btcdonate_nonce", "lnc_btcdonate_nonce_field"); ?>
           <div class="form-row" id="donateform">
              <div class="form-group col-md-12">
                 <input placeholder="Name" type="text" class="form-control" id="donor-name" name="donor_name" />
              </div>
           </div>
           <div class="form-row">
              <div class="form-group col-md-8">
              <input type="number" min="0.0" step=".01" placeholder="0" class="form-control" id="donation-amount" name="donation_amount" />
              </div>
              <div class="form-group col-md-4">
                 <select id="donor-currency" class="form-control" name="donor_currency">
                    <?php foreach ($selected_currencies as $code): ?>
                    <?php $name =
                        lnc_btcdonate_get_currency_list()[$code] ?? ""; ?>
                    <option value="<?php echo esc_attr(
                        $code
                    ); ?>"><?php echo esc_html($name); ?> (<?php echo esc_html(
     $code
 ); ?>)</option>
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
                 <button type="submit" name="submit" class="btn btn-primary">Donate!</button>
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

function publish_donations($atts)
{
    ob_start();

    $args = [
        "post_type" => "donation", // Replace with your actual custom post type
        "posts_per_page" => -1, // Display all items
    ];

    $custom_query = new WP_Query($args);

    if ($custom_query->have_posts()):
        while ($custom_query->have_posts()):
            $custom_query->the_post();
            // Output the HTML structure with custom classes
            ?>
            <div class="custom-post-type-item">
                <h3><?php the_title(); ?></h3>
                <div class="content"><?php the_content(); ?></div>
            </div>
            <?php
        endwhile;
        wp_reset_postdata(); // Reset post data to restore the main query's context
    else:
        echo "No custom post type items found.";
    endif;

    return ob_get_clean();
}

add_shortcode("wp-donate-lnbits-donations", "publish_donations");
