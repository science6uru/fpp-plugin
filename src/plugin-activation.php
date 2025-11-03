<?php

global $fpp_db_version;
global $fpp_photos;
global $fpp_stations;
global $wpdb;

$fpp_db_version = '1.2';
$fpp_photos = $wpdb->prefix . 'fpp_photos';
$fpp_stations = $wpdb->prefix . 'fpp_stations';

function fpp_install_data() {
    global $wpdb, $fpp_stations;

    $station_array = [1, 2, 3];
    foreach($station_array as $station_id) {
        $station = $wpdb->get_row("SELECT * FROM $fpp_stations where id = $station_id");
        if (! $station) {
            $wpdb->insert( 
                $fpp_stations, 
                array( 
                    'id' => $station_id, 
                    'name' => "FPP Station $station_id", 
                ) 
            );
        }
    }
}

function fpp_install() {
    global $wpdb;
    global $fpp_db_version, $fpp_photos, $fpp_stations;

    $charset_collate = $wpdb->get_charset_collate();

    $photos_sql = "CREATE TABLE $fpp_photos (
        id int NOT NULL AUTO_INCREMENT,
        station_id int NOT NULL,
        ip varchar(55) NOT NULL,
        file_name varchar(32) NOT NULL UNIQUE,
        approved tinyint(1) NOT NULL DEFAULT 0,
        rejected tinyint(1) NOT NULL DEFAULT 0,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";
    
    $stations_sql = "CREATE TABLE $fpp_stations (
        id int NOT NULL AUTO_INCREMENT,
        name VARCHAR(255),
        lat DECIMAL(10, 8),
        lon DECIMAL(11, 8),
        PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $stations_sql );
    dbDelta( $photos_sql );

	fpp_install_data();
}

function ensure_default_options() {
	if (get_option('fpp_images_base_dir', false) === false) {
		add_option('fpp_images_base_dir', wp_upload_dir()['basedir'] . '/fpp_images');
	}
	if (get_option('fpp_db_version', false) === false) {
		add_option('fpp_db_version', '0.0');
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
}
add_action( 'plugins_loaded', 'fpp_update_db_check' );

?>