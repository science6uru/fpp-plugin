<?php
/**
 * FPP Admin Menu & Settings
 * This file handles:
 * - Admin menu registration
 * - Settings page with reCAPTCHA v3 keys
 * - Render functions for dashboard and station management
 */

add_action('admin_enqueue_scripts', 'fpp_admin_styles');
add_action('admin_enqueue_scripts', 'fpp_admin_scripts');

function fpp_in_place_redirect() {
    // Get the current protocol (http or https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

    // Get the current hostname
    $host = $_SERVER['HTTP_HOST'];

    // Get the current request URI (path and query string)
    $uri = $_SERVER['REQUEST_URI'];

    // Construct the full current URL
    $current_url = "{$protocol}://{$host}{$uri}";

    header("Location:" . $current_url );
    exit();
}

function fpp_admin_scripts($hook) {
    wp_enqueue_script(
        'fpp-admin-js', 
        plugins_url( '/js/admin.js', __FILE__ ),
        ['jquery'], 
        false,
        true
    );
}

function fpp_admin_styles($hook) {
    $current_screen = get_current_screen();
    
    if ($current_screen && (
        strpos($current_screen->base, 'fpp-plugin') !== false || 
        strpos($current_screen->base, 'fpp-settings') !== false
    )) {
        wp_enqueue_style(
            'fpp-admin-style', 
            plugin_dir_url(__FILE__) . 'css/admin-style.css', 
            array(), 
            '1.7'
        );
    }
}

add_action( 'admin_menu', 'fpp_admin_register' );

function fpp_admin_register() {
    global $wpdb, $fpp_stations, $fpp_photos;
    $notification = "";
    if (!isset($_GET["page"]) || !str_starts_with($_GET['page'], "fpp")) {
        $pending_photos = $wpdb->get_var("SELECT COUNT(*) FROM $fpp_photos where status = 'unreviewed'");
        $notification = $pending_photos > 0?"<span class='awaiting-mod'>$pending_photos</span>":"";
    }
    add_menu_page(
        'Manage Photolog Stations', // Page title
        'Photolog'.$notification,   // Menu title
        'manage_options',           // Capability
        'fpp-plugin-admin',
        'fpp_stations_render',
        'dashicons-camera',         // Icon (optional)
        65                          // Position (optional)
    );

    // Dynamically add station submenus
    $stations = $wpdb->get_results("SELECT * FROM $fpp_stations ORDER BY id ASC");
    
    if ($stations) {
        foreach ($stations as $station) {
            $pending_photos = $wpdb->get_var("SELECT COUNT(*) FROM $fpp_photos where station_id = $station->id and status = 'unreviewed'");
            $notification = $pending_photos > 0?"<span class='awaiting-mod'>$pending_photos</span>":"";
            add_submenu_page(
                'fpp-plugin-admin',
                'Manage Station: ' . esc_html($station->name),
                esc_html($station->name) . $notification,
                'edit_posts',
                'fpp-plugin-manage-' . $station->slug,
                'fpp_admin_manage_render'
            );
        }
    }

    // Keep Settings as last item
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
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_low_threshold', 'fpp_sanitize_recaptcha_threshold' );
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_high_threshold', 'fpp_sanitize_recaptcha_threshold' );
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_v2_site_key', 'sanitize_text_field' );
    register_setting( 'fpp_settings_group', 'fpp_recaptcha_v2_secret_key', 'sanitize_text_field' );
    register_setting( 'fpp_settings_group', 'fpp_images_base_dir', 'fpp_sanitize_images_base_dir' );
    register_setting( 'fpp_settings_group', 'fpp_max_upload_size_mb', 'fpp_sanitize_max_upload_size_mb' );


    // reCAPTCHA v3 section
    add_settings_section(
        'fpp_recaptcha_section',
        'reCAPTCHA v3 Settings',
        'fpp_recaptcha_section_callback',
        'fpp-settings'
    );

    // reCAPTCHA v2 section
    add_settings_section(
        'fpp_recaptcha_v2_section',
        'reCAPTCHA v2 Settings',
        'fpp_recaptcha_v2_section_callback',
        'fpp-settings'
    );

    // Upload settings section
    add_settings_section(
        'fpp_upload_section',
        'Upload Settings',
        'fpp_upload_section_callback',
        'fpp-settings'
    );

    // reCAPTCHA v3 fields
    add_settings_field(
        'fpp_recaptcha_site_key',
        'Site Key (v3)',
        'fpp_recaptcha_site_key_callback',
        'fpp-settings',
        'fpp_recaptcha_section'
    );

    add_settings_field(
        'fpp_recaptcha_secret_key',
        'Secret Key (v3)',
        'fpp_recaptcha_secret_key_callback',
        'fpp-settings',
        'fpp_recaptcha_section'
    );

    add_settings_field(
        'fpp_recaptcha_low_threshold',
        'Low Threshold (v3)',
        'fpp_recaptcha_low_threshold_callback',
        'fpp-settings',
        'fpp_recaptcha_section',
        array( 'label_for' => 'fpp_recaptcha_low_threshold' )
    );

    add_settings_field(
        'fpp_recaptcha_high_threshold',
        'High Threshold (v3)',
        'fpp_recaptcha_high_threshold_callback',
        'fpp-settings',
        'fpp_recaptcha_section',
        array( 'label_for' => 'fpp_recaptcha_high_threshold' )
    );

    // reCAPTCHA v2 fields
    add_settings_field(
        'fpp_recaptcha_v2_site_key',
        'Site Key (v2)',
        'fpp_recaptcha_v2_site_key_callback',
        'fpp-settings',
        'fpp_recaptcha_v2_section'
    );

    add_settings_field(
        'fpp_recaptcha_v2_secret_key',
        'Secret Key (v2)',
        'fpp_recaptcha_v2_secret_key_callback',
        'fpp-settings',
        'fpp_recaptcha_v2_section'
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
    $default = 'fpp-plugin';
    
    if ($trimmed === '') {
        return $default;
    }

    // Ensure path is absolute and normalize separators
    $path = wp_normalize_path($trimmed);

    return trim($path, "/");
}

function fpp_recaptcha_section_callback() {
    echo '<p>Enter your Google reCAPTCHA v3 keys below. Get them from <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Google reCAPTCHA Admin</a> (select v3 type). v3 is invisible and scores user interactions.</p>';
    echo '<p><strong>Threshold System:</strong> Scores above High Threshold are accepted immediately. Scores between Low and High Threshold require reCAPTCHA v2 verification. Scores below Low Threshold are rejected.</p>';
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

function fpp_recaptcha_low_threshold_callback() {
    $value = get_option( 'fpp_recaptcha_low_threshold', '0.3' );
    echo '<input type="number" step="0.01" min="0" max="1" id="fpp_recaptcha_low_threshold" name="fpp_recaptcha_low_threshold" value="' . esc_attr( $value ) . '" placeholder="0.3" class="small-text" /> ';
    echo '<span class="description">Scores below this are rejected immediately.</span>';
}

function fpp_recaptcha_high_threshold_callback() {
    $value = get_option( 'fpp_recaptcha_high_threshold', '0.7' );
    echo '<input type="number" step="0.01" min="0" max="1" id="fpp_recaptcha_high_threshold" name="fpp_recaptcha_high_threshold" value="' . esc_attr( $value ) . '" placeholder="0.7" class="small-text" /> ';
    echo '<span class="description">Scores above this are accepted without v2 challenge.</span>';
}

function fpp_recaptcha_v2_section_callback() {
    echo '<p>Enter your Google reCAPTCHA v2 keys below. These will be used as a fallback when v3 verification fails or has low confidence.</p>';
}

function fpp_recaptcha_v2_site_key_callback() {
    $value = get_option( 'fpp_recaptcha_v2_site_key', '' );
    echo '<input type="text" id="fpp_recaptcha_v2_site_key" name="fpp_recaptcha_v2_site_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function fpp_recaptcha_v2_secret_key_callback() {
    $value = get_option( 'fpp_recaptcha_v2_secret_key', '' );
    echo '<input type="text" id="fpp_recaptcha_v2_secret_key" name="fpp_recaptcha_v2_secret_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
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
    $default = 'fpp-plugin';
    
    $path_to_check = empty($value) ? $default : $value;
    $dir_to_check = wp_upload_dir()['basedir'] . "/" . $path_to_check;
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
        $warning_html = '<span class="fpp-warning-icon" title="Directory issue">⚠</span>';
    }
    
    // JS: create + sync handlers
    ?>
    <script>
    function createDirectory() {
        const btn = document.getElementById("create-dir-btn");
        btn.disabled = true;
        btn.textContent = "Creating...";
        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fpp_create_directory",
                nonce: "<?php echo wp_create_nonce('fpp_create_directory'); ?>",
                path: "<?php echo esc_js($dir_to_check); ?>"
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
                nonce: "<?php echo wp_create_nonce('fpp_sync_station_folders'); ?>",
                path: "<?php echo esc_js($dir_to_check); ?>"
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
    </script>
    
    <div class="fpp-settings-field">
        <div class="input-container">
            <span class="prefix-text"><?= wp_upload_dir()["basedir"] ?>/</span>
            <input type="text" id="fpp_images_base_dir" name="fpp_images_base_dir" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($default); ?>" class="regular-text" />
        </div>
        <?php echo $warning_html; ?>
        <p class="description">This is where uploaded photos will be stored.<br/>Changing this value after photos have been uploaded will result in failure to locate images; they will have to be manually moved to the new location.</p>
    </div>
    <?php
    
    if (!$exists) {
        ?>
        <div class="fpp-warning-message">
            <span>The specified directory does not exist.</span>
            <button type="button" id="create-dir-btn" onclick="createDirectory()" class="fpp-create-dir-btn">Create Directory</button>
        </div>
        <?php
    } elseif (!$is_writable) {
        ?>
        <div class="fpp-warning-message">
            <span>The specified directory is not writable by the web server. Please ensure the directory has proper write permissions.</span>
        </div>
        <?php
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
            ?>
            <div class="fpp-warning-message">
                <span>Detected discrepancy between DB and folders. <?php echo esc_html($sync_note); ?></span>
                <button type="button" id="sync-dir-btn" onclick="syncStationFolders()" class="fpp-sync-dir-btn">Sync Station Folders</button>
            </div>
            <?php
        } else {
            ?>
            <div class="fpp-success-message">
                <p>Directory exists and is writable. Station folders are in sync with the database.</p>
            </div>
            <?php
        }
    }

    // Troubleshooting
    ?>
    <div class="fpp-destructive-section">
        <h3>Troubleshooting</h3>
        <p><strong>Warning:</strong> These advanced actions are for directory management only. Use with extreme caution, as they may result in permanent data loss.</p>
        <div class="fpp-troubleshoot-actions">
            <button type="button" id="rebuild-dir-btn" onclick="if(confirm('This action would DELETE all image files inside the station subdirectories. Are you sure?')){ alert('Action not implemented.'); }" class="fpp-action-btn delete">Full Rebuild (delete images)</button>
        </div>
    </div>
    <?php
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

    $file = plugin_dir_path( __FILE__) . 'admin_dashboard.php';

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
    // Extract station slug from page slug (e.g., fpp-plugin-manage-bluebird-prairie → bluebird-prairie)
    $page_slug   = $_GET['page'] ?? '';
    $station_slug       = str_replace( 'fpp-plugin-manage-', "", $page_slug );

    // Get station name for display
    global $wpdb, $fpp_stations;
    $station_name = '';
    $station_id = null;
    if (!empty($station_slug)  && !empty($fpp_stations)) {
        $station = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fpp_stations WHERE slug = %s", $station_slug));
        if ($station) {
            $station_name = esc_html($station->name);
            $station_id = $station->id;
        }
    }
    ?>
    <div class="wrap">
        <!-- <h1><?php echo esc_html( $title ); ?></h1>

        <div class="fpp-destructive-section">
            <h3>Delete All Photos</h3>
            <p><strong>Warning:</strong> This action will permanently delete all photos for this station from the database and file system. This cannot be undone.</p>
            <button type="button" id="delete-all-photos-btn" class="fpp-action-btn delete" onclick="deleteAllPhotos(<?php echo $station_id; ?>)">Delete All Photos</button>
        </div> -->

        <script>
        function deleteAllPhotos(stationId) {
            if (!confirm("Are you sure you want to delete ALL photos for this station? This will remove them from the database and file system permanently.")) {
                return;
            }
            const btn = document.getElementById("delete-all-photos-btn");
            btn.disabled = true;
            btn.textContent = "Deleting...";
            fetch(ajaxurl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "fpp_delete_station_photos",
                    nonce: "<?php echo wp_create_nonce('fpp_delete_station_photos'); ?>",
                    station_id: stationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Success: " + data.data);
                    location.reload();
                } else {
                    alert("Error: " + data.data);
                    btn.disabled = false;
                    btn.textContent = "Delete All Photos";
                }
            })
            .catch(error => {
                alert("Error: " + error);
                btn.disabled = false;
                btn.textContent = "Delete All Photos";
            });
        }
        </script>

        <?php
        $file = plugin_dir_path( __FILE__) . 'admin_manage.php';
        if ( file_exists( $file ) ) {
            require $file;
        } else {
            echo '<p><strong>File Not Found:</strong> ' . esc_html( $file ) . '</p>';
        }
        ?>
    </div>
    <?php
}

// This won't stay in the long term
add_action('wp_ajax_fpp_delete_station_photos', function() {
    check_ajax_referer('fpp_delete_station_photos', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $station_id = isset($_POST['station_id']) ? intval($_POST['station_id']) : 0;
    if ($station_id <= 0) {
        wp_send_json_error('Invalid station ID');
        return;
    }

    global $wpdb;
    $prefix = $wpdb->prefix;
    $photos_table = $prefix . 'fpp_photos';

    // Delete photos from database
    $deleted_db = $wpdb->delete(
        $photos_table,
        array('station_id' => $station_id),
        array('%d')
    );

    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }

    $station_dir = fpp_photos_dir($station_id);

    if (!is_dir($station_dir)) {
        wp_send_json_error('Station directory not found');
        return;
    }

    $deleted_files = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($station_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        if ($todo($fileinfo->getRealPath())) {
            $deleted_files++;
        }
    }

    wp_send_json_success("Deleted $deleted_db photos from database and $deleted_files files from directory.");
});

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
    $configured = fpp_photos_dir();
    $path = rtrim($path, '/');
    if ($path !== $configured) {
        wp_send_json_error('Path mismatch with configured base directory.');
        return;
    }

    // Get DB station IDs
    $db_stations = $wpdb->get_results("SELECT id, name FROM {$fpp_stations} ORDER BY id ASC", ARRAY_A);
    if ($wpdb->last_error) {
        wp_send_json_error('DB error: ' . $wpdb->last_error);
        return;
    }
    $db_ids = array_column($db_stations, 'id');

    // Get filesystem station IDs 
    $fs_stations = array();
    foreach (glob($path . '/station-*', GLOB_ONLYDIR) as $dir) {
        if (preg_match('/station-(\d+)$/', $dir, $m)) {
            $id = (int)$m[1];
            $files = glob($dir . '/*');
            $fs_stations[$id] = array(
                'path' => $dir,
                'has_files' => !empty($files)
            );
        }
    }
    
    $created = 0;
    $removed = 0;
    $auto_added = 0;
    

    // Handle directories with no DB entry
    foreach ($fs_stations as $id => $info) {
        if (!in_array($id, $db_ids)) {
            if ($info['has_files']) {
                // Directory has files but no DB entry - auto add it
                // TODO: Right now, they don't get added to the database so they just stay as unlinked images when this happens, fix later
                $wpdb->insert(
                    $fpp_stations,
                    array('id' => $id, 'name' => "Auto-added Station {$id}"), 
                    array('%d', '%s')
                );
                $auto_added++;
            } else {
                // Empty directory, remove it
                @rmdir($info['path']);
                $removed++;
            }
        }
    }

    // Create missing directories for DB entries
    foreach ($db_ids as $id) {
        if (!isset($fs_stations[$id])) {
            $d = $path . '/station-' . $id;
            if (wp_mkdir_p($d)) {
                $created++;
            }
        }
    }

    $parts = array();
    if ($created > 0) { $parts[] = "created: {$created}"; }
    if ($removed > 0) { $parts[] = "removed: {$removed}"; }
    if ($auto_added > 0) { $parts[] = "auto-added: {$auto_added}"; }
    
    wp_send_json_success(empty($parts) ? "No changes needed" : implode('; ', $parts));
});

add_action('wp_ajax_fpp_verify_recaptcha_score', 'fpp_verify_recaptcha_score_handler');
add_action('wp_ajax_nopriv_fpp_verify_recaptcha_score', 'fpp_verify_recaptcha_score_handler');

function fpp_verify_recaptcha_score_handler() {
    check_ajax_referer('fpp_verify_recaptcha_score', 'nonce');
    
    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    if (empty($token)) {
        wp_send_json_error('No token provided');
        return;
    }
    
    $v3_secret_key = get_option('fpp_recaptcha_secret_key');
    if (empty($v3_secret_key)) {
        wp_send_json_error('reCAPTCHA v3 not configured');
        return;
    }
    
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    
    $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
        'timeout' => 10,
        'body' => array(
            'secret' => $v3_secret_key,
            'response' => $token,
            'remoteip' => $remote_ip,
        ),
    ));
    
    if (is_wp_error($verify_response)) {
        error_log('FPP reCAPTCHA v3 verification error: ' . $verify_response->get_error_message());
        wp_send_json_error('Connection to reCAPTCHA service failed');
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($verify_response);
    if ($response_code !== 200) {
        error_log('FPP reCAPTCHA v3 HTTP error: ' . $response_code);
        wp_send_json_error('reCAPTCHA service returned error: ' . $response_code);
        return;
    }
    
    $response_body = wp_remote_retrieve_body($verify_response);
    $response_data = json_decode($response_body);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('FPP reCAPTCHA v3 JSON parse error: ' . json_last_error_msg());
        wp_send_json_error('Invalid response from reCAPTCHA service');
        return;
    }
    
    if (!$response_data || !isset($response_data->success)) {
        error_log('FPP reCAPTCHA v3 invalid response: ' . $response_body);
        wp_send_json_error('Invalid response format from reCAPTCHA service');
        return;
    }
    
    if (!$response_data->success) {
        $error_codes = isset($response_data->{'error-codes'}) ? implode(', ', $response_data->{'error-codes'}) : 'Unknown error';
        error_log('FPP reCAPTCHA v3 verification failed: ' . $error_codes);
        wp_send_json_error('reCAPTCHA verification failed: ' . $error_codes);
        return;
    }
    
    $low_threshold = floatval(get_option('fpp_recaptcha_low_threshold', '0.3'));
    $high_threshold = floatval(get_option('fpp_recaptcha_high_threshold', '0.7'));
    $score = isset($response_data->score) ? floatval($response_data->score) : 0;
    
    // Determine if v2 is required
    $requires_v2 = ($score >= $low_threshold && $score < $high_threshold);
    
    wp_send_json_success(array(
        'score' => $score,
        'requires_v2' => $requires_v2,
        'low_threshold' => $low_threshold,
        'high_threshold' => $high_threshold
    ));
}

function fpp_photo_file_path($station_id, $filename) {
    $base_dir = fpp_photos_dir($station_id);
    return rtrim($base_dir, '/') . '/' . ltrim($filename, '/');
}

function fpp_check_admin_post() {
    global $wpdb, $fpp_stations, $fpp_photos;
    if (isset($_POST['action']) && in_array($_POST['action'] , ['add_station', 'update_station', 'delete_station', 'update_station_upload_slug', 'fpp_photo_delete', 'fpp_photo_update_status'])) {
        $nonce_action = "fpp_stations_nonce";
        if (in_array($_POST['action'], ['fpp_photo_update_status', 'fpp_photo_delete'])) {
            $nonce_action = 'fpp_photo_manage';
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
            add_settings_error(
                'fpp_stations',
                'nonce_fail',
                'Security check failed. Please try again.',
                'error'
            );
        } else {
            $action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
            switch ($action) {
                case 'add_station':
                    if (isset($_POST['station_name'])) {
                        $name = sanitize_text_field($_POST['station_name']);
                        
                        // Get the highest station ID and inc by 1
                        $next_id = $wpdb->get_var("SELECT MAX(id) FROM $fpp_stations");
                        $next_id = intval($next_id) + 1;
                        
                        $wpdb->insert(
                            $fpp_stations,
                            array(
                                'id' => $next_id,
                                'name' => $name,
                                'slug' =>  str_replace(" ", "-", strtolower($name)) 
                            ),
                            array('%d', '%s', '%s')
                        );
                        
                        // Create station directory
                        wp_mkdir_p(fpp_photos_dir($next_id));
                        add_settings_error('fpp_stations', 'station_added', 'Station added.', 'updated');
                    }
                    break;

                case 'update_station':
                    if (isset($_POST['station_id']) && isset($_POST['station_name'])) {
                        $update_data = array('name' => sanitize_text_field($_POST['station_name']));
                        $update_format = array('%s');
                        
                        if (isset($_POST['station_upload_slug'])) {
                            $update_data['upload_page_slug'] = sanitize_text_field($_POST['station_upload_slug']);
                            $update_format[] = '%s';
                        }
                        
                        $update_data['slug'] = str_replace(" ", "-", strtolower(sanitize_text_field($_POST['station_name'])));
                        $update_format[] = '%s';
                        
                        $wpdb->update(
                            $fpp_stations,
                            $update_data,
                            array('id' => intval($_POST['station_id'])),
                            $update_format,
                            array('%d')
                        );
                        add_settings_error('fpp_stations', 'station_updated', 'Station updated.', 'updated');
                    }
                    break;

                case 'update_station_upload_slug':
                    if (isset($_POST['station_id']) && isset($_POST['station_upload_slug'])) {
                        $wpdb->update(
                            $fpp_stations,
                            array('upload_page_slug' => sanitize_text_field($_POST['station_upload_slug'])),
                            array('id' => intval($_POST['station_id'])),
                            array('%s'),
                            array('%d')
                        );
                        add_settings_error('fpp_stations', 'station_updated', 'Station updated.', 'updated');
                    }
                    break;

                case 'delete_station':
                    if (isset($_POST['station_id'])) {
                        $station_id = intval($_POST['station_id']);
                        
                        // Check if station has photos
                        $photos_count = intval( $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}fpp_photos WHERE station_id = %d",
                            $station_id
                        ) ) );

                        if ($photos_count > 0) {
                            add_settings_error(
                                'fpp_stations',
                                'station_has_photos',
                                'Cannot delete station: It has associated photos.',
                                'error'
                            );
                        } else {
                            $deleted = $wpdb->delete($fpp_stations, array('id' => $station_id), array('%d'));
                            if ( $deleted === false ) {
                                add_settings_error('fpp_stations', 'delete_failed', 'Failed to delete station (DB error).', 'error');
                            } else {
                                // Remove station directory if empty
                                $station_dir = fpp_photos_dir($station_id);
                                if (is_dir($station_dir) && (glob($station_dir . '/*') === false || count(glob($station_dir . '/*')) === 0)) {
                                    @rmdir($station_dir);
                                }
                                add_settings_error('fpp_stations', 'station_deleted', 'Station deleted.', 'updated');
                            }
                        }
                    }
                    break;
                case 'fpp_photo_delete':
                    $id = isset($_POST['fpp_photo_id']) ? intval($_POST['fpp_photo_id']) : 0;
                    if ($id <= 0) {
                        wp_send_json_error('Invalid station ID');
                        return;
                    }
                    
                    $photo = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM $fpp_photos WHERE id = %d",
                        intval($id)
                    ) );
                    
                    if ($photo) {
                        $station_id = intval($photo->station_id);
                        $file_path = fpp_photo_file_path($station_id, $photo->file_name);
                        $thumb_path = fpp_photo_file_path($station_id, $photo->thumb_200);
                        $image_2000_path = fpp_photo_file_path($station_id, $photo->image_2000);

                        // Delete from fs
                        if (file_exists($file_path)) {
                            @unlink($file_path);
                        }
                        if (file_exists($thumb_path)) {
                            @unlink($thumb_path);
                        }
                        if (file_exists($image_2000_path)) {
                            @unlink($image_2000_path);
                        }
                        //Delete from DB
                        $wpdb->delete(
                            $fpp_photos,
                            array('id' => intval($id)),
                            array('%d')
                        );
                    }
                    break;
                case 'fpp_photo_update_status':
                    $status = $_POST['fpp_photo_status'];
                    $id = $_POST['fpp_photo_id'];
                    $wpdb->update(
                        $fpp_photos,
                        array('status' => sanitize_text_field($status)),
                        array('id' => intval($id)),
                        array('%s'),
                        array('%d')
                    );
                    if ($status == 'approved') {
                        $photo = $wpdb->get_row("select * from $fpp_photos where id = ". intval($id). ";");
                        if ($photo) {
                            fpp_generate_display_image($photo);
                        }
                    }
                    break;
            }
            fpp_in_place_redirect();
        }
    }
}
add_action( 'admin_init', 'fpp_check_admin_post' );


function fpp_stations_render() {
    global $wpdb, $fpp_stations;

    // Handle form submissions
    

    // Get current stations
    $stations = $wpdb->get_results("SELECT s.*, COUNT(p.id) as photo_count 
        FROM $fpp_stations s 
        LEFT JOIN {$wpdb->prefix}fpp_photos p ON s.id = p.station_id 
        GROUP BY s.id 
        ORDER BY s.id ASC");

    ?>
    <div class="wrap">
        <h1>Manage Photolog Stations</h1>
        <?php settings_errors('fpp_stations'); ?>

        <!-- Add New Station -->
        <div class="card" style="margin-bottom:18px;">
            <h2>Add New Station</h2>
            <form method="post" action="">
                <?php wp_nonce_field('fpp_stations_nonce'); ?>
                <input type="hidden" name="action" value="add_station">
                <input type="hidden" name="auto_update" value="true">
                <p>
                    <label for="station_name">Station Name:</label>
                    <input type="text" name="station_name" id="station_name" required>
                </p>
                <?php submit_button('Add Station'); ?>
            </form>
        </div>

        <!-- Existing Stations  -->
        <div class="">
            <h2>Existing Stations</h2>
            <table class="wp-list-table widefat fixed striped fpp-stations-table">
                <thead>
                    <tr>
                        <th style="width:20px;">ID</th>
                        <th class="name-col">Name</th>
                        <th class="photos-col">Photos</th>
                        <th class="slug-col">Upload Page Slug</th>
                        <th style="width:180px;">Actions</th>
                        <th class="upload-shortcode-col">Upload Shortcode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stations as $station): ?>
                    <tr>
                        <td><?php echo esc_html($station->id); ?></td>
                        <td>
                            <form method="post" action="" style="display:inline; width:100%;" id="form-station-<?php echo esc_attr($station->id); ?>">
                                <?php wp_nonce_field('fpp_stations_nonce'); ?>
                                <input type="hidden" name="action" value="update_station">
                                <input type="hidden" name="station_id" value="<?php echo esc_attr($station->id); ?>">
                                <input type="text" name="station_name" value="<?php echo esc_attr($station->name); ?>" class="fpp-station-name-input">
                        </td>
                        <td style="text-align:center;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fpp-plugin-manage-' . esc_attr($station->slug) ) ); ?>" class="fpp-photo-link">
                                <div class="fpp-photo-box" aria-label="Photo count: <?php echo esc_attr( intval( $station->photo_count ) ); ?>">
                                    <?php echo esc_html( intval( $station->photo_count ) ); ?>
                                </div>
                            </a>
                        </td>
                        <td>
                                <input type="text" name="station_upload_slug" value="<?php echo esc_attr($station->upload_page_slug); ?>" class="fpp-station-slug-input">
                        </td>
                        <td class="actions-col">
                            <button type="submit" form="form-station-<?php echo esc_attr($station->id); ?>" class="fpp-action-btn update">Update</button>
                            </form>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field('fpp_stations_nonce'); ?>
                                <input type="hidden" name="action" value="delete_station">
                                <input type="hidden" name="station_id" value="<?php echo esc_attr($station->id); ?>">
                                <button type="submit" class="fpp-action-btn delete" 
                                        <?php if ($station->photo_count > 0): ?>
                                            disabled
                                            data-tooltip="Cannot delete: Station has <?php echo esc_attr($station->photo_count); ?> photo<?php echo $station->photo_count > 1 ? 's' : ''; ?>"
                                        <?php else: ?>
                                            onclick="return confirm('Are you sure you want to delete this station?')"
                                        <?php endif; ?>>
                                    Delete
                                </button>
                            </form>
                        </td>
                        <td style="text-align:left;" class="upload-shortcode-col">
                            <input type="text" readonly value="[fpp_upload station=<?= $station->slug ?>]"/>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $stations ) ) : ?>
                    <tr>
                        <td colspan="4"><?php echo esc_html__('No stations found.', 'fpp-plugin'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
add_action( 'pre_get_posts', 'fpp_search_exclude_filter' );
function fpp_search_exclude_filter($query) {
    /*
    This function excludes the fpp upload pages from search results.
    */
    global $wpdb, $fpp_stations;
    if (! $query->is_admin && $query->is_search && $query->is_main_query()) {
        $excluded_slugs = $wpdb->get_col("SELECT upload_page_slug FROM $fpp_stations");

        // Get the IDs of the pages based on their slugs
        $excluded_ids = array();
        foreach ($excluded_slugs as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page) {
                $excluded_ids[] = $page->ID;
            }
        }

        // Get any existing exclusions and merge them
        $existing_exclusions = (array) $query->get('post__not_in');
        $merged_exclusions = array_merge($existing_exclusions, $excluded_ids);

        // Set the merged exclusions back into the query
        $query->set('post__not_in', $merged_exclusions);
    }
}

?>