This is the <b>manage</b> Page for station <?=$station_id ?>
<br/>
<?php require plugin_dir_path( __FILE__ ) ."fpp-plugin-fpp_carousel.php" ?>

<?php 
	print "fpp db version is" . get_option( 'fpp_db_version' );
?>
<div>
<b>Upload image to station <?=$station_id?></b><br/>
<?php echo do_shortcode("[fpp_upload station_id={$station_id}]"); ?>
</div>
<b>If you don't see 8M upload limit below, then rebuild your dev environment</b><br/>
<?php
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . '<br>';
echo 'post_max_size: ' . ini_get('post_max_size') . '<br>';
echo 'memory_limit: ' . ini_get('memory_limit') . '<br>';
?>