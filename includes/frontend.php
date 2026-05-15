<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;


class RSS_Post_Aggregator_Frontend {

	/**
	 * RSS post custom post type controller.
	 *
	 * @since 0.2.0
	 *
	 * @var RSS_Post_Aggregator_CPT
	 */
	public $cpt;

	/**
	 * Constructor
	 *
	 * @since 0.1.1
	 *
	 * @param Array $cpt Custom Post Type Object.
	 */
	public function __construct( $cpt ) {
		$this->cpt = $cpt;
	}

	/**
	 * Initiate hooks.
	 *
	 * @since 0.1.1
	 */
	public function hooks() {
		add_action( 'pre_get_posts', array( $this, 'include_rss_posts_on_homepage' ) );
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'the_permalink', array( $this, 'get_post_and_post_link' ) );
	}

	/**
	 * Include imported RSS posts in the default blog/home feed.
	 *
	 * This lets the newest imported podcast entries appear beside regular posts
	 * on the initial posts screen without requiring a theme-level query change.
	 *
	 * @since 0.2.1
	 *
	 * @param \WP_Query $query Main WordPress query.
	 */
	public function include_rss_posts_on_homepage( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
			return;
		}

		$should_include = apply_filters( 'rss_post_aggregator_include_rss_posts_on_home', true, $query );
		if ( ! $should_include ) {
			return;
		}

		$post_types = $query->get( 'post_type' );

		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}

		if ( ! is_array( $post_types ) || in_array( 'any', $post_types, true ) ) {
			return;
		}

		$post_types[] = $this->cpt->post_type();

		$query->set( 'post_type', array_values( array_unique( $post_types ) ) );
	}

	/**
	 * Get Post Link.
	 *
	 * @since 0.1.1
	 *
	 * @param  string $link Link.
	 * @return string       Post link.
	 */
	public function get_post_and_post_link( $link ) {
		$post = get_post();
		if ( empty( $post ) ) {
			return $link;
		}

		return $this->post_link( $link, $post );
	}

	/**
	 * Return Post link via post.
	 *
	 * @since 0.1.1
	 *
	 * @param  string $link Link.
	 * @param  array $post Post Class Object.
	 * @return string       Link.
	 */
	function post_link( $link, $post ) {

		// Don't mess w/ the permalink for attachments
		if ( isset( $GLOBALS['post'], $GLOBALS['post']->post_type ) && 'attachment' === $GLOBALS['post']->post_type ) {
			return $link;
		}

		if ( ! isset( $post->post_type ) || $post->post_type != $this->cpt->post_type() ) {
			return $link;
		}

		static $original_urls = array();

		$post_id = is_numeric( $post ) ? (int) $post : (int) $post->ID;

		if ( array_key_exists( $post_id, $original_urls ) ) {
			return $original_urls[ $post_id ];
		}

		$use_original_url = apply_filters( 'rss_post_aggregator_link_to_original_url', false, $post_id, $post, $link );
		if ( ! $use_original_url ) {
			$original_urls[ $post_id ] = $link;
			return $original_urls[ $post_id ];
		}

		$original_url = get_post_meta( $post_id, $this->cpt->prefix . 'original_url', true );

		$original_urls[ $post_id ] = $original_url ? $original_url : $link;

		return $original_urls[ $post_id ];
	}

}
