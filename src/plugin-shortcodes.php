<?php
add_shortcode("fpp_upload", "fpp_template_render");
add_shortcode("fpp_carousel", "fpp_template_render");

add_action('wp_enqueue_scripts', 'fpp_frontend_styles');

function fpp_frontend_styles() {
    wp_enqueue_style('fpp-frontend-style', plugin_dir_url(__FILE__) . 'frontend-style.css', array(), '1.0');
}

function fpp_template_render($atts, $content, $shortcode_tag) {
    $station_id = $atts["station_id"] ?? ''; // Idk figure out how to handle default later
    wp_enqueue_script( $shortcode_tag );
    wp_enqueue_style( $shortcode_tag  );

    ob_start();
    require("fpp-plugin-$shortcode_tag.php");

    if (ob_get_length() > 0) {
        $output = ob_get_contents();
    }
    ob_end_clean();
    return $output;
}
?>