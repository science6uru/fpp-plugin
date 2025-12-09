<?php
$site_key = get_option('fpp_recaptcha_site_key');
$v2_site_key = get_option('fpp_recaptcha_v2_site_key');
?>

<form id="fpp-upload-form" action="/?rest_route=/fpp/v1/photo_upload/<?= $station_slug ?>" method="post" enctype="multipart/form-data">

  <div class="fpp_container">
    <?php
    if (array_key_exists("uploaded", $_GET) && $_GET['uploaded'] == 'success' && ! is_admin()) :
    ?>
      <div class="card">
        <h3>Photo Uploaded Successfully</h3>
        <div class="drop_box">

          <header>
            <h4>Thank you for your submission!</h4>
          </header>
          <p>Your photo will be added to our timelapse once it has been approved.</p>
        </div>
      </div>
    <?php
    else:
    ?>
      <div class="card" id="file-selector">
        <!-- <h3>Photo Submission</h3> -->
        <div class="drop_box">
          <header>
            <h4 style="text-align:center;">Photo Submission for <?= $station_name ?> </h4>
          </header>
          <p>Files Supported: JPEG</p>
          <input type="file" accept=".jpg,.jpeg,image/jpeg" id="file-upload" name="user_photo" hidden style="display:none;" />
          <button id="file-upload-btn" class="filechooser wide">Select Photo to Upload</button>
        </div>
        <a class="info-link"><img src="/wp-content/plugins/photolog/assets/info.svg" /></a>
      </div>
      <div class="card" id="file-display">
        <p><span id="upload_preview_filename"></span></p>
        <div class="drop_box">
          <img id="upload_preview" width="100%" />
        </div>
        
        <!-- reCAPTCHA v2 container (initial hidden) -->
        <div id="recaptcha-v2-container" style="display: none; margin: 15px 0;">
          <p>Please complete the security verification:</p>
          <div id="g-recaptcha"></div>
        </div>
        
        <button type="submit" class="wide busy" id="upload-submit-btn">Upload File</button>
        <button type="cancel" id="file-upload-cancel-btn" class="wide cancel">Select Different File</button>
        <input type="hidden" id="fpp-return-url" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
        <input type="number" readonly id="image_width" hidden name="image_width" style="width: 33%;display:none;" />
        <input type="number" readonly id="image_height" hidden name="image_height" style="width: 33%;display:none;" />
        <input type="number" readonly id="image_size" hidden name="image_size" style="width: 30%;display:none;" />
      </div>
  <?php if (!empty($site_key)): ?>
      <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response"/>
  <?php endif; ?>
      <input type="hidden" name="g-recaptcha-score-v3" id="g-recaptcha-score-v3"/>
      <input type="hidden" name="g-recaptcha-response-v2" id="g-recaptcha-response-v2"/>
<?php
      endif;
?>

    <?php
    if (array_key_exists("uploaded", $_GET) && $_GET['uploaded'] != 'success'):
    ?>
    <div id="fpp-upload-error-modal" class="fpp_modal" style="display:block;">
        <div class="modal-content">
            <div class="modal-header">
                <!-- Link to close the modal by navigating to the main page fragment -->
                <a href="#" class="close-btn">&times;</a>
                <h2>Photolog Photo Upload</h2>
                <h4>Upload Failed</h4>
            </div>
            <div class="modal-body">
              <?php
              switch($_GET['uploaded']) {
                case "too-large":
                  $message = "The file uploaded exceeds maximum file size. Please reduce the file size and upload again, or select a different file.";
                  break;
                case "invalid-form":
                  $message = "There was a problem validating your submission. Please close this notice and try submitting your photo again.";
                  break;
                case "processing-error":
                  $message = "There was a problem processing your photo. Please try again.";
                  break;
                case "server-error":
                default:
                  $message = "An unknown error has occurred. Please try again later.";
              }

              ?>
                <p><?= $message ?></p>
            </div>
            <br/>
            <div class="modal-footer">
                <p>If this issue is persistent and you think it is in error, please report it <a href="https://github.com/science6uru/fpp-plugin/issues" target="_blank">here</a>.</p>
            </div>
        </div>
    </div>
    <?php
    endif;
    ?>
    <div id="fpp-info-modal" class="fpp_modal">
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
  </div>
</form>
