
(function ($) {
    $(document).ready(function () {
        $('#fpp-upload-form').on('submit', function (e) {
            if ($("#upload_preview").attr("src")){
                if(php_vars.site_key) {
                    e.preventDefault();
                    grecaptcha.ready(function () {
                        grecaptcha.execute(php_vars.site_key, { action: 'upload_photo' }).then(function (token) {
                            document.getElementById('g-recaptcha-response').value = token;
                            e.target.submit();
                        });
                    });
                }
            } else {
                e.preventDefault();
            }
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

        }

        $("#file-upload-btn").click(function(e) {
            e.preventDefault();
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
            // $("#upload_preview")

        //   let filedata = `
        //     <form action="" method="post">
        //     <div class="form">
        //     <h4>${fileName}</h4>
        //     <input type="email" placeholder="Enter email upload file">
        //     <button class="btn">Upload</button>
        //     </div>
        //     </form>`;
        //   dropArea.innerHTML = filedata;
        });
    });
})(jQuery);