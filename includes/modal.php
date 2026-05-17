<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;


class RSS_Post_Aggregator_Modal {

	public $feed_links = array();

	/**
	 * @since 0.1.1
	 *
	 * @var RSS_Post_Aggregator_Feeds
	 */
	public $rss;

	/**
	 * @since 0.1.1
	 *
	 * @var RSS_Post_Aggregator_CPT
	 */
	public $cpt;

	/**
	 * @since 0.1.1
	 *
	 * @var RSS_Post_Aggregator_Taxonomy
	 */
	public $tax;

	/**
	 * RSS_Post_Aggregator_Modal constructor.
	 *
	 * @since 0.1.1
	 *
	 * @param RSS_Post_Aggregator_Feeds $rss
	 * @param RSS_Post_Aggregator_CPT $cpt
	 * @param RSS_Post_Aggregator_Taxonomy $tax
	 */
	public function __construct( $rss, $cpt, $tax ) {
		$this->rss = $rss;
		$this->cpt = $cpt;
		$this->tax = $tax;
	}

	/**
	 * Initiate hooks.
	 *
	 * @since 0.1.1
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'render_import_notice' ) );
		add_action( 'admin_post_wds_rss_post_aggregator_import_all', array( $this, 'handle_manual_import_all' ) );
		add_action( 'admin_post_wds_rss_post_aggregator_import_feed', array( $this, 'handle_manual_import_feed' ) );
		add_action( 'wp_ajax_rss_get_data', array( $this, 'rss_get_data' ) );
		add_action( 'wp_ajax_rss_save_posts', array( $this, 'rss_save_posts' ) );
	}

	/**
	 * Handle the settings-page request to run automatic feed imports now.
	 *
	 * @since 0.2.6
	 */
	public function handle_manual_import_all() {
		$this->verify_manual_import_request( 'wds_rss_post_aggregator_import_all' );

		$summary = $this->summarize_import_results( $this->import_all_feeds() );

		$this->redirect_after_manual_import( $summary );
	}

	/**
	 * Handle a per-feed request to run an import immediately.
	 *
	 * @since 0.2.6
	 */
	public function handle_manual_import_feed() {
		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;

		$this->verify_manual_import_request( 'wds_rss_post_aggregator_import_feed_' . $feed_id );

		$feed = $feed_id ? get_term( $feed_id, $this->tax->taxonomy() ) : false;

		if ( ! $feed || is_wp_error( $feed ) ) {
			$this->redirect_after_manual_import( array(
				'attempted' => 0,
				'imported'  => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'errors'    => 1,
			) );
		}

		$summary = $this->summarize_import_results( array(
			$feed_id => $this->import_feed( $this->get_feed_url( $feed ), $feed_id ),
		) );

		$this->redirect_after_manual_import( $summary );
	}

	/**
	 * Verify a manual import admin request.
	 *
	 * @since 0.2.6
	 *
	 * @param string $nonce_action Nonce action.
	 */
	protected function verify_manual_import_request( $nonce_action ) {
		if ( ! current_user_can( $this->get_manual_import_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to run RSS imports.', 'wds-rss-post-aggregator' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Get the capability required to force manual imports.
	 *
	 * @since 0.2.6
	 *
	 * @return string Required capability.
	 */
	protected function get_manual_import_capability() {
		return apply_filters( 'rss_post_aggregator_manual_import_capability', 'manage_options' );
	}

	/**
	 * Summarize import results for admin notices.
	 *
	 * @since 0.2.6
	 *
	 * @param array $results Import results keyed by feed term ID.
	 * @return array Summary counts.
	 */
	protected function summarize_import_results( $results ) {
		$summary = array(
			'attempted' => 0,
			'imported'  => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => 0,
		);

		if ( empty( $results ) || ! is_array( $results ) ) {
			return $summary;
		}

		foreach ( $results as $feed_result ) {
			$summary['attempted']++;

			if ( is_wp_error( $feed_result ) ) {
				$summary['errors']++;
				continue;
			}

			if ( ! is_array( $feed_result ) ) {
				$summary['failed']++;
				continue;
			}

			foreach ( $feed_result as $post_result ) {
				if ( is_array( $post_result ) && isset( $post_result['status'] ) && 'skipped_existing' === $post_result['status'] ) {
					$summary['skipped']++;
				} elseif ( is_array( $post_result ) && ! empty( $post_result['post_id'] ) ) {
					$summary['imported']++;
				} else {
					$summary['failed']++;
				}
			}
		}

		return $summary;
	}

	/**
	 * Redirect back to the admin screen with manual import results.
	 *
	 * @since 0.2.6
	 *
	 * @param array $summary Import summary counts.
	 */
	protected function redirect_after_manual_import( $summary ) {
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=rss-posts&page=' . RSS_Post_Aggregator_Settings::PAGE_SLUG );
		}

		$redirect = remove_query_arg( array( 'wds_rss_import', 'attempted', 'imported', 'skipped', 'failed', 'errors' ), $redirect );
		$redirect = add_query_arg(
			array(
				'wds_rss_import' => 'complete',
				'attempted'      => absint( $summary['attempted'] ),
				'imported'       => absint( $summary['imported'] ),
				'skipped'        => absint( $summary['skipped'] ),
				'failed'         => absint( $summary['failed'] ),
				'errors'         => absint( $summary['errors'] ),
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render an admin notice after a manual import completes.
	 *
	 * @since 0.2.6
	 */
	public function render_import_notice() {
		if ( ! isset( $_GET['wds_rss_import'] ) || 'complete' !== $_GET['wds_rss_import'] ) {
			return;
		}

		$attempted = isset( $_GET['attempted'] ) ? absint( $_GET['attempted'] ) : 0;
		$imported  = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
		$skipped   = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
		$failed    = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
		$errors    = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;
		$class     = $failed || $errors ? 'notice notice-warning is-dismissible' : 'notice notice-success is-dismissible';

		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p>
				<?php
				printf(
					esc_html__( 'RSS manual import complete. Feeds attempted: %1$d. Imported: %2$d. Skipped existing: %3$d. Failed posts: %4$d. Feed errors: %5$d.', 'wds-rss-post-aggregator' ),
					$attempted,
					$imported,
					$skipped,
					$failed,
					$errors
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Method for storing posts.
	 *
	 * @since 0.1.1
	 *
	 * Stores and returns JSON AJAX response.
	 */
	public function rss_save_posts() {
		$this->verify_ajax_request();

		foreach ( array( 'to_add', 'feed_url', 'feed_id' ) as $required ) {
			if ( ! isset( $_REQUEST[ $required ] ) ) {
				wp_send_json_error( $required . ' missing.' );
			}
		}

		$updated = $this->save_posts( $_REQUEST['to_add'], $_REQUEST['feed_id'] );
		wp_send_json_success( array( $_REQUEST, $updated ) );

	}

	/**
	 * Verify AJAX request permissions.
	 *
	 * @since 0.2.4
	 */
	protected function verify_ajax_request() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'You do not have permission to import RSS posts.', 'wds-rss-post-aggregator' ), 403 );
		}

		check_ajax_referer( 'rss_post_aggregator_ajax', 'nonce' );
	}

	/**
	 * Store posts.
	 *
	 * @since 0.1.1
	 *
	 * @param  array $posts   Array of posts to store.
	 * @param  integer $feed_id Feed ID.
	 * @return array          Array of posts stored.
	 */
	public function save_posts( $posts, $feed_id, $post_type = '' ) {

		$post_type = $post_type ? sanitize_key( $post_type ) : $this->tax->get_target_post_type( $feed_id );
		$updated   = array();
		foreach ( $posts as $post_data ) {
			$updated[ $post_data['title'] ] = $this->cpt->insert( $post_data, $feed_id, $post_type );
		}

		return $updated;
	}

	/**
	 * Import all configured automatic feeds.
	 *
	 * @since 0.2.4
	 *
	 * @return array Import results keyed by feed term ID.
	 */
	public function import_all_feeds() {
		$feeds = get_terms( array(
			'taxonomy'   => $this->tax->taxonomy(),
			'hide_empty' => false,
		) );

		if ( is_wp_error( $feeds ) || empty( $feeds ) ) {
			return array();
		}

		$results = array();

		foreach ( $feeds as $feed ) {
			if ( ! $this->tax->is_auto_import_enabled( $feed->term_id ) ) {
				continue;
			}

			$results[ $feed->term_id ] = $this->import_feed( $this->get_feed_url( $feed ), $feed->term_id );
		}

		return $results;
	}

	/**
	 * Import one configured feed.
	 *
	 * @since 0.2.4
	 *
	 * @param string $feed_url Feed URL.
	 * @param int    $feed_id  Feed term ID.
	 * @return array|\WP_Error Import result.
	 */
	public function import_feed( $feed_url, $feed_id ) {
		$feed_url = $this->normalize_feed_url( $feed_url );

		if ( ! $feed_url ) {
			return new \WP_Error( 'rss_post_aggregator_invalid_feed_url', __( 'The saved RSS feed URL is invalid. Edit the feed link and enter the full RSS URL, including https://.', 'wds-rss-post-aggregator' ) );
		}

		$feed_items = $this->rss->get_items( $feed_url, array(
			'show_author'  => true,
			'show_date'    => true,
			'show_summary' => true,
			'show_image'   => true,
			'items'        => (int) apply_filters( 'rss_post_aggregator_scheduled_import_item_limit', 20, $feed_url, $feed_id ),
			'cache_time'   => 0,
		) );

		if ( isset( $feed_items['error'] ) ) {
			return new \WP_Error( 'rss_post_aggregator_feed_error', $feed_items['error'] );
		}

		if ( empty( $feed_items ) ) {
			return new \WP_Error( 'rss_post_aggregator_empty_feed', __( 'The feed returned no importable items.', 'wds-rss-post-aggregator' ) );
		}

		return $this->save_posts( $feed_items, $feed_id, $this->tax->get_target_post_type( $feed_id ) );
	}


	/**
	 * Get RSS data.
	 *
	 * @since 0.1.1
	 *
	 * @return string Return JSON object.
	 */
	public function rss_get_data() {
		$this->verify_ajax_request();

		foreach ( array( 'feed_url', 'feed_id' ) as $required ) {
			if ( ! isset( $_REQUEST[ $required ] ) ) {
				wp_send_json_error( $required . ' missing.' );
			}
		}

		$feed_url = $this->normalize_feed_url( wp_unslash( $_REQUEST['feed_url'] ) );
		$feed_id  = absint( $_REQUEST['feed_id'] );

		if ( ! $feed_url ) {
			wp_send_json_error( __( 'Please enter a valid RSS feed URL, including https://.', 'wds-rss-post-aggregator' ) );
		}

		if ( ! $feed_id ) {
			$feed_id = $this->ensure_feed_term( $feed_url );
		}

		if ( ! $feed_id ) {
			wp_send_json_error( __( 'There was an error with the RSS feed link creation.', 'wds-rss-post-aggregator' ) );
		}

		$feed_items = $this->rss->get_items( $feed_url, array(
			'show_author'  => true,
			'show_date'    => true,
			'show_summary' => true,
			'show_image'   => true,
			'items'        => 20,
		) );

		if ( isset( $feed_items['error'] ) ) {
			wp_send_json_error( $feed_items['error'] );
		}

		wp_send_json_success( compact( 'feed_url', 'feed_id', 'feed_items' ) );
	}

	/**
	 * Get the best available URL for a saved feed term.
	 *
	 * @since 0.2.7
	 *
	 * @param \WP_Term $feed Feed term.
	 * @return string Feed URL.
	 */
	protected function get_feed_url( $feed ) {
		$feed_url = get_term_meta( $feed->term_id, RSS_Post_Aggregator_Taxonomy::META_FEED_URL, true );
		$feed_url = $feed_url ? $feed_url : $feed->name;
		$feed_url = $this->normalize_feed_url( $feed_url );

		if ( $feed_url ) {
			update_term_meta( $feed->term_id, RSS_Post_Aggregator_Taxonomy::META_FEED_URL, esc_url_raw( $feed_url ) );
		}

		return $feed_url;
	}

	/**
	 * Normalize saved feed input into a usable URL.
	 *
	 * Also repairs WordPress term slugs that were pasted into the feed name field,
	 * such as https-rss-buzzsprout-com-1603417-rss.
	 *
	 * @since 0.2.7
	 *
	 * @param string $feed_url Feed URL or URL-like term slug.
	 * @return string Valid feed URL, or an empty string when it cannot be normalized.
	 */
	protected function normalize_feed_url( $feed_url ) {
		$raw_feed_url = trim( (string) $feed_url );

		if ( preg_match( '/^https?-/', $raw_feed_url ) ) {
			$rebuilt_url = $this->url_from_term_slug( $raw_feed_url );

			if ( $rebuilt_url ) {
				return $rebuilt_url;
			}
		}

		$feed_url = esc_url_raw( $raw_feed_url );
		$parts    = $feed_url ? wp_parse_url( $feed_url ) : array();

		if (
			! empty( $parts['scheme'] )
			&& ! empty( $parts['host'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
		) {
			return $feed_url;
		}

		return '';
	}

	/**
	 * Convert a URL-like WordPress term slug back into a URL.
	 *
	 * @since 0.2.7
	 *
	 * @param string $feed_slug URL-like feed slug.
	 * @return string Rebuilt URL, or an empty string.
	 */
	protected function url_from_term_slug( $feed_slug ) {
		$feed_slug = sanitize_title( $feed_slug );

		if ( ! preg_match( '/^(https?)-(.+)$/', $feed_slug, $matches ) ) {
			return '';
		}

		$parts = explode( '-', $matches[2] );
		$tlds  = array( 'com', 'org', 'net', 'edu', 'gov', 'io', 'co', 'us', 'fm', 'info', 'biz', 'tv' );
		$host  = array();
		$path  = array();

		foreach ( $parts as $part ) {
			$host[] = $part;

			if ( in_array( $part, $tlds, true ) ) {
				$path = array_slice( $parts, count( $host ) );
				break;
			}
		}

		if ( empty( $path ) ) {
			return '';
		}

		$last = end( $path );
		if ( in_array( $last, array( 'rss', 'xml', 'atom', 'json' ), true ) && count( $path ) > 1 ) {
			array_pop( $path );
			$path[ count( $path ) - 1 ] .= '.' . $last;
		}

		$url = $matches[1] . '://' . implode( '.', $host ) . '/' . implode( '/', $path );

		return esc_url_raw( $url );
	}

	/**
	 * Ensure a feed URL has a term and default import settings.
	 *
	 * @since 0.2.4
	 *
	 * @param string $feed_url Feed URL.
	 * @return int|false Feed term ID, or false on failure.
	 */
	protected function ensure_feed_term( $feed_url ) {
		$feed_url = $this->normalize_feed_url( $feed_url );

		if ( ! $feed_url ) {
			return false;
		}

		$link = get_term_by( 'name', $feed_url, $this->tax->taxonomy() );

		if ( $link ) {
			update_term_meta( $link->term_id, RSS_Post_Aggregator_Taxonomy::META_FEED_URL, esc_url_raw( $feed_url ) );
			return (int) $link->term_id;
		}

		$link = wp_insert_term( $feed_url, $this->tax->taxonomy() );
		if ( is_wp_error( $link ) || empty( $link['term_id'] ) ) {
			return false;
		}

		update_term_meta( $link['term_id'], RSS_Post_Aggregator_Taxonomy::META_AUTO_IMPORT, '1' );
		update_term_meta( $link['term_id'], RSS_Post_Aggregator_Taxonomy::META_FEED_URL, esc_url_raw( $feed_url ) );

		return (int) $link['term_id'];
	}

	/**
	 * Enqueue Assets.
	 *
	 * @since 0.1.1
	 *
	 */
	public function enqueue() {
		if ( ! $this->cpt->is_listing() ) {
			return;
		}

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$dependencies = array(
			'jquery', // obvious reasons
			'wp-backbone', // Needed for backbone and `wp.template`
		);

		wp_enqueue_script( 'rss-aggregator', RSS_Post_Aggregator::url( "assets/js/rss_post_aggregator{$min}.js" ), $dependencies, RSS_Post_Aggregator::VERSION );

		// wp_die( '<xmp>: '. print_r( $this->cpt->slug_to_redirect, true ) .'</xmp>' );
		wp_localize_script( 'rss-aggregator', 'RSSPost_l10n', array(
			'debug'           => ! $min,
			'cpt_url'         => add_query_arg( 'post_type', $this->cpt->post_type(), admin_url( '/edit.php' ) ),
			'feeds'           => $this->get_feed_links(),
			'cpt'             => $this->cpt->post_type(),
			'show_modal'      => isset( $_GET[ $this->cpt->slug_to_redirect ] ),
			'no_data'         => __( 'No feed data found', 'wds-rss-post-aggregator' ),
			'nothing_checked' => __( "You didn't select any posts. Do you want to close the search?", 'wds-rss-post-aggregator' ),
			'nonce'           => wp_create_nonce( 'rss_post_aggregator_ajax' ),
		) );

		delete_option( 'wds_rss_aggregate_saved_feed_urls' );
		// Needed to style the search modal
		wp_register_style( 'rss-search-box', admin_url( "/css/media{$min}.css" ) );
		wp_enqueue_style( 'rss-aggregator', RSS_Post_Aggregator::url( "assets/css/rss_post_aggregator{$min}.css" ), array( 'rss-search-box' ), RSS_Post_Aggregator::VERSION );

		add_action( 'admin_footer', array( $this, 'js_modal_template' ) );

	}

	/**
	 * Get feed links.
	 *
	 * @since 0.1.1
	 *
	 * @return array Return array of links.
	 */
	public function get_feed_links() {
		if ( ! empty( $this->feed_links ) ) {
			return $this->feed_links;
		}

		$feed_links = get_terms( array(
			'taxonomy'   => $this->tax->taxonomy(),
			'hide_empty' => false,
		) );

		if ( $feed_links && is_array( $feed_links ) ) {
			foreach ( $feed_links as $link ) {
				$feed_url = $this->get_feed_url( $link );

				if ( ! $feed_url ) {
					continue;
				}

				$this->feed_links[ $link->term_id ] = esc_url( $feed_url );
			}
		}

		return $this->feed_links;
	}

	/**
	 * Modal Template File.
	 *
	 * @since 0.1.1
	 */
	public function js_modal_template() {
		include_once 'modal-markup.php';
	}

}
