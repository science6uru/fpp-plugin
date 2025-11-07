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
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_threshold', 'fpp_sanitize_recaptcha_threshold' );
    register_setting( 'fpp_settings_group', 'fpp_images_base_dir', 'fpp_sanitize_images_base_dir' );
    register_setting( 'fpp_settings_group', 'fpp_max_upload_size_mb', 'fpp_sanitize_max_upload_size_mb' );

    // reCAPTCHA section
    add_settings_section(
        'fpp_recaptcha_section',
        'reCAPTCHA v3 Settings',
        'fpp_recaptcha_section_callback',
        'fpp-settings'
    );

    // Upload settings section
    add_settings_section(
        'fpp_upload_section',
        'Upload Settings',
        'fpp_upload_section_callback',
        'fpp-settings'
    );

    // reCAPTCHA fields
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

    add_settings_field(
        'fpp_recaptcha_threshold',
        'Detection Threshold',
        'fpp_recaptcha_threshold_callback',
        'fpp-settings',
        'fpp_recaptcha_section',
        array( 'label_for' => 'fpp_recaptcha_threshold' )
    );

    // Upload settings fields
    add_settings_field(
        'fpp_images_base_dir',
        'Images Base Directory',
        'fpp_images_base_dir_callback',
        'fpp-settings',
        'fpp_upload_section',
        array( 'label_for' => 'fpp_images_base_dir' )
    );

    add_settings_field(
        'fpp_max_upload_size_mb',
        'Max Upload Size (MB)',
        'fpp_max_upload_size_mb_callback',
        'fpp-settings',
        'fpp_upload_section',
        array( 'label_for' => 'fpp_max_upload_size_mb' )
    );
}

function fpp_sanitize_recaptcha_threshold( $value ) {
    // If saved empty, use default 0.5
    $trimmed = is_scalar( $value ) ? trim( (string) $value ) : '';
    if ( $trimmed === '' ) {
        return '0.5';
    }

    $f = floatval( $trimmed );
    if ( $f < 0.0 ) { $f = 0.0; }
    if ( $f > 1.0 ) { $f = 1.0; }
    return (string) $f;
}

function fpp_sanitize_max_upload_size_mb( $value ) {
    // If saved empty, use default 8 MB
    $trimmed = is_scalar( $value ) ? trim( (string) $value ) : '';
    if ( $trimmed === '' ) {
        return '8';
    }

    $f = floatval( $trimmed );
    if ( $f < 0.0 ) { $f = 0.0; }
    if ( $f > 512.0 ) { $f = 512.0; }
    return (string) $f;
}

function fpp_sanitize_images_base_dir($value) {
    // If saved empty or invalid, use default path
    $trimmed = is_scalar($value) ? trim((string)$value) : '';
    $default = wp_upload_dir()['basedir'] . '/fpp-plugin';
    
    if ($trimmed === '') {
        return $default;
    }

    // Ensure path is absolute and normalize separators
    $path = wp_normalize_path($trimmed);
    if (!path_is_absolute($path)) {
        return $default;
    }

    // Basic security: prevent paths outside WP uploads
    $uploads_base = wp_normalize_path(wp_upload_dir()['basedir']);
    if (strpos($path, $uploads_base) !== 0) {
        return $default;
    }

    return $path;
}

function fpp_recaptcha_section_callback() {
    echo '<p>Enter your Google reCAPTCHA v3 keys below. Get them from <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Google reCAPTCHA Admin</a> (select v3 type). v3 is invisible and scores user interactions.</p>';
}

function fpp_upload_section_callback() {
    echo '<p>Configurations for file submissions.</p>';
}

function fpp_recaptcha_site_key_callback() {
    $value = get_option( 'fpp_recaptcha_site_key', '' );
    echo '<input type="text" id="fpp_recaptcha_site_key" name="fpp_recaptcha_site_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function fpp_recaptcha_secret_key_callback() {
    $value = get_option( 'fpp_recaptcha_secret_key', '' );
    echo '<input type="text" id="fpp_recaptcha_secret_key" name="fpp_recaptcha_secret_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function fpp_recaptcha_threshold_callback() {
    $value = get_option( 'fpp_recaptcha_threshold', '0.0' );
    // show default as placeholder (greyed out) when field is empty
    echo '<input type="number" step="0.01" min="0" max="1" id="fpp_recaptcha_threshold" name="fpp_recaptcha_threshold" value="' . esc_attr( $value ) . '" placeholder="0.5" class="small-text" /> ';
    echo '<span class="description">Score cutoff (0.0 - 1.0). Lower is more forgiving; higher is stricter.</span>';
}

function fpp_check_dir_writable($dir) {
    if (empty($dir)) return false;
    if (!is_dir($dir)) {
        return is_writable(dirname($dir));
    }
    return is_writable($dir);
}

function fpp_check_dir_exists($dir) {
    return !empty($dir) && is_dir($dir);
}

function fpp_images_base_dir_callback() {
    global $wpdb, $fpp_stations;

    $value = get_option('fpp_images_base_dir', '');
    $default = wp_upload_dir()['basedir'] . '/fpp-plugin';
    
    $dir_to_check = empty($value) ? $default : $value;
    $exists = fpp_check_dir_exists($dir_to_check);
    $is_writable = fpp_check_dir_writable($dir_to_check);
    $stations_table = !empty($fpp_stations) ? $fpp_stations : $wpdb->prefix . 'fpp_stations';
    $table_exists = false;
    $res = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $stations_table) );
    if ($res === $stations_table) {
        $table_exists = true;
    }

    $warning_html = '';
    if (!$exists || !$is_writable) {
        $warning_html = '<span class="fpp-warning-icon" style="color: #f0ad4e; margin-left: 5px;" title="Directory issue">⚠</span>';
    }
    
    echo '<style>
        .fpp-warning-message {
            color: #856404;
            background-color: #fff3cd;
            border-left: 4px solid #f0ad4e;
            padding: 10px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fpp-create-dir-btn, .fpp-sync-dir-btn, .fpp-rebuild-dir-btn {
            background: #fff;
            border: 1px solid #f0ad4e;
            padding: 5px 10px;
            cursor: pointer;
            color: #856404;
            margin-left: 8px;
        }
        .fpp-create-dir-btn:hover, .fpp-sync-dir-btn:hover { background: #f0ad4e; color: #fff; }
        .fpp-rebuild-dir-btn { border-color: #d9534f; color: #d9534f; }
        .fpp-rebuild-dir-btn:hover { background: #d9534f; color: #fff; }
    </style>';

    // JS: create + sync handlers
    echo '<script>
    function createDirectory() {
        const btn = document.getElementById("create-dir-btn");
        btn.disabled = true;
        btn.textContent = "Creating...";
        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fpp_create_directory",
                nonce: "' . wp_create_nonce('fpp_create_directory') . '",
                path: "' . esc_js($dir_to_check) . '"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + data.data);
                btn.disabled = false;
                btn.textContent = "Create Directory";
            }
        })
        .catch(error => {
            alert("Error: " + error);
            btn.disabled = false;
            btn.textContent = "Create Directory";
        });
    }

    function syncStationFolders() {
        const btn = document.getElementById("sync-dir-btn");
        btn.disabled = true;
        btn.textContent = "Syncing...";
        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fpp_sync_station_folders",
                nonce: "' . wp_create_nonce('fpp_sync_station_folders') . '",
                path: "' . esc_js($dir_to_check) . '"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Sync completed: " + (data.data || "OK"));
                location.reload();
            } else {
                alert("Error: " + data.data);
                btn.disabled = false;
                btn.textContent = "Sync Station Folders";
            }
        })
        .catch(error => {
            alert("Error: " + error);
            btn.disabled = false;
            btn.textContent = "Sync Station Folders";
        });
    }
    </script>';
    
    echo '<input type="text" id="fpp_images_base_dir" name="fpp_images_base_dir" value="' . esc_attr($value) . '" placeholder="' . esc_attr($default) . '" class="regular-text" />';
    echo $warning_html;
    
    echo '<p class="description">This is where uploaded photos will be stored.</p>';
    
    if (!$exists) {
        echo '<div class="fpp-warning-message">
            <span>The specified directory does not exist.</span>
            <button type="button" id="create-dir-btn" onclick="createDirectory()" class="fpp-create-dir-btn">Create Directory</button>
        </div>';
    } elseif (!$is_writable) {
        echo '<div class="fpp-warning-message">
            <span>The specified directory is not writable by the web server. Please ensure the directory has proper write permissions.</span>
        </div>';
    } else {
        $show_sync = false;
        $sync_note = '';

        if ($table_exists) {
            // Get ids from DB
            $ids = $wpdb->get_col("SELECT id FROM {$stations_table} ORDER BY id ASC");
            if ($wpdb->last_error) {
                $ids = array();
            }
            $ids = array_map('intval', $ids);

            // Get station folders on fs
            $dir_ids = array();
            foreach (glob(rtrim($dir_to_check, '/') . '/station-*', GLOB_ONLYDIR) as $d) {
                if (preg_match('/station-(\d+)$/', basename($d), $m)) {
                    $dir_ids[] = (int)$m[1];
                }
            }
            sort($ids);
            sort($dir_ids);

            $missing = array_values(array_diff($ids, $dir_ids)); // in DB but missing on fs
            $extra   = array_values(array_diff($dir_ids, $ids)); // on fs but not in DB

            if (!empty($missing) || !empty($extra)) {
                $show_sync = true;
                $parts = array();
                if (!empty($missing)) { $parts[] = 'missing: ' . implode(',', array_slice($missing,0,10)); }
                if (!empty($extra))   { $parts[] = 'extra: ' . implode(',', array_slice($extra,0,10)); }
                $sync_note = implode('; ', $parts);
            }
        }

        if ($show_sync) {
            echo '<div class="fpp-warning-message">
                <span>Detected discrepancy between DB and folders. ' . esc_html($sync_note) . '</span>
                <button type="button" id="sync-dir-btn" onclick="syncStationFolders()" class="fpp-sync-dir-btn">Sync Station Folders</button>
            </div>';
        }
        else {
            echo '<p class="description" style="color: green;">Directory exists and is writable. Station folders are in sync with the database.</p>';
        }
    }

    // Troubleshooting
    echo '<div style="margin-top:12px;padding:10px;border:1px solid #ddd;background:#f7f7f7;">
        <strong>Troubleshooting</strong>
        <p style="margin:6px 0 8px 0;">Stuff you probably won\'t need to use a lot. Use with caution. </p>
        <button type="button" id="rebuild-dir-btn" onclick="if(confirm(\'(Placeholder - not functional) \nThis would DELETE all image files inside the station subdirectories. Are you sure?\')){ alert(\'Action not implemented.\'); }" class="fpp-rebuild-dir-btn">Full Rebuild (delete images)</button>
    </div>';
}

function fpp_max_upload_size_mb_callback() {
    $value = get_option( 'fpp_max_upload_size_mb', '0.0' ); // MB
    $server_upload = ini_get('upload_max_filesize');
    $server_post   = ini_get('post_max_size');
    // show default as placeholder (greyed out) when field is empty
    echo '<input type="number" step=".1" min="0" id="fpp_max_upload_size_mb" name="fpp_max_upload_size_mb" value="' . esc_attr( $value ) . '" placeholder="8" class="small-text" /> ';
    echo '<span class="description">Does not affect PHP server upload limit. PHP server limit: ' . esc_html( $server_upload ) . 'B.</span>';
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

add_action('wp_ajax_fpp_create_directory', function() {
    check_ajax_referer('fpp_create_directory', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
    if (empty($path) || !path_is_absolute($path)) {
        wp_send_json_error('Invalid path specified');
        return;
    }

    // Ensure base directory exists
    if (!wp_mkdir_p($path)) {
        wp_send_json_error('Failed to create base directory');
        return;
    }

    global $wpdb, $fpp_stations;
    
    // Get actual station IDs from database
    $ids = $wpdb->get_col("SELECT id FROM $fpp_stations ORDER BY id ASC");
    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }

    if (empty($ids)) {
        wp_send_json_error('No stations found in database');
        return;
    }

    $created = 0;
    foreach ($ids as $id) {
        $station_dir = $path . '/station-' . intval($id);
        if (!wp_mkdir_p($station_dir)) {
            error_log("FPP Plugin - Failed to create directory: $station_dir");
            wp_send_json_error("Failed to create station-$id directory");
            return;
        }
        $created++;
    }
    
    wp_send_json_success("Created base directory and $created station directories");
});

add_action('wp_ajax_fpp_sync_station_folders', function() {
    check_ajax_referer('fpp_sync_station_folders', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    global $wpdb, $fpp_stations;
    $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
    if (empty($path)) {
        wp_send_json_error('No path specified');
        return;
    }

    // Only target the configured base dir
    $configured = get_option('fpp_images_base_dir', wp_upload_dir()['basedir'] . '/fpp-plugin');
    $configured = rtrim($configured, '/');
    $path = rtrim($path, '/');
    if ($path !== $configured) {
        wp_send_json_error('Path mismatch with configured base directory.');
        return;
    }

    // Ensure we know the stations table
    $stations_table = !empty($fpp_stations) ? $fpp_stations : $wpdb->prefix . 'fpp_stations';
    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $stations_table) );
    if ($exists !== $stations_table) {
        wp_send_json_error('Stations table not found.');
        return;
    }

    // Get station ids from DB
    $ids = $wpdb->get_col("SELECT id FROM {$stations_table} ORDER BY id ASC");
    if ($wpdb->last_error) {
        wp_send_json_error('DB error: ' . $wpdb->last_error);
        return;
    }
    $ids = array_map('intval', $ids);

    // Ensure station dirs exist, create missing
    $created = 0;
    foreach ($ids as $id) {
        $d = $path . '/station-' . $id;
        if (!is_dir($d)) {
            if (wp_mkdir_p($d)) {
                $created++;
            } else {
                // continue, report later
            }
        }
    }

    // Remove station-* dirs that are not in DB, only if tyhey're empty
    $removed = 0;
    $skipped_nonempty = array();
    foreach (glob($path . '/station-*', GLOB_ONLYDIR) as $dir) {
        $basename = basename($dir);
        if (preg_match('/station-(\d+)$/', $basename, $m)) {
            $sid = (int)$m[1];
            if (!in_array($sid, $ids, true)) {
                $files = glob($dir . '/*');
                if ($files === false || count($files) === 0) {
                    if (@rmdir($dir)) {
                        $removed++;
                    } else {
                        $skipped_nonempty[] = $basename;
                    }
                } else {
                    $skipped_nonempty[] = $basename;
                }
            }
        }
    }

    $parts = array();
    $parts[] = "created: {$created}";
    $parts[] = "removed: {$removed}";
    if (!empty($skipped_nonempty)) {
        $parts[] = "skipped_nonempty: " . implode(',', array_slice($skipped_nonempty,0,10));
    }

    wp_send_json_success(implode('; ', $parts));
});
?>