(function ($) {
    $(document).ready(function () {
        $('#fpp-upload-form').on('submit', function (e) {
            if(!recaptchaSubmit){
                performRecaptchaVerification(true);
                return false;
            }
            //I want to move all the form submission code here instead of in the php file
        });
    
        const dropArea = document.querySelector(".drop_box"),
        input = dropArea.querySelector("input");

        function cancelPhotoSubmission() {
            $("#upload_preview_filename").text("");
            $('#upload_preview').attr("src", "");
            $("#image_size").val("");
            $("#file-selector").show();
            $("#file-display").hide();
            $("button.filechooser").removeClass("busy");
            resetRecaptcha();
        }

        $("#file-upload-btn").click(function(e) {
            e.preventDefault();
            // Start reCAPTCHA verification process when file is selected
            input.click();
        });

        $("#file-upload-cancel-btn").click(function(e) {
            e.preventDefault();
            cancelPhotoSubmission();
        });

        $('#upload_preview').on("load", function() {
            var preview = $('#upload_preview');
            var previewUrl = preview.attr("src");
            if (previewUrl) {
                imgwidth = preview.prop("naturalWidth");
                imgheight = preview.prop("naturalHeight");

                previewUrl = null;
                if (imgheight >= imgwidth) {
                    setTimeout(function () {
                        alert("Photo must be landscape mode.");
                        cancelPhotoSubmission();
                    }, 50);
                } else {
                    $("#image_width").val(imgwidth);
                    $("#image_height").val(imgheight);
                }
                URL.revokeObjectURL(previewUrl) // free memory
            }
        });

        if(input) {
            input.addEventListener("change", function (e) {
                $("button.filechooser").addClass("busy");
                var file = e.target.files[0];
                $("#upload_preview_filename").text(file.name);
                $("#image_size").val(file.size);
                if ((file.size / 1048576) > php_vars.fpp_max_upload_size_mb) {
                    alert("The selected photo is too large ( >" + php_vars.fpp_max_upload_size_mb +"MB). \nPlease select another or change photo app options to reduce file sizes.");
                    cancelPhotoSubmission();
                    return;
                }
                var preview = $('#upload_preview');
                var previewUrl = URL.createObjectURL(file);

                $("#file-selector").hide();
                $("#file-display").show();
                preview.attr("src", previewUrl);
            });
        }
        let v3Token = '';
        let v2Token = '';
        let recaptchaWidgetId = null;
        let requiresV2 = false;
        let recaptchaVerified = false;
        let recaptchaSubmit = false;


        // cancelBtn.addEventListener('click', function(e) {
        //     e.preventDefault();
        //     fileUpload.value = '';
        //     fileDisplay.style.display = 'none';
        //     fileSelector.style.display = 'block';
        // });

        async function performRecaptchaVerification(doSubmit=false) {
            // uploadSubmitBtn.disabled = true;
            // uploadSubmitBtn.textContent = 'Verifying...';
            
            try {
                if (php_vars.v3SiteKey) {
                    // Try v3 first
                    v3Token = await executeV3Recaptcha();
                    if (v3Token && doSubmit) {
                        document.getElementById('g-recaptcha-response').value = v3Token;
                        recaptchaSubmit = true;
                        setTimeout(function () {
                            $('#fpp-upload-form').submit();
                        }, 250);
                        return;
                    }
                    if (v3Token) {
                        // console.log("verifying: "+v3Token);
                        const verificationResult = await verifyV3Score(v3Token);
                        
                        if (verificationResult.success) {
                            document.getElementById('g-recaptcha-response').value = v3Token;
                            // console.log("received v3 token");
                            if (!verificationResult.data.requires_v2) {
                                // v3 passed - no v2 required
                                recaptchaVerified = true;
                                requiresV2 = false;
                                // uploadSubmitBtn.disabled = false;
                                // uploadSubmitBtn.textContent = 'Upload File';
                                // console.log("v3 passed");
                                return true;
                            } else {
                                // console.log("v3 failed, falling back to v2");
                                // v3 requires v2 fallback
                                requiresV2 = true;
                                renderV2Recaptcha();
                                // uploadSubmitBtn.textContent = 'Upload File';
                                return false;
                            }
                        } else {
                            // console.log("V3 verify failed");
                        }
                    } else {
                        // console.log("did not receive v3 token");
                    }
                    return false;
                } else {
                    // console.log("v3 not configured");
                }
                
                // If v3 failed or not configured, try v2
                if (php_vars.v2SiteKey) {
                    requiresV2 = true;
                    renderV2Recaptcha();
                    // uploadSubmitBtn.textContent = 'Upload File';
                    return false;
                } else {
                    // No reCAPTCHA configured
                    recaptchaVerified = true;
                    $('#fpp-upload-form').submit();
                    // uploadSubmitBtn.disabled = false;
                    // uploadSubmitBtn.textContent = 'Upload File';
                }
                return true;
                
            } catch (error) {
                console.error('reCAPTCHA verification error:', error);
                // Fallback to v2 or allow submission if no reCAPTCHA
                if (php_vars.v2SiteKey) {
                    requiresV2 = true;
                    renderV2Recaptcha();
                } else {
                    recaptchaVerified = true;
                    $('#fpp-upload-form').submit();

                    // uploadSubmitBtn.disabled = false;
                }
                // uploadSubmitBtn.textContent = 'Upload File';
                return false;
            }
        }

        function executeV3Recaptcha() {
            return new Promise((resolve, reject) => {
                if (!php_vars.v3SiteKey) {
                    resolve('');
                    return;
                }
                
                grecaptcha.ready(function() {
                    grecaptcha.execute(php_vars.v3SiteKey, {action: 'upload_photo'}).then(function(token) {
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
                formData.append('action', 'nopriv_fpp_verify_recaptcha_score');
                formData.append('token', token);
                formData.append('nonce', php_vars.verify_recaptcha_nonce);

                fetch(php_vars.ajaxUrl, {
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
            if (!php_vars.v2SiteKey) {
                alert('Security verification required but not configured. Please contact site administrator.');
                return;
            }
            
            const container = document.getElementById('recaptcha-v2-container');
            container.style.display = 'block';
            
            if (recaptchaWidgetId !== null) {
                grecaptcha.reset(recaptchaWidgetId);
            } else {
                recaptchaWidgetId = grecaptcha.render('g-recaptcha', {
                    'sitekey': php_vars.v2SiteKey,
                    'callback': function(token) {
                        v2Token = token;
                        document.getElementById('g-recaptcha-response-v2').value = token;
                        recaptchaVerified = true;
                        // uploadSubmitBtn.disabled = false;
                    },
                    'expired-callback': function() {
                        v2Token = '';
                        document.getElementById('g-recaptcha-response-v2').value = '';
                        recaptchaVerified = false;
                        // uploadSubmitBtn.disabled = true;
                    }
                });
            }
        }

        function resetRecaptcha() {
            v3Token = '';
            v2Token = '';
            requiresV2 = false;
            recaptchaVerified = false;
            document.getElementById('g-recaptcha-response').value = '';
            document.getElementById('g-recaptcha-response-v2').value = '';
            
            const container = document.getElementById('recaptcha-v2-container');
            container.style.display = 'none';
            
            if (recaptchaWidgetId !== null) {
                grecaptcha.reset(recaptchaWidgetId);
            }
            
            // uploadSubmitBtn.disabled = false;
            // uploadSubmitBtn.textContent = 'Upload File';
        }

        // uploadForm.addEventListener('submit', async function(e) {
        //     if (!recaptchaVerified) {
        //         e.preventDefault();
        //         alert('Please complete the security verification before uploading.');
        //         return false;
        //     }
            
        //     // All verification passed, allow form submission
        //     // Tokens are already set in hidden fields
        // });

        window.onRecaptchaLoad = function() {
            // reCAPTCHA loaded, ready for use
            if(!recaptchaVerified && input) {
                // console.log("recaptcha loaded");
                performRecaptchaVerification();
            }
        };

    });
})(jQuery);