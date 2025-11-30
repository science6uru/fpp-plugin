(function($) {

    $(document).ready(function(){
        $(".input-container").click(function() {
            $(this).find('input').trigger('focus');
        });
    });
    
}
)(jQuery)