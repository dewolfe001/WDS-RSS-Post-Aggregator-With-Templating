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
		add_filter( 'the_content', array( $this, 'prepend_audio_player' ) );
		add_shortcode( 'rss_posts', array( $this, 'rss_posts_shortcode' ) );
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
	 * Render a templated list of imported RSS posts.
	 *
	 * Usage: [rss_posts limit="5" item_template="<li><a href='{permalink}'>{title}</a></li>"]
	 *
	 * @since 0.2.13
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered shortcode markup.
	 */
	public function rss_posts_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'            => 5,
				'posts_per_page'   => '',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'category'         => '',
				'category_slug'    => '',
				'category_id'      => '',
				'feed'             => '',
				'feed_slug'        => '',
				'feed_id'          => '',
				'excerpt_length'   => 25,
				'date_format'      => get_option( 'date_format' ),
				'image_size'       => 'thumbnail',
				'item_template'    => '<li class="rss-post-aggregator-list__item"><a class="rss-post-aggregator-list__title" href="{permalink}">{title}</a>{excerpt}</li>',
				'wrapper_template' => '<ul class="rss-post-aggregator-list">{items}</ul>',
				'no_posts'         => __( 'No RSS posts found.', 'wds-rss-post-aggregator' ),
			),
			(array) $atts,
			'rss_posts'
		);

		$posts_per_page = '' !== $atts['posts_per_page'] ? absint( $atts['posts_per_page'] ) : absint( $atts['limit'] );
		$posts_per_page = $posts_per_page ? $posts_per_page : 5;
		$order          = 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC';
		$orderby        = sanitize_key( $atts['orderby'] );
		$allowed_orderby = array( 'date', 'title', 'menu_order', 'modified', 'rand' );

		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$query_args = array(
			'post_type'           => $this->cpt->post_type(),
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_per_page,
			'orderby'             => $orderby,
			'order'               => $order,
			'ignore_sticky_posts' => true,
		);

		$tax_query = $this->get_shortcode_tax_query( $atts );
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		/**
		 * Filter the RSS posts shortcode query arguments.
		 *
		 * @since 0.2.13
		 *
		 * @param array                         $query_args Query arguments.
		 * @param array                         $atts       Shortcode attributes.
		 * @param RSS_Post_Aggregator_Frontend  $frontend   Frontend instance.
		 */
		$query_args = apply_filters( 'rss_post_aggregator_shortcode_query_args', $query_args, $atts, $this );

		$rss_posts = new \WP_Query( $query_args );
		if ( ! $rss_posts->have_posts() ) {
			return wp_kses_post( $atts['no_posts'] );
		}

		$items = '';
		while ( $rss_posts->have_posts() ) {
			$rss_posts->the_post();
			$items .= $this->render_shortcode_item( get_post(), $atts );
		}

		wp_reset_postdata();

		$wrapper_template = apply_filters( 'rss_post_aggregator_shortcode_wrapper_template', $atts['wrapper_template'], $atts, $this );
		$output           = str_replace( '{items}', $items, $wrapper_template );

		return wp_kses_post( apply_filters( 'rss_post_aggregator_shortcode_output', $output, $items, $atts, $this ) );
	}

	/**
	 * Build taxonomy query clauses from shortcode attributes.
	 *
	 * @since 0.2.13
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array Tax query clauses.
	 */
	protected function get_shortcode_tax_query( $atts ) {
		$tax_query = array();

		$category_slugs = $this->csv_to_array( $atts['category'] ? $atts['category'] : $atts['category_slug'] );
		if ( ! empty( $category_slugs ) ) {
			$tax_query[] = array(
				'taxonomy' => 'rss-category',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', $category_slugs ),
			);
		}

		$category_ids = $this->csv_to_absint_array( $atts['category_id'] );
		if ( ! empty( $category_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'rss-category',
				'field'    => 'term_id',
				'terms'    => $category_ids,
			);
		}

		$feed_slugs = $this->csv_to_array( $atts['feed'] ? $atts['feed'] : $atts['feed_slug'] );
		if ( ! empty( $feed_slugs ) ) {
			$tax_query[] = array(
				'taxonomy' => $this->cpt->tax_slug,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', $feed_slugs ),
			);
		}

		$feed_ids = $this->csv_to_absint_array( $atts['feed_id'] );
		if ( ! empty( $feed_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => $this->cpt->tax_slug,
				'field'    => 'term_id',
				'terms'    => $feed_ids,
			);
		}

		if ( 1 < count( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	/**
	 * Render one RSS post shortcode item.
	 *
	 * @since 0.2.13
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $atts Shortcode attributes.
	 * @return string Rendered item markup.
	 */
	protected function render_shortcode_item( $post, $atts ) {
		$item_template = apply_filters( 'rss_post_aggregator_shortcode_item_template', $atts['item_template'], $post, $atts, $this );
		$tokens        = $this->get_shortcode_template_tokens( $post, $atts );
		$item          = strtr( $item_template, $tokens );

		return apply_filters( 'rss_post_aggregator_shortcode_item', $item, $post, $atts, $tokens, $this );
	}

	/**
	 * Get escaped shortcode template token values for one RSS post.
	 *
	 * @since 0.2.13
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $atts Shortcode attributes.
	 * @return array Token values keyed by token.
	 */
	protected function get_shortcode_template_tokens( $post, $atts ) {
		$excerpt_length = absint( $atts['excerpt_length'] );
		$excerpt        = get_the_excerpt( $post );
		if ( $excerpt_length ) {
			$excerpt = wp_trim_words( $excerpt, $excerpt_length );
		}

		$thumbnail = get_the_post_thumbnail( $post, sanitize_key( $atts['image_size'] ), array(
			'class' => 'rss-post-aggregator-list__thumbnail',
		) );

		$tokens = array(
			'{id}'              => (string) absint( $post->ID ),
			'{title}'           => esc_html( get_the_title( $post ) ),
			'{permalink}'       => esc_url( get_permalink( $post ) ),
			'{original_url}'    => esc_url( get_post_meta( $post->ID, $this->cpt->prefix . 'original_url', true ) ),
			'{excerpt}'         => $excerpt ? wp_kses_post( wpautop( $excerpt ) ) : '',
			'{content}'         => wp_kses_post( apply_filters( 'the_content', $post->post_content ) ),
			'{date}'            => esc_html( get_the_date( $atts['date_format'], $post ) ),
			'{thumbnail}'       => $thumbnail ? wp_kses_post( $thumbnail ) : '',
			'{audio_url}'       => esc_url( rss_post_get_audio_url( $post ) ),
			'{feed_url}'        => esc_url( $this->get_shortcode_feed_url( $post ) ),
			'{feed_name}'       => esc_html( rss_post_get_feed_url( $post ) ),
			'{feed_source}'     => esc_html( rss_post_get_feed_source( $post ) ),
			'{rss_categories}'  => esc_html( $this->get_shortcode_term_names( $post->ID, 'rss-category' ) ),
			'{rss_feed_terms}'  => esc_html( $this->get_shortcode_term_names( $post->ID, $this->cpt->tax_slug ) ),
		);

		return apply_filters( 'rss_post_aggregator_shortcode_template_tokens', $tokens, $post, $atts, $this );
	}


	/**
	 * Get the saved feed URL for a shortcode item.
	 *
	 * @since 0.2.13
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Feed URL.
	 */
	protected function get_shortcode_feed_url( $post ) {
		$feed = rss_post_get_feed_object( $post );
		if ( empty( $feed->term_id ) ) {
			return '';
		}

		$feed_url = get_term_meta( $feed->term_id, RSS_Post_Aggregator_Taxonomy::META_FEED_URL, true );

		return $feed_url ? $feed_url : rss_post_get_feed_url( $post );
	}

	/**
	 * Convert a comma-separated string to a trimmed array.
	 *
	 * @since 0.2.13
	 *
	 * @param string $value Comma-separated value.
	 * @return array Values.
	 */
	protected function csv_to_array( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return array();
		}

		return array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
	}

	/**
	 * Convert a comma-separated string to positive integers.
	 *
	 * @since 0.2.13
	 *
	 * @param string $value Comma-separated value.
	 * @return array Integer values.
	 */
	protected function csv_to_absint_array( $value ) {
		return array_filter( array_map( 'absint', $this->csv_to_array( $value ) ) );
	}

	/**
	 * Get a comma-separated list of term names for a post.
	 *
	 * @since 0.2.13
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string Term names.
	 */
	protected function get_shortcode_term_names( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Prepend the retained podcast audio enclosure to local RSS post detail pages.
	 *
	 * @since 0.2.2
	 *
	 * @param string $content Post content.
	 * @return string Content with the audio player when an audio URL exists.
	 */
	public function prepend_audio_player( $content ) {
		if ( ! is_singular( $this->cpt->post_type() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$audio_url = rss_post_get_audio_url();
		if ( ! $audio_url ) {
			return $content;
		}

		$audio = wp_audio_shortcode( array(
			'src' => $audio_url,
		) );

		if ( ! $audio ) {
			return $content;
		}

		return '<div class="rss-post-audio">' . $audio . '</div>' . $content;
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
