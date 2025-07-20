(function ($) {
    'use strict';

    $(document).ready(function () {
        $('#create-site-form').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                url: zeecreatives_ajax.ajaxurl, // Use localized data
                type: 'POST',
                data: {
                    action: 'zeecreatives_create_site',
                    nonce: zeecreatives_ajax.nonce, // Use localized nonce
                    formData: formData
                },
                success: function (response) {
                    if (response.success) {
                        $('#response-message').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('#create-site-form')[0].reset();  // Clear form fields after success
                    } else {
                        $('#response-message').html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                    }

                    // Scroll down to the response message with animation
                    $('html, body').animate({
                        scrollTop: $('#response-message').offset().top - 50 // Adjust offset if needed
                    }, 800); // 800ms for smooth scrolling

                    // Hide and clear the message after 5 seconds
                    setTimeout(function () {
                        $('#response-message').fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function () {
                    $('#response-message').html('<div class="notice notice-error is-dismissible"><p>An error occurred. Please try again.</p></div>');

                    // Scroll down to the response message with animation
                    $('html, body').animate({
                        scrollTop: $('#response-message').offset().top - 50 // Adjust offset if needed
                    }, 800); // 800ms for smooth scrolling

                    // Hide and clear the message after 5 seconds
                    setTimeout(function () {
                        $('#response-message').fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                }
            });
        });
    });

})(jQuery);
