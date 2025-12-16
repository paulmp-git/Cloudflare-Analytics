jQuery(document).ready(function($) {
    'use strict';

    const widget = {
        timeRange: $('#cloudflare-time-range'),
        dataContainer: $('#cloudflare-data'),
        currentRequest: null,
        refreshInterval: null,
        
        init: function() {
            if (this.timeRange.length === 0 || this.dataContainer.length === 0) {
                return;
            }
            this.fetchData();
            this.bindEvents();
            this.startAutoRefresh();
        },
        
        bindEvents: function() {
            this.timeRange.on('change', () => this.fetchData());
        },
        
        startAutoRefresh: function() {
            // Refresh data every 5 minutes
            this.refreshInterval = setInterval(() => this.fetchData(), 300000);
        },
        
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
        },
        
        fetchData: function() {
            // Abort any pending request
            if (this.currentRequest) {
                this.currentRequest.abort();
            }
            
            this.showLoading();
            
            this.currentRequest = $.ajax({
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
                error: (xhr, status) => {
                    if (status !== 'abort') {
                        this.showError(cloudflareAnalytics.i18n.error);
                    }
                },
                complete: () => {
                    this.currentRequest = null;
                }
            });
        },
        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        renderData: function(data) {
            const i18n = cloudflareAnalytics.i18n;
            const metrics = [
                { label: i18n.uniqueVisitors || 'Unique Visitors', value: data.total_visitors, icon: '👥' },
                { label: i18n.totalRequests || 'Total Requests', value: data.total_requests, icon: '📊' },
                { label: i18n.pageviews || 'Pageviews', value: data.pageviews, icon: '📄' },
                { label: i18n.bandwidth || 'Bandwidth', value: data.bandwidth, icon: '📡' },
                { label: i18n.cacheRatio || 'Cache Ratio', value: data.cache_ratio, icon: '⚡' },
                { label: i18n.threatsBlocked || 'Threats Blocked', value: data.threats_blocked, icon: '🛡️' },
                { label: i18n.httpsPercentage || 'HTTPS Traffic', value: data.https_percentage, icon: '🔒' }
            ];
            
            const container = $('<div class="analytics-grid"></div>');
            
            metrics.forEach(metric => {
                if (metric.value !== undefined && metric.value !== null) {
                    const item = $('<div class="analytics-item"></div>');
                    item.append($('<h4></h4>').text(metric.label));
                    item.append($('<div class="analytics-value"></div>').text(metric.value));
                    container.append(item);
                }
            });
            
            this.dataContainer.empty().append(container);
        },
        
        showLoading: function() {
            const loading = $('<div class="loading"></div>');
            loading.append($('<span></span>').text(cloudflareAnalytics.i18n.loading));
            loading.append('<div class="spinner is-active"></div>');
            this.dataContainer.empty().append(loading);
        },
        
        showError: function(message) {
            const errorDiv = $('<div class="error-message"></div>');
            errorDiv.append($('<p></p>').text(message));
            
            const retryBtn = $('<button class="button button-secondary"></button>')
                .text(cloudflareAnalytics.i18n.retry || 'Retry')
                .on('click', () => this.fetchData());
            
            errorDiv.append(retryBtn);
            this.dataContainer.empty().append(errorDiv);
        }
    };
    
    widget.init();
});
