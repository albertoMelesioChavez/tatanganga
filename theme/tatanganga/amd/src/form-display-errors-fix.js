// Fix for Bootstrap 3 .form-control-feedback:visible selector issue
// This overrides the problematic Boost form display errors
define(['jquery'], function($) {
    return {
        init: function() {
            // Override the problematic event listener
            $('form').off('submit.boost-form-errors').on('submit.boost-form-errors', function() {
                var visibleError = $('.form-control-feedback').filter(':visible');
                if (visibleError.length) {
                    visibleError[0].focus();
                }
            });
        }
    };
});
