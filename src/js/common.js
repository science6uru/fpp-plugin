
class PhotologModal {
    constructor(id) {
        this.modal = jQuery(id);
        let self = this;
    }
    show() {
        this.modal.fadeIn(250);
    }
}

(function($) {
    // append to end of body to ensure it can be placed above all other page elements
    $(".fpp_modal").appendTo($("body"));
    $(".fpp_modal").each(function() {
        let self = $(this);
        $(this).find(".close-btn").on("click", function () {
            self.fadeOut(250);
            return false;
        });
        $(this).on("click", function (event) {
            if(!jQuery(this).find(".modal-content")[0].contains(event.target)) {
                self.fadeOut(250);
                return false;
            }
        });
    });
})(jQuery);