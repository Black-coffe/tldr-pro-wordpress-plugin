/**
 * TL;DR Pro Enhanced Modal
 * Advanced modal functionality with social sharing, themes, and rating
 * 
 * @package TLDR_Pro
 * @since 2.3.0
 */

(function($) {
    'use strict';

    // Create namespace to avoid conflicts with other plugins
    if (typeof window.TLDRProPlugin === 'undefined') {
        window.TLDRProPlugin = {};
    }

    // Modal module - scoped to plugin namespace
    window.TLDRProPlugin.Modal = {
        
        // Configuration
        config: {
            modalSelector: '#tldr-pro-modal',
            overlaySelector: '#tldr-pro-overlay',
            contentSelector: '.tldr-pro-modal-content',
            animationSpeed: 300,
            theme: 'light', // light, dark, auto
            fullscreenBreakpoint: 768,
            enableSharing: true,
            enableRating: true,
            enablePrint: true,
            enableCopy: true,
            enableFullscreen: true,
            enableThemeToggle: true
        },

        // State management
        state: {
            isOpen: false,
            isFullscreen: false,
            currentTheme: 'light',
            currentPostId: null,
            userRating: null,
            isLoading: false
        },

        /**
         * Initialize enhanced modal
         */
        init: function() {
            this.detectSystemTheme();
            this.enhanceExistingModal();
            this.bindEvents();
            this.loadUserPreferences();
        },

        /**
         * Enhance the existing modal with new features
         */
        enhanceExistingModal: function() {
            // Add toolbar to existing modal
            this.addModalToolbar();
            
            // Add loading indicator
            this.addLoadingIndicator();
            
            // Add rating system
            if (this.config.enableRating) {
                this.addRatingSystem();
            }
            
            // Add theme toggle
            if (this.config.enableThemeToggle) {
                this.addThemeToggle();
            }
        },

        /**
         * Add modal toolbar with actions
         */
        addModalToolbar: function() {
            let toolbarHtml = `
                <div class="tldr-pro-modal-toolbar">
                    <div class="tldr-pro-toolbar-left">
                        ${this.config.enableCopy ? `
                            <button class="tldr-pro-action-btn tldr-pro-copy" title="Copy to clipboard">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <span class="tldr-tooltip">Copy</span>
                            </button>
                        ` : ''}
                        
                        ${this.config.enablePrint ? `
                            <button class="tldr-pro-action-btn tldr-pro-print" title="Print summary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9V2h12v7"></path>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                <span class="tldr-tooltip">Print</span>
                            </button>
                        ` : ''}
                        
                        ${this.config.enableFullscreen ? `
                            <button class="tldr-pro-action-btn tldr-pro-fullscreen" title="Toggle fullscreen">
                                <svg class="tldr-expand-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                                </svg>
                                <svg class="tldr-compress-icon" style="display:none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
                                </svg>
                                <span class="tldr-tooltip">Fullscreen</span>
                            </button>
                        ` : ''}
                    </div>
                    
                    <div class="tldr-pro-toolbar-center">
                        ${this.config.enableSharing ? this.getSocialShareButtons() : ''}
                    </div>
                    
                    <div class="tldr-pro-toolbar-right">
                        ${this.config.enableThemeToggle ? `
                            <button class="tldr-pro-action-btn tldr-pro-theme-toggle" title="Toggle theme">
                                <svg class="tldr-sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="5"></circle>
                                    <line x1="12" y1="1" x2="12" y2="3"></line>
                                    <line x1="12" y1="21" x2="12" y2="23"></line>
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                    <line x1="1" y1="12" x2="3" y2="12"></line>
                                    <line x1="21" y1="12" x2="23" y2="12"></line>
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                </svg>
                                <svg class="tldr-moon-icon" style="display:none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                </svg>
                                <span class="tldr-tooltip">Theme</span>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;

            // Insert toolbar after modal header
            let $modalHeader = $('.tldr-pro-modal-header');
            if ($modalHeader.length && !$('.tldr-pro-modal-toolbar').length) {
                $modalHeader.after(toolbarHtml);
            }
        },

        /**
         * Get social share buttons HTML
         */
        getSocialShareButtons: function() {
            return `
                <div class="tldr-pro-social-share">
                    <button class="tldr-pro-share-btn tldr-share-facebook" data-network="facebook" title="Share on Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                        </svg>
                    </button>
                    
                    <button class="tldr-pro-share-btn tldr-share-twitter" data-network="twitter" title="Share on Twitter">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M23.44 4.83c-.8.37-1.5.38-2.22.02.93-.56.98-.96 1.32-2.02-.88.52-1.86.9-2.9 1.1-.82-.88-2-1.43-3.3-1.43-2.5 0-4.55 2.04-4.55 4.54 0 .36.03.7.1 1.04-3.77-.2-7.12-2-9.36-4.75-.4.67-.6 1.45-.6 2.3 0 1.56.8 2.95 2 3.77-.74-.03-1.44-.23-2.05-.55v.06c0 2.2 1.56 4.03 3.64 4.44-.67.2-1.37.2-2.06.08.58 1.8 2.26 3.12 4.25 3.16C5.78 18.1 3.37 18.74 1 18.46c2 1.3 4.4 2.04 6.97 2.04 8.35 0 12.92-6.92 12.92-12.93 0-.2 0-.4-.02-.6.9-.63 1.96-1.22 2.56-2.14z"/>
                        </svg>
                    </button>
                    
                    <button class="tldr-pro-share-btn tldr-share-linkedin" data-network="linkedin" title="Share on LinkedIn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.47 2H3.53a1.45 1.45 0 00-1.47 1.43v17.14A1.45 1.45 0 003.53 22h16.94a1.45 1.45 0 001.47-1.43V3.43A1.45 1.45 0 0020.47 2zM8.09 18.74h-3v-9h3v9zM6.59 8.48a1.56 1.56 0 110-3.12 1.56 1.56 0 110 3.12zm13.32 10.26h-3v-4.83c0-1.21-.43-2-1.52-2A1.65 1.65 0 0012.85 13a2 2 0 00-.1.73v5h-3v-9h3V11a3 3 0 012.71-1.5c2 0 3.45 1.29 3.45 4.06v5.18z"/>
                        </svg>
                    </button>
                    
                    <button class="tldr-pro-share-btn tldr-share-whatsapp" data-network="whatsapp" title="Share on WhatsApp">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                    </button>
                    
                    <button class="tldr-pro-share-btn tldr-share-email" data-network="email" title="Share via Email">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </button>
                </div>
            `;
        },

        /**
         * Add loading indicator
         */
        addLoadingIndicator: function() {
            let loaderHtml = `
                <div class="tldr-pro-modal-loader" style="display:none;">
                    <div class="tldr-spinner">
                        <div class="tldr-spinner-circle"></div>
                        <div class="tldr-spinner-text">Loading summary...</div>
                    </div>
                </div>
            `;
            
            let $modalBody = $('.tldr-pro-modal-body');
            if ($modalBody.length && !$('.tldr-pro-modal-loader').length) {
                $modalBody.prepend(loaderHtml);
            }
        },

        /**
         * Add rating system
         */
        addRatingSystem: function() {
            let ratingHtml = `
                <div class="tldr-pro-rating-container">
                    <div class="tldr-pro-rating-prompt">Was this summary helpful?</div>
                    <div class="tldr-pro-rating-stars">
                        <button class="tldr-rating-star" data-rating="1">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </button>
                        <button class="tldr-rating-star" data-rating="2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </button>
                        <button class="tldr-rating-star" data-rating="3">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </button>
                        <button class="tldr-rating-star" data-rating="4">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </button>
                        <button class="tldr-rating-star" data-rating="5">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </button>
                    </div>
                    <div class="tldr-pro-rating-feedback" style="display:none;">
                        <span class="tldr-rating-thanks">Thank you for your feedback!</span>
                    </div>
                </div>
            `;
            
            let $modalFooter = $('.tldr-pro-modal-footer');
            if ($modalFooter.length && !$('.tldr-pro-rating-container').length) {
                $modalFooter.append(ratingHtml);
            }
        },

        /**
         * Add theme toggle to modal
         */
        addThemeToggle: function() {
            // Theme toggle is already in toolbar
            this.applyTheme(this.state.currentTheme);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            let self = this;

            // Copy to clipboard
            $(document).on('click', '.tldr-pro-copy', function(e) {
                e.preventDefault();
                self.copyToClipboard();
            });

            // Print
            $(document).on('click', '.tldr-pro-print', function(e) {
                e.preventDefault();
                self.printSummary();
            });

            // Fullscreen toggle
            $(document).on('click', '.tldr-pro-fullscreen', function(e) {
                e.preventDefault();
                self.toggleFullscreen();
            });

            // Theme toggle
            $(document).on('click', '.tldr-pro-theme-toggle', function(e) {
                e.preventDefault();
                self.toggleTheme();
            });

            // Social sharing
            $(document).on('click', '.tldr-pro-share-btn', function(e) {
                e.preventDefault();
                let network = $(this).data('network');
                self.shareOnSocial(network);
            });

            // Rating stars
            $(document).on('click', '.tldr-rating-star', function(e) {
                e.preventDefault();
                let rating = $(this).data('rating');
                self.submitRating(rating);
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (!self.state.isOpen) return;

                switch(e.key) {
                    case 'Escape':
                        if (self.state.isFullscreen) {
                            self.exitFullscreen();
                        }
                        break;
                    case 'p':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            self.printSummary();
                        }
                        break;
                    case 'c':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            self.copyToClipboard();
                        }
                        break;
                    case 'f':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            self.toggleFullscreen();
                        }
                        break;
                }
            });

            // Listen for modal open/close events
            $(document).on('tldr:modal:opened', function() {
                self.state.isOpen = true;
                self.onModalOpened();
            });

            $(document).on('tldr:modal:closed', function() {
                self.state.isOpen = false;
                self.onModalClosed();
            });
        },

        /**
         * Copy summary to clipboard
         */
        copyToClipboard: function() {
            let $content = $(this.config.contentSelector);
            let text = $content.text().trim();
            
            // Create temporary textarea
            let $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                this.showNotification('Summary copied to clipboard!', 'success');
            } catch (err) {
                // Fallback for modern browsers
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        this.showNotification('Summary copied to clipboard!', 'success');
                    }.bind(this));
                } else {
                    this.showNotification('Failed to copy to clipboard', 'error');
                }
            }
            
            $temp.remove();
        },

        /**
         * Print summary
         */
        printSummary: function() {
            let $content = $(this.config.contentSelector);
            let printWindow = window.open('', '_blank');
            
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>TL;DR Summary - ${document.title}</title>
                    <style>
                        body {
                            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            max-width: 800px;
                            margin: 0 auto;
                            padding: 40px 20px;
                        }
                        h1 {
                            color: #667eea;
                            border-bottom: 2px solid #667eea;
                            padding-bottom: 10px;
                            margin-bottom: 30px;
                        }
                        .meta {
                            color: #666;
                            font-size: 0.9em;
                            margin-bottom: 20px;
                        }
                        @media print {
                            body { padding: 20px; }
                        }
                    </style>
                </head>
                <body>
                    <h1>TL;DR Summary</h1>
                    <div class="meta">
                        <strong>Original Article:</strong> ${document.title}<br>
                        <strong>URL:</strong> ${window.location.href}<br>
                        <strong>Date:</strong> ${new Date().toLocaleDateString()}
                    </div>
                    <div class="content">
                        ${$content.html()}
                    </div>
                    <script>
                        window.onload = function() { 
                            window.print(); 
                            window.onafterprint = function() {
                                window.close();
                            }
                        }
                    </script>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
        },

        /**
         * Toggle fullscreen mode
         */
        toggleFullscreen: function() {
            if (this.state.isFullscreen) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        },

        /**
         * Enter fullscreen mode
         */
        enterFullscreen: function() {
            let $modal = $(this.config.modalSelector);
            $modal.addClass('tldr-fullscreen-mode');
            this.state.isFullscreen = true;
            
            // Update button icons
            $('.tldr-pro-fullscreen .tldr-expand-icon').hide();
            $('.tldr-pro-fullscreen .tldr-compress-icon').show();
            
            // Trigger event
            $(document).trigger('tldr:fullscreen:entered');
        },

        /**
         * Exit fullscreen mode
         */
        exitFullscreen: function() {
            let $modal = $(this.config.modalSelector);
            $modal.removeClass('tldr-fullscreen-mode');
            this.state.isFullscreen = false;
            
            // Update button icons
            $('.tldr-pro-fullscreen .tldr-expand-icon').show();
            $('.tldr-pro-fullscreen .tldr-compress-icon').hide();
            
            // Trigger event
            $(document).trigger('tldr:fullscreen:exited');
        },

        /**
         * Toggle theme
         */
        toggleTheme: function() {
            let newTheme = this.state.currentTheme === 'light' ? 'dark' : 'light';
            this.applyTheme(newTheme);
            this.saveUserPreference('theme', newTheme);
        },

        /**
         * Apply theme
         */
        applyTheme: function(theme) {
            let $modal = $(this.config.modalSelector);
            $modal.removeClass('tldr-theme-light tldr-theme-dark');
            $modal.addClass('tldr-theme-' + theme);
            
            this.state.currentTheme = theme;
            
            // Update icon
            if (theme === 'dark') {
                $('.tldr-pro-theme-toggle .tldr-sun-icon').hide();
                $('.tldr-pro-theme-toggle .tldr-moon-icon').show();
            } else {
                $('.tldr-pro-theme-toggle .tldr-sun-icon').show();
                $('.tldr-pro-theme-toggle .tldr-moon-icon').hide();
            }
            
            // Update body class for overlay
            $('body').removeClass('tldr-dark-theme tldr-light-theme');
            $('body').addClass('tldr-' + theme + '-theme');
        },

        /**
         * Detect system theme preference
         */
        detectSystemTheme: function() {
            if (this.config.theme === 'auto') {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    this.state.currentTheme = 'dark';
                } else {
                    this.state.currentTheme = 'light';
                }
            } else {
                this.state.currentTheme = this.config.theme;
            }
        },

        /**
         * Share on social networks
         */
        shareOnSocial: function(network) {
            let url = encodeURIComponent(window.location.href);
            let title = encodeURIComponent(document.title);
            let summary = encodeURIComponent($('.tldr-pro-modal-content').text().substring(0, 200) + '...');
            
            let shareUrls = {
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
                twitter: `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
                linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
                whatsapp: `https://wa.me/?text=${title}%20${url}`,
                email: `mailto:?subject=${title}&body=${summary}%0A%0ARead more: ${url}`
            };
            
            if (shareUrls[network]) {
                if (network === 'email') {
                    window.location.href = shareUrls[network];
                } else {
                    window.open(shareUrls[network], '_blank', 'width=600,height=400');
                }
                
                // Track share
                this.trackShare(network);
            }
        },

        /**
         * Submit rating
         */
        submitRating: function(rating) {
            let self = this;
            
            // Update UI
            $('.tldr-rating-star').each(function(index) {
                if (index < rating) {
                    $(this).addClass('tldr-star-filled');
                } else {
                    $(this).removeClass('tldr-star-filled');
                }
            });
            
            // Send rating to server
            $.ajax({
                url: tldr_pro_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'tldr_pro_submit_rating',
                    nonce: tldr_pro_frontend.nonce,
                    post_id: this.state.currentPostId || tldr_pro_frontend.post_id,
                    rating: rating
                },
                success: function() {
                    self.state.userRating = rating;
                    $('.tldr-pro-rating-prompt').hide();
                    $('.tldr-pro-rating-feedback').fadeIn();
                    
                    // Hide feedback after 3 seconds
                    setTimeout(function() {
                        $('.tldr-pro-rating-feedback').fadeOut();
                    }, 3000);
                }
            });
        },

        /**
         * Track social share
         */
        trackShare: function(network) {
            $.post(tldr_pro_frontend.ajax_url, {
                action: 'tldr_pro_track_share',
                nonce: tldr_pro_frontend.nonce,
                post_id: this.state.currentPostId || tldr_pro_frontend.post_id,
                network: network
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            let $notification = $('<div class="tldr-pro-notification tldr-notification-' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            $notification.fadeIn(300);
            
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Load user preferences
         */
        loadUserPreferences: function() {
            // Load theme preference
            let savedTheme = localStorage.getItem('tldr_pro_theme');
            if (savedTheme) {
                this.state.currentTheme = savedTheme;
                this.applyTheme(savedTheme);
            }
            
            // Load other preferences
            let savedFullscreen = localStorage.getItem('tldr_pro_fullscreen');
            if (savedFullscreen === 'true') {
                this.config.enableFullscreen = true;
            }
        },

        /**
         * Save user preference
         */
        saveUserPreference: function(key, value) {
            localStorage.setItem('tldr_pro_' + key, value);
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('.tldr-pro-modal-loader').show();
            $('.tldr-pro-modal-content').hide();
            this.state.isLoading = true;
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.tldr-pro-modal-loader').hide();
            $('.tldr-pro-modal-content').show();
            this.state.isLoading = false;
        },

        /**
         * Modal opened callback
         */
        onModalOpened: function() {
            // Focus management for accessibility
            this.previousFocus = document.activeElement;
            
            // Trap focus in modal
            this.trapFocus();
            
            // Add aria attributes
            $(this.config.modalSelector).attr({
                'role': 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': 'tldr-modal-title'
            });
        },

        /**
         * Modal closed callback
         */
        onModalClosed: function() {
            // Restore focus
            if (this.previousFocus) {
                this.previousFocus.focus();
            }
            
            // Clean up
            if (this.state.isFullscreen) {
                this.exitFullscreen();
            }
        },

        /**
         * Trap focus within modal
         */
        trapFocus: function() {
            let $modal = $(this.config.modalSelector);
            let focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            let firstFocusable = focusableElements.first();
            let lastFocusable = focusableElements.last();

            $modal.on('keydown.trapFocus', function(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey) { // Shift + Tab
                        if (document.activeElement === firstFocusable[0]) {
                            e.preventDefault();
                            lastFocusable.focus();
                        }
                    } else { // Tab
                        if (document.activeElement === lastFocusable[0]) {
                            e.preventDefault();
                            firstFocusable.focus();
                        }
                    }
                }
            });
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if (typeof TLDRProButton !== 'undefined') {
            TLDRProModal.init();
        }
    });

})(jQuery);