<?php
$site_key = get_option('fpp_recaptcha_site_key');
$v2_site_key = get_option('fpp_recaptcha_v2_site_key');
?>

<form id="fpp-upload-form" action="/?rest_route=/fpp/v1/photo_upload/<?= $station_slug ?>" method="post" enctype="multipart/form-data">

  <div class="fpp_container">
    <?php
    if (array_key_exists("uploaded", $_GET) && ! is_admin()) :
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
          <p>Files Supported: JPEG, HEIC</p>
          <input type="file" accept=".jpg,.heic,image/jpeg,image/heic" id="file-upload" name="user_photo" hidden style="display:none;" />
          <button id="file-upload-btn" class="filechooser wide">Select Photo to Upload</button>
        </div>
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
        
        <button type="submit" class="wide" id="upload-submit-btn">Upload File</button>
        <button type="cancel" id="file-upload-cancel-btn" class="wide cancel">Select Different File</button>
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
        <input type="number" readonly id="image_width" hidden name="image_width" style="width: 33%;display:none;" />
        <input type="number" readonly id="image_height" hidden name="image_height" style="width: 33%;display:none;" />
        <input type="number" readonly id="image_size" hidden name="image_size" style="width: 30%;display:none;" />
      </div>
  </div>
  <?php if (!empty($site_key)): ?>
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
  <?php endif; ?>
  <input type="hidden" name="g-recaptcha-response-v2" id="g-recaptcha-response-v2">
<?php
      endif;
?>


</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileUpload = document.getElementById('file-upload');
    const fileUploadBtn = document.getElementById('file-upload-btn');
    const fileDisplay = document.getElementById('file-display');
    const fileSelector = document.getElementById('file-selector');
    const uploadForm = document.getElementById('fpp-upload-form');
    const uploadPreview = document.getElementById('upload_preview');
    const uploadPreviewFilename = document.getElementById('upload_preview_filename');
    const cancelBtn = document.getElementById('file-upload-cancel-btn');
    const uploadSubmitBtn = document.getElementById('upload-submit-btn');
    
    const v3SiteKey = '<?php echo esc_js($site_key); ?>';
    const v2SiteKey = '<?php echo esc_js($v2_site_key); ?>';
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    
    let v3Token = '';
    let v2Token = '';
    let recaptchaWidgetId = null;
    let requiresV2 = false;

    fileUploadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileUpload.click();
    });

    fileUpload.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            uploadPreviewFilename.textContent = file.name;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                uploadPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            fileSelector.style.display = 'none';
            fileDisplay.style.display = 'block';
            
            if (v3SiteKey) {
                // load captcha vefore but don't get token yet
                uploadSubmitBtn.disabled = false;
            } else if (v2SiteKey) {
                // If only v2 is configured, show challenge immdeiately
                renderV2Recaptcha();
            } else {
                // No configs 
                uploadSubmitBtn.disabled = false;
            }
        }
    });

    cancelBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileUpload.value = '';
        fileDisplay.style.display = 'none';
        fileSelector.style.display = 'block';
        resetRecaptcha();
    });

    function executeV3Recaptcha() {
        return new Promise((resolve, reject) => {
            if (!v3SiteKey) {
                resolve('');
                return;
            }
            
            grecaptcha.ready(function() {
                grecaptcha.execute(v3SiteKey, {action: 'upload_photo'}).then(function(token) {
                    resolve(token);
                }).catch(function(error) {
                    console.error('reCAPTCHA v3 execution failed:', error);
                    reject(error);
                });
            });
        });
    }

    function verifyV3Score(token) {
        return new Promise((resolve) => {
            const formData = new FormData();
            formData.append('action', 'fpp_verify_recaptcha_score');
            formData.append('token', token);
            formData.append('nonce', '<?php echo wp_create_nonce("fpp_verify_recaptcha_score"); ?>');

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resolve(data);
            })
            .catch(error => {
                console.error('Error verifying reCAPTCHA score:', error);
                resolve({success: false, data: 'Network error'});
            });
        });
    }

    function renderV2Recaptcha() {
        if (!v2SiteKey) {
            alert('Security verification required but not configured. Please contact site administrator.');
            return;
        }
        
        const container = document.getElementById('recaptcha-v2-container');
        container.style.display = 'block';
        
        if (recaptchaWidgetId !== null) {
            grecaptcha.reset(recaptchaWidgetId);
        } else {
            recaptchaWidgetId = grecaptcha.render('g-recaptcha', {
                'sitekey': v2SiteKey,
                'callback': function(token) {
                    v2Token = token;
                    document.getElementById('g-recaptcha-response-v2').value = token;
                    uploadSubmitBtn.disabled = false;
                },
                'expired-callback': function() {
                    v2Token = '';
                    document.getElementById('g-recaptcha-response-v2').value = '';
                    uploadSubmitBtn.disabled = true;
                }
            });
        }
        
        uploadSubmitBtn.disabled = true;
    }

    function resetRecaptcha() {
        v3Token = '';
        v2Token = '';
        requiresV2 = false;
        document.getElementById('g-recaptcha-response').value = '';
        document.getElementById('g-recaptcha-response-v2').value = '';
        
        const container = document.getElementById('recaptcha-v2-container');
        container.style.display = 'none';
        
        if (recaptchaWidgetId !== null) {
            grecaptcha.reset(recaptchaWidgetId);
        }
        
        uploadSubmitBtn.disabled = false;
    }

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        uploadSubmitBtn.disabled = true;
        uploadSubmitBtn.textContent = 'Processing...';
        
        try {
            if (v3SiteKey && !requiresV2) {
                // re fresh v3 token before submission
                v3Token = await executeV3Recaptcha();
                
                if (v3Token) {
                    const verificationResult = await verifyV3Score(v3Token);
                    
                    if (verificationResult.success) {
                        if (verificationResult.data.requires_v2) {
                            requiresV2 = true;
                            renderV2Recaptcha();
                            uploadSubmitBtn.disabled = false;
                            uploadSubmitBtn.textContent = 'Upload File';
                            return false;
                        } else {
                            //  Score is good
                            document.getElementById('g-recaptcha-response').value = v3Token;
                        }
                    } else {
                        // Failed, require v2
                        requiresV2 = true;
                        renderV2Recaptcha();
                        uploadSubmitBtn.disabled = false;
                        uploadSubmitBtn.textContent = 'Upload File';
                        return false;
                    }
                } else {
                    // v3 failed, go to v2
                    requiresV2 = true;
                    renderV2Recaptcha();
                    uploadSubmitBtn.disabled = false;
                    uploadSubmitBtn.textContent = 'Upload File';
                    return false;
                }
            }
            
            if (requiresV2 && !v2Token) {
                alert('Please complete the security verification.');
                renderV2Recaptcha();
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.textContent = 'Upload File';
                return false;
            }
            
            if (!v3SiteKey && v2SiteKey && !v2Token) {
                alert('Please complete the security verification.');
                renderV2Recaptcha();
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.textContent = 'Upload File';
                return false;
            }
            
            // All passed
            this.submit();
            
        } catch (error) {
            console.error('Submission error:', error);
            alert('An error occurred during submission. Please try again.');
            uploadSubmitBtn.disabled = false;
            uploadSubmitBtn.textContent = 'Upload File';
        }
    });

    if (v3SiteKey || v2SiteKey) {
        const script = document.createElement('script');
        let scriptUrl = 'https://www.google.com/recaptcha/api.js';
        
        if (v3SiteKey) {
            scriptUrl += '?render=' + v3SiteKey;
        }
        
        if (v2SiteKey) {
            scriptUrl += (v3SiteKey ? '&' : '?') + 'onload=onRecaptchaLoad';
        }
        
        script.src = scriptUrl;
        document.head.appendChild(script);
    }

    window.onRecaptchaLoad = function() {
    };
});
</script>