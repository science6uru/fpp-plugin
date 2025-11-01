<?php
add_action( 'admin_menu', 'fpp_admin_register' );

function fpp_admin_register()
{
    echo "registering admin menu";
    add_menu_page(
        'FPP Plugin Admin',     // page title
        'FPP Admin',     // menu title
        'manage_options',   // capability
        'fpp-plugin-admin',     // menu slug
        'fpp_admin_render' // callback function
    );
    add_submenu_page("fpp-plugin-admin", "Manage Station 1", "Station 1", "edit_posts", "fpp-plugin-manage-1", "fpp_admin_manage_render");
    add_submenu_page("fpp-plugin-admin", "Manage Station 2", "Station 2", "edit_posts", "fpp-plugin-manage-2", "fpp_admin_manage_render");
}
function fpp_admin_render()
{
    global $title;

    print '<div class="wrap">';
    print "<h1>$title</h1>";

    $file = plugin_dir_path( __FILE__ ) . "admin_dashboard.php";

    if ( file_exists( $file ) )
        require $file;
    else
        print "File Not Found";

    //print "<p class='description'>Included from <code>$file</code></p>";

    print '</div>';
}

function fpp_admin_manage_render()
{
    global $title;
    $page_slug = $_GET['page'];
    $parts = explode('-', $page_slug);
    $station_id = end($parts);

    print '<div class="wrap">';
    print "<h1>$title</h1>";
    $file = plugin_dir_path( __FILE__ ) . "admin_manage.php";

    if ( file_exists( $file ) )
        require $file;
    else
        print "File Not Found";

    //print "<p class='description'>Included from <code>$file</code></p>";

    print '</div>';
}
?>