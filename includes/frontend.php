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
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'the_permalink', array( $this, 'get_post_and_post_link' ) );
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

		$original_url = get_post_meta( $post_id, $this->cpt->prefix . 'original_url', true );

		$original_urls[ $post_id ] = $original_url ? $original_url : $link;

		return $original_urls[ $post_id ];
	}

}
