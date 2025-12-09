<div class="timelapse-player small two-by-one">
    <div class="timelapse-header">
        <div class="title">Chronological Conservation - <?=  $station_name ?></div>
        <a class="info-link"><img src="/wp-content/plugins/photolog/assets/info.svg" /></a>
        <button class="sizing-button"></button>
    </div>
    <div class="timelapse-container" id="timelapse-player-<?= $station_slug ?>">
            
    </div>
    <div class="timelapse-footer">
        <div class="timelapse-controls">
            <div class="play-btn-wrapper">
                <button class="play-button playing"></button>
            </div>
            <div class="subcontrols">
                <div class="seek-bar">
                    <div class="cursorbar"></div>
                    <div class="cursor"></div>
                </div>
                <div class="nav">
                    <span class="rate">
                        <button class="btn-minus rate-decrease" tooltip="Reduce Playback Speed" id="rate-decrease"></button>
                        <button class="btn-plus rate-increase" tooltip="Increase Playback Speed" id="rate-increase"></button>
                    </span>
                    <button class="start" tooltip="Skip to first image"></button>
                    <button class="previous" tooltip="Previous image"></button>
                    <button class="next" tooltip="Next image"></button>
                    <button class="end" tooltip="Skip to last image"></button>
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="timelapse-caption">Loading...</div>
            <div class="help-text"></div>
        </div>
    </div>
</div>

<div id="fpp-carousel-info" class="fpp_modal">
    <div class="modal-content">
        <div class="modal-header">
            <!-- Link to close the modal by navigating to the main page fragment -->
            <a href="#" class="close-btn">&times;</a>
            <h2>Chronological Conservation</h2>
            <h4>Photolog plugin</h4>
        </div>
        <div class="modal-body">
            <p>This plugin was developed as part of an Eagle Scout Service Project to enhance conservation efforts at the Sprint Creek Forest Preserve.</p>
            <p>Learn more about <a href="https://springcreekforest.org/news-items/scout-project-fixed-point-photography-stations-for-environmental-analysis/">the project</a>.</p><br/>
        </div>
        <div class="modal-footer">
            <p>View on <a href="https://github.com/science6uru/fpp-plugin" target="_blank">GitHub</a>.</p>
        </div>
    </div>
</div>
<?php
    // Localize the fpp_carousel script to bootstrap the photos data
    global $wpdb, $fpp_photos;
    $photos = $wpdb->get_results("select UNIX_TIMESTAMP(taken) AS taken_epoch, image_2000, thumb_200, DATE_FORMAT(taken, '%M %d, %Y at %h:%i %p') as timestamp, created from $fpp_photos where station_id = $station_id and status='approved' order by taken asc;", ARRAY_A);
    $base_url = fpp_photos_uri($station_id);
    $min_time = PHP_INT_MAX;
    $max_time = 0;
    foreach ($photos as &$photo) {
        if ($photo['taken_epoch'] < $min_time) {
            $min_time = $photo['taken_epoch'];
        }
        if ($photo['taken_epoch'] > $max_time) {
            $max_time = $photo['taken_epoch'];
        }
        $photo['file_name'] = $base_url . "/" . $photo['image_2000'];
        $photo['thumb_200'] = $base_url . "/" . $photo['thumb_200'];
    }
    foreach ($photos as &$photo) {
        if ($max_time == $min_time) {
            $photo['timeline_pos'] = 0;
        } else {
            $photo['timeline_pos'] = (100 * ($photo['taken_epoch'] - $min_time) / ($max_time - $min_time));
        }
    }
    $var_name = "timelapse_player_" . str_replace("-", "_", $station_slug);
    $data = json_encode(array("photos" => $photos, "timerange" => [$min_time, $max_time]));
    echo <<< EOC
    <script>
    var $var_name = $data;
    </script>
    EOC;
?>