This is the <b>manage</b> Page for staiton <?=$station_number ?>
<br/>
<?php require plugin_dir_path( __FILE__ ) ."carousel.php"  ?>

<?php 
	print "fpp db version is" . get_option( 'fpp_db_version' );
?>