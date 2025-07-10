<?php

global $fpp_db_version;
global $fpp_photos;
global $fpp_stations;
global $wpdb;

$fpp_db_version = '1.0';
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
		station tinyint NOT NULL,
		ip varchar(55) NOT NULL,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";
		# TODO: Add foreign key constraint for station 
		# TODO: Add approved/curated/include column
    
	$stations_sql = "CREATE TABLE $fpp_stations (
		id int NOT NULL AUTO_INCREMENT,
		name VARCHAR(255),
		lat DECIMAL(10, 8),
        lon DECIMAL(11, 8),
		PRIMARY KEY  (id)
        ) ENGINE=InnoDB  $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $photos_sql );
	dbDelta( $stations_sql );

	fpp_install_data();
	add_option( 'fpp_db_version', $fpp_db_version );
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
}

register_uninstall_hook(__FILE__, 'fpp_uninstall');
register_activation_hook(__FILE__, 'fpp_install');

function fpp_update_db_check() {
    global $fpp_db_version;
    if ( get_option( 'fpp_db_version' ) != $fpp_db_version ) {
        fpp_install();
		update_option( 'fpp_db_version', $fpp_db_version );

    }
}
add_action( 'plugins_loaded', 'fpp_update_db_check' );

?>