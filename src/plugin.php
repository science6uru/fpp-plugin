<?php
/*
Plugin Name: Photolog
Description: Fixed-Point Photography Time Lapse
Version: 1.0
Author: Andrew Owen
License: GPLv2 or later
*/


function fpp_register_scripts() {
    $fpp_upload_dependencies = ["jquery"];

    $fpp_upload_data = array("fpp_max_upload_size_mb" => get_option("fpp_max_upload_size_mb"));

    $site_key = get_option('fpp_recaptcha_site_key');
    if (!empty($site_key)) {
        wp_register_script('fpp-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
        $fpp_upload_dependencies[] = "fpp-recaptcha-v3";
        $fpp_upload_data['site_key'] = esc_attr($site_key);
    }
    wp_register_script(
        'fpp_upload',
        plugins_url( '/js/fpp_upload.js', __FILE__ ),
        $fpp_upload_dependencies,
        false,           // Version number
        true               // Load in the footer (recommended for performance)
    );


    wp_localize_script( 'fpp_upload', 'php_vars', $fpp_upload_data );

    wp_register_style('fpp_upload', plugins_url(  "css/fpp_upload.css", __FILE__ ));

}
add_action( 'wp_enqueue_scripts', 'fpp_register_scripts' );
add_action( 'admin_enqueue_scripts', 'fpp_register_scripts' );

require("plugin-admin.php");
require("plugin-activation.php");
require("plugin-shortcodes.php");
require("fpp_uploads.php");


// function add_my_custom_page() {
//   $my_post = array(
//     'post_title'    => 'My Custom Page',
//     'post_content'  => 'This is a custom page created by a plugin.',
//     'post_status'   => 'publish',
//     'post_author'   => 1,
//     'post_type'     => 'page',
//   );
//   $res = wp_insert_post( $my_post );
//   is_wp_error(($res))
// }
// register_activation_hook( __FILE__, 'add_my_custom_page' );

// function delete_my_custom_page() {
//     $my_post = array(
//       'post_title'    => 'My Custom Page',
//       'post_content'  => 'This is a custom page created by a plugin.',
//       'post_status'   => 'publish',
//       'post_author'   => 1,
//       'post_type'     => 'page',
//     );
//     wp_insert_post( $my_post );
//     wp_get_single_post( $postid:integer, $mode:string )
// register_deactivation_hook(__FILE__, 'delete_my_custom_page')
?>