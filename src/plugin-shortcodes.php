<?php
add_shortcode("fpp_upload", "fpp_template_render");
add_shortcode("fpp_carousel", "fpp_template_render");

function fpp_template_render($atts, $content, $shortcode_tag) {
    $station_id = $atts["station_id"] ?? ''; // Idk figure out how to handle default later
    if ($shortcode_tag === 'fpp_upload') {
        // Enqueue reCAPTCHA v3 script if site key is set
        $site_key = get_option('fpp_recaptcha_site_key');
        if (!empty($site_key)) {
            wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
        }
    }

    ob_start();
    require("fpp-plugin-$shortcode_tag.php");

    if (ob_get_length() > 0) {
        $output = ob_get_contents();
    }
    ob_end_clean();
    return $output;
}



?>