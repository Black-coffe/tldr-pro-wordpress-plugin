<?php
/**
 * Database operations handler for the plugin.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database operations class.
 *
 * Handles all database operations for summaries including
 * CRUD operations, batch processing, and migrations.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */
class TLDR_Pro_Database {

	/**
	 * The single instance of the class.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      TLDR_Pro_Database    $instance    The single instance of the class.
	 */
	protected static $instance = null;

	/**
	 * The WordPress database object.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      wpdb    $wpdb    WordPress database abstraction object.
	 */
	protected $wpdb;

	/**
	 * The summaries table name.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $table_name    The summaries table name.
	 */
	protected $table_name;

	/**
	 * The current database version.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $db_version    The current database version.
	 */
	protected $db_version = '1.0.0';

	/**
	 * Main TLDR_Pro_Database Instance.
	 *
	 * Ensures only one instance of TLDR_Pro_Database is loaded or can be loaded.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   TLDR_Pro_Database    Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	protected function __construct() {
		global $wpdb;
		
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'tldr_pro_summaries';
	}

	/**
	 * Create database tables.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function create_tables() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			summary_text longtext NOT NULL,
			summary_meta longtext DEFAULT NULL,
			generated_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			api_provider varchar(50) DEFAULT NULL,
			tokens_used int(11) DEFAULT 0,
			generation_time float DEFAULT 0,
			status varchar(20) DEFAULT 'active',
			language varchar(10) DEFAULT 'en',
			version varchar(10) DEFAULT '1.0',
			PRIMARY KEY (id),
			UNIQUE KEY post_id (post_id),
			KEY status (status),
			KEY generated_at (generated_at),
			KEY api_provider (api_provider)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			tldr_pro_log( 'Failed to create database table: ' . $this->table_name, 'error' );
			return false;
		}

		// Store/update database version
		update_option( 'tldr_pro_db_version', $this->db_version );
		
		return true;
	}

	/**
	 * Insert or update a summary.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Summary data.
	 * @return   int|false         The number of rows affected, or false on error.
	 */
	public function insert_summary( $data ) {
		tldr_pro_log( '=== Database Insert Summary Started ===', 'info' );
		tldr_pro_log( 'Data received: ' . json_encode($data), 'debug' );
		
		// Required fields
		if ( empty( $data['post_id'] ) || empty( $data['summary_text'] ) ) {
			tldr_pro_log( 'Missing required fields for insert_summary', 'error' );
			tldr_pro_log( 'post_id: ' . (isset($data['post_id']) ? $data['post_id'] : 'missing'), 'error' );
			tldr_pro_log( 'summary_text length: ' . (isset($data['summary_text']) ? strlen($data['summary_text']) : 'missing'), 'error' );
			return false;
		}
		
		tldr_pro_log( 'Post ID: ' . $data['post_id'], 'info' );
		tldr_pro_log( 'Summary text length: ' . strlen($data['summary_text']) . ' chars', 'debug' );
		tldr_pro_log( 'API Provider: ' . (isset($data['api_provider']) ? $data['api_provider'] : 'unknown'), 'debug' );

		// Prepare data for insertion
		$insert_data = array(
			'post_id'         => absint( $data['post_id'] ),
			'summary_text'    => wp_kses_post( $data['summary_text'] ),
			'summary_meta'    => isset( $data['summary_meta'] ) ? wp_json_encode( $data['summary_meta'] ) : null,
			'api_provider'    => isset( $data['api_provider'] ) ? sanitize_text_field( $data['api_provider'] ) : 'unknown',
			'tokens_used'     => isset( $data['tokens_used'] ) ? absint( $data['tokens_used'] ) : 0,
			'generation_time' => isset( $data['generation_time'] ) ? floatval( $data['generation_time'] ) : 0,
			'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'language'        => isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : 'en',
			'version'         => isset( $data['version'] ) ? sanitize_text_field( $data['version'] ) : '1.0',
		);

		// Check if summary exists for this post
		$existing_id = $this->get_summary_id( $data['post_id'] );
		tldr_pro_log( 'Checking for existing summary...', 'debug' );
		tldr_pro_log( 'Existing summary ID: ' . ($existing_id ? $existing_id : 'none'), 'debug' );

		if ( $existing_id ) {
			// Update existing summary
			tldr_pro_log( 'Updating existing summary ID: ' . $existing_id, 'info' );
			$insert_data['updated_at'] = current_time( 'mysql' );
			
			$result = $this->wpdb->update(
				$this->table_name,
				$insert_data,
				array( 'id' => $existing_id ),
				array( '%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			
			tldr_pro_log( 'Update result: ' . var_export($result, true), 'debug' );
		} else {
			// Insert new summary
			tldr_pro_log( 'Inserting new summary', 'info' );
			$insert_data['generated_at'] = current_time( 'mysql' );
			$insert_data['updated_at'] = current_time( 'mysql' );
			
			tldr_pro_log( 'Table name: ' . $this->table_name, 'debug' );
			
			$result = $this->wpdb->insert(
				$this->table_name,
				$insert_data,
				array( '%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			
			tldr_pro_log( 'Insert result: ' . var_export($result, true), 'debug' );
			if ( $result ) {
				tldr_pro_log( 'Insert ID: ' . $this->wpdb->insert_id, 'debug' );
			}
		}

		if ( false === $result ) {
			tldr_pro_log( 'Database operation FAILED', 'error' );
			tldr_pro_log( 'Database error: ' . $this->wpdb->last_error, 'error' );
			tldr_pro_log( 'Last query: ' . $this->wpdb->last_query, 'debug' );
			return false;
		}
		
		tldr_pro_log( 'Database operation SUCCESS', 'info' );

		// Clear cache for this post
		$this->clear_summary_cache( $data['post_id'] );

		return $result;
	}

	/**
	 * Get a summary by post ID.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 * @return   object|null        The summary object or null if not found.
	 */
	public function get_summary( $post_id ) {
		$post_id = absint( $post_id );
		
		// Try to get from cache first
		$cache_key = 'tldr_pro_summary_' . $post_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// Get from database
		$summary = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} 
				WHERE post_id = %d AND status = 'active'
				LIMIT 1",
				$post_id
			)
		);

		if ( $summary ) {
			// Decode JSON fields
			if ( ! empty( $summary->summary_meta ) ) {
				$summary->summary_meta = json_decode( $summary->summary_meta, true );
			}
			
			// Cache for 1 hour
			set_transient( $cache_key, $summary, HOUR_IN_SECONDS );
		}

		return $summary;
	}

	/**
	 * Update a summary.
	 *
	 * @since    1.0.0
	 * @param    int      $post_id    The post ID.
	 * @param    array    $data       Data to update.
	 * @return   int|false           The number of rows updated, or false on error.
	 */
	public function update_summary( $post_id, $data ) {
		$post_id = absint( $post_id );
		
		// Prepare update data
		$update_data = array();
		$format = array();

		// Allowed fields to update
		$allowed_fields = array(
			'summary_text'    => '%s',
			'summary_meta'    => '%s',
			'api_provider'    => '%s',
			'tokens_used'     => '%d',
			'generation_time' => '%f',
			'status'          => '%s',
			'language'        => '%s',
			'version'         => '%s',
		);

		foreach ( $allowed_fields as $field => $field_format ) {
			if ( isset( $data[ $field ] ) ) {
				if ( 'summary_meta' === $field && is_array( $data[ $field ] ) ) {
					$update_data[ $field ] = wp_json_encode( $data[ $field ] );
				} else {
					$update_data[ $field ] = $data[ $field ];
				}
				$format[] = $field_format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// Always update the updated_at timestamp
		$update_data['updated_at'] = current_time( 'mysql' );
		$format[] = '%s';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array( 'post_id' => $post_id ),
			$format,
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->clear_summary_cache( $post_id );
		}

		return $result;
	}

	/**
	 * Delete a summary.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 * @return   int|false         The number of rows deleted, or false on error.
	 */
	public function delete_summary( $post_id ) {
		$post_id = absint( $post_id );
		
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->clear_summary_cache( $post_id );
		}

		return $result;
	}

	/**
	 * Bulk insert summaries.
	 *
	 * @since    1.0.0
	 * @param    array    $summaries    Array of summary data.
	 * @return   int                    Number of summaries inserted.
	 */
	public function bulk_insert( $summaries ) {
		if ( empty( $summaries ) || ! is_array( $summaries ) ) {
			return 0;
		}

		$inserted = 0;
		$values = array();
		$placeholders = array();

		foreach ( $summaries as $summary ) {
			if ( empty( $summary['post_id'] ) || empty( $summary['summary_text'] ) ) {
				continue;
			}

			$values[] = absint( $summary['post_id'] );
			$values[] = wp_kses_post( $summary['summary_text'] );
			$values[] = isset( $summary['summary_meta'] ) ? wp_json_encode( $summary['summary_meta'] ) : null;
			$values[] = current_time( 'mysql' );
			$values[] = current_time( 'mysql' );
			$values[] = isset( $summary['api_provider'] ) ? sanitize_text_field( $summary['api_provider'] ) : 'unknown';
			$values[] = isset( $summary['tokens_used'] ) ? absint( $summary['tokens_used'] ) : 0;
			$values[] = isset( $summary['generation_time'] ) ? floatval( $summary['generation_time'] ) : 0;
			$values[] = isset( $summary['status'] ) ? sanitize_text_field( $summary['status'] ) : 'active';
			$values[] = isset( $summary['language'] ) ? sanitize_text_field( $summary['language'] ) : 'en';
			$values[] = isset( $summary['version'] ) ? sanitize_text_field( $summary['version'] ) : '1.0';

			$placeholders[] = "(%d, %s, %s, %s, %s, %s, %d, %f, %s, %s, %s)";
			$inserted++;
		}

		if ( empty( $placeholders ) ) {
			return 0;
		}

		// Build the query
		$sql = "INSERT INTO {$this->table_name} 
				(post_id, summary_text, summary_meta, generated_at, updated_at, 
				 api_provider, tokens_used, generation_time, status, language, version) 
				VALUES " . implode( ', ', $placeholders ) . "
				ON DUPLICATE KEY UPDATE 
				summary_text = VALUES(summary_text),
				summary_meta = VALUES(summary_meta),
				updated_at = VALUES(updated_at),
				api_provider = VALUES(api_provider),
				tokens_used = VALUES(tokens_used),
				generation_time = VALUES(generation_time),
				status = VALUES(status),
				language = VALUES(language),
				version = VALUES(version)";

		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, $values ) );

		// Clear cache for all affected posts
		foreach ( $summaries as $summary ) {
			if ( ! empty( $summary['post_id'] ) ) {
				$this->clear_summary_cache( $summary['post_id'] );
			}
		}

		return $result !== false ? $inserted : 0;
	}

	/**
	 * Get summaries with pagination.
	 *
	 * @since    1.0.0
	 * @param    array    $args    Query arguments.
	 * @return   array             Array of summaries.
	 */
	public function get_summaries( $args = array() ) {
		$defaults = array(
			'limit'        => 20,
			'offset'       => 0,
			'orderby'      => 'generated_at',
			'order'        => 'DESC',
			'status'       => 'active',
			'api_provider' => '',
			'search'       => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['api_provider'] ) ) {
			$where[] = 'api_provider = %s';
			$values[] = $args['api_provider'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = 'summary_text LIKE %s';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		// Validate orderby
		$allowed_orderby = array( 'id', 'post_id', 'generated_at', 'updated_at', 'tokens_used', 'generation_time' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'generated_at';
		
		// Validate order
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build query
		$sql = "SELECT * FROM {$this->table_name} 
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		$results = $this->wpdb->get_results( 
			empty( $values ) ? $sql : $this->wpdb->prepare( $sql, $values ) 
		);

		// Decode JSON fields
		foreach ( $results as &$result ) {
			if ( ! empty( $result->summary_meta ) ) {
				$result->summary_meta = json_decode( $result->summary_meta, true );
			}
		}

		return $results;
	}

	/**
	 * Get total count of summaries.
	 *
	 * @since    1.0.0
	 * @param    string    $status    Optional status filter.
	 * @return   int                  Total count.
	 */
	public function get_total_count( $status = '' ) {
		$sql = "SELECT COUNT(*) FROM {$this->table_name}";
		
		if ( ! empty( $status ) ) {
			$sql = $this->wpdb->prepare( $sql . " WHERE status = %s", $status );
		}

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Get statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics data.
	 */
	public function get_statistics() {
		$stats = array();

		// Total summaries
		$total = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name}"
		);
		$stats['total_summaries'] = $total ? intval( $total ) : 0;

		// Summaries today
		$today = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE DATE(generated_at) = %s",
				current_time( 'Y-m-d' )
			)
		);
		$stats['today_summaries'] = $today ? intval( $today ) : 0;

		// Summaries this month
		$month = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE MONTH(generated_at) = %d AND YEAR(generated_at) = %d",
				current_time( 'n' ),
				current_time( 'Y' )
			)
		);
		$stats['month_summaries'] = $month ? intval( $month ) : 0;

		// Average generation time
		$avg_time = $this->wpdb->get_var(
			"SELECT AVG(generation_time) FROM {$this->table_name}"
		);
		$stats['avg_generation_time'] = $avg_time ? round( $avg_time, 2 ) : 0;

		// Total tokens used
		$tokens = $this->wpdb->get_var(
			"SELECT SUM(tokens_used) FROM {$this->table_name}"
		);
		$stats['total_tokens'] = $tokens ? intval( $tokens ) : 0;

		// Active summaries count
		$active = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'"
		);
		$stats['active_summaries'] = $active ? intval( $active ) : 0;

		// Get provider stats
		$providers = $this->wpdb->get_results(
			"SELECT 
				api_provider,
				COUNT(*) as count,
				SUM(tokens_used) as tokens,
				AVG(generation_time) as avg_time
			FROM {$this->table_name}
			GROUP BY api_provider"
		);

		$stats['providers'] = array();
		if ( $providers ) {
			foreach ( $providers as $provider ) {
				$stats['providers'][ $provider->api_provider ] = array(
					'count'  => (int) $provider->count,
					'tokens' => (int) $provider->tokens,
					'avg_time' => round( (float) $provider->avg_time, 2 ),
				);
			}
		}

		// Language statistics
		$languages = $this->wpdb->get_results(
			"SELECT 
				language,
				COUNT(*) as count
			FROM {$this->table_name}
			GROUP BY language
			ORDER BY count DESC"
		);
		
		$stats['languages'] = array();
		if ( $languages ) {
			foreach ( $languages as $lang ) {
				$stats['languages'][ $lang->language ] = (int) $lang->count;
			}
		}
		
		// Recent activity (last 7 days)
		$recent = $this->wpdb->get_results(
			"SELECT DATE(generated_at) as date, COUNT(*) as count
			FROM {$this->table_name}
			WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY DATE(generated_at)
			ORDER BY date DESC",
			ARRAY_A
		);
		$stats['recent_activity'] = $recent ? $recent : array();

		return $stats;
	}

	/**
	 * Clean up old summaries.
	 *
	 * @since    1.0.0
	 * @param    int    $days    Number of days to keep.
	 * @return   int             Number of rows deleted.
	 */
	public function cleanup_old_summaries( $days = 90 ) {
		$days = absint( $days );
		
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} 
				WHERE generated_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND status != 'active'",
				$days
			)
		);

		return $result;
	}

	/**
	 * Migrate database to new version.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function migrate_database() {
		$current_version = get_option( 'tldr_pro_db_version', '0.0.0' );
		
		if ( version_compare( $current_version, $this->db_version, '>=' ) ) {
			return true; // No migration needed
		}

		// Run migrations based on version
		if ( version_compare( $current_version, '1.0.0', '<' ) ) {
			$this->create_tables();
		}

		// Future migrations can be added here
		// if ( version_compare( $current_version, '1.1.0', '<' ) ) {
		//     $this->migrate_to_1_1_0();
		// }

		update_option( 'tldr_pro_db_version', $this->db_version );
		
		return true;
	}

	/**
	 * Get summary ID by post ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $post_id    The post ID.
	 * @return   int|null           The summary ID or null.
	 */
	private function get_summary_id( $post_id ) {
		return $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE post_id = %d LIMIT 1",
				$post_id
			)
		);
	}

	/**
	 * Clear summary cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $post_id    The post ID.
	 */
	private function clear_summary_cache( $post_id ) {
		delete_transient( 'tldr_pro_summary_' . $post_id );
		
		// Clear any other related caches
		wp_cache_delete( 'tldr_pro_summary_' . $post_id, 'tldr_pro' );
	}

	/**
	 * Get recent summaries.
	 *
	 * @since    1.0.0
	 * @param    int    $limit    Number of summaries to retrieve.
	 * @return   array  Array of summary objects.
	 */
	public function get_recent_summaries( $limit = 10 ) {
		$sql = $this->wpdb->prepare(
			"SELECT s.*, p.post_title 
			FROM {$this->table_name} s
			LEFT JOIN {$this->wpdb->posts} p ON s.post_id = p.ID
			ORDER BY s.generated_at DESC
			LIMIT %d",
			$limit
		);

		return $this->wpdb->get_results( $sql );
	}


	/**
	 * Get summaries for bulk operations.
	 *
	 * @since    1.0.0
	 * @param    array    $post_ids    Array of post IDs.
	 * @return   array    Array of post IDs that have summaries.
	 */
	public function get_posts_with_summaries( $post_ids = array() ) {
		if ( empty( $post_ids ) ) {
			$sql = "SELECT DISTINCT post_id FROM {$this->table_name}";
			return $this->wpdb->get_col( $sql );
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$this->table_name} WHERE post_id IN ({$placeholders})",
			$post_ids
		);

		return $this->wpdb->get_col( $sql );
	}

	/**
	 * Get posts without summaries.
	 *
	 * @since    1.0.0
	 * @param    int    $limit    Number of posts to retrieve.
	 * @return   array  Array of post objects.
	 */
	public function get_posts_without_summaries( $limit = 50 ) {
		$sql = $this->wpdb->prepare(
			"SELECT ID, post_title, post_content 
			FROM {$this->wpdb->posts} p
			WHERE p.post_type IN ('post', 'page')
			AND p.post_status = 'publish'
			AND NOT EXISTS (
				SELECT 1 FROM {$this->table_name} s 
				WHERE s.post_id = p.ID
			)
			ORDER BY p.post_date DESC
			LIMIT %d",
			$limit
		);

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * Drop database tables.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function drop_tables() {
		$result = $this->wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
		
		if ( false === $result ) {
			tldr_pro_log( 'Failed to drop table: ' . $this->table_name, 'error' );
			return false;
		}

		delete_option( 'tldr_pro_db_version' );
		
		return true;
	}
}