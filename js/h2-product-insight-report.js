// file name: js/h2-product-insight-report.js

jQuery(document).ready(function($) {
    // Function to show notifications
    function showNotification(message) {
        var notification = $('<div class="h2-notification"></div>').text(message);
        $('body').append(notification);
        setTimeout(function() {
            notification.addClass('show');
            setTimeout(function() {
                notification.removeClass('show').addClass('hide');
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }, 2000);
        }, 10);
    }

    // Copy API Key to clipboard
    $('.copy-api-key-button').on('click', function() {
        var apiKey = $(this).siblings('.api-key-text').text();
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(apiKey).select();
        document.execCommand('copy');
        tempInput.remove();
        showNotification('API Key copied to clipboard!');
    });

    // Modal functionality
    var modal = $('#h2-domain-modal');
    var span = $('.h2-close');

    $('.edit-domain-button').on('click', function() {
        var tr = $(this).closest('tr');
        var subscriptionId = tr.data('subscription-id');
        var currentDomain = tr.find('.domain-text').text();

        $('#h2-modal-subscription-id').val(subscriptionId);
        $('#h2-modal-new-domain').val(currentDomain);
        modal.fadeIn();
    });

    span.on('click', function() {
        modal.fadeOut();
    });

    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.fadeOut();
        }
    });

    // Save new domain
    $('#h2-save-domain').on('click', function() {
        var subscriptionId = $('#h2-modal-subscription-id').val();
        var newDomain = $('#h2-modal-new-domain').val();

        if ($.trim(newDomain) === '') {
            showNotification('Customer domain cannot be empty.');
            return;
        }

        $.ajax({
            url: h2_report_params.ajax_url,
            method: 'POST',
            data: {
                action: 'update_customer_domain',
                nonce: h2_report_params.nonce,
                subscription_id: subscriptionId,
                new_domain: newDomain
            },
            success: function(response) {
                if (response.success) {
                    // Update the domain text in the table
                    $('tr[data-subscription-id="' + subscriptionId + '"] .domain-text').text(newDomain);
                    modal.fadeOut();
                    showNotification('Customer domain updated successfully.');
                } else {
                    showNotification('Error: ' + response.data);
                }
            },
            error: function() {
                showNotification('An unexpected error occurred.');
            }
        });
    });
});
