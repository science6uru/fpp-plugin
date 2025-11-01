This is the <b>manage</b> Page for station <?=$station_id ?>
<br/>
<?php require plugin_dir_path( __FILE__ ) ."fpp-plugin-fpp_carousel.php" ?>

<?php 
	print "fpp db version is" . get_option( 'fpp_db_version' );
?>