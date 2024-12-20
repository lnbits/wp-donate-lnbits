<?php
/*
Plugin Name: WP Donate LNbits
Description: Accept Bitcoin Donations on your Wordpress website. Initially created for bitcoin payment provider Lightning Checkout.
Version: 0.2
Author: cryptoteun
*/

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/lnbits/wp-donate-lnbits/',
	__FILE__,
	'wp-donate-lnbits'
);


//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');

//If you want to use release assets, call the enableReleaseAssets() method after creating the update checker instance:
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

include_once(plugin_dir_path(__FILE__) . 'includes/settings.php');
include_once(plugin_dir_path(__FILE__) . 'includes/custom-post-type.php');
include_once(plugin_dir_path(__FILE__) . 'includes/webhook.php');
include_once(plugin_dir_path(__FILE__) . 'includes/shortcode.php');
include_once(plugin_dir_path(__FILE__) . 'includes/styles.php');

// Function for menu page
function lnc_btcdonate_settings()
{
    add_menu_page("WP Donate", "WP Donate", "manage_options", "lnc_btcdonate_settings", "lnc_btcdonate_render_settings_page");
}

// Add action for menu page after including files
add_action("admin_menu", "lnc_btcdonate_settings");

// Hook into admin_init to register settings
add_action("admin_init", "lnc_btcdonate_register_settings");
