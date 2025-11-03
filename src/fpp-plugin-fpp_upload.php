<?php
$site_key = get_option('fpp_recaptcha_site_key');
?>

<form id="fpp-upload-form" action="/?rest_route=/fpp/v1/photo_upload/<?=$station_id?>" method="post" enctype="multipart/form-data">
  <label for="file-upload">Choose a file to upload:</label>
  <input type="file" id="file-upload" name="user_photo" required>
  
  <?php if (!empty($site_key)): ?>
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
  <?php endif; ?>
  
  <button type="submit">Upload File</button>
</form>

<?php if (!empty($site_key)): ?>
<script>
document.getElementById('fpp-upload-form').addEventListener('submit', function(e) {
  e.preventDefault();
  grecaptcha.ready(function() {
    grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'upload_photo'}).then(function(token) {
      document.getElementById('g-recaptcha-response').value = token;
      e.target.submit();
    });
  });
});
</script>
<?php endif; ?>