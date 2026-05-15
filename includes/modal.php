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
		add_action( 'wp_ajax_rss_get_data', array( $this, 'rss_get_data' ) );
		add_action( 'wp_ajax_rss_save_posts', array( $this, 'rss_save_posts' ) );
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

		$post_type = isset( $_REQUEST['import_post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['import_post_type'] ) ) : '';
		$updated   = $this->save_posts( $_REQUEST['to_add'], $_REQUEST['feed_id'], $post_type );
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

			$results[ $feed->term_id ] = $this->import_feed( $feed->name, $feed->term_id );
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
		$feed_items = $this->rss->get_items( esc_url_raw( $feed_url ), array(
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

		$feed_url = esc_url( $_REQUEST['feed_url'] );
		$feed_id  = absint( $_REQUEST['feed_id'] );

		if ( ! $feed_id ) {
			$feed_id = $this->ensure_feed_term( $feed_url );
		}

		if ( ! $feed_id ) {
			wp_send_json_error( __( 'There was an error with the RSS feed link creation.', 'wds-rss-post-aggregator' ) );
		}

		$feed_items = $this->rss->get_items( esc_url( $_REQUEST['feed_url'] ), array(
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
	 * Ensure a feed URL has a term and default import settings.
	 *
	 * @since 0.2.4
	 *
	 * @param string $feed_url Feed URL.
	 * @return int|false Feed term ID, or false on failure.
	 */
	protected function ensure_feed_term( $feed_url ) {
		$link = get_term_by( 'name', $feed_url, $this->tax->taxonomy() );

		if ( $link ) {
			return (int) $link->term_id;
		}

		$link = wp_insert_term( $feed_url, $this->tax->taxonomy() );
		if ( is_wp_error( $link ) || empty( $link['term_id'] ) ) {
			return false;
		}

		update_term_meta( $link['term_id'], RSS_Post_Aggregator_Taxonomy::META_AUTO_IMPORT, '1' );

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
				$this->feed_links[ $link->term_id ] = esc_url( $link->name );
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
