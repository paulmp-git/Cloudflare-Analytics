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
        },
        
        fetchData: function() {
            this.showLoading();
            
            $.ajax({
                url: cloudflareAnalytics.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_cloudflare_data',
                    nonce: cloudflareAnalytics.nonce,
                    time_range: this.timeRange.val()
                },
                success: (response) => {
                    if (response.success) {
                        this.renderData(response.data);
                    } else {
                        this.showError(response.data);
                    }
                },
                error: () => {
                    this.showError(cloudflareAnalytics.i18n.error);
                }
            });
        },
        
        renderData: function(data) {
            const metrics = [
                { label: 'Total Visitors', value: data.total_visitors },
                { label: 'Maximum Visitors', value: data.max_visitors },
                { label: 'Minimum Visitors', value: data.min_visitors },
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
                    ${cloudflareAnalytics.i18n.loading}
                </div>
            `);
        },
        
        showError: function(message) {
            this.dataContainer.html(`
                <div class="error-message">
                    ${message}
                </div>
            `);
        }
    };
    
    widget.init();
});
