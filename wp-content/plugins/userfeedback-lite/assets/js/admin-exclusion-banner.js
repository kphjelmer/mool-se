/**
 * UserFeedback Admin Exclusion Banner
 * Handles display and dismissal of admin exclusion notice
 */
(function() {
    'use strict';

    // Configuration from PHP
    var config = window.userfeedback_banner_config || {};

    // LocalStorage key for dismissal
    var DISMISSAL_KEY = 'userfeedback_admin_banner_dismissed';

    /**
     * Check if banner was previously dismissed
     */
    function isBannerDismissed() {
        try {
            return localStorage.getItem(DISMISSAL_KEY) === 'true';
        } catch (e) {
            return false;
        }
    }

    /**
     * Mark banner as dismissed
     */
    function dismissBanner() {
        try {
            localStorage.setItem(DISMISSAL_KEY, 'true');
        } catch (e) {
            // localStorage not available, banner will show again on refresh
        }
    }

    /**
     * Hide banner with animation
     */
    function hideBanner() {
        var banner = document.getElementById('userfeedback-admin-banner');
        if (!banner) {
            return;
        }

        banner.classList.remove('userfeedback-admin-banner--visible');
        setTimeout(function() {
            banner.style.display = 'none';
        }, 300);
    }

    /**
     * Initialize and show banner
     */
    function initBanner() {
        // Check if already dismissed
        if (isBannerDismissed()) {
            return;
        }

        var banner = document.getElementById('userfeedback-admin-banner');
        if (!banner) {
            return;
        }

        // Populate content from config
        var title = banner.querySelector('.userfeedback-admin-banner__title');
        var message = banner.querySelector('.userfeedback-admin-banner__message');
        var learnMore = banner.querySelector('.userfeedback-admin-banner__learn-more');
        var closeBtn = banner.querySelector('.userfeedback-admin-banner__close');

        if (title && config.title) {
            title.textContent = config.title;
        }

        if (message && config.message) {
            message.textContent = config.message;
        }

        if (learnMore && config.learn_more_url) {
            learnMore.href = config.learn_more_url;
        }

        // Handle close button
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideBanner();
                dismissBanner();
            });
        }

        // Show banner with fade-in animation after a short delay
        setTimeout(function() {
            banner.style.display = 'block';
            // Trigger reflow for animation
            banner.offsetHeight;
            setTimeout(function() {
                banner.classList.add('userfeedback-admin-banner--visible');
            }, 10);
        }, 500);
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBanner);
    } else {
        initBanner();
    }
})();
