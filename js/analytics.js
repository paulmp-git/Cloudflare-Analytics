jQuery(document).ready(function($) {
    'use strict';

    const widget = {
        timeRange: $('#cloudflare-time-range'),
        dataContainer: $('#cloudflare-data'),
        
        init: function() {
            this.fetchData();
            this.bindEvents();
        },
        
        bindEvents: function() {
            this.timeRange.on('change', () => this.fetchData());
            
            // Add error handling for failed AJAX requests
            $(document).ajaxError((event, jqXHR, settings, error) => {
                if (settings.url.includes('admin-ajax.php')) {
                    this.showError('Failed to fetch analytics data. Please try again.');
                    console.error('AJAX Error:', error);
                }
            });
        },
        
        fetchData: function() {
            this.showLoading();
            
            // Debug log
            console.log('Fetching data for time range:', this.timeRange.val());
            
            $.ajax({
                url: cloudflareAnalytics.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_cloudflare_data',
                    nonce: cloudflareAnalytics.nonce,
                    time_range: this.timeRange.val(),
                    _: new Date().getTime() // Cache busting
                },
                success: (response) => {
                    console.log('API Response:', response);
                    if (response.success && response.data) {
                        this.renderData(response.data);
                    } else {
                        const errorMessage = response.data || 'Unknown error occurred';
                        console.error('API Error:', errorMessage);
                        this.showError(errorMessage);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX Error:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        error: errorThrown
                    });
                    this.showError('Network error occurred. Please check your connection and try again.');
                }
            });
        },
        
        renderData: function(data) {
            console.log('Rendering data:', data);
            const metrics = [
                { label: 'Unique Visitors', value: data.total_visitors },
                { label: 'Total Requests', value: data.requests },
                { label: 'Total Pageviews', value: data.pageviews }
            ];
            
            const html = metrics.map(metric => `
                <div class="analytics-item">
                    <h4>${metric.label}</h4>
                    <div class="analytics-value">${metric.value}</div>
                </div>
            `).join('');
            
            this.dataContainer.html(html);
        },
        
        showLoading: function() {
            this.dataContainer.html(`
                <div class="loading">
                    <p>${cloudflareAnalytics.i18n.loading}</p>
                    <div class="spinner is-active"></div>
                </div>
            `);
        },
        
        showError: function(message) {
            console.error('Showing error:', message);
            this.dataContainer.html(`
                <div class="error-message">
                    <p>${message}</p>
                    <button class="button button-secondary retry-button">
                        ${cloudflareAnalytics.i18n.retry}
                    </button>
                </div>
            `);
            
            $('.retry-button').on('click', () => {
                console.log('Retrying data fetch...');
                this.fetchData();
            });
        }
    };
    
    widget.init();
});
