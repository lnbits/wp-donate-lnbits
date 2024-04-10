<?php
// Register Settings
function lnc_btcdonate_register_settings()
{
    register_setting("lnc_btcdonate_settings_group", "lnc_btcdonate_api_endpoint");
    register_setting("lnc_btcdonate_settings_group","lnc_btcdonate_api_key");
    register_setting("lnc_btcdonate_settings_group","lnc_btcdonate_api_wallet");
    register_setting("lnc_btcdonate_settings_group", "lnc_btcdonate_api_createpost", array(
        'type' => 'boolean', // Ensure that the type is set to boolean
        'sanitize_callback' => 'sanitize_checkbox', // Add a sanitize callback for checkbox
    ));
    register_setting("lnc_btcdonate_settings_group","lnc_btcdonate_currency_options","lnc_btcdonate_sanitize_currency_options");

    add_settings_section("lnc_btcdonate_settings_section","API Settings","lnc_btcdonate_settings_section_callback","lnc_btcdonate_settings");

    add_settings_field("lnc_btcdonate_api_endpoint","API Endpoint","lnc_btcdonate_api_endpoint_callback","lnc_btcdonate_settings","lnc_btcdonate_settings_section");
    add_settings_field("lnc_btcdonate_api_key","API Key","lnc_btcdonate_api_key_callback","lnc_btcdonate_settings","lnc_btcdonate_settings_section");
    add_settings_field("lnc_btcdonate_api_wallet","Lightning Wallet","lnc_btcdonate_api_wallet_callback","lnc_btcdonate_settings","lnc_btcdonate_settings_section");
    add_settings_field("lnc_btcdonate_api_createpost", "After payment", "lnc_btcdonate_api_createpost_callback", "lnc_btcdonate_settings", "lnc_btcdonate_settings_section");
    add_settings_field("lnc_btcdonate_currency_options","Currency Options","lnc_btcdonate_currency_options_callback","lnc_btcdonate_settings","lnc_btcdonate_settings_section");
}

// Make sure the checkboxes are saved correctly
function sanitize_checkbox($input)
{
    return (bool) $input;
}


function lnc_btcdonate_settings_section_callback()
{
    echo "Enter your API settings and configure currency options below:";
}


function lnc_btcdonate_api_endpoint_callback()
{
    $endpoint = get_option("lnc_btcdonate_api_endpoint");
    echo "<input type='text' name='lnc_btcdonate_api_endpoint' value='$endpoint' />";
}

function lnc_btcdonate_api_key_callback()
{
    $key = get_option("lnc_btcdonate_api_key");
    echo "<input type='text' name='lnc_btcdonate_api_key' value='$key' />";
}

function lnc_btcdonate_api_wallet_callback()
{
    $wallet = get_option("lnc_btcdonate_api_wallet");
    echo "<input type='text' name='lnc_btcdonate_api_wallet' value='$wallet' />";
}

function lnc_btcdonate_api_createpost_callback()
{
    $enable_api_createpost = get_option("lnc_btcdonate_api_createpost", false); ?>
    <label for="lnc_btcdonate_api_createpost">
        <input type="checkbox" id="lnc_btcdonate_api_createpost" name="lnc_btcdonate_api_createpost" <?php checked(true, $enable_api_createpost); ?> />
        Create donation post
    </label>
    <?php
}


function lnc_btcdonate_currency_options_callback()
{
    $currency_options = get_option("lnc_btcdonate_currency_options"); ?>
    <input type="text" name="lnc_btcdonate_currency_options" value="<?php echo esc_attr($currency_options); ?>" placeholder="Enter currency codes (comma-separated)" />
    <?php
}

function lnc_btcdonate_render_main_page()
{
    ?>
    <div class="wrap"><h1>Lightning Checkout</h1></div>
    <?php
}

function lnc_btcdonate_render_settings_page()
{
    ?>
    <div class="wrap"><h1>Bitcoin Donate Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields("lnc_btcdonate_settings_group");
            do_settings_sections("lnc_btcdonate_settings");
            submit_button("Save Settings");?>
        </form>
    </div>
    <?php
}

function lnc_btcdonate_sanitize_currency_options($input)
{
    // Sanitize and ensure that only valid currency codes are saved
    $valid_currencies = lnc_btcdonate_get_currency_list();

    // Convert the input to an array, Filter out invalid currencies, Convert back to a comma-separated string
    $input_array = explode(",", $input);
    $sanitized_input = array_intersect($input_array, array_keys($valid_currencies));
    $sanitized_input_string = implode(",", $sanitized_input);
    return $sanitized_input_string;
}

// Function to define a basic set of currencies (replace with your desired list)
function lnc_btcdonate_get_currency_list()
{
    return [
        "USD" => "US Dollar",
        "EUR" => "Euro",
        "GBP" => "British Pound",
        "SAT" => "Bitcoin",
        // Add more currencies as needed
    ];
}