<?php
$site_key = get_option('fpp_recaptcha_site_key');
?>

<form id="fpp-upload-form" action="/?rest_route=/fpp/v1/photo_upload/<?=$station_id?>" method="post" enctype="multipart/form-data">
  
<div class="fpp_container">
  <div class="card" id="file-selector">
    <!-- <h3>Photo Submission</h3> -->
    <div class="drop_box">
      <header>
        <h4>Photo Submission</h4>
      </header>
      <p>Files Supported: JPEG, HEIC</p>
      <input type="file" accept=".jpg,.heic,image/jpeg,image/heic" id="file-upload" name="user_photo" hidden style="display:none;"/>
      <button id="file-upload-btn" class="filechooser wide">Select Photo to Upload</button>
    </div>
  </div>
  <div class="card" id="file-display">
    <p><span id="upload_preview_filename"></span></p>
    <div class="drop_box">
    <img id="upload_preview" width="100%"/>
</div>
    <button type="submit" class="wide">Upload File</button>
    <button type="cancel" id="file-upload-cancel-btn" class="wide cancel">Select Different File</button>
    <input type="number" readonly id="image_width" hidden name="image_width" style="width: 33%;display:none;"/>
    <input type="number" readonly id="image_height" hidden name="image_height" style="width: 33%;display:none;"/>
    <input type="number" readonly id="image_size" hidden name="image_size" style="width: 30%;display:none;"/>
  </div>
</div>
  <?php if (!empty($site_key)): ?>
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
  <?php endif; ?>
  
</form>
