<?php
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Active Theme Styles
function lnc_btcdonate_theme_styles()
{
    // Enqueue the active theme's stylesheet
    wp_enqueue_style("lightningcheckout-bitcoin-donate-theme-styles", get_stylesheet_uri());
}

add_action( "wp_enqueue_scripts", "lnc_btcdonate_theme_styles");

// User-Provided Styles
function lnc_btcdonate_user_styles()
{
    /**
     * Action hook for users to enqueue their own stylesheet for the custom payment form.
     */
    do_action("lnc_btcdonate_enqueue_styles");
}

add_action("wp_head", "lnc_btcdonate_user_styles");

function lnc_btcdonate_enqueue_styles()
{
    // Enqueue Bootstrap stylesheet (pinned version, verified with Subresource Integrity below)
    wp_enqueue_style("bootstrap", "https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css", array(), null);
    // Enqueue your plugin stylesheet
    wp_enqueue_style( "lightningcheckout-style", plugins_url("lightningcheckout-donate-style.css", __FILE__));
}

add_action("wp_enqueue_scripts", "lnc_btcdonate_enqueue_styles");

// Add Subresource Integrity to the CDN hosted Bootstrap stylesheet
function lnc_btcdonate_bootstrap_sri($html, $handle)
{
    if ($handle === "bootstrap" && strpos($html, "cdn.jsdelivr.net/npm/bootstrap@4.6.2/") !== false) {
        $html = str_replace(
            " href=",
            " integrity='sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N' crossorigin='anonymous' href=",
            $html
        );
    }
    return $html;
}

add_filter("style_loader_tag", "lnc_btcdonate_bootstrap_sri", 10, 2);
