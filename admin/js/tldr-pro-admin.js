/**
 * Admin JavaScript for TL;DR Pro
 *
 * @package TLDR_Pro
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Initialize Select2 for language dropdown
		if ($('.tldr-pro-select2').length && $.fn.select2) {
			$('.tldr-pro-select2').select2({
				placeholder: 'Select a language',
				allowClear: false,
				width: '400px',
				templateResult: function(data) {
					if (!data.id) {
						return data.text;
					}
					// Format with flag
					let $result = $('<span>' + data.text + '</span>');
					return $result;
				},
				templateSelection: function(data) {
					return data.text;
				}
			});
		}
		
		// Test API Connection
		$('.tldr-pro-test-api').on('click', function(e) {
			e.preventDefault();
			
			let $button = $(this);
			let provider = $button.data('provider');
			let $section = $button.closest('.tldr-pro-provider-section');
			let apiKey = $section.find('.tldr-pro-api-key').val();
			let $resultsDiv = $section.find('.tldr-pro-test-results');
			
			if (!apiKey) {
				showTestResult($resultsDiv, 'error', 'Please enter an API key first.');
				return;
			}
			
			$button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
			
			// Show progress bar
			let progressHtml = '<div class="tldr-pro-test-progress">';
			progressHtml += '<div class="notice notice-info"><p>Initializing test...</p></div>';
			progressHtml += '<div class="tldr-pro-progress-bar">';
			progressHtml += '<div class="tldr-pro-progress-fill" style="width: 0%"></div>';
			progressHtml += '<div class="tldr-pro-progress-text">0%</div>';
			progressHtml += '</div>';
			progressHtml += '<div class="tldr-pro-test-status"></div>';
			progressHtml += '</div>';
			$resultsDiv.html(progressHtml);
			
			// Start progress monitoring
			let progressInterval = setInterval(function() {
				$.ajax({
					url: tldr_pro_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'tldr_pro_get_test_progress',
						nonce: tldr_pro_admin.nonce
					},
					success: function(response) {
						if (response.success && response.data) {
							let progress = response.data;
							$resultsDiv.find('.tldr-pro-progress-fill').css('width', progress.progress + '%');
							$resultsDiv.find('.tldr-pro-progress-text').text(progress.progress + '%');
							
							let statusText = '';
							switch(progress.current_test) {
								case 'api_key_validation':
									statusText = '🔑 Validating API key format...';
									break;
								case 'network_connectivity':
									statusText = '🌐 Testing network connectivity...';
									break;
								case 'authentication':
									statusText = '🔐 Authenticating with API provider...';
									break;
								case 'model_verification':
								case 'model_availability':
									statusText = '🤖 Verifying model availability...';
									break;
								case 'generation_test':
									statusText = '✍️ Testing content generation...';
									break;
								case 'finalizing':
									statusText = '✨ Finalizing test results...';
									break;
								default:
									statusText = 'Testing...';
							}
							$resultsDiv.find('.tldr-pro-test-status').html('<p>' + statusText + '</p>');
						}
					}
				});
			}, 500);
			
			// Main test request
			$.ajax({
				url: tldr_pro_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'tldr_pro_test_api',
					nonce: tldr_pro_admin.nonce_test || tldr_pro_admin.nonce,
					provider: provider,
					api_key: apiKey
				},
				success: function(response) {
					if (response.success && response.data) {
						displayDetailedTestResults($resultsDiv, response.data);
						
						// Update validation badge on success
						if (response.data.status === 'success') {
							updateValidationBadge($section, true);
						}
					} else if (response.success) {
						let successMsg = 'Connection successful!';
						if (response && response.data && response.data.message) {
							successMsg = response.data.message;
						}
						showTestResult($resultsDiv, 'success', successMsg);
						updateValidationBadge($section, true);
					} else {
						if (response.data && response.data.tests) {
							displayDetailedTestResults($resultsDiv, response.data);
						} else {
							let errorMsg = 'Connection failed.';
							if (response && response.data && response.data.message) {
								errorMsg = response.data.message;
							}
							showTestResult($resultsDiv, 'error', errorMsg);
						}
						// Update validation badge on failure
						updateValidationBadge($section, false);
					}
				},
				error: function(xhr, status, error) {
					showTestResult($resultsDiv, 'error', 'Network error: ' + error);
				},
				complete: function() {
					clearInterval(progressInterval);
					$button.prop('disabled', false).html('Test Connection');
					
					// Load test history
					loadTestHistory(provider);
				}
			});
		});
		
		// Function to load test history
		function loadTestHistory(provider) {
			$.ajax({
				url: tldr_pro_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'tldr_pro_get_test_history',
					nonce: tldr_pro_admin.nonce,
					provider: provider,
					limit: 5
				},
				success: function(response) {
					if (response.success && response.data.length > 0) {
						let $historyDiv = $('#tldr-pro-test-history-' + provider);
						if ($historyDiv.length === 0) {
							$historyDiv = $('<div id="tldr-pro-test-history-' + provider + '" class="tldr-pro-test-history"></div>');
							$('.tldr-pro-provider-section[data-provider="' + provider + '"]').append($historyDiv);
						}
						
						let historyHtml = '<h4>Recent Test History</h4>';
						historyHtml += '<table class="wp-list-table widefat fixed striped">';
						historyHtml += '<thead><tr>';
						historyHtml += '<th>Date</th>';
						historyHtml += '<th>Model</th>';
						historyHtml += '<th>Status</th>';
						historyHtml += '<th>Time</th>';
						historyHtml += '</tr></thead>';
						historyHtml += '<tbody>';
						
						$.each(response.data, function(i, test) {
							let statusIcon = test.status === 'success' ? '✅' : 
											test.status === 'warning' ? '⚠️' : '❌';
							let date = new Date(test.tested_at);
							let dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
							
							historyHtml += '<tr>';
							historyHtml += '<td>' + dateStr + '</td>';
							historyHtml += '<td>' + test.model + '</td>';
							historyHtml += '<td>' + statusIcon + ' ' + test.status + '</td>';
							historyHtml += '<td>' + (test.timing ? test.timing.toFixed(2) + 's' : '-') + '</td>';
							historyHtml += '</tr>';
						});
						
						historyHtml += '</tbody></table>';
						$historyDiv.html(historyHtml).slideDown();
					}
				}
			});
		}
		
		// Function to display detailed test results
		function displayDetailedTestResults($container, data) {
			let html = '<div class="tldr-pro-test-report">';
			
			// Overall status
			let statusClass = data.status === 'success' ? 'notice-success' : 
							 data.status === 'warning' ? 'notice-warning' : 'notice-error';
			let statusIcon = data.status === 'success' ? '✅' : 
							data.status === 'warning' ? '⚠️' : '❌';
			
			html += '<div class="notice ' + statusClass + '">';
			html += '<p><strong>' + statusIcon + ' ' + (data.summary || 'Test completed') + '</strong></p>';
			html += '</div>';
			
			// Individual test results
			if (data.tests) {
				html += '<div class="tldr-pro-test-details">';
				html += '<h4>Test Results:</h4>';
				html += '<ul class="tldr-pro-test-list">';
				
				$.each(data.tests, function(key, test) {
					let testIcon = test.status === 'success' ? '✅' : 
								  test.status === 'warning' ? '⚠️' : 
								  test.status === 'error' ? '❌' : 'ℹ️';
					let testClass = 'tldr-test-' + test.status;
					
					html += '<li class="' + testClass + '">';
					html += testIcon + ' <strong>' + formatTestName(key) + ':</strong> ' + test.message;
					
					if (test.details) {
						html += '<ul class="tldr-test-details">';
						$.each(test.details, function(detailKey, detailValue) {
							html += '<li>' + formatTestName(detailKey) + ': ' + detailValue + '</li>';
						});
						html += '</ul>';
					}
					
					html += '</li>';
				});
				
				html += '</ul>';
				html += '</div>';
			}
			
			// Model information
			if (data.model_info) {
				html += '<div class="tldr-pro-model-info">';
				html += '<h4>Model Information:</h4>';
				html += '<ul>';
				html += '<li><strong>Model:</strong> ' + data.model_info.name + '</li>';
				html += '<li><strong>Description:</strong> ' + data.model_info.description + '</li>';
				html += '<li><strong>Pricing:</strong> ' + data.model_info.pricing + '</li>';
				html += '<li><strong>Limits:</strong> ' + data.model_info.limits + '</li>';
				html += '</ul>';
				html += '</div>';
			}
			
			// Recommendations
			if (data.recommendations && data.recommendations.length > 0) {
				html += '<div class="tldr-pro-recommendations">';
				html += '<h4>Recommendations:</h4>';
				html += '<ul>';
				$.each(data.recommendations, function(i, rec) {
					html += '<li>💡 ' + rec + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}
			
			// Timing information
			if (data.timing && data.timing.total) {
				html += '<div class="tldr-pro-timing">';
				html += '<p class="description">Total test time: ' + data.timing.total.toFixed(2) + ' seconds</p>';
				html += '</div>';
			}
			
			html += '</div>';
			
			$container.html(html);
		}
		
		// Helper function to format test names
		function formatTestName(name) {
			return name.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
				return l.toUpperCase();
			});
		}
		
		// Simple test result display
		function showTestResult($container, status, message) {
			let statusClass = status === 'success' ? 'notice-success' : 
							 status === 'warning' ? 'notice-warning' : 'notice-error';
			let html = '<div class="notice ' + statusClass + '"><p>' + message + '</p></div>';
			$container.html(html);
		}
		
		// Function to update validation badge
		function updateValidationBadge($section, isValid) {
			let $header = $section.find('.provider-header');
			let $badge = $header.find('.tldr-pro-validation-badge');
			
			if (isValid) {
				// Remove any existing badge
				$badge.remove();
				
				// Add validated badge
				let now = new Date();
				let dateStr = now.toLocaleDateString();
				let validBadge = '<span class="tldr-pro-validation-badge validated">';
				validBadge += '✅ Validated ';
				validBadge += '<span class="validation-date">' + dateStr + '</span>';
				validBadge += '</span>';
				
				$header.append(validBadge);
			} else {
				// Update to not validated if there's a key
				if ($section.find('.tldr-pro-api-key').val()) {
					$badge.remove();
					let invalidBadge = '<span class="tldr-pro-validation-badge not-validated">';
					invalidBadge += '❌ Not Validated';
					invalidBadge += '</span>';
					$header.append(invalidBadge);
				}
			}
		}
		
		// Bulk Operations
		if ($('.tldr-pro-bulk-wrapper').length > 0) {
			
			// Select/Deselect All
			$('#tldr-pro-check-all').on('change', function() {
				$('input[name="post_ids[]"]').prop('checked', $(this).prop('checked'));
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
			
			// Generate Single Summary
			$('.tldr-pro-generate-single, .tldr-pro-regenerate').on('click', function(e) {
				e.preventDefault();
				
				let $button = $(this);
				let postId = $button.data('post-id');
				
				$button.prop('disabled', true).html('<span class="spinner is-active"></span> Starting...');
				
				// Create status display
				let $row = $button.closest('tr');
				let $statusCell = $row.find('td:nth-child(5)');
				$statusCell.html('<div class="tldr-pro-generation-status"><span class="spinner is-active"></span> <span class="status-text">Initializing...</span></div>');
				
				// Function to poll status
				function pollGenerationStatus(requestId, startTime) {
					$.ajax({
						url: tldr_pro_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'tldr_pro_get_generation_status',
							nonce: tldr_pro_admin.nonce,
							request_id: requestId
						},
						success: function(statusResponse) {
							if (statusResponse.success && statusResponse.data) {
								let status = statusResponse.data;
								let elapsed = Math.round((Date.now() - startTime) / 1000);
								
								// Update status text with elapsed time
								$statusCell.find('.status-text').html(status.message + ' (' + elapsed + 's)');
								
								// Check if completed or error
								if (status.status === 'completed' || status.status === 'error' || status.status === 'timeout') {
									// Stop polling
									return;
								} else {
									// Continue polling
									setTimeout(function() {
										pollGenerationStatus(requestId, startTime);
									}, 2000); // Poll every 2 seconds
								}
							}
						}
					});
				}
				
				// Store request start time
				let startTime = Date.now();
				
				$.ajax({
					url: tldr_pro_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'tldr_pro_generate_summary',
						nonce: tldr_pro_admin.nonce_test,
						post_id: postId
					},
					success: function(response) {
						if (response.success) {
							// Update UI to show summary was generated
							$statusCell.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Generated');
							
							// Add Preview button if it doesn't exist
							if (!$button.siblings('.tldr-pro-preview').length) {
								let previewBtn = '<button type="button" class="button button-small tldr-pro-preview" data-post-id="' + postId + '" data-post-title="' + $row.find('td:nth-child(2) a').text() + '">Preview</button>';
								$button.after(' ' + previewBtn);
							}
							
							// Change Generate to Regenerate
							$button.text('Regenerate').removeClass('tldr-pro-generate-single').addClass('tldr-pro-regenerate');
							
							alert('Summary generated successfully!');
						} else {
							// Safely access the error message
							let errorMessage = 'Failed to generate summary';
							if (response && response.data && response.data.message) {
								errorMessage = response.data.message;
							} else if (tldr_pro_admin && tldr_pro_admin.strings && tldr_pro_admin.strings.error) {
								errorMessage = tldr_pro_admin.strings.error;
							} else if (tldr_pro_admin && tldr_pro_admin.i18n && tldr_pro_admin.i18n.error) {
								errorMessage = tldr_pro_admin.i18n.error;
							}
							console.error('Summary generation failed:', response);
							alert(errorMessage);
						}
					},
					error: function(xhr, status, error) {
						console.error('AJAX error:', status, error);
						console.error('Response:', xhr.responseText);
						let errorMessage = 'Connection error';
						if (tldr_pro_admin && tldr_pro_admin.strings && tldr_pro_admin.strings.error) {
							errorMessage = tldr_pro_admin.strings.error;
						} else if (tldr_pro_admin && tldr_pro_admin.i18n && tldr_pro_admin.i18n.error) {
							errorMessage = tldr_pro_admin.i18n.error;
						}
						alert(errorMessage + ': ' + error);
					},
					complete: function() {
						$button.prop('disabled', false).text('Generate');
					}
				});
			});
			
			// Bulk Generate
			$('#tldr-pro-generate-bulk').on('click', function(e) {
				e.preventDefault();
				
				let postIds = [];
				$('input[name="post_ids[]"]:checked').each(function() {
					postIds.push($(this).val());
				});
				
				if (postIds.length === 0) {
					alert('Please select at least one post.');
					return;
				}
				
				// Show progress modal
				$('#tldr-pro-progress-modal').show();
				$('.tldr-pro-progress-total').text(postIds.length);
				$('.tldr-pro-progress-current').text(0);
				$('.tldr-pro-progress-fill').css('width', '0%');
				
				let processed = 0;
				let cancelled = false;
				
				// Process posts one by one
				function processNext() {
					if (cancelled || processed >= postIds.length) {
						$('#tldr-pro-progress-modal').hide();
						if (!cancelled) {
							alert('Bulk generation completed!');
							location.reload();
						}
						return;
					}
					
					let postId = postIds[processed];
					
					$.ajax({
						url: tldr_pro_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'tldr_pro_generate_summary',
							nonce: tldr_pro_admin.nonce_test,
							post_id: postId
						},
						success: function(response) {
							processed++;
							$('.tldr-pro-progress-current').text(processed);
							$('.tldr-pro-progress-fill').css('width', (processed / postIds.length * 100) + '%');
							processNext();
						},
						error: function() {
							processed++;
							$('.tldr-pro-progress-current').text(processed);
							$('.tldr-pro-progress-fill').css('width', (processed / postIds.length * 100) + '%');
							processNext();
						}
					});
				}
				
				// Start processing
				processNext();
				
				// Cancel button
				$('#tldr-pro-cancel-bulk').on('click', function() {
					cancelled = true;
					$('#tldr-pro-progress-modal').hide();
				});
			});
			
			// Preview Summary (use delegation for dynamically added buttons)
			$(document).on('click', '.tldr-pro-preview', function(e) {
				e.preventDefault();
				
				let $button = $(this);
				let postId = $button.data('post-id');
				let postTitle = $button.data('post-title');
				
				$button.prop('disabled', true).text('Loading...');
				
				// Load summary from database
				$.ajax({
					url: tldr_pro_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'tldr_pro_get_summary',
						nonce: tldr_pro_admin.nonce_test,
						post_id: postId
					},
					success: function(response) {
						if (response.success) {
							// Populate modal with summary data
							$('#tldr-preview-post-title').text(postTitle);
							
							// Handle HTML formatted summaries
							let summaryContent = response.data.summary_html || response.data.summary_text || '';
							
							// Check if content contains HTML tags
							if (summaryContent.indexOf('<div') !== -1 || summaryContent.indexOf('<ul') !== -1 || summaryContent.indexOf('<p') !== -1) {
								// Content already has HTML formatting, insert as-is
								$('#tldr-preview-content').html(summaryContent);
							} else {
								// Plain text content, wrap in paragraph tags for better display
								let paragraphs = summaryContent.split('\n\n');
								let formattedContent = paragraphs.map(function(para) {
									return '<p>' + para.replace(/\n/g, '<br>') + '</p>';
								}).join('');
								$('#tldr-preview-content').html(formattedContent);
							}
							
							// Add metadata if available
							if (response.data.provider) {
								$('#tldr-preview-provider').html('<span class="dashicons dashicons-cloud"></span> ' + response.data.provider);
							}
							if (response.data.tokens_used) {
								$('#tldr-preview-tokens').html('<span class="dashicons dashicons-admin-generic"></span> ' + response.data.tokens_used + ' tokens');
							}
							if (response.data.generation_time) {
								$('#tldr-preview-time').html('<span class="dashicons dashicons-clock"></span> ' + response.data.generation_time + 's');
							}
							
							// Calculate word and character count
							let summaryHtml = response.data.summary_html || response.data.summary_text || response.data.summary || '';
							let textContent = $('<div>').html(summaryHtml).text();
							let wordCount = textContent.trim().split(/\s+/).filter(function(word) { return word.length > 0; }).length;
							let charCount = textContent.length;
							
							$('#tldr-preview-words').html('<span class="dashicons dashicons-editor-alignleft"></span> ' + wordCount + ' words');
							$('#tldr-preview-chars').html('<span class="dashicons dashicons-editor-code"></span> ' + charCount + ' chars');
							
							// Show modal
							$('#tldr-pro-preview-modal').fadeIn(300);
						} else {
							let errorMsg = 'Failed to load summary';
							if (response && response.data && response.data.message) {
								errorMsg = response.data.message;
							}
							alert(errorMsg);
						}
					},
					error: function() {
						alert('Network error occurred');
					},
					complete: function() {
						$button.prop('disabled', false).text('Preview');
					}
				});
			});
			
			// Close modal handlers
			$('.tldr-pro-modal-close, .tldr-pro-modal-close-btn, .tldr-pro-modal-overlay').on('click', function() {
				$('#tldr-pro-preview-modal').fadeOut(300);
			});
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('#tldr-pro-preview-modal').fadeOut(300);
				}
			});
		}
		
		// Statistics Page
		if ($('#tldr-pro-monthly-chart').length > 0 && typeof Chart !== 'undefined') {
			let ctx = document.getElementById('tldr-pro-monthly-chart').getContext('2d');
			new Chart(ctx, {
				type: 'line',
				data: {
					labels: tldrProChartData.labels,
					datasets: [{
						label: 'Summaries Generated',
						data: tldrProChartData.data,
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34, 113, 177, 0.1)',
						tension: 0.1
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						y: {
							beginAtZero: true
						}
					}
				}
			});
		}
		
		// Export Functions
		$('#tldr-pro-export-csv').on('click', function(e) {
			e.preventDefault();
			window.location.href = tldr_pro_admin.ajax_url + '?action=tldr_pro_export_stats&format=csv&nonce=' + tldr_pro_admin.nonce;
		});
		
		$('#tldr-pro-export-json').on('click', function(e) {
			e.preventDefault();
			window.location.href = tldr_pro_admin.ajax_url + '?action=tldr_pro_export_stats&format=json&nonce=' + tldr_pro_admin.nonce;
		});
		
		// Settings Form Enhancement
		$('#tldr_pro_api_provider').on('change', function() {
			let provider = $(this).val();
			let $apiKeyField = $('#tldr_pro_api_key');
			let $description = $apiKeyField.siblings('.description');
			
			switch(provider) {
				case 'deepseek':
					$description.text('Enter your DeepSeek API key. Get one at https://platform.deepseek.com/');
					break;
				case 'gemini':
					$description.text('Enter your Google Gemini API key. Get one at https://makersuite.google.com/app/apikey');
					break;
			}
		});
		
		// REMOVED ALL AUTO-SAVE FUNCTIONALITY
		// Settings will ONLY save when user clicks Save Changes button
		
		// Form submission handler - ensure ALL fields are submitted
		$('#tldr-pro-settings-form').on('submit', function(e) {
			let $form = $(this);
			
			// IMPORTANT: Add class to show all tabs temporarily
			// This ensures all form fields are submitted, not just visible ones
			$form.addClass('submitting');
			
			// Visual feedback
			let $submitButton = $form.find('input[type="submit"]');
			$submitButton.val('Saving...');
			
			// Allow form to submit normally to options.php
			// WordPress will handle the actual saving
		});
		
		// Settings Management Buttons
		$('#tldr-pro-init-defaults').on('click', function(e) {
			e.preventDefault();
			let $button = $(this);
			let $status = $('#settings-management-status');
			
			if (confirm('This will initialize all missing default settings. Continue?')) {
				$button.prop('disabled', true).text('Initializing...');
				
				$.ajax({
					url: tldr_pro_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'tldr_pro_init_defaults',
						nonce: tldr_pro_admin.nonce_general || tldr_pro_admin.nonce
					},
					success: function(response) {
						if (response.success) {
							$status.html('<div class="notice notice-success inline"><p>Default settings initialized successfully!</p></div>');
							// Reload page after 2 seconds
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							$status.html('<div class="notice notice-error inline"><p>Failed to initialize defaults: ' + (response.data || 'Unknown error') + '</p></div>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error inline"><p>Error initializing defaults</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false).text('Initialize Default Settings');
					}
				});
			}
		});
		
		$('#tldr-pro-reset-settings').on('click', function(e) {
			e.preventDefault();
			let $button = $(this);
			let $status = $('#settings-management-status');
			
			if (confirm('WARNING: This will reset ALL settings to their default values. This cannot be undone. Continue?')) {
				if (confirm('Are you absolutely sure? All your custom settings will be lost.')) {
					$button.prop('disabled', true).text('Resetting...');
					
					// Debug logging
					console.log('Reset Settings AJAX Request:');
					console.log('URL:', tldr_pro_admin.ajax_url);
					console.log('Nonce:', tldr_pro_admin.nonce_general || tldr_pro_admin.nonce);
					
					$.ajax({
						url: tldr_pro_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'tldr_pro_reset_all_settings',
							nonce: tldr_pro_admin.nonce_general || tldr_pro_admin.nonce
						},
						success: function(response) {
							console.log('Reset Settings Response:', response);
							if (response.success) {
								$status.html('<div class="notice notice-success inline"><p>All settings reset to defaults successfully!</p></div>');
								// Reload page after 2 seconds
								setTimeout(function() {
									location.reload();
								}, 2000);
							} else {
								console.error('Reset failed:', response.data);
								$status.html('<div class="notice notice-error inline"><p>Failed to reset settings: ' + (response.data || 'Unknown error') + '</p></div>');
							}
						},
						error: function(xhr, status, error) {
							console.error('AJAX Error:', status, error);
							console.error('Response:', xhr.responseText);
							$status.html('<div class="notice notice-error inline"><p>Error resetting settings: ' + error + '</p></div>');
						},
						complete: function() {
							$button.prop('disabled', false).text('Reset All Settings to Defaults');
						}
					});
				}
			}
		});
		
		// Prompt Templates Functionality
		initPromptTemplates();
		
		function initPromptTemplates() {
			// Reset to Default button for individual prompts
			$('.reset-prompt').on('click', function(e) {
				e.preventDefault();
				let $button = $(this);
				let provider = $button.data('provider');
				
				if (confirm('Are you sure you want to reset this prompt to default?')) {
					$.ajax({
						url: tldr_pro_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'tldr_pro_reset_prompt',
							nonce: tldr_pro_admin.nonce_reset_prompt || tldr_pro_admin.nonce_general || tldr_pro_admin.nonce,
							provider: provider
						},
						beforeSend: function() {
							$button.prop('disabled', true).html('<span class="spinner is-active"></span> Resetting...');
						},
						success: function(response) {
							if (response.success && response.data.prompt) {
								$('#tldr_pro_prompt_' + provider).val(response.data.prompt);
								showPromptStatus('success', 'Prompt reset to default successfully!');
								validatePrompt(provider, response.data.prompt);
							} else {
								showPromptStatus('error', 'Failed to reset prompt');
							}
						},
						error: function() {
							showPromptStatus('error', 'Error resetting prompt');
						},
						complete: function() {
							$button.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> Reset to Default');
						}
					});
				}
			});
			
			// Preview Prompt button
			$('.preview-prompt').on('click', function(e) {
				e.preventDefault();
				let provider = $(this).data('provider');
				let prompt = $('#tldr_pro_prompt_' + provider).val();
				
				if (!prompt) {
					alert('Please enter a prompt template first');
					return;
				}
				
				// Process prompt with actual configured settings
				let sampleVars = {
					content: 'This is sample content for preview purposes. It represents the actual article content that will be summarized.',
					language: tldr_pro_admin.current_settings.language || 'English',
					max_length: tldr_pro_admin.current_settings.max_length || 150,
					style: tldr_pro_admin.current_settings.style || 'professional',
					format: tldr_pro_admin.current_settings.format || 'paragraph',
					bullet_points: tldr_pro_admin.current_settings.bullet_points || 5
				};
				
				let processedPrompt = prompt;
				for (var key in sampleVars) {
					let regex = new RegExp('\\{' + key + '\\}', 'g');
					processedPrompt = processedPrompt.replace(regex, sampleVars[key]);
				}
				
				// Show in modal
				$('#prompt-preview-content').text(processedPrompt);
				$('#prompt-preview-modal').fadeIn();
			});
			
			// Close modal
			$('#prompt-preview-modal .close').on('click', function() {
				$('#prompt-preview-modal').fadeOut();
			});
			
			// Close modal on outside click
			$('#prompt-preview-modal').on('click', function(e) {
				if (e.target === this) {
					$(this).fadeOut();
				}
			});
			
			// Save All Templates button
			$('#save-all-prompts').on('click', function(e) {
				e.preventDefault();
				let $button = $(this);
				let prompts = {};
				let hasErrors = false;
				
				// Collect all prompts
				$('.prompt-template').each(function() {
					let $textarea = $(this);
					let provider = $textarea.attr('id').replace('tldr_pro_prompt_', '');
					let promptText = $textarea.val();
					
					if (promptText) {
						// Validate prompt
						if (promptText.indexOf('{content}') === -1) {
							hasErrors = true;
							$('#validation-' + provider)
								.addClass('error')
								.removeClass('success')
								.text('Error: Prompt must include {content} variable')
								.show();
						} else {
							prompts[provider] = promptText;
							$('#validation-' + provider).hide();
						}
					}
				});
				
				if (hasErrors) {
					showPromptStatus('error', 'Please fix validation errors before saving');
					return;
				}
				
				// Save prompts via AJAX
				$.ajax({
					url: tldr_pro_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'tldr_pro_save_prompts',
						nonce: $('#tldr_pro_prompts_nonce').val() || tldr_pro_admin.nonce_prompts || tldr_pro_admin.nonce,
						prompts: prompts
					},
					beforeSend: function() {
						$button.prop('disabled', true).html('<span class="spinner is-active"></span> Saving...');
					},
					success: function(response) {
						if (response.success) {
							showPromptStatus('success', 'All templates saved successfully!');
							
							// Show validation success for each saved prompt
							Object.keys(prompts).forEach(function(provider) {
								$('#validation-' + provider)
									.addClass('success')
									.removeClass('error')
									.text('✓ Template saved')
									.show();
								
								setTimeout(function() {
									$('#validation-' + provider).fadeOut();
								}, 3000);
							});
						} else {
							showPromptStatus('error', response.data || 'Failed to save templates');
						}
					},
					error: function() {
						showPromptStatus('error', 'Error saving templates');
					},
					complete: function() {
						$button.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save All Templates');
					}
				});
			});
			
			// Reset All to Defaults button
			$('#reset-all-prompts').on('click', function(e) {
				e.preventDefault();
				let $button = $(this);
				
				if (confirm('Are you sure you want to reset ALL prompts to their defaults? This cannot be undone.')) {
					$.ajax({
						url: tldr_pro_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'tldr_pro_reset_all_prompts',
							nonce: tldr_pro_admin.nonce_general || tldr_pro_admin.nonce
						},
						beforeSend: function() {
							$button.prop('disabled', true).html('<span class="spinner is-active"></span> Resetting...');
						},
						success: function(response) {
							if (response.success && response.data.prompts) {
								// Update all textareas with default prompts
								for (var provider in response.data.prompts) {
									$('#tldr_pro_prompt_' + provider).val(response.data.prompts[provider]);
								}
								showPromptStatus('success', 'All prompts reset to defaults successfully!');
							} else {
								showPromptStatus('error', 'Failed to reset prompts');
							}
						},
						error: function() {
							showPromptStatus('error', 'Error resetting prompts');
						},
						complete: function() {
							$button.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> Reset All to Defaults');
						}
					});
				}
			});
			
			// Live validation on prompt change
			$('.prompt-template').on('input', function() {
				let $textarea = $(this);
				let provider = $textarea.attr('id').replace('tldr_pro_prompt_', '');
				let prompt = $textarea.val();
				
				validatePrompt(provider, prompt);
			});
		}
		
		function validatePrompt(provider, prompt) {
			let $validation = $('#validation-' + provider);
			
			if (!prompt) {
				$validation.hide();
				return;
			}
			
			if (prompt.indexOf('{content}') === -1) {
				$validation
					.addClass('error')
					.removeClass('success')
					.text('Error: Prompt must include {content} variable')
					.show();
			} else if (prompt.length < 50) {
				$validation
					.addClass('error')
					.removeClass('success')
					.text('Warning: Prompt seems too short (minimum 50 characters recommended)')
					.show();
			} else {
				$validation
					.addClass('success')
					.removeClass('error')
					.text('✓ Valid prompt template')
					.show();
				
				setTimeout(function() {
					$validation.fadeOut();
				}, 2000);
			}
		}
		
		function showPromptStatus(type, message) {
			let $status = $('.prompt-save-status');
			$status
				.removeClass('success error')
				.addClass(type)
				.text(message)
				.show();
			
			setTimeout(function() {
				$status.fadeOut();
			}, 3000);
		}
		
		// Auto-validate API keys on page load
		$('.tldr-pro-provider-section').each(function() {
			let $section = $(this);
			let provider = $section.data('provider');
			let $apiKeyField = $section.find('.tldr-pro-api-key');
			let $badge = $section.find('.validation-badge');
			
			// Only auto-validate if there's an API key but no validation badge
			if ($apiKeyField.val() && $badge.length === 0) {
				console.log('Auto-validating ' + provider + ' API key...');
				setTimeout(function() {
					$section.find('.tldr-pro-test-api').trigger('click');
				}, Math.random() * 2000 + 1000); // Random delay between 1-3 seconds
			}
		});
		
		// Initialize jQuery UI Sortable for fallback order
		if ($('#fallback-order-sortable').length && $.ui && $.ui.sortable) {
			$('#fallback-order-sortable').sortable({
				axis: 'y',
				cursor: 'move',
				placeholder: 'fallback-placeholder',
				tolerance: 'pointer',
				opacity: 0.8,
				helper: 'clone',
				start: function(e, ui) {
					ui.placeholder.height(ui.item.height());
					console.log('Drag started');
				},
				update: function(e, ui) {
					let order = [];
					$(this).children('.fallback-item').each(function() {
						order.push($(this).data('provider'));
					});
					console.log('New fallback order:', order);
					$('#tldr_pro_fallback_order').val(order.join(','));
				}
			});
			
			// Add visual feedback styles
			if (!$('#fallback-sortable-styles').length) {
				let sortableStyles = '<style id="fallback-sortable-styles">';
				sortableStyles += '.fallback-item { ';
				sortableStyles += '  padding: 12px 16px; ';
				sortableStyles += '  margin: 8px 0; ';
				sortableStyles += '  background: #f9f9f9; ';
				sortableStyles += '  border: 1px solid #ddd; ';
				sortableStyles += '  border-radius: 6px; ';
				sortableStyles += '  cursor: move; ';
				sortableStyles += '  display: flex; ';
				sortableStyles += '  align-items: center; ';
				sortableStyles += '  gap: 10px; ';
				sortableStyles += '  transition: all 0.3s ease; ';
				sortableStyles += '}';
				sortableStyles += '.fallback-item:hover { ';
				sortableStyles += '  background: #e9e9e9; ';
				sortableStyles += '  border-color: #999; ';
				sortableStyles += '}';
				sortableStyles += '.fallback-placeholder { ';
				sortableStyles += '  background: #e1f5fe; ';
				sortableStyles += '  border: 2px dashed #0073aa; ';
				sortableStyles += '  border-radius: 6px; ';
				sortableStyles += '  margin: 8px 0; ';
				sortableStyles += '}';
				sortableStyles += '.ui-sortable-helper { ';
				sortableStyles += '  background: #fff; ';
				sortableStyles += '  box-shadow: 0 4px 10px rgba(0,0,0,0.15); ';
				sortableStyles += '  transform: rotate(2deg); ';
				sortableStyles += '}';
				sortableStyles += '</style>';
				$('head').append(sortableStyles);
			}
		} else {
			console.warn('jQuery UI Sortable not available for fallback order');
		}
	});
	
	// Add custom CSS for progress bar
	if (!$('#tldr-pro-test-styles').length) {
		let styles = '<style id="tldr-pro-test-styles">';
		styles += '.tldr-pro-progress-bar {';
		styles += '  width: 100%;';
		styles += '  height: 30px;';
		styles += '  background: #f0f0f1;';
		styles += '  border-radius: 15px;';
		styles += '  overflow: hidden;';
		styles += '  position: relative;';
		styles += '  margin: 15px 0;';
		styles += '}';
		styles += '.tldr-pro-progress-fill {';
		styles += '  height: 100%;';
		styles += '  background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);';
		styles += '  transition: width 0.3s ease;';
		styles += '  border-radius: 15px;';
		styles += '}';
		styles += '.tldr-pro-progress-text {';
		styles += '  position: absolute;';
		styles += '  top: 50%;';
		styles += '  left: 50%;';
		styles += '  transform: translate(-50%, -50%);';
		styles += '  font-weight: bold;';
		styles += '  color: #fff;';
		styles += '  text-shadow: 0 1px 2px rgba(0,0,0,0.3);';
		styles += '}';
		styles += '.tldr-pro-test-status {';
		styles += '  text-align: center;';
		styles += '  font-style: italic;';
		styles += '  color: #666;';
		styles += '  margin-top: 10px;';
		styles += '}';
		styles += '.tldr-pro-test-history {';
		styles += '  margin-top: 30px;';
		styles += '  padding: 20px;';
		styles += '  background: #f8f9fa;';
		styles += '  border-radius: 5px;';
		styles += '  display: none;';
		styles += '}';
		styles += '.tldr-pro-test-history h4 {';
		styles += '  margin-top: 0;';
		styles += '}';
		styles += '</style>';
		$('head').append(styles);
	}

})(jQuery);