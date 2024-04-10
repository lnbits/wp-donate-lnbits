<?php

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
    // Enqueue Bootstrap stylesheet
    wp_enqueue_style("bootstrap", "https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css");
    // Enqueue your plugin stylesheet
    wp_enqueue_style( "lightningcheckout-style", plugins_url("lightningcheckout-donate-style.css", __FILE__));
}

add_action("wp_enqueue_scripts", "lnc_btcdonate_enqueue_styles");