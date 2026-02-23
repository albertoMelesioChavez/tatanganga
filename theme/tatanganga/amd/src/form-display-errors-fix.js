// Fix for Bootstrap 3 .form-control-feedback:visible selector issue
// This overrides the problematic Boost form display errors
define(['jquery'], function($) {
    return {
        init: function() {
            console.log('[Tatanganga Debug] Form display errors fix loaded');
            // Override the problematic event listener with fixed selector
            $('form').off('submit.boost-form-errors').on('submit.boost-form-errors', function() {
                console.log('[Tatanganga Debug] Override submit handler triggered');
                var allFeedback = $('.form-control-feedback');
                console.log('[Tatanganga Debug] All .form-control-feedback elements:', allFeedback.length);
                var visibleError = allFeedback.filter(':visible');
                console.log('[Tatanganga Debug] Visible .form-control-feedback elements:', visibleError.length);
                console.log('[Tatanganga Debug] Visible error elements:', visibleError.get());
                if (visibleError.length) {
                    console.log('[Tatanganga Debug] Focusing on first visible error');
                    visibleError[0].focus();
                }
            });
        }
    };
});
