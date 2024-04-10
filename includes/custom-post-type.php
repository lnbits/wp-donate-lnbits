<?php
// Create a custom post type for donations
function create_donation_post_type() {
    register_post_type('donation', array(
        'labels' => array(
            'name' => 'Donations',
            'singular_name' => 'Donation',
        ),
        'public' => true,
        'has_archive' => false,
        'publicly_queryable' => false,
		'revisions' => false,
        'supports' => array('title', 'editor'),
    ));
}

// Add a noindex meta tag for the 'donation' custom post type
function prevent_indexing_custom_post_type() {
    // Check if it's a single post of the custom post type 'donation'
    if (is_singular('donation')) {
        echo '<meta name="robots" content="noindex" />';
    }
}

add_action('init', 'create_donation_post_type');
add_action('wp_head', 'prevent_indexing_custom_post_type');

// Hook to add the metabox
add_action('add_meta_boxes', 'lnc_btcdonate_add_custom_metabox');

// Callback function to add metabox
function lnc_btcdonate_add_custom_metabox() {
    add_meta_box(
        'lnc_btcdonate_custom_metabox',
        'Payment Information',
        'lnc_btcdonate_render_custom_metabox',
        'donation',
        'normal',
        'high'
    );
}

// Callback function to render the metabox content
function lnc_btcdonate_render_custom_metabox($post) {
    // Retrieve the payment hash from post meta
    $payment_hash = get_post_meta($post->ID, '_payment_hash', true);

    // Output the HTML for your custom fields
    ?>
    <label for="lnc_btcdonate_payment_hash">Payment Hash:</label>
    <input type="text" id="lnc_btcdonate_payment_hash" size="75" name="lnc_btcdonate_payment_hash" value="<?php echo esc_attr($payment_hash); ?> " disabled />
    <?php
}