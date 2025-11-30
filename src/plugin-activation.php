<?php

global $fpp_db_version;
global $fpp_photos;
global $fpp_stations;
global $wpdb;

$fpp_db_version = '1.9';
$fpp_photos = $wpdb->prefix . 'fpp_photos';
$fpp_stations = $wpdb->prefix . 'fpp_stations';

function fpp_install_data() {
    global $wpdb, $fpp_stations;

    // Scan for existing station directories
    $upload_dir = fpp_photos_dir();
    $pattern = $upload_dir . '/station-*';
    $existing_dirs = glob($pattern, GLOB_ONLYDIR);
    
    if ($existing_dirs) {
        foreach ($existing_dirs as $dir) {
            if (preg_match('/station-(\d+)$/', $dir, $matches)) {
                $station_id = intval($matches[1]);
                $station = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $fpp_stations WHERE id = %d",
                    $station_id
                ));
                
                if (!$station) {
                    $wpdb->insert(
                        $fpp_stations,
                        array(
                            'id' => $station_id,
                            'name' => "FPP Station $station_id"
                        ),
                        array('%d', '%s')
                    );
                }
            }
        }
    } else {
        // Create at least one default station if none exist
        $wpdb->insert(
            $fpp_stations,
            array(
                'id' => 1,
                'name' => "FPP Station 1"
            ),
            array('%d', '%s')
        );
        wp_mkdir_p($upload_dir . '/station-1');
    }
}

function fpp_install() {
    global $wpdb, $fpp_db_version, $fpp_photos, $fpp_stations;

    $charset_collate = $wpdb->get_charset_collate();

    $photos_sql = "CREATE TABLE $fpp_photos (
        id int NOT NULL AUTO_INCREMENT,
        station_id int NOT NULL,
        ip varchar(55) NOT NULL,
        file_name varchar(32) NOT NULL UNIQUE,
        thumb_200 varchar(38),
        status enum('unreviewed', 'approved', 'rejected') NOT NULL default 'unreviewed',
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";
    
    $stations_sql = "CREATE TABLE $fpp_stations (
        id int NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) UNIQUE,
        slug VARCHAR(255) UNIQUE,
        upload_page_slug VARCHAR(64) NOT NULL default '',
        lat DECIMAL(10, 8),
        lon DECIMAL(11, 8),
        PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $stations_sql );
    dbDelta( $photos_sql );

    // Ensure base upload directory exists
    $upload_dir = wp_upload_dir()['basedir'] . '/fpp-plugin';
    wp_mkdir_p($upload_dir);

    // Create station directories based on DB entries
    $ids = $wpdb->get_col("SELECT id FROM $fpp_stations ORDER BY id ASC");
    if (!empty($ids)) {
        foreach ($ids as $id) {
            wp_mkdir_p($upload_dir . '/station-' . intval($id));
        }
    }

	fpp_install_data();
}

function ensure_default_options() {
	if (get_option('fpp_images_base_dir', false) === false) {
		add_option('fpp_images_base_dir', 'fpp_images');
	}
	if (get_option('fpp_db_version', false) === false) {
		add_option('fpp_db_version', '0.0');
	}
	if (get_option('fpp_recaptcha_threshold', false) === false) {
		add_option('fpp_recaptcha_threshold', '0.5');
	}
	if (get_option('fpp_max_upload_size_mb', false) === false) {
		add_option('fpp_max_upload_size_mb', '8.0');
	}
	if (get_option('fpp_reconcile_resize_count', false) === false) {
		add_option('fpp_reconcile_resize_count', '5');
	}
}

function delete_plugin_database_tables(){
    global $wpdb;
    $tableArray = [   
        $wpdb->prefix . 'fpp_photos',
        $wpdb->prefix . 'fpp_stations'
    ];

    foreach ($tableArray as $tablename) {
        $wpdb->query("DROP TABLE IF EXISTS $tablename");
    }
}

function fpp_uninstall() {
    delete_plugin_database_tables();
    delete_option("fpp_db_version");
	delete_option("fpp_images_base_dir");
}

function version_specific_changes($from_version, $to_version) {
    global $wpdb, $fpp_stations, $fpp_photos;

    if ((float)$from_version < 1.1) {
        $wpdb->query(
            "ALTER TABLE ".$fpp_photos." ".
            "ADD CONSTRAINT fk_station_id
            FOREIGN KEY (station_id)
            REFERENCES ".$fpp_stations."(id)
            ON DELETE RESTRICT
            ON UPDATE CASCADE;"
        );
        if ($wpdb->last_error) {
            error_log("Foreign key constraint error: " . $wpdb->last_error);
        }
    }
    if ((float)$from_version < 1.6) {
        $stations = $wpdb->get_results("SELECT * FROM $fpp_stations ORDER BY id ASC");
    
        if ($stations) {
            foreach ($stations as $station) {
                $station_slug = str_replace(" ", "-", strtolower($station->name));
                
                $wpdb->update(
                    $fpp_stations,
                    array('slug' => $station_slug),
                    array('id' => $station->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
    }
}

register_uninstall_hook(__FILE__, 'fpp_uninstall');
register_activation_hook(__FILE__, 'fpp_install');

function fpp_update_db_check() {
    global $fpp_db_version;
	ensure_default_options();
    $from_version = get_option( 'fpp_db_version');
    if ( $from_version != $fpp_db_version ) {
        fpp_install();
        version_specific_changes($from_version, $fpp_db_version);
        update_option( 'fpp_db_version', $fpp_db_version );

    }
    fpp_reconcile();
}
add_action( 'plugins_loaded', 'fpp_update_db_check' );



function fpp_reconcile() {
    global $wpdb, $fpp_photos;
    $limit = (int)get_option('fpp_reconcile_resize_count', 3);

	$photos = $wpdb->get_results("SELECT * FROM {$fpp_photos} where thumb_200 is NULL LIMIT $limit");

    foreach ($photos as $photo) {
        fpp_generate_thumbnail($photo);
    }
}


function fpp_photo_subdir(int $station_id = -1) {
    $fpp_dir = get_option('fpp_images_base_dir', 'fpp-plugin');
    if ($station_id > 0) {
        return $fpp_dir . "/station-$station_id";
    }
    return $fpp_dir;
}

function fpp_photos_dir(int $station_id = -1) {
    return wp_upload_dir()['basedir'] . "/" . fpp_photo_subdir($station_id);
}
function fpp_photos_uri(int $station_id = -1) {
    return wp_upload_dir()['baseurl'] . "/" . fpp_photo_subdir($station_id);
}
?>