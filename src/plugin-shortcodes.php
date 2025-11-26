<?php
add_shortcode("fpp_upload", "fpp_template_render");
add_shortcode("fpp_carousel", "fpp_template_render");

add_action('wp_enqueue_scripts', 'fpp_frontend_styles');

function fpp_frontend_styles() {
    wp_enqueue_style('fpp-frontend-style', plugin_dir_url(__FILE__) . 'frontend-style.css', array(), '1.0');
}

function fpp_template_render($atts, $content, $shortcode_tag) {
    global $wpdb, $fpp_stations;
    $station_slug = $atts["station"] ?? ''; // Idk figure out how to handle default later
    $station = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fpp_stations WHERE slug = %s", $station_slug));
    $station_name = $station->name;

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