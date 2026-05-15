<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;


class RSS_Post_Aggregator_Feeds {

	/**
	 * Current RSS feed URL.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public $rss_link = '';

	/**
	 * Current SimplePie item being processed.
	 *
	 * @since 0.2.0
	 *
	 * @var object|null
	 */
	protected $item = null;

	/**
	 * Cache duration, in seconds.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public $cache_time = 0;

	/**
	 * Transient cache key for the current request.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public $transient_id = '';

	/**
	 * DOM parser used to inspect feed item HTML.
	 *
	 * @since 0.2.0
	 *
	 * @var \DOMDocument|null
	 */
	protected $dom = null;

	/**
	 * Replaces wp_widget_rss_output.
	 *
	 * @since 0.1.1
	 *
	 * @param  string $rss_link RSS link.
	 * @param  array $args     Array of arguments.
	 * @return array           Returns an array with error message or RSS item results.
	 */
	public function get_items( $rss_link, $args ) {
		$this->rss_link = $rss_link;

		$args = $this->process_args( $args );

		$rss_items = get_transient( $this->transient_id );

		if ( ! isset( $_GET['delete-trans'] ) && $this->cache_time && $rss_items ) { # phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $rss_items;
		}

		$items = (int) $args['items'];
		if ( 1 > $items || 20 < $items ) {
			$items = 10;
		}
		$show_image    = (int) $args['show_image'];
		$show_summary  = (int) $args['show_summary'];
		$show_author   = (int) $args['show_author'];
		$show_date     = (int) $args['show_date'];

		$rss = fetch_feed( $this->rss_link );

		if ( is_wp_error( $rss ) ) {
			// if ( is_admin() || current_user_can( 'manage_options' ) )
			return array(
				// translators: RSS Error: %s
				'error' => sprintf( __( 'RSS Error: %s', 'wds-rss-post-aggregator' ), $rss->get_error_message() ),
			);
		}

		if ( ! $rss->get_item_quantity() ) {
			$rss->__destruct();
			unset( $rss );
			return array(
				'error' => __( 'An error has occurred, which probably means the feed is down. Try again later.', 'wds-rss-post-aggregator' ),
			);
		}

		$parse  = parse_url( $this->rss_link );
		$source = isset( $parse['host'] ) ? $parse['host'] : $this->rss_link;

		$rss_items = array();

		foreach ( $rss->get_items( 0, $items ) as $index => $item ) {
			$this->item = $item;

			$rss_item = array();

			$rss_item['link']          = $this->get_link();
			$rss_item['title']         = $this->get_title();
			$rss_item['audio_url']     = $this->get_audio_url();
			$rss_item['rss_item_meta'] = $this->get_rss_item_meta();

			if ( $show_image ) {
				$rss_item['image'] = $this->get_image();
			}

			if ( $show_summary ) {
				$rss_item['summary'] = $this->get_summary();
			}

			if ( $show_date ) {
				$rss_item['date'] = $this->get_date();
			}

			if ( $show_author ) {
				$rss_item['author'] = $this->get_author();
			}

			$rss_item['source']   = $source;
			$rss_item['rss_link'] = $this->rss_link;
			$rss_item['index']    = $index;

			$rss_items[ $index ]  = $rss_item;
		}

		$rss->__destruct();
		unset( $rss );

		if ( $this->cache_time ) {
			set_transient( $this->transient_id, $rss_items, $this->cache_time );
		}

		return apply_filters( 'rss_post_aggregator_feed_items', $rss_items, $this->rss_link, $this );
	}

	/**
	 * Process the arguments.
	 *
	 * @since 0.1.1
	 *
	 * @param  array $args Arguments to be processed.
	 * @return array       Processed arguments.
	 */
	public function process_args( $args ) {
		$args = apply_filters( 'rss_post_aggregator_feed_args', $args, $this->rss_link, $this );

		$args = wp_parse_args( $args, array(
			'show_author'  => 0,
			'show_date'    => 0,
			'show_summary' => 0,
			'show_image'   => 0,
			'items'        => 0,
			'cache_time'   => DAY_IN_SECONDS,
		) );
		$this->cache_time = (int) $args['cache_time'];

		$this->transient_id = md5( serialize( array_merge( array(
			'rss_link'       => $this->rss_link,
			'schema_version' => RSS_Post_Aggregator::VERSION,
		), $args ) ) );
		return $args;
	}

	/**
	 * Get feed title.
	 *
	 * @since 0.1.1
	 *
	 * @return string
	 */
	public function get_title() {
		$title = esc_html( trim( strip_tags( RSS_Post_Aggregator::decode_entities( $this->item->get_title() ) ) ) );
		if ( empty( $title ) ) {
			$title = __( 'Untitled', 'wds-rss-post-aggregator' );
		}

		return apply_filters( 'rss_post_aggregator_feed_title', $title, $this->rss_link, $this );
	}

	/**
	 * Get feed item link.
	 *
	 * @since 0.1.1
	 *
	 * @return string Link to RSS feed item.
	 */
	public function get_link() {
		$link = (string) $this->item->get_link();

		while ( stristr( $link, 'http' ) != $link ) {
			$link = substr( $link, 1 );
		}

		$link = esc_url( strip_tags( trim( $link ) ) );

		return apply_filters( 'rss_post_aggregator_feed_link', $link, $this->rss_link, $this );
	}

	/**
	 * Get the audio enclosure URL for podcast RSS items.
	 *
	 * @since 0.2.2
	 *
	 * @return string Audio enclosure URL.
	 */
	public function get_audio_url() {
		$audio_url  = '';
		$enclosures = $this->item->get_enclosures();

		if ( empty( $enclosures ) ) {
			$enclosure = $this->item->get_enclosure();
			$enclosures = $enclosure ? array( $enclosure ) : array();
		}

		foreach ( $enclosures as $enclosure ) {
			$type = (string) $enclosure->get_type();
			$link = (string) $enclosure->get_link();

			if ( empty( $link ) ) {
				continue;
			}

			if ( 0 === strpos( $type, 'audio/' ) || preg_match( '/\.(mp3|m4a|ogg|oga|wav)(\?.*)?$/i', $link ) ) {
				$audio_url = esc_url_raw( $link );
				break;
			}
		}

		return apply_filters( 'rss_post_aggregator_feed_audio_url', $audio_url, $this->rss_link, $this );
	}


	/**
	 * Get the RSS item fields that should be retained as post meta on import.
	 *
	 * @since 0.2.3
	 *
	 * @return array RSS item meta fields keyed by normalized field name.
	 */
	public function get_rss_item_meta() {
		$guid_tag      = $this->get_item_tag( array( '' ), 'guid' );
		$guid_attribs  = $this->get_tag_attribs( $guid_tag );
		$enclosure_tag = $this->get_item_tag( array( '' ), 'enclosure' );

		$meta = array(
			'itunes_title'        => $this->get_namespaced_value( $this->itunes_namespace(), 'title' ),
			'title'               => $this->get_namespaced_value( array( '' ), 'title' ),
			'itunes_summary'      => $this->get_namespaced_value( $this->itunes_namespace(), 'summary', true ),
			'description'         => $this->get_namespaced_value( array( '' ), 'description', true ),
			'content_encoded'     => $this->get_namespaced_value( $this->content_namespace(), 'encoded', true ),
			'enclosure'           => $this->get_enclosure_meta( $enclosure_tag ),
			'itunes_author'       => $this->get_namespaced_value( $this->itunes_namespace(), 'author' ),
			'guid'                => array(
				'value'        => $this->sanitize_meta_value( isset( $guid_tag['data'] ) ? $guid_tag['data'] : '' ),
				'is_permalink' => isset( $guid_attribs['isPermaLink'] ) ? $this->sanitize_meta_value( $guid_attribs['isPermaLink'] ) : '',
			),
			'pub_date'            => $this->get_namespaced_value( array( '' ), 'pubDate' ),
			'itunes_duration'     => $this->get_namespaced_value( $this->itunes_namespace(), 'duration' ),
			'itunes_keywords'     => $this->get_namespaced_value( $this->itunes_namespace(), 'keywords' ),
			'itunes_episode'      => $this->get_namespaced_value( $this->itunes_namespace(), 'episode' ),
			'itunes_episode_type' => $this->get_namespaced_value( $this->itunes_namespace(), 'episodeType' ),
			'itunes_explicit'     => $this->get_namespaced_value( $this->itunes_namespace(), 'explicit' ),
		);

		/**
		 * Filters the retained RSS item meta before the item is returned to the importer.
		 *
		 * @since 0.2.3
		 *
		 * @param array                     $meta     Retained RSS item meta.
		 * @param string                    $rss_link Current RSS feed URL.
		 * @param RSS_Post_Aggregator_Feeds $feeds    Current feeds instance.
		 */
		return apply_filters( 'rss_post_aggregator_feed_item_meta', $meta, $this->rss_link, $this );
	}

	/**
	 * Get known iTunes RSS namespaces.
	 *
	 * @since 0.2.3
	 *
	 * @return array iTunes namespaces.
	 */
	protected function itunes_namespace() {
		return array(
			'http://www.itunes.com/dtds/podcast-1.0.dtd',
			'http://www.itunes.com/DTDs/Podcast-1.0.dtd',
		);
	}

	/**
	 * Get known content module namespaces.
	 *
	 * @since 0.2.3
	 *
	 * @return array Content module namespaces.
	 */
	protected function content_namespace() {
		return array( 'http://purl.org/rss/1.0/modules/content/' );
	}

	/**
	 * Get and sanitize a namespaced item value.
	 *
	 * @since 0.2.3
	 *
	 * @param array|string $namespaces Namespaces to inspect.
	 * @param string       $tag        Tag name.
	 * @param bool         $allow_html Whether safe HTML should be retained.
	 * @return string Sanitized value.
	 */
	protected function get_namespaced_value( $namespaces, $tag, $allow_html = false ) {
		$item_tag = $this->get_item_tag( (array) $namespaces, $tag );
		$value    = isset( $item_tag['data'] ) ? $item_tag['data'] : '';

		return $this->sanitize_meta_value( $value, $allow_html );
	}

	/**
	 * Get a SimplePie item tag from the first namespace that contains it.
	 *
	 * @since 0.2.3
	 *
	 * @param array  $namespaces Namespaces to inspect.
	 * @param string $tag        Tag name.
	 * @return array Tag data.
	 */
	protected function get_item_tag( $namespaces, $tag ) {
		if ( ! is_object( $this->item ) || ! method_exists( $this->item, 'get_item_tags' ) ) {
			return array();
		}

		foreach ( $namespaces as $namespace ) {
			$item_tags = $this->item->get_item_tags( $namespace, $tag );

			if ( ! empty( $item_tags[0] ) && is_array( $item_tags[0] ) ) {
				return $item_tags[0];
			}
		}

		return array();
	}

	/**
	 * Get flattened tag attributes from a SimplePie tag array.
	 *
	 * @since 0.2.3
	 *
	 * @param array $tag Tag data.
	 * @return array Tag attributes.
	 */
	protected function get_tag_attribs( $tag ) {
		if ( empty( $tag['attribs'] ) || ! is_array( $tag['attribs'] ) ) {
			return array();
		}

		$attribs = array();
		foreach ( $tag['attribs'] as $namespace_attribs ) {
			if ( is_array( $namespace_attribs ) ) {
				$attribs = array_merge( $attribs, $namespace_attribs );
			}
		}

		return $attribs;
	}

	/**
	 * Get enclosure attributes to retain as RSS item meta.
	 *
	 * @since 0.2.3
	 *
	 * @param array $enclosure_tag Enclosure tag data.
	 * @return array Enclosure meta.
	 */
	protected function get_enclosure_meta( $enclosure_tag ) {
		$attribs   = $this->get_tag_attribs( $enclosure_tag );
		$enclosure = array(
			'url'    => isset( $attribs['url'] ) ? esc_url_raw( $attribs['url'] ) : '',
			'length' => isset( $attribs['length'] ) ? $this->sanitize_meta_value( $attribs['length'] ) : '',
			'type'   => isset( $attribs['type'] ) ? sanitize_mime_type( $attribs['type'] ) : '',
		);

		if ( empty( $enclosure['url'] ) ) {
			$simplepie_enclosure = $this->item->get_enclosure();

			if ( $simplepie_enclosure ) {
				$enclosure['url']    = esc_url_raw( (string) $simplepie_enclosure->get_link() );
				$enclosure['length'] = $this->sanitize_meta_value( (string) $simplepie_enclosure->get_length() );
				$enclosure['type']   = sanitize_mime_type( (string) $simplepie_enclosure->get_type() );
			}
		}

		return $enclosure;
	}

	/**
	 * Sanitize retained RSS meta values.
	 *
	 * @since 0.2.3
	 *
	 * @param string $value      Value to sanitize.
	 * @param bool   $allow_html Whether safe HTML should be retained.
	 * @return string Sanitized value.
	 */
	protected function sanitize_meta_value( $value, $allow_html = false ) {
		$value = RSS_Post_Aggregator::decode_entities( $value );
		$value = trim( $value );

		return $allow_html ? wp_kses_post( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Get RSS item date.
	 *
	 * @since 0.1.1
	 *
	 * @return string Feed item date.
	 */
	public function get_date() {
		$get_date = $this->item->get_date( 'U' );
		$date = ( $get_date )
			? date_i18n( get_option( 'date_format' ), $get_date )
			: '';

		return apply_filters( 'rss_post_aggregator_feed_date', $date, $this->rss_link, $this );
	}

	/**
	 * Get feed item author.
	 *
	 * @since 0.1.1
	 *
	 * @return string Author.
	 */
	public function get_author() {
		$author = $this->item->get_author();
		$author = ( ( $author ) && is_object( $author ) )
			? esc_html( strip_tags( RSS_Post_Aggregator::decode_entities( $author->get_name() ) ) )
			: '';

		return apply_filters( 'rss_post_aggregator_feed_author', $author, $this->rss_link, $this );
	}

	/**
	 * Get feed item summary.
	 *
	 * @since 0.1.1
	 *
	 * @return string Feed item summary.
	 */
	public function get_summary() {
		$summary = RSS_Post_Aggregator::decode_entities( $this->item->get_description() );

		$length = (int) apply_filters( 'rss_post_aggregator_feed_summary_length', 100, $this->rss_link, $this );

		$summary = esc_attr( wp_trim_words( $summary, $length, ' [&hellip;]' ) );

		// Change existing [...] to [&hellip;].
		if ( '[...]' == substr( $summary, -5 ) ) {
			$summary = substr( $summary, 0, -5 ) . '[&hellip;]';
		}

		return apply_filters( 'rss_post_aggregator_feed_summary', $summary, $this->rss_link, $this );
	}

	/**
	 * Get feed image.
	 *
	 * @since 0.1.1
	 *
	 * @return string Feed image.
	 */
	public function get_image() {

		// Set image src to an empty string temporarily.
		$src = '';

		// Get link to the parent item.
		$link = (string) $this->item->get_link();

		// Get HTTP request response.
		$data = wp_remote_get( $link );

		// If response isn't 200, bail.
		if ( 200 != wp_remote_retrieve_response_code( $data ) ) {
			return $src;
		}

		// Retrieve only the body from the raw response.
		$content_body = wp_remote_retrieve_body( $data );

		// Bail if content isn't valid.
		if ( empty( $content_body ) ) {
			return $src;
		}

		// Load DOM object for our content.
		$previous_libxml_errors = libxml_use_internal_errors( true );
		$this->dom()->loadHTML( $content_body );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_errors );

		// Get og:image meta tag value from our content.
		foreach ( $this->dom()->getElementsByTagName( 'meta' ) as $meta ) {

			if ( 'og:image' == $meta->getAttribute( 'property' ) ) {
				$src = $meta->getAttribute( 'content' );
				break;
			}
		}

		return apply_filters( 'rss_post_aggregator_feed_image_src', $src, $this->rss_link, $this );
	}

	/**
	 * Get Dom.
	 *
	 * @since 0.1.1
	 *
	 * @return array Returns an object.
	 */
	public function dom() {
		if ( null !== $this->dom ) {
			return $this->dom;
		}
		$this->dom = new \DOMDocument();

		return $this->dom;
	}

}
