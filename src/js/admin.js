(function($) {

    $(document).ready(function(){
        $(".input-container").click(function() {
            $(this).find('input').trigger('focus');
        });
        function setSelectedView() {
            if (!window.location.hash) {
                return false;
            }
            const hash = window.location.hash.slice(1);
            const viewSelector = "#view-" + hash;
            const tabSelector = "#tab-" + hash;
            const selView = $(viewSelector);
            if (selView) {
                $(".fpp-tab").removeClass("active");
                $(".fpp-tab-view").removeClass("active");
                selView.addClass("active");
                $(tabSelector).addClass("active");
            }
            return true;
        }
        $(window).on("hashchange", function() {
            setSelectedView();
        });
        $(".fpp-tab-group fpp-tab").on("click", function() {
            const id = $(this).attr("id");
            const viewId = id.replace(/^tab/, "view");
            const tabber = $(this).closest(".fpp-tabber");
            tabber.find(".fpp-tab-view").removeClass("active");
            tabber.find(".fpp-tab").removeClass("active");
            $(this).addClass("active");
            $("#"+viewId).addClass("active");
        });
        if (!setSelectedView()) {
            $("#tab-manage-photos").addClass("active");
            $("#view-manage-photos").addClass("active");
        }
        $("#fpp-upload-form").find("#fpp-return-url").each(function() {
            $(this).val($(this).val()+"#upload-photo");
        });
    });
    
}
)(jQuery)