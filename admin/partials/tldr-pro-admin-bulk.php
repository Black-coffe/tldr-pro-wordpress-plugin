<?php
/**
 * Bulk operations page template
 *
 * @package TLDR_Pro
 * @subpackage TLDR_Pro/admin/partials
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'tldr-pro' ) );
}

// Ensure required classes are available
if ( ! class_exists( 'TLDR_Pro_Database' ) ) {
	require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/class-tldr-pro-database.php';
}

// Get current tab
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'posts';

// Get filter parameters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';
$category_filter = isset( $_GET['category'] ) ? intval( $_GET['category'] ) : 0;

// Get database instance
$database = TLDR_Pro_Database::get_instance();
?>

<div class="wrap">
	<h1><?php _e( 'Bulk Operations', 'tldr-pro' ); ?></h1>
	
	<div class="tldr-pro-bulk-wrapper">
		<div class="notice notice-info">
			<p><?php _e( 'Generate summaries for multiple posts at once. Select posts below and click "Generate Summaries".', 'tldr-pro' ); ?></p>
		</div>
		
		<!-- Tabs Navigation -->
		<h2 class="nav-tab-wrapper">
			<a href="?page=tldr-pro-bulk&tab=posts" class="nav-tab <?php echo $current_tab === 'posts' ? 'nav-tab-active' : ''; ?>">
				<?php _e( 'Posts', 'tldr-pro' ); ?>
			</a>
			<a href="?page=tldr-pro-bulk&tab=pages" class="nav-tab <?php echo $current_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
				<?php _e( 'Pages', 'tldr-pro' ); ?>
			</a>
		</h2>
		
		<!-- Filters -->
		<div class="tldr-pro-filters">
			<form method="get" action="">
				<input type="hidden" name="page" value="tldr-pro-bulk" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>" />
				
				<?php if ( $current_tab === 'posts' ) : ?>
					<!-- Category filter for posts only -->
					<label for="category"><?php _e( 'Category:', 'tldr-pro' ); ?></label>
					<select name="category" id="category">
						<option value="0"><?php _e( 'All Categories', 'tldr-pro' ); ?></option>
						<?php
						$categories = get_categories( array( 'hide_empty' => false ) );
						foreach ( $categories as $category ) {
							printf(
								'<option value="%d" %s>%s</option>',
								$category->term_id,
								selected( $category_filter, $category->term_id, false ),
								esc_html( $category->name )
							);
						}
						?>
					</select>
				<?php endif; ?>
				
				<label for="status"><?php _e( 'Status:', 'tldr-pro' ); ?></label>
				<select name="status" id="status">
					<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php _e( 'All', 'tldr-pro' ); ?></option>
					<option value="no_summary" <?php selected( $status_filter, 'no_summary' ); ?>><?php _e( 'Without Summary', 'tldr-pro' ); ?></option>
					<option value="has_summary" <?php selected( $status_filter, 'has_summary' ); ?>><?php _e( 'With Summary', 'tldr-pro' ); ?></option>
				</select>
				
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'tldr-pro' ); ?>" />
			</form>
		</div>
		
		<form id="tldr-pro-bulk-form">
			<div class="tablenav top">
				<div class="alignleft actions">
					<button type="button" id="tldr-pro-select-all" class="button"><?php _e( 'Select All', 'tldr-pro' ); ?></button>
					<button type="button" id="tldr-pro-deselect-all" class="button"><?php _e( 'Deselect All', 'tldr-pro' ); ?></button>
					<button type="button" id="tldr-pro-generate-bulk" class="button button-primary"><?php _e( 'Generate Summaries', 'tldr-pro' ); ?></button>
				</div>
			</div>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="check-column">
							<input type="checkbox" id="tldr-pro-check-all" />
						</th>
						<th><?php _e( 'Title', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Type', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Word Count', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Summary Status', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Actions', 'tldr-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Build query arguments
					$post_type = ( $current_tab === 'pages' ) ? 'page' : 'post';
					
					$args = array(
						'post_type' => $post_type,
						'posts_per_page' => 20,
						'paged' => isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1,
						'post_status' => 'publish',
					);
					
					// Apply category filter for posts only
					if ( $post_type === 'post' && $category_filter > 0 ) {
						$args['cat'] = $category_filter;
					}
					
					// Get posts for filtering by summary status
					if ( $status_filter !== 'all' ) {
						// First get all posts to filter by summary status
						$args['posts_per_page'] = -1;
						$all_posts = get_posts( $args );
						$filtered_ids = array();
						
						foreach ( $all_posts as $post ) {
							$has_summary = $database->get_summary( $post->ID );
							
							if ( ( $status_filter === 'has_summary' && $has_summary ) || 
							     ( $status_filter === 'no_summary' && ! $has_summary ) ) {
								$filtered_ids[] = $post->ID;
							}
						}
						
						// Reset for pagination
						if ( ! empty( $filtered_ids ) ) {
							$args['post__in'] = $filtered_ids;
							$args['posts_per_page'] = 20;
							$args['paged'] = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
						} else {
							$args['post__in'] = array( 0 ); // Force no results
						}
					}
					
					// Execute query
					$query = new WP_Query( $args );
					
					if ( $query->have_posts() ) :
						while ( $query->have_posts() ) : $query->the_post();
							$post_id = get_the_ID();
							$summary = $database->get_summary( $post_id );
							$content = get_post_field( 'post_content', $post_id );
							$word_count = str_word_count( strip_tags( $content ) );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="post_ids[]" value="<?php echo $post_id; ?>" />
								</th>
								<td>
									<strong>
										<a href="<?php echo get_edit_post_link( $post_id ); ?>">
											<?php echo get_the_title(); ?>
										</a>
									</strong>
								</td>
								<td><?php echo get_post_type(); ?></td>
								<td><?php echo number_format( $word_count ); ?></td>
								<td>
									<?php if ( $summary ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
										<?php _e( 'Generated', 'tldr-pro' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-minus" style="color: orange;"></span>
										<?php _e( 'Not Generated', 'tldr-pro' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small tldr-pro-generate-single" data-post-id="<?php echo $post_id; ?>">
										<?php echo $summary ? __( 'Regenerate', 'tldr-pro' ) : __( 'Generate', 'tldr-pro' ); ?>
									</button>
									<?php if ( $summary ) : ?>
										<button type="button" class="button button-small tldr-pro-preview" data-post-id="<?php echo $post_id; ?>" data-post-title="<?php echo esc_attr( get_the_title() ); ?>">
											<?php _e( 'Preview', 'tldr-pro' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
							<?php
						endwhile;
					else :
						?>
						<tr>
							<td colspan="6"><?php _e( 'No posts found.', 'tldr-pro' ); ?></td>
						</tr>
						<?php
					endif;
					wp_reset_postdata();
					?>
				</tbody>
			</table>
			
			<!-- Pagination -->
			<div class="tablenav bottom">
				<?php
				$total_pages = $query->max_num_pages;
				if ( $total_pages > 1 ) {
					$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
					
					$pagination_args = array(
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'current' => $current_page,
						'total' => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					);
					
					echo '<div class="tablenav-pages">';
					echo paginate_links( $pagination_args );
					echo '</div>';
				}
				?>
			</div>
		</form>
		
		<!-- Progress Modal -->
		<div id="tldr-pro-progress-modal" style="display: none;">
			<div class="tldr-pro-modal-content">
				<h2><?php _e( 'Generating Summaries', 'tldr-pro' ); ?></h2>
				<div class="tldr-pro-progress-bar">
					<div class="tldr-pro-progress-fill" style="width: 0%;"></div>
				</div>
				<p class="tldr-pro-progress-text">
					<span class="tldr-pro-progress-current">0</span> / <span class="tldr-pro-progress-total">0</span> <?php _e( 'completed', 'tldr-pro' ); ?>
				</p>
				<button type="button" id="tldr-pro-cancel-bulk" class="button"><?php _e( 'Cancel', 'tldr-pro' ); ?></button>
			</div>
		</div>
		
		<!-- Preview Modal -->
		<div id="tldr-pro-preview-modal" style="display: none;">
			<div class="tldr-pro-modal-overlay"></div>
			<div class="tldr-pro-modal-container">
				<div class="tldr-pro-modal-header">
					<h2 class="tldr-pro-modal-title"><?php _e( 'Summary Preview', 'tldr-pro' ); ?></h2>
					<button type="button" class="tldr-pro-modal-close" aria-label="<?php _e( 'Close', 'tldr-pro' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="tldr-pro-modal-body">
					<div class="tldr-pro-preview-wrapper">
						<h3 id="tldr-preview-title"></h3>
						<div id="tldr-preview-content"></div>
						<div class="tldr-pro-preview-details">
							<div class="tldr-pro-preview-language">
								<strong><?php _e( 'Language:', 'tldr-pro' ); ?></strong>
								<span id="tldr-preview-language"></span>
							</div>
							<div class="tldr-pro-preview-meta">
								<span class="tldr-pro-preview-provider" id="tldr-preview-provider"></span>
								<span class="tldr-pro-preview-tokens" id="tldr-preview-tokens"></span>
								<span class="tldr-pro-preview-time" id="tldr-preview-time"></span>
								<span class="tldr-pro-preview-words" id="tldr-preview-words"></span>
								<span class="tldr-pro-preview-chars" id="tldr-preview-chars"></span>
							</div>
						</div>
					</div>
				</div>
				<div class="tldr-pro-modal-footer">
					<button type="button" class="button tldr-pro-modal-close-btn"><?php _e( 'Close', 'tldr-pro' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>

<style type="text/css">
/* Filters styling */
.tldr-pro-filters {
	margin: 20px 0;
	padding: 15px;
	background: #f5f5f5;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.tldr-pro-filters form {
	display: flex;
	align-items: center;
	gap: 15px;
	flex-wrap: wrap;
}

.tldr-pro-filters label {
	font-weight: 600;
}

.tldr-pro-filters select {
	min-width: 150px;
}

/* Progress modal styling */
#tldr-pro-progress-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.7);
	z-index: 100000;
	display: flex;
	align-items: center;
	justify-content: center;
}

.tldr-pro-modal-content {
	background: white;
	padding: 30px;
	border-radius: 5px;
	min-width: 400px;
	text-align: center;
}

.tldr-pro-progress-bar {
	width: 100%;
	height: 20px;
	background: #f0f0f0;
	border-radius: 10px;
	overflow: hidden;
	margin: 20px 0;
}

.tldr-pro-progress-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s ease;
}

/* Preview modal styling */
#tldr-pro-preview-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 100000;
}

.tldr-pro-modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.7);
}

.tldr-pro-modal-container {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: white;
	max-width: 800px;
	width: 90%;
	max-height: 80vh;
	overflow-y: auto;
	border-radius: 5px;
	box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
}

.tldr-pro-modal-header {
	padding: 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.tldr-pro-modal-body {
	padding: 20px;
}

.tldr-pro-modal-footer {
	padding: 20px;
	border-top: 1px solid #ddd;
	text-align: right;
}

.tldr-pro-modal-close {
	background: none;
	border: none;
	font-size: 20px;
	cursor: pointer;
	color: #666;
}

.tldr-pro-modal-close:hover {
	color: #000;
}

.tldr-pro-preview-meta {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #ddd;
	color: #666;
	font-size: 13px;
}

.tldr-pro-preview-meta span {
	display: inline-block;
	margin-right: 15px;
}
</style>