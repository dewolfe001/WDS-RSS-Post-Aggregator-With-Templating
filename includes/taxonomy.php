<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;
use Taxonomy_Core;

if ( ! class_exists( 'Taxonomy_Core' ) ) {
	RSS_Post_Aggregator::include_file( 'libraries/Taxonomy_Core/Taxonomy_Core' );
}

/**
 * CPT child class
 */
class RSS_Post_Aggregator_Taxonomy extends Taxonomy_Core {

	/**
	 * Register Custom Post Types. See documentation in Taxonomy_Core, and in wp-includes/post.php
	 *
	 * @since 0.1.1
	 *
	 * @param string $tax_slug
	 * @param CPT_Core $cpt
	 */
	public function __construct( $tax_slug, $cpt ) {

		// Register this cpt
		parent::__construct(
			array( __( 'RSS Feed Link', 'wds-rss-post-aggregator' ), __( 'RSS Feed Links', 'wds-rss-post-aggregator' ), $tax_slug ),
			array(
				'show_admin_column' => false,
			),
			array( $cpt->post_type() )
		);
	}

	public function hooks() {
		add_action( 'admin_notices', array( $this, 'rss_modal_link_notice' ) );
	}

	public function rss_modal_link_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $screen->taxonomy ) || $this->taxonomy() !== $screen->taxonomy ) {
			return;
		}

		echo '<div class="notice notice-info"><p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'post_type' => isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'rss-posts', 'rss_search_modal' => 1 ), admin_url( 'edit.php' ) ) ) . '">' . esc_html__( 'Open RSS Import Modal', 'wds-rss-post-aggregator' ) . '</a></p></div>';
	}

}
