<?php
/*
Plugin Name: FPP
Description: Fixed-Point Photography Time Lapse
Version: 1.0
Author: Andrew Owen
License: GPLv2 or later
*/
require("plugin-admin.php");
require("plugin-activation.php");


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