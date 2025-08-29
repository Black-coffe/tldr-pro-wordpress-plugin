/**
 * TL;DR Pro Bulk Operations Module
 *
 * @package TLDR_Pro
 * @subpackage Admin/JS
 */

(function($) {
	'use strict';
	
	// Ensure ajaxurl is defined
	if (typeof ajaxurl === 'undefined') {
		if (typeof tldr_pro_admin !== 'undefined' && tldr_pro_admin.ajaxurl) {
			var ajaxurl = tldr_pro_admin.ajaxurl;
		} else if (typeof tldr_pro_admin !== 'undefined' && tldr_pro_admin.ajax_url) {
			var ajaxurl = tldr_pro_admin.ajax_url;
		} else {
			// Fallback to WordPress admin-ajax.php
			var ajaxurl = '/wp-admin/admin-ajax.php';
			console.error('Warning: ajaxurl not defined, using fallback:', ajaxurl);
		}
	}

	// Create plugin namespace to avoid conflicts
	if (typeof window.TLDRProPlugin === 'undefined') {
		window.TLDRProPlugin = {};
	}
	
	/**
	 * Bulk operations handler
	 */
	window.TLDRProPlugin.Bulk = {
		
		// Properties
		isProcessing: false,
		cancelRequested: false,
		currentQueue: [],
		processedCount: 0,
		totalCount: 0,
		
		/**
		 * Initialize module
		 */
		init: function() {
			this.bindEvents();
			this.setupProgressModal();
		},
		
		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;
			
			// Select/Deselect all
			$('#tldr-pro-check-all').on('change', function() {
				$('input[name="post_ids[]"]').prop('checked', $(this).is(':checked'));
			});
			
			$('#tldr-pro-select-all').on('click', function(e) {
				e.preventDefault();
				$('input[name="post_ids[]"]').prop('checked', true);
				$('#tldr-pro-check-all').prop('checked', true);
			});
			
			$('#tldr-pro-deselect-all').on('click', function(e) {
				e.preventDefault();
				$('input[name="post_ids[]"]').prop('checked', false);
				$('#tldr-pro-check-all').prop('checked', false);
			});
			
			// Generate bulk summaries
			$('#tldr-pro-generate-bulk').on('click', function(e) {
				e.preventDefault();
				self.startBulkGeneration();
			});
			
			// Generate single summary
			$('.tldr-pro-generate-single, .tldr-pro-regenerate').on('click', function(e) {
				e.preventDefault();
				var postId = $(this).data('post-id');
				self.generateSingle(postId, $(this));
			});
			
			// Preview summary
			$('.tldr-pro-preview').on('click', function(e) {
				e.preventDefault();
				var postId = $(this).data('post-id');
				var postTitle = $(this).data('post-title');
				self.previewSummary(postId, postTitle);
			});
			
			// Cancel bulk operation
			$('#tldr-pro-cancel-bulk').on('click', function(e) {
				e.preventDefault();
				self.cancelBulkGeneration();
			});
		},
		
		/**
		 * Setup progress modal
		 */
		setupProgressModal: function() {
			// Modal is already in HTML, just ensure it's hidden
			$('#tldr-pro-progress-modal').hide();
			
			// Close modal handlers
			$('.tldr-pro-modal-close, .tldr-pro-modal-close-btn').on('click', function() {
				$(this).closest('[id$="-modal"]').fadeOut();
			});
			
			// Close on overlay click
			$('.tldr-pro-modal-overlay').on('click', function() {
				$(this).parent().fadeOut();
			});
		},
		
		/**
		 * Start bulk generation process
		 */
		startBulkGeneration: function() {
			var self = this;
			
			// Get selected post IDs
			var selectedPosts = $('input[name="post_ids[]"]:checked').map(function() {
				return $(this).val();
			}).get();
			
			if (selectedPosts.length === 0) {
				alert(tldr_pro_admin.i18n.no_posts_selected || 'Please select at least one post.');
				return;
			}
			
			// Confirm action
			if (!confirm(tldr_pro_admin.i18n.confirm_bulk || 'Generate summaries for ' + selectedPosts.length + ' posts?')) {
				return;
			}
			
			// Setup queue
			this.currentQueue = selectedPosts;
			this.processedCount = 0;
			this.totalCount = selectedPosts.length;
			this.cancelRequested = false;
			this.isProcessing = true;
			
			// Show progress modal
			this.showProgressModal();
			
			// Start processing
			this.processNextInQueue();
		},
		
		/**
		 * Process next post in queue
		 */
		processNextInQueue: function() {
			var self = this;
			
			// Check if cancelled or queue is empty
			if (this.cancelRequested || this.currentQueue.length === 0) {
				this.finishBulkGeneration();
				return;
			}
			
			// Get next post ID
			var postId = this.currentQueue.shift();
			
			// Update progress
			this.updateProgress();
			
			// Generate summary for this post
			console.log('Processing post ID:', postId, 'Queue remaining:', this.currentQueue.length);
			
			// Debug check for tldr_pro_admin object
			if (typeof tldr_pro_admin === 'undefined') {
				console.error('ERROR: tldr_pro_admin is not defined! Script localization failed.');
				alert('Configuration error: Script localization failed. Please refresh the page.');
				return;
			}
			
			var ajaxData = {
				action: 'tldr_pro_generate_summary',
				post_id: postId,
				nonce: tldr_pro_admin.nonce_test || tldr_pro_admin.nonce
			};
			
			console.log('Bulk AJAX data:', ajaxData);
			console.log('Using AJAX URL:', ajaxurl);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: ajaxData,
				beforeSend: function() {
					console.log('Sending bulk request for post:', postId);
				},
				success: function(response) {
					console.log('Bulk response for post', postId, ':', response);
					
					if (response.success) {
						console.log('Success! Post', postId, 'summary generated');
						// Update table row
						self.updateTableRow(postId, true);
					} else {
						console.error('Failed to generate summary for post ' + postId + ':', response.data);
						console.error('Error details:', response);
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error for post ' + postId + ':', error);
					console.error('XHR:', xhr);
					console.error('Status:', status);
					console.error('Response:', xhr.responseText);
				},
				complete: function() {
					// Process next regardless of success/failure
					self.processedCount++;
					self.updateProgress();
					
					// Add small delay to prevent server overload
					setTimeout(function() {
						self.processNextInQueue();
					}, 500);
				}
			});
		},
		
		/**
		 * Generate summary for single post
		 */
		generateSingle: function(postId, $button) {
			var self = this;
			
			console.log('=== Starting Single Summary Generation ===');
			console.log('Post ID:', postId);
			console.log('Time:', new Date().toISOString());
			
			// Disable button and show loading
			$button.prop('disabled', true).text(tldr_pro_admin.i18n.generating || 'Generating...');
			
			// Debug check for tldr_pro_admin object
			if (typeof tldr_pro_admin === 'undefined') {
				console.error('ERROR: tldr_pro_admin is not defined! Script localization failed.');
				alert('Configuration error: Script localization failed. Please refresh the page.');
				return;
			}
			
			var ajaxData = {
				action: 'tldr_pro_generate_summary',
				post_id: postId,
				nonce: tldr_pro_admin.nonce_test || tldr_pro_admin.nonce
			};
			
			console.log('AJAX URL:', ajaxurl);
			console.log('AJAX Data:', ajaxData);
			console.log('Using nonce:', ajaxData.nonce);
			console.log('tldr_pro_admin object:', tldr_pro_admin);
			
			var startTime = Date.now();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: ajaxData,
				beforeSend: function(xhr) {
					console.log('Sending AJAX request...');
					console.log('Request headers:', xhr.getAllResponseHeaders ? xhr.getAllResponseHeaders() : 'N/A');
				},
				success: function(response) {
					var duration = (Date.now() - startTime) / 1000;
					console.log('AJAX Success! Duration:', duration + 's');
					console.log('Response:', response);
					
					if (response.success) {
						console.log('Summary generated successfully');
						console.log('Response data:', response.data);
						
						// Update row
						self.updateTableRow(postId, true);
						
						// Show success message
						self.showNotice('success', response.data.message || 'Summary generated successfully.');
					} else {
						console.error('Server returned error');
						console.error('Error message:', response.data ? response.data.message : 'Unknown error');
						console.error('Full response:', response);
						
						self.showNotice('error', response.data.message || 'Failed to generate summary.');
					}
				},
				error: function(xhr, status, error) {
					var duration = (Date.now() - startTime) / 1000;
					console.error('AJAX Error! Duration:', duration + 's');
					console.error('Status:', status);
					console.error('Error:', error);
					console.error('XHR:', xhr);
					console.error('Response Text:', xhr.responseText);
					console.error('Status Code:', xhr.status);
					
					self.showNotice('error', 'Connection error: ' + error);
				},
				complete: function(xhr, status) {
					console.log('AJAX Complete');
					console.log('Final status:', status);
					console.log('=== Single Summary Generation Completed ===');
					
					// Re-enable button
					var btnConfig = (typeof tldr_pro_bulk !== 'undefined') ? tldr_pro_bulk : tldr_pro_admin;
			$button.prop('disabled', false).text((btnConfig && btnConfig.i18n && btnConfig.i18n.regenerate) || 'Regenerate');
				}
			});
		},
		
		/**
		 * Preview summary
		 */
		previewSummary: function(postId, postTitle) {
			var self = this;
			
			// Show loading in modal
			$('#tldr-pro-preview-modal').fadeIn();
			$('#tldr-preview-content').html('<div class="spinner is-active"></div>');
			$('#tldr-preview-title').text(postTitle || 'Loading...');
			
			// Get summary
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'tldr_pro_get_summary',
					post_id: postId,
					nonce: (typeof tldr_pro_bulk !== 'undefined' ? (tldr_pro_bulk.nonce_test || tldr_pro_bulk.nonce) : (tldr_pro_admin.nonce_test || tldr_pro_admin.nonce))
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						
						// Update modal content
						$('#tldr-preview-content').html(data.summary_html || data.summary_text);
						$('#tldr-preview-provider').text('Provider: ' + (data.api_provider || 'Unknown'));
						$('#tldr-preview-words').text('Words: ' + (data.word_count || '0'));
						$('#tldr-preview-chars').text('Characters: ' + (data.summary_text ? data.summary_text.length : '0'));
						
						// Update time if available
						if (data.generated_at) {
							$('#tldr-preview-time').text('Generated: ' + data.generated_at);
						}
					} else {
						$('#tldr-preview-content').html('<p class="error">' + (response.data.message || 'Failed to load summary') + '</p>');
					}
				},
				error: function(xhr, status, error) {
					$('#tldr-preview-content').html('<p class="error">Connection error: ' + error + '</p>');
				}
			});
		},
		
		/**
		 * Update table row after generation
		 */
		updateTableRow: function(postId, success) {
			var $row = $('input[value="' + postId + '"]').closest('tr');
			
			if (success) {
				// Update status column (column 4)
				var $statusCell = $row.find('td').eq(3);
				$statusCell.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + (tldr_pro_admin.i18n.generated || 'Generated'));
				
				// Update actions column (column 5)
				var $actionsCell = $row.find('td').eq(4);
				var postTitle = $row.find('td').eq(0).find('strong a').text();
				
				// Clear and rebuild actions column with both buttons
				$actionsCell.html(
					'<button type="button" class="button button-small tldr-pro-generate-single" data-post-id="' + postId + '">' + 
					(tldr_pro_admin.i18n.regenerate || 'Regenerate') + 
					'</button> ' +
					'<button type="button" class="button button-small tldr-pro-preview" data-post-id="' + postId + '" data-post-title="' + postTitle + '">' + 
					(tldr_pro_admin.i18n.preview || 'Preview') + 
					'</button>'
				);
				
				// Bind events to new buttons
				$actionsCell.find('.tldr-pro-generate-single').on('click', function(e) {
					e.preventDefault();
					window.TLDRProPlugin.Bulk.generateSingle(postId, $(this));
				});
				
				$actionsCell.find('.tldr-pro-preview').on('click', function(e) {
					e.preventDefault();
					window.TLDRProPlugin.Bulk.previewSummary(postId, postTitle);
				});
			}
		},
		
		/**
		 * Show progress modal
		 */
		showProgressModal: function() {
			$('#tldr-pro-progress-modal').fadeIn();
			this.updateProgress();
		},
		
		/**
		 * Update progress display
		 */
		updateProgress: function() {
			var percentage = this.totalCount > 0 ? Math.round((this.processedCount / this.totalCount) * 100) : 0;
			
			$('.tldr-pro-progress-fill').css('width', percentage + '%');
			$('.tldr-pro-progress-current').text(this.processedCount);
			$('.tldr-pro-progress-total').text(this.totalCount);
		},
		
		/**
		 * Cancel bulk generation
		 */
		cancelBulkGeneration: function() {
			this.cancelRequested = true;
			var cancelConfig = (typeof tldr_pro_bulk !== 'undefined') ? tldr_pro_bulk : tldr_pro_admin;
			$('#tldr-pro-cancel-bulk').prop('disabled', true).text((cancelConfig && cancelConfig.i18n && cancelConfig.i18n.cancelling) || 'Cancelling...');
		},
		
		/**
		 * Finish bulk generation
		 */
		finishBulkGeneration: function() {
			this.isProcessing = false;
			
			// Hide modal after short delay
			setTimeout(function() {
				$('#tldr-pro-progress-modal').fadeOut();
				
				// Reset progress
				$('.tldr-pro-progress-fill').css('width', '0%');
				$('.tldr-pro-progress-current').text('0');
				$('.tldr-pro-progress-total').text('0');
				var cancelBtnConfig = (typeof tldr_pro_bulk !== 'undefined') ? tldr_pro_bulk : tldr_pro_admin;
				$('#tldr-pro-cancel-bulk').prop('disabled', false).text((cancelBtnConfig && cancelBtnConfig.i18n && cancelBtnConfig.i18n.cancel) || 'Cancel');
			}, 1000);
			
			// Show completion message
			var message = this.cancelRequested ? 
				'Bulk generation cancelled. Processed ' + this.processedCount + ' of ' + this.totalCount + ' posts.' :
				'Successfully processed ' + this.processedCount + ' posts.';
			
			this.showNotice('info', message);
		},
		
		/**
		 * Show admin notice
		 */
		showNotice: function(type, message) {
			var noticeClass = 'notice-' + type;
			var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
			
			$('.wrap > h1').after($notice);
			
			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}
	};
	
	// Initialize when document is ready
	$(document).ready(function() {
		if ($('.tldr-pro-bulk-wrapper').length > 0) {
			window.TLDRProPlugin.Bulk.init();
		}
	});
	
})(jQuery);