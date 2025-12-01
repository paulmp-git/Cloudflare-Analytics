jQuery(document).ready(function($) {
    const $timeRange = $('#cloudflare-time-range');
    const $dataContainer = $('#cloudflare-data');
    let currentRequest = null;
    
    function fetchAnalytics() {
        // If there's an existing request, abort it
        if (currentRequest) {
            currentRequest.abort();
        }
        
        $dataContainer.html('<div class="loading">Loading analytics...</div>');
        
        currentRequest = $.ajax({
            url: cloudflareAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_cloudflare_data',
                time_range: $timeRange.val(),
                nonce: cloudflareAnalytics.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayAnalytics(response.data);
                } else {
                    displayError(response.data);
                }
            },
            error: function(xhr, status, error) {
                displayError('Failed to fetch analytics data.');
            },
            complete: function() {
                currentRequest = null;
            }
        });
    }
    
    function displayAnalytics(data) {
        const html = `
            <div class="analytics-grid">
                <div class="analytics-item">
                    <h4>Total Unique Visitors</h4>
                    <div class="analytics-value">${data.total_visitors}</div>
                </div>
                <div class="analytics-item">
                    <h4>Maximum Visitors Per Hour</h4>
                    <div class="analytics-value">${data.max_visitors}</div>
                </div>
                <div class="analytics-item">
                    <h4>Minimum Visitors Per Hour</h4>
                    <div class="analytics-value">${data.min_visitors}</div>
                </div>
            </div>
        `;
        
        $dataContainer.html(html);
    }
    
    function displayError(message) {
        $dataContainer.html(`<div class="error-message">${message}</div>`);
    }
    
    // Initial fetch
    fetchAnalytics();
    
    // Handle time range changes
    $timeRange.on('change', fetchAnalytics);
    
    // Refresh data every 5 minutes
    setInterval(fetchAnalytics, 300000);
});