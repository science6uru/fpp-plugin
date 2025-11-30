(function ($) {
    $(document).ready(function () {
        $('#fpp-upload-form').on('submit', function (e) {
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
        });
    });
})(jQuery);