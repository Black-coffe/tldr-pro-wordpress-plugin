<?php
/**
 * Statistics page template - Simplified and Real Data Only
 *
 * @package TLDR_Pro
 * @subpackage TLDR_Pro/admin/partials
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get database instance
$database = TLDR_Pro_Database::get_instance();
global $wpdb;
$table_name = $wpdb->prefix . 'tldr_pro_summaries';

// Get basic statistics
$total_summaries = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
$active_summaries = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'" );
$today_summaries = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$table_name} WHERE DATE(generated_at) = %s",
	current_time( 'Y-m-d' )
) );

// Get provider statistics
$provider_stats = $wpdb->get_results( "
	SELECT api_provider, COUNT(*) as count, AVG(generation_time) as avg_time
	FROM {$table_name}
	WHERE api_provider IS NOT NULL
	GROUP BY api_provider
" );

// Get language statistics
$language_stats = $wpdb->get_results( "
	SELECT language, COUNT(*) as count
	FROM {$table_name}
	WHERE language IS NOT NULL
	GROUP BY language
	ORDER BY count DESC
	LIMIT 10
" );

// Pagination parameters
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 10;
$offset = ( $current_page - 1 ) * $per_page;

// Get total count for pagination
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
$total_pages = ceil( $total_items / $per_page );

// Get recent summaries with pagination
$recent_summaries = $wpdb->get_results( $wpdb->prepare( "
	SELECT post_id, api_provider, language, generation_time, tokens_used, generated_at
	FROM {$table_name}
	ORDER BY generated_at DESC
	LIMIT %d OFFSET %d
", $per_page, $offset ) );

// Calculate totals
$total_tokens = $wpdb->get_var( "SELECT SUM(tokens_used) FROM {$table_name}" );
$avg_generation_time = $wpdb->get_var( "SELECT AVG(generation_time) FROM {$table_name}" );
?>

<div class="wrap">
	<h1><?php _e( 'TL;DR Pro Statistics', 'tldr-pro' ); ?></h1>
	
	<div class="tldr-pro-stats-wrapper">
		
		<!-- Overview Cards -->
		<div class="tldr-pro-stats-cards">
			<div class="tldr-pro-stat-card">
				<h3><?php _e( 'Total Summaries', 'tldr-pro' ); ?></h3>
				<p class="tldr-pro-stat-number"><?php echo number_format( intval( $total_summaries ) ); ?></p>
				<small><?php echo sprintf( __( '%d active', 'tldr-pro' ), $active_summaries ); ?></small>
			</div>
			
			<div class="tldr-pro-stat-card">
				<h3><?php _e( 'Generated Today', 'tldr-pro' ); ?></h3>
				<p class="tldr-pro-stat-number"><?php echo number_format( intval( $today_summaries ) ); ?></p>
			</div>
			
			<div class="tldr-pro-stat-card">
				<h3><?php _e( 'Total Tokens Used', 'tldr-pro' ); ?></h3>
				<p class="tldr-pro-stat-number"><?php echo number_format( intval( $total_tokens ) ); ?></p>
			</div>
			
			<div class="tldr-pro-stat-card">
				<h3><?php _e( 'Avg Generation Time', 'tldr-pro' ); ?></h3>
				<p class="tldr-pro-stat-number">
					<?php echo number_format( floatval( $avg_generation_time ), 2 ); ?>s
				</p>
			</div>
		</div>
		
		<div class="tldr-pro-stats-row">
			<!-- Provider Statistics -->
			<div class="tldr-pro-stats-section">
				<h2><?php _e( 'AI Provider Usage', 'tldr-pro' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Provider', 'tldr-pro' ); ?></th>
							<th><?php _e( 'Summaries', 'tldr-pro' ); ?></th>
							<th><?php _e( 'Avg Time', 'tldr-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $provider_stats ) : ?>
							<?php foreach ( $provider_stats as $provider ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( ucfirst( $provider->api_provider ?: 'Unknown' ) ); ?></strong>
									</td>
									<td><?php echo number_format( intval( $provider->count ) ); ?></td>
									<td><?php echo number_format( floatval( $provider->avg_time ), 2 ); ?>s</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="3"><?php _e( 'No data available', 'tldr-pro' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			
			<!-- Language Statistics -->
			<div class="tldr-pro-stats-section">
				<h2><?php _e( 'Language Distribution', 'tldr-pro' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Language', 'tldr-pro' ); ?></th>
							<th><?php _e( 'Count', 'tldr-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $language_stats ) : ?>
							<?php 
							$language_names = array(
								'en' => 'English',
								'es' => 'Spanish',
								'fr' => 'French',
								'de' => 'German',
								'it' => 'Italian',
								'pt' => 'Portuguese',
								'ru' => 'Russian',
								'ja' => 'Japanese',
								'zh' => 'Chinese',
								'ar' => 'Arabic',
								'uk' => 'Ukrainian'
							);
							foreach ( $language_stats as $lang ) : 
								$lang_code = $lang->language ?: 'en';
								$lang_name = isset( $language_names[$lang_code] ) ? $language_names[$lang_code] : strtoupper( $lang_code );
							?>
								<tr>
									<td>
										<strong><?php echo esc_html( $lang_name ); ?></strong>
										<small>(<?php echo esc_html( $lang_code ); ?>)</small>
									</td>
									<td><?php echo number_format( intval( $lang->count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="2"><?php _e( 'No data available', 'tldr-pro' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		
		<!-- Recent Summaries -->
		<div class="tldr-pro-stats-section tldr-pro-full-width">
			<h2><?php _e( 'Recent Summaries', 'tldr-pro' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Post', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Type', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Provider', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Language', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Generation Time', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Tokens Used', 'tldr-pro' ); ?></th>
						<th><?php _e( 'Date', 'tldr-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $recent_summaries ) : ?>
						<?php foreach ( $recent_summaries as $summary ) : ?>
							<tr>
								<td>
									<?php 
									$post = get_post( $summary->post_id );
									if ( $post ) :
									?>
										<a href="<?php echo get_edit_post_link( $summary->post_id ); ?>">
											<?php echo esc_html( $post->post_title ); ?>
										</a>
									<?php else : ?>
										<?php echo sprintf( __( 'Post #%d (deleted)', 'tldr-pro' ), $summary->post_id ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php 
									if ( $post ) {
										$post_type_obj = get_post_type_object( $post->post_type );
										echo '<span class="post-type-badge">' . esc_html( $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type ) . '</span>';
									} else {
										echo '-';
									}
									?>
								</td>
								<td><?php echo esc_html( ucfirst( $summary->api_provider ?: 'Unknown' ) ); ?></td>
								<td><?php echo esc_html( strtoupper( $summary->language ?: 'en' ) ); ?></td>
								<td><?php echo number_format( floatval( $summary->generation_time ), 2 ); ?>s</td>
								<td><?php echo number_format( intval( $summary->tokens_used ) ); ?></td>
								<td><?php echo esc_html( human_time_diff( strtotime( $summary->generated_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'tldr-pro' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="7"><?php _e( 'No summaries generated yet', 'tldr-pro' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tldr-pro-pagination">
					<?php
					$pagination_args = array(
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'total' => $total_pages,
						'current' => $current_page,
						'show_all' => false,
						'end_size' => 2,
						'mid_size' => 2,
						'prev_next' => true,
						'prev_text' => __( '&laquo; Previous', 'tldr-pro' ),
						'next_text' => __( 'Next &raquo;', 'tldr-pro' ),
						'type' => 'plain',
						'add_args' => false,
						'add_fragment' => ''
					);
					
					echo '<div class="pagination-wrapper">';
					echo paginate_links( $pagination_args );
					echo '</div>';
					
					echo '<div class="pagination-info">';
					echo sprintf( 
						__( 'Showing %d-%d of %d summaries', 'tldr-pro' ),
						$offset + 1,
						min( $offset + $per_page, $total_items ),
						$total_items
					);
					echo '</div>';
					?>
				</div>
			<?php endif; ?>
		</div>
		
	</div>
</div>

<style>
.tldr-pro-stats-wrapper {
	margin-top: 20px;
}

.tldr-pro-stats-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.tldr-pro-stat-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
}

.tldr-pro-stat-card h3 {
	margin: 0 0 10px;
	color: #666;
	font-size: 14px;
	font-weight: 500;
	text-transform: uppercase;
}

.tldr-pro-stat-number {
	font-size: 36px;
	font-weight: 700;
	margin: 10px 0;
	color: #333;
}

.tldr-pro-stat-card small {
	color: #999;
	font-size: 12px;
}

.tldr-pro-stats-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 30px;
}

.tldr-pro-stats-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
}

.tldr-pro-stats-section h2 {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	color: #333;
}

.tldr-pro-full-width {
	grid-column: 1 / -1;
}

.tldr-pro-stats-section table {
	margin: 0;
}

@media (max-width: 768px) {
	.tldr-pro-stats-row {
		grid-template-columns: 1fr;
	}
}

/* Post Type Badge */
.post-type-badge {
	display: inline-block;
	padding: 3px 8px;
	background: #f0f0f1;
	border: 1px solid #ddd;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
	text-transform: capitalize;
	color: #666;
}

/* Pagination Styles */
.tldr-pro-pagination {
	margin-top: 20px;
	padding: 15px;
	background: #f9f9f9;
	border-top: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.pagination-wrapper {
	display: flex;
	gap: 5px;
	align-items: center;
}

.pagination-wrapper .page-numbers {
	padding: 6px 12px;
	background: #fff;
	border: 1px solid #ddd;
	color: #2271b1;
	text-decoration: none;
	border-radius: 3px;
	transition: all 0.2s ease;
}

.pagination-wrapper .page-numbers:hover {
	background: #f0f0f1;
	border-color: #2271b1;
}

.pagination-wrapper .page-numbers.current {
	background: #2271b1;
	color: #fff;
	border-color: #2271b1;
}

.pagination-wrapper .page-numbers.dots {
	border: none;
	background: none;
	padding: 6px 3px;
}

.pagination-info {
	color: #666;
	font-size: 13px;
}

@media (max-width: 640px) {
	.tldr-pro-pagination {
		flex-direction: column;
		gap: 10px;
		text-align: center;
	}
	
	.pagination-wrapper {
		flex-wrap: wrap;
		justify-content: center;
	}
}
</style>