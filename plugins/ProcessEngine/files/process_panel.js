/**
 * ProcessEngine - Dashboard JS
 *
 * Handles filter interactions and auto-refresh for the dashboard.
 */
(function() {
    'use strict';

    // Auto-refresh dashboard every 60 seconds
    var PE_REFRESH_INTERVAL = 60000;
    var refreshTimer = null;

    function initDashboard() {
        // Highlight active filter button
        var filterBtns = document.querySelectorAll('.widget-toolbox .btn-group .btn');
        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                filterBtns.forEach(function(b) {
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-white');
                });
                this.classList.remove('btn-white');
                this.classList.add('btn-primary');
            });
        });

        // Setup auto-refresh
        startAutoRefresh();
    }

    function startAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        refreshTimer = setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                window.location.reload();
            }
        }, PE_REFRESH_INTERVAL);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
