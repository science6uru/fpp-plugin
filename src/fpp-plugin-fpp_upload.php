This is the fpp_upload page <?=$station_id?>

<form action="/?rest_route=/fpp/v1/photo_upload/<?=$station_id?>" method="post" enctype="multipart/form-data">
  <label for="file-upload">Choose a file to upload:</label>
  <input type="text" id="file-upload" name="user_photo" required>
  <button type="submit">Upload File</button>
</form>

