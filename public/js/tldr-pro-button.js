/**
 * TL;DR Pro Button - Frontend JavaScript
 * 
 * Handles the TL;DR button functionality on the frontend
 * @package TLDR_Pro
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * TL;DR Pro Button Handler
     */
    let TLDRProButton = {
        
        // Configuration
        config: {
            buttonSelector: '.tldr-pro-button',
            modalSelector: '#tldr-pro-modal',
            overlaySelector: '#tldr-pro-overlay',
            contentSelector: '.tldr-pro-modal-content',
            closeSelector: '.tldr-pro-close',
            loadingClass: 'tldr-loading',
            activeClass: 'tldr-active',
            animationSpeed: 300,
            cookieName: 'tldr_pro_viewed',
            cookieExpiry: 7, // days
            trackClicks: true,
            abTest: false
        },

        // State
        state: {
            isLoading: false,
            isModalOpen: false,
            currentPostId: null,
            summaryCache: {},
            clickCount: 0
        },

        /**
         * Initialize the button functionality
         */
        init: function() {
            // Check if we should display buttons
            if (!this.shouldDisplayButton()) {
                return;
            }

            // Set up event handlers
            this.bindEvents();
            
            // Initialize floating button if enabled
            if (tldr_pro_frontend.floating_button === '1') {
                this.initFloatingButton();
            }

            // Initialize A/B testing if enabled
            if (tldr_pro_frontend.ab_testing === '1') {
                this.initABTesting();
            }

            // Track page view
            this.trackPageView();
        },

        /**
         * Check if button should be displayed
         */
        shouldDisplayButton: function() {
            // Check if summaries exist for current post
            if (tldr_pro_frontend.has_summary !== '1') {
                return false;
            }

            // Check minimum word count
            let wordCount = this.getContentWordCount();
            if (wordCount < parseInt(tldr_pro_frontend.min_word_count)) {
                return false;
            }

            // Check if user has already viewed (cookie check)
            if (tldr_pro_frontend.hide_if_viewed === '1' && this.hasViewedSummary()) {
                return false;
            }

            return true;
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            let self = this;

            // Button click handler
            $(document).on('click', this.config.buttonSelector, function(e) {
                e.preventDefault();
                self.handleButtonClick($(this));
            });

            // Close modal handlers
            $(document).on('click', this.config.closeSelector + ', ' + this.config.overlaySelector, function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // ESC key to close modal
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && self.state.isModalOpen) {
                    self.closeModal();
                }
            });

            // Handle window resize for responsive behavior
            $(window).on('resize', this.debounce(function() {
                self.adjustModalPosition();
            }, 250));
        },

        /**
         * Handle button click
         */
        handleButtonClick: function($button) {
            if (this.state.isLoading) {
                return;
            }

            let postId = $button.data('post-id') || tldr_pro_frontend.post_id;
            
            // Track click
            this.trackClick($button);

            // Check cache first
            if (this.state.summaryCache[postId]) {
                this.showSummary(this.state.summaryCache[postId]);
                return;
            }

            // Load summary from server
            this.loadSummary(postId, $button);
        },

        /**
         * Load summary via AJAX
         */
        loadSummary: function(postId, $button) {
            let self = this;
            
            this.state.isLoading = true;
            this.state.currentPostId = postId;
            
            // Add loading state
            $button.addClass(this.config.loadingClass);
            
            $.ajax({
                url: tldr_pro_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'tldr_pro_get_frontend_summary',
                    nonce: tldr_pro_frontend.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Cache the summary
                        self.state.summaryCache[postId] = response.data;
                        
                        // Show the summary
                        self.showSummary(response.data);
                        
                        // Set viewed cookie
                        self.setViewedCookie(postId);
                        
                        // Track view
                        self.trackView(postId);
                    } else {
                        self.showError(response.data.message || 'Failed to load summary');
                    }
                },
                error: function() {
                    self.showError('Network error. Please try again.');
                },
                complete: function() {
                    self.state.isLoading = false;
                    $button.removeClass(self.config.loadingClass);
                }
            });
        },

        /**
         * Show summary in modal
         */
        showSummary: function(data) {
            let self = this;
            
            // Create modal if it doesn't exist
            if (!$(this.config.modalSelector).length) {
                this.createModal();
            }

            // Populate modal content
            let $modal = $(this.config.modalSelector);
            let $content = $modal.find(this.config.contentSelector);
            
            // Set title
            $modal.find('.tldr-pro-modal-title').text(data.post_title || 'TL;DR Summary');
            
            // Set summary content as plain HTML
            // Content already contains formatted HTML from the database
            $content.html(data.summary_html || data.summary_text);
            
            // Add metadata if available
            if (data.reading_time) {
                $modal.find('.tldr-pro-reading-time').text(data.reading_time);
            }
            
            if (data.word_count) {
                $modal.find('.tldr-pro-word-count').text(data.word_count + ' words');
            }

            if (data.provider) {
                $modal.find('.tldr-pro-provider').text('Powered by ' + data.provider);
            }

            // Show modal with animation
            this.openModal();
        },

        /**
         * Create modal HTML
         */
        createModal: function() {
            let modalHtml = `
                <div id="tldr-pro-overlay" class="tldr-pro-overlay"></div>
                <div id="tldr-pro-modal" class="tldr-pro-modal">
                    <div class="tldr-pro-modal-inner">
                        <div class="tldr-pro-modal-header">
                            <h2 class="tldr-pro-modal-title"></h2>
                            <button class="tldr-pro-close" aria-label="Close">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="tldr-pro-modal-body">
                            <div class="tldr-pro-modal-content"></div>
                        </div>
                        <div class="tldr-pro-modal-footer">
                            <div class="tldr-pro-meta">
                                <span class="tldr-pro-reading-time"></span>
                                <span class="tldr-pro-word-count"></span>
                                <span class="tldr-pro-provider"></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        },

        /**
         * Open modal
         */
        openModal: function() {
            let self = this;
            
            this.state.isModalOpen = true;
            
            // Add body class to prevent scrolling
            $('body').addClass('tldr-modal-open');
            
            // Show overlay and modal with animation
            $(this.config.overlaySelector).fadeIn(this.config.animationSpeed);
            $(this.config.modalSelector).fadeIn(this.config.animationSpeed, function() {
                // Focus on close button for accessibility
                $(self.config.closeSelector).focus();
            });
            
            // Adjust position for responsive
            this.adjustModalPosition();
        },

        /**
         * Close modal
         */
        closeModal: function() {
            let self = this;
            
            this.state.isModalOpen = false;
            
            // Remove body class
            $('body').removeClass('tldr-modal-open');
            
            // Hide modal and overlay with animation
            $(this.config.modalSelector).fadeOut(this.config.animationSpeed);
            $(this.config.overlaySelector).fadeOut(this.config.animationSpeed, function() {
                // Clear content
                $(self.config.contentSelector).empty();
            });
        },

        /**
         * Initialize floating button
         */
        initFloatingButton: function() {
            let self = this;
            
            // Create floating button HTML
            let buttonHtml = `
                <div class="tldr-pro-floating-button ${tldr_pro_frontend.floating_position}" 
                     data-post-id="${tldr_pro_frontend.post_id}">
                    <span class="tldr-pro-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="tldr-pro-label">TL;DR</span>
                </div>
            `;
            
            $('body').append(buttonHtml);
            
            // Add scroll behavior
            let $floatingButton = $('.tldr-pro-floating-button');
            let scrollThreshold = 200;
            
            $(window).on('scroll', this.throttle(function() {
                if ($(window).scrollTop() > scrollThreshold) {
                    $floatingButton.addClass('tldr-visible');
                } else {
                    $floatingButton.removeClass('tldr-visible');
                }
            }, 100));
        },

        /**
         * Initialize A/B testing
         */
        initABTesting: function() {
            // Determine variant (stored in cookie for consistency)
            let variant = this.getCookie('tldr_ab_variant');
            
            if (!variant) {
                // Randomly assign variant
                variant = Math.random() < 0.5 ? 'a' : 'b';
                this.setCookie('tldr_ab_variant', variant, 30); // 30 days
            }
            
            // Apply variant styles
            $('body').addClass('tldr-variant-' + variant);
            
            // Track variant
            this.trackABVariant(variant);
        },

        /**
         * Adjust modal position for responsive
         */
        adjustModalPosition: function() {
            if (!this.state.isModalOpen) {
                return;
            }
            
            let $modal = $(this.config.modalSelector);
            let windowHeight = $(window).height();
            let modalHeight = $modal.outerHeight();
            
            if (modalHeight > windowHeight * 0.9) {
                $modal.addClass('tldr-modal-fullscreen');
            } else {
                $modal.removeClass('tldr-modal-fullscreen');
            }
        },

        /**
         * Get content word count
         */
        getContentWordCount: function() {
            let content = $('.entry-content, .post-content, article').first().text();
            return content.trim().split(/\s+/).length;
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Create error notification
            let errorHtml = `
                <div class="tldr-pro-error">
                    <p>${message}</p>
                </div>
            `;
            
            $('body').append(errorHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.tldr-pro-error').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Cookie management
         */
        setCookie: function(name, value, days) {
            let expires = "";
            if (days) {
                let date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        },

        getCookie: function(name) {
            let nameEQ = name + "=";
            let ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        },

        setViewedCookie: function(postId) {
            let viewed = this.getCookie(this.config.cookieName) || '';
            if (viewed.indexOf(postId) === -1) {
                viewed += (viewed ? ',' : '') + postId;
                this.setCookie(this.config.cookieName, viewed, this.config.cookieExpiry);
            }
        },

        hasViewedSummary: function() {
            let viewed = this.getCookie(this.config.cookieName) || '';
            return viewed.indexOf(tldr_pro_frontend.post_id) !== -1;
        },

        /**
         * Internal tracking
         */
        trackClick: function($button) {
            this.state.clickCount++;
            
            // Send to server for internal tracking
            if (this.config.trackClicks) {
                $.post(tldr_pro_frontend.ajax_url, {
                    action: 'tldr_pro_track_click',
                    nonce: tldr_pro_frontend.nonce,
                    post_id: tldr_pro_frontend.post_id,
                    button_type: $button.hasClass('tldr-pro-floating-button') ? 'floating' : 'inline'
                });
            }
        },

        trackView: function(postId) {
            // Track summary view internally
            $.post(tldr_pro_frontend.ajax_url, {
                action: 'tldr_pro_track_view',
                nonce: tldr_pro_frontend.nonce,
                post_id: postId
            });
        },

        trackPageView: function() {
            // Track that page has TL;DR available
            if (this.config.trackClicks) {
                $.post(tldr_pro_frontend.ajax_url, {
                    action: 'tldr_pro_track_page_view',
                    nonce: tldr_pro_frontend.nonce,
                    post_id: tldr_pro_frontend.post_id
                });
            }
        },

        trackABVariant: function(variant) {
            // Track A/B test variant internally
            $.post(tldr_pro_frontend.ajax_url, {
                action: 'tldr_pro_track_ab_variant',
                nonce: tldr_pro_frontend.nonce,
                post_id: tldr_pro_frontend.post_id,
                variant: variant
            });
        },

        /**
         * Utility functions
         */
        debounce: function(func, wait) {
            let timeout;
            return function() {
                let context = this;
                let args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                let args = arguments;
                let context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize on single posts/pages
        if (tldr_pro_frontend && tldr_pro_frontend.is_singular === '1') {
            TLDRProButton.init();
        }
    });

})(jQuery);