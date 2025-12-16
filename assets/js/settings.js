jQuery(document).ready(function($) {
    'use strict';

    const testButton = $('#cloudflare-test-connection');
    const resultSpan = $('#connection-test-result');

    testButton.on('click', function() {
        const originalText = testButton.text();
        
        testButton.prop('disabled', true).text(cloudflareSettings.i18n.testing);
        resultSpan.html('').removeClass('success error');

        $.ajax({
            url: cloudflareSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'cloudflare_test_connection',
                nonce: cloudflareSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan
                        .html('<span style="color: #46b450;">✓ ' + cloudflareSettings.i18n.success + '</span>')
                        .addClass('success');
                    
                    if (response.data && response.data.email) {
                        resultSpan.append(' <small>(' + response.data.email + ')</small>');
                    }
                } else {
                    resultSpan
                        .html('<span style="color: #dc3232;">✗ ' + (response.data || cloudflareSettings.i18n.error) + '</span>')
                        .addClass('error');
                }
            },
            error: function(xhr, status, error) {
                resultSpan
                    .html('<span style="color: #dc3232;">✗ ' + cloudflareSettings.i18n.error + ': ' + error + '</span>')
                    .addClass('error');
            },
            complete: function() {
                testButton.prop('disabled', false).text(originalText);
            }
        });
    });
});
