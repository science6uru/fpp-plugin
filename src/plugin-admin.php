<?php
/**
 * FPP Admin Menu & Settings
 * This file handles:
 * - Admin menu registration
 * - Settings page with reCAPTCHA v3 keys
 * - Render functions for dashboard and station management
 */

add_action( 'admin_menu', 'fpp_admin_register' );

function fpp_admin_register() {
    // Main menu item
    add_menu_page(
        'FPP Plugin Admin',     // Page title
        'FPP Admin',            // Menu title
        'manage_options',       // Capability
        'fpp-plugin-admin',     // Menu slug
        'fpp_admin_render',     // Callback
        'dashicons-camera',     // Icon (optional)
        65                      // Position (optional)
    );

    // Submenu: Manage Station 1
    add_submenu_page(
        'fpp-plugin-admin',
        'Manage Station 1',
        'Station 1',
        'edit_posts',
        'fpp-plugin-manage-1',
        'fpp_admin_manage_render'
    );

    // Submenu: Manage Station 2
    add_submenu_page(
        'fpp-plugin-admin',
        'Manage Station 2',
        'Station 2',
        'edit_posts',
        'fpp-plugin-manage-2',
        'fpp_admin_manage_render'
    );

    // Submenu: Settings
    add_submenu_page(
        'fpp-plugin-admin',
        'FPP Settings',
        'Settings',
        'manage_options',
        'fpp-settings',
        'fpp_settings_render'
    );
}

// Register settings on admin_init
add_action( 'admin_init', 'fpp_register_settings' );

function fpp_register_settings() {
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_site_key', 'sanitize_text_field' );
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_secret_key', 'sanitize_text_field' );

    add_settings_section(
        'fpp_recaptcha_section',
        'reCAPTCHA v3 Settings',
        'fpp_recaptcha_section_callback',
        'fpp-settings'
    );

    add_settings_field(
        'fpp_recaptcha_site_key',
        'Site Key',
        'fpp_recaptcha_site_key_callback',
        'fpp-settings',
        'fpp_recaptcha_section'
    );

    add_settings_field(
        'fpp_recaptcha_secret_key',
        'Secret Key',
        'fpp_recaptcha_secret_key_callback',
        'fpp-settings',
        'fpp_recaptcha_section'
    );
}

function fpp_recaptcha_section_callback() {
    echo '<p>Enter your Google reCAPTCHA v3 keys below. Get them from <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Google reCAPTCHA Admin</a> (select v3 type). v3 is invisible and scores user interactions.</p>';
}

function fpp_recaptcha_site_key_callback() {
    $value = get_option( 'fpp_recaptcha_site_key', '' );
    echo '<input type="text" id="fpp_recaptcha_site_key" name="fpp_recaptcha_site_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function fpp_recaptcha_secret_key_callback() {
    $value = get_option( 'fpp_recaptcha_secret_key', '' );
    echo '<input type="text" id="fpp_recaptcha_secret_key" name="fpp_recaptcha_secret_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function fpp_settings_render() {
    ?>
    <div class="wrap">
        <h1>FPP Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'fpp_settings_group' );
            do_settings_sections( 'fpp-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function fpp_admin_render() {
    global $title;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html( $title ) . '</h1>';

    $file = plugin_dir_path( __FILE__ ) . 'admin_dashboard.php';

    if ( file_exists( $file ) ) {
        require $file;
    } else {
        echo '<p><strong>File Not Found:</strong> ' . esc_html( $file ) . '</p>';
    }

    echo '</div>';
}

// Station management
function fpp_admin_manage_render() {
    global $title;

    // Extract station ID from page slug (e.g., fpp-plugin-manage-1 → 1)
    $page_slug   = $_GET['page'] ?? '';
    $parts       = explode( '-', $page_slug );
    $station_id  = is_numeric( end( $parts ) ) ? end( $parts ) : 0;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html( $title ) . '</h1>';

    $file = plugin_dir_path( __FILE__ ) . 'admin_manage.php';

    if ( file_exists( $file ) ) {
        require $file;
    } else {
        echo '<p><strong>File Not Found:</strong> ' . esc_html( $file ) . '</p>';
    }

    echo '</div>';
}
?>