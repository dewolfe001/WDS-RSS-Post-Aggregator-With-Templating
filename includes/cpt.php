<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;
use CPT_Core;

if ( ! class_exists( 'CPT_Core' ) ) {
	RSS_Post_Aggregator::include_file( 'libraries/CPT_Core/CPT_Core' );
}

/**
 * CPT child class
 */
class RSS_Post_Aggregator_CPT extends CPT_Core {

	/**
	 * Prefix.
	 *
	 * @since 0.1.1
	 *
	 * @var string
	 */
	public $prefix = '_rsspost_';

	/**
	 * Redirect slug.
	 *
	 * @since 0.1.1
	 *
	 * @var string
	 */
	public $slug_to_redirect = 'rss_search_modal';

	/**
	 * Tax slug.
	 *
	 * @since 0.1.1
	 *
	 * @var string $tax_slug
	 */
	public $tax_slug;

	/**
	 * Whether the current admin screen is this CPT's listing table.
	 *
	 * @since 0.2.0
	 *
	 * @var bool|null
	 */
	public $is_listing = null;

	/**
	 * Register Custom Post Types. See documentation in CPT_Core, and in wp-includes/post.php
	 *
	 * @since 0.1.1
	 *
	 * @param string $cpt_slug
	 * @param string $tax_slug
	 */
	public function __construct( $cpt_slug, $tax_slug ) {
		$this->tax_slug = $tax_slug;

		// Register this cpt
		parent::__construct(
			array( __( 'RSS Post', 'wds-rss-post-aggregator' ), __( 'RSS Posts', 'wds-rss-post-aggregator' ), $cpt_slug ),
			array(
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'menu_icon'    => 'dashicons-rss',
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Initiate hooks.
	 *
	 * @since 0.1.1
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'pseudo_menu_item' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
	}

	/**
	 * Redirect menu item.
	 *
	 * @since 0.1.1
	 *
	 * @return false Return false if page is not correct.
	 */
	public function pseudo_menu_item() {
		add_submenu_page( 'edit.php?post_type=' . $this->post_type(), '', esc_html__( 'Find RSS Post', 'wds-rss-post-aggregator' ), 'edit_posts', $this->slug_to_redirect, '__return_empty_string' );

		if ( ! isset( $_GET['page'] ) || $this->slug_to_redirect != $_GET['page'] ) {
			return;
		}

		wp_redirect( add_query_arg( array(
			'post_type'             => $this->post_type(),
			$this->slug_to_redirect => true,
		), admin_url( '/edit.php' ) ) );
		exit();
	}

	/**
	 * Check if listing screen.
	 *
	 * @since 0.1.1
	 *
	 * @return boolean Returns boolean.
	 */
	public function is_listing() {
		if ( isset( $this->is_listing ) ) {
			return $this->is_listing;
		}

		$screen = get_current_screen();
		$this->is_listing = isset( $screen->base, $screen->post_type ) && 'edit' == $screen->base && $this->post_type() == $screen->post_type;

		return $this->is_listing;
	}

	/**
	 * Registers admin columns to display. Hooked in via CPT_Core.
	 * @since  0.1.0
	 *
	 * @param  array $columns Array of registered column names/labels
	 *
	 * @return array           Modified array
	 */
	public function columns( $columns ) {
		$columns = array(
			'thumbnail'             => esc_html__( 'Thumbnail', 'wds-rss-post-aggregator' ),
			'cb'                    => $columns['cb'],
			'title'                 => $columns['title'],
			'source'                => esc_html__( 'Source', 'wds-rss-post-aggregator' ),
			'taxonomy-rss-category' => $columns['taxonomy-rss-category'],
			'date'                  => $columns['date'],
		);
		return $columns;
	}

	/**
	 * Handles admin column display. Hooked in via CPT_Core.
	 * @since  0.1.0
	 *
	 * @param  array $column Array of registered column names
	 * @param int    $post_id
	 */
	public function columns_display( $column, $post_id ) {
		global $post;

		switch ( $column ) {

			case 'thumbnail':
				$size = isset( $_GET['mode'] ) && 'excerpt' == $_GET['mode'] ? 'thumb' : array( 50, 50 ); # phpcs:ignore WordPress.Security.NonceVerification.Recommended
				the_post_thumbnail( $size );
				break;

			case 'source':
				$link = rss_post_get_feed_url( $post->ID );
				if ( $link ) {
					echo '<a target="_blank" href="' . esc_url( $link ) . '">' . $link . '</a>';
				}
				break;
		}
	}

	/**
	 * Loads up metaboxes.
	 *
	 * @since 0.1.1
	 * @author JayWood
	 */
	public function add_meta_box() {
		add_meta_box( 'rsslink_mb', esc_html__( 'RSS Item Info', 'wds-rss-post-aggregator' ), array( $this, 'render_metabox' ), $this->post_type() );
		add_meta_box( 'rsspost_title_image_help', esc_html__( 'Podcast Title Image', 'wds-rss-post-aggregator' ), array( $this, 'render_title_image_help_metabox' ), $this->post_type(), 'side', 'default' );
	}

	/**
	 * Renders custom metabox output.
	 *
	 * @since 0.1.1
	 *
	 * @author JayWood
	 */
	public function render_metabox( $object ) {
		wp_nonce_field( 'rsslink_mb_metabox', 'rsslink_mb_nonce' );

		$meta        = get_post_meta( $object->ID, $this->prefix . 'original_url', 1 );
		$audio_url   = get_post_meta( $object->ID, $this->prefix . 'audio_url', 1 );
		$meta_value  = empty( $meta ) ? '' : esc_url( $meta );
		$audio_value = empty( $audio_url ) ? '' : esc_url( $audio_url );

		?>
		<fieldset>
			<p>
				<label for="<?php echo $this->prefix; ?>original_url"><?php esc_html_e( 'Original URL', 'wds-rss-post-aggregator' ); ?></label><br />
				<input name="<?php echo $this->prefix; ?>original_url" id="<?php echo $this->prefix; ?>original_url" value="<?php echo esc_attr( $meta_value ); ?>" class="regular-text" />
			</p>
			<p>
				<label for="<?php echo $this->prefix; ?>audio_url"><?php esc_html_e( 'Podcast Audio URL', 'wds-rss-post-aggregator' ); ?></label><br />
				<input name="<?php echo $this->prefix; ?>audio_url" id="<?php echo $this->prefix; ?>audio_url" value="<?php echo esc_attr( $audio_value ); ?>" class="regular-text" />
				<?php if ( $audio_value ) : ?>
					<br /><a href="<?php echo esc_url( $audio_value ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open audio file', 'wds-rss-post-aggregator' ); ?></a>
				<?php endif; ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render help text for the editable podcast title image.
	 *
	 * @since 0.2.1
	 *
	 * @param \WP_Post $object Current post object.
	 */
	public function render_title_image_help_metabox( $object ) {
		?>
		<p>
			<?php esc_html_e( 'Use the Featured image panel to upload or replace the title image for this imported podcast/blog entry.', 'wds-rss-post-aggregator' ); ?>
		</p>
		<?php if ( has_post_thumbnail( $object ) ) : ?>
			<p><?php esc_html_e( 'A title image is currently set. Replacing the Featured image will update what appears on podcast tiles and listings.', 'wds-rss-post-aggregator' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'No title image is set yet. Add a Featured image before publishing if this entry needs a custom weekly image.', 'wds-rss-post-aggregator' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the post meta.
	 *
	 * @since 0.1.1
	 *
	 * @param $post_id
	 *
	 * @author JayWood
	 * @return int|void
	 */
	public function save_meta( $post_id ) {
		if ( ( ! isset( $_POST['rsslink_mb_nonce'] ) || ! wp_verify_nonce( $_POST['rsslink_mb_nonce'], 'rsslink_mb_metabox' ) )
			|| ! current_user_can( 'edit_post', $post_id )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! isset( $_POST[ $this->prefix . 'original_url' ] )
		) {
			return $post_id;
		}

		$url       = esc_url_raw( wp_unslash( $_POST[ $this->prefix . 'original_url' ] ) );
		$audio_url = isset( $_POST[ $this->prefix . 'audio_url' ] ) ? esc_url_raw( wp_unslash( $_POST[ $this->prefix . 'audio_url' ] ) ) : '';

		update_post_meta( $post_id, $this->prefix . 'original_url', $url );

		if ( $audio_url ) {
			update_post_meta( $post_id, $this->prefix . 'audio_url', $audio_url );
		} else {
			delete_post_meta( $post_id, $this->prefix . 'audio_url' );
		}
	}

	/**
	 * Inserts the feed post items.
	 *
	 * @param array $post_data An array of post data, similar to WP_Post
	 * @param int   $feed_id
	 *
	 * @since 0.1.0
	 *
	 * @author JayWood, Justin Sternberg
	 * @return array|string
	 */
	public function insert( $post_data, $feed_id ) {
		$args = array(
			'post_content'  => wp_kses_post( stripslashes( $post_data['summary'] ) ),
			'post_title'    => esc_html( stripslashes( $post_data['title'] ) ),
			'post_status'   => 'draft',
			'post_type'     => $this->post_type(),
			'post_date'     => date( 'Y-m-d H:i:s', strtotime( $post_data['date'] ) ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( $post_data['date'] ) ),
		);

		$existing_post = $this->post_exists( $post_data['link'] );
		if ( $existing_post ) {
			$args['ID'] = $existing_post->ID;
			$args['post_status'] = $existing_post->post_status;
		}

		$post_id = wp_insert_post( $args );
		if ( $post_id ) {
			$audio_url = isset( $post_data['audio_url'] ) ? esc_url_raw( $post_data['audio_url'] ) : '';

			if ( $audio_url ) {
				update_post_meta( $post_id, $this->prefix . 'audio_url', $audio_url );
			} else {
				$audio_url = get_post_meta( $post_id, $this->prefix . 'audio_url', true );
			}

			$report = array(
				'post_id'           => $post_id,
				'original_url'      => update_post_meta( $post_id, $this->prefix . 'original_url', esc_url_raw( $post_data['link'] ) ),
				'audio_url'         => $audio_url,
				'img_src'           => has_post_thumbnail( $post_id ) ? wp_get_attachment_url( get_post_thumbnail_id( $post_id ) ) : $this->sideload_featured_image( isset( $post_data['image'] ) ? esc_url_raw( $post_data['image'] ) : '', $post_id ),
				'wp_set_post_terms' => wp_set_post_terms( $post_id, array( $feed_id ), $this->tax_slug, true ),
			);
		} else {
			$report = 'failed';
		}

		return $report;
	}

	/**
	 * Check if post exists via Url.
	 *
	 * @param string $url
	 *
	 * @since 0.1.0
	 *
	 * @author JayWood, Justin Sternberg
	 * @return bool|mixed
	 */
	public function post_exists( $url ) {
		$args = array(
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
			'post_type'      => $this->post_type(),
			'meta_key'       => $this->prefix . 'original_url',
			'meta_value'     => esc_url_raw( $url ),
		);
		$posts = get_posts( $args );

		return $posts && is_array( $posts ) ? $posts[0] : false;
	}

	/**
	 * Import image via url.
	 *
	 * @since 0.1.1
	 *
	 * @param string $file_url
	 * @param int $post_id
	 *
	 * @author JayWood, Justin Sternberg
	 * @return string
	 */
	public function sideload_featured_image( $file_url, $post_id ) {
		if ( empty( $file_url ) || empty( $post_id ) ) {
			return false;
		}

		// Set variables for storage, fix file filename for query strings.
		if ( ! preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png|webp)\b/i', $file_url, $matches ) ) {
			return false;
		}

		$file_array = array();
		$file_array['name'] = basename( $matches[0] );

		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $file_url );

		// If error storing temporarily, return the error.
		if ( is_wp_error( $file_array['tmp_name'] ) ) {
			return $file_array['tmp_name'];
		}

		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, $post_id );

		// If error storing permanently, unlink.
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $id;
		}

		$src = wp_get_attachment_url( $id );

		if ( $src ) {
			set_post_thumbnail( $post_id, $id );
		}

		return $src;
	}
}



/**
 * Get imported podcast audio URL.
 *
 * @since 0.2.2
 *
 * @param bool|\WP_Post|int $post Optional post object or ID.
 * @return string Audio URL.
 */
function rss_post_get_audio_url( $post = false ) {
	global $RSS_Post_Aggregator;

	if ( ! $post ) {
		$post = get_post( get_the_ID() );
	} else {
		$post = $post && is_int( $post ) ? get_post( $post ) : $post;
	}

	if ( ! isset( $post->ID ) || empty( $RSS_Post_Aggregator->rsscpt ) ) {
		return '';
	}

	return esc_url( get_post_meta( $post->ID, $RSS_Post_Aggregator->rsscpt->prefix . 'audio_url', true ) );
}

/**
 * Find an imported RSS post by its original feed item URL.
 *
 * @since 0.2.1
 *
 * @param string $url Original feed item URL.
 * @return \WP_Post|false Imported post when available.
 */
function rss_post_get_post_by_original_url( $url ) {
	global $RSS_Post_Aggregator;

	if ( empty( $url ) || empty( $RSS_Post_Aggregator->rsscpt ) ) {
		return false;
	}

	return $RSS_Post_Aggregator->rsscpt->post_exists( $url );
}

/**
 * Get RSS feed object.
 *
 * @param bool|WP_Post|int $post
 *
 * @since 0.1.0
 *
 * @author JayWood, Justin Sternberg
 * @return string
 */
function rss_post_get_feed_object( $post = false ) {
	global $RSS_Post_Aggregator;

	if ( ! $post ) {
		$post = get_post( get_the_ID() );
	} else {
		$post = $post && is_int( $post ) ? get_post( $post ) : $post;
	}

	static $source_links = array();

	if ( ! isset( $post->ID ) ) {
		return '';
	}

	if ( array_key_exists( $post->ID, $source_links ) ) {
		return $source_links[ $post->ID ];
	}

	$links = get_the_terms( $post->ID, $RSS_Post_Aggregator->tax_slug );
	$source_links[ $post->ID ] = ( $links && is_array( $links ) )
		? array_shift( $links )
		: '';

	return $source_links[ $post->ID ];
}

/**
 * Get RSS feed name.
 *
 * @param bool|WP_Post|int $post
 *
 * @since 0.1.0
 *
 * @author JayWood, Justin Sternberg
 * @return bool|string
 */
function rss_post_get_feed_url( $post = false ) {
	$feed = rss_post_get_feed_object( $post );

	if ( $feed && isset( $feed->name ) ) {
		return $feed->name;
	}

	return false;
}

/**
 * Get RSS feed source.
 *
 * @param bool|WP_Post|int $post
 *
 * @since 0.1.0
 *
 * @author JayWood, Justin Sternberg
 * @return bool|string
 */
function rss_post_get_feed_source( $post = false ) {

	$feed = rss_post_get_feed_object( $post );
	if ( $feed ) {
		if ( isset( $feed->description ) && $feed->description ) {
			return esc_html( $feed->description );
		}
	}

	$url = rss_post_get_feed_url( $post );
	if ( $url ) {
		$parts = parse_url( $url );
		return isset( $parts['host'] ) ? $parts['host'] : '';
	}

	return false;
}
