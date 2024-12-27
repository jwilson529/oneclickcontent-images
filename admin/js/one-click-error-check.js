(function($) {
    'use strict';

    $(document).ready(function() {
        let pollingInterval = setInterval(function() {
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'check_image_error',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        showSubscriptionPrompt(
                            'API Limit Reached',
                            response.data.message,
                            response.data.ad_url
                        );
                        clearInterval(pollingInterval);
                    }
                },
                error: function(xhr, status, error) {
                  //Removed unnecessary logging
                },
            });
        }, 5000);
    });
})(jQuery);