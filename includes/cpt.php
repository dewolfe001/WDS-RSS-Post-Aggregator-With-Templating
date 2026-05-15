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

		$meta          = get_post_meta( $object->ID, $this->prefix . 'original_url', 1 );
		$audio_url     = get_post_meta( $object->ID, $this->prefix . 'audio_url', 1 );
		$rss_item_meta = get_post_meta( $object->ID, $this->prefix . 'rss_item_fields', true );
		$meta_value    = empty( $meta ) ? '' : esc_url( $meta );
		$audio_value   = empty( $audio_url ) ? '' : esc_url( $audio_url );

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
		<?php if ( ! empty( $rss_item_meta ) && is_array( $rss_item_meta ) ) : ?>
			<hr />
			<h4><?php esc_html_e( 'Retained RSS Item Fields', 'wds-rss-post-aggregator' ); ?></h4>
			<p><?php esc_html_e( 'These custom fields were captured from the original RSS item during import.', 'wds-rss-post-aggregator' ); ?></p>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $rss_item_meta as $field_key => $field_value ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $field_key ); ?></th>
							<td><code><?php echo esc_html( $this->format_meta_field_for_display( $field_value ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
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
	public function insert( $post_data, $feed_id, $post_type = '' ) {
		$post_type     = $this->get_import_post_type( $post_type );
		$existing_post = $this->imported_post_exists( $post_data, $post_type );

		if ( $existing_post ) {
			return array(
				'post_id' => $existing_post->ID,
				'status'  => 'skipped_existing',
			);
		}

		$post_timestamp = $this->get_import_timestamp( $post_data );
		$settings       = new RSS_Post_Aggregator_Settings();

		$args = array(
			'post_content'  => wp_kses_post( stripslashes( $settings->render_post_content( $post_data ) ) ),
			'post_title'    => esc_html( RSS_Post_Aggregator::decode_entities( stripslashes( $post_data['title'] ) ) ),
			'post_status'   => 'draft',
			'post_type'     => $post_type,
			'post_date'     => date( 'Y-m-d H:i:s', $post_timestamp ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $post_timestamp ),
		);

		$post_id = wp_insert_post( $args );
		if ( $post_id ) {
			$audio_url     = isset( $post_data['audio_url'] ) ? esc_url_raw( $post_data['audio_url'] ) : '';
			$rss_item_meta = isset( $post_data['rss_item_meta'] ) && is_array( $post_data['rss_item_meta'] ) ? $post_data['rss_item_meta'] : array();
			$import_uid    = $this->get_import_uid( $post_data );

			if ( $audio_url ) {
				update_post_meta( $post_id, $this->prefix . 'audio_url', $audio_url );
			} else {
				$audio_url = get_post_meta( $post_id, $this->prefix . 'audio_url', true );
			}

			$this->save_rss_item_meta( $post_id, $rss_item_meta );

			$report = array(
				'post_id'           => $post_id,
				'original_url'      => update_post_meta( $post_id, $this->prefix . 'original_url', esc_url_raw( $post_data['link'] ) ),
				'import_uid'        => $import_uid ? update_post_meta( $post_id, $this->prefix . 'import_uid', $import_uid ) : false,
				'audio_url'         => $audio_url,
				'import_post_type'  => update_post_meta( $post_id, $this->prefix . 'import_post_type', $post_type ),
				'rss_item_meta'     => $rss_item_meta,
				'img_src'           => has_post_thumbnail( $post_id ) ? wp_get_attachment_url( get_post_thumbnail_id( $post_id ) ) : $this->sideload_featured_image( isset( $post_data['image'] ) ? esc_url_raw( $post_data['image'] ) : '', $post_id ),
				'wp_set_post_terms' => taxonomy_exists( $this->tax_slug ) ? wp_set_post_terms( $post_id, array( $feed_id ), $this->tax_slug, true ) : false,
			);
		} else {
			$report = 'failed';
		}

		return $report;
	}


	/**
	 * Save retained RSS item fields as post meta.
	 *
	 * @since 0.2.3
	 *
	 * @param int   $post_id       Imported post ID.
	 * @param array $rss_item_meta Retained RSS item meta.
	 */
	public function save_rss_item_meta( $post_id, $rss_item_meta ) {
		if ( empty( $post_id ) || empty( $rss_item_meta ) || ! is_array( $rss_item_meta ) ) {
			return;
		}

		$meta = $this->sanitize_rss_item_meta( $rss_item_meta );

		update_post_meta( $post_id, $this->prefix . 'rss_item_fields', $meta );

		foreach ( $meta as $field_key => $field_value ) {
			update_post_meta( $post_id, $this->prefix . 'rss_item_' . sanitize_key( $field_key ), $field_value );
		}
	}

	/**
	 * Sanitize retained RSS item meta before storing it.
	 *
	 * @since 0.2.3
	 *
	 * @param array $rss_item_meta Retained RSS item meta.
	 * @return array Sanitized RSS item meta.
	 */
	protected function sanitize_rss_item_meta( $rss_item_meta ) {
		$sanitized = array();

		foreach ( $rss_item_meta as $field_key => $field_value ) {
			$field_key = sanitize_key( $field_key );

			if ( is_array( $field_value ) ) {
				$sanitized[ $field_key ] = $this->sanitize_rss_item_meta( $field_value );
				continue;
			}

			$sanitized[ $field_key ] = is_scalar( $field_value ) ? wp_kses_post( RSS_Post_Aggregator::decode_entities( $field_value ) ) : '';
		}

		return $sanitized;
	}

	/**
	 * Format a retained RSS item meta field for display in the metabox.
	 *
	 * @since 0.2.3
	 *
	 * @param mixed $field_value Retained field value.
	 * @return string Display value.
	 */
	protected function format_meta_field_for_display( $field_value ) {
		if ( is_array( $field_value ) ) {
			return wp_json_encode( $field_value );
		}

		return (string) $field_value;
	}

	/**
	 * Get the timestamp to use for imported post dates.
	 *
	 * @since 0.2.3
	 *
	 * @param array $post_data Incoming RSS item data.
	 * @return int Unix timestamp.
	 */
	protected function get_import_timestamp( $post_data ) {
		$raw_date = '';

		if ( ! empty( $post_data['rss_item_meta']['pub_date'] ) ) {
			$raw_date = $post_data['rss_item_meta']['pub_date'];
		} elseif ( ! empty( $post_data['date'] ) ) {
			$raw_date = $post_data['date'];
		}

		$timestamp = $raw_date ? strtotime( $raw_date ) : false;

		return $timestamp ? $timestamp : current_time( 'timestamp' );
	}


	/**
	 * Find an imported post using stable RSS item identifiers.
	 *
	 * Some feeds reuse the same item link for every entry (for example, a
	 * podcast homepage). In those feeds, checking only the source URL causes one
	 * imported entry to make all remaining items look like duplicates. Prefer a
	 * saved RSS GUID or enclosure/audio URL when available, and only fall back to
	 * the legacy original URL check when no stronger per-item identity exists.
	 *
	 * @since 0.2.8
	 *
	 * @param array  $post_data Incoming RSS item data.
	 * @param string $post_type Import destination post type.
	 * @return \WP_Post|false Imported post when available.
	 */
	protected function imported_post_exists( $post_data, $post_type = 'any' ) {
		$identifiers        = $this->get_import_identifiers( $post_data );
		$strong_identifiers = $this->get_import_identifiers( $post_data, false );

		if ( ! empty( $identifiers ) ) {
			$posts = get_posts( array(
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'post_type'      => $post_type ? $post_type : 'any',
				'meta_query'     => array(
					array(
						'key'     => $this->prefix . 'import_uid',
						'value'   => $identifiers,
						'compare' => 'IN',
					),
				),
			) );

			if ( $posts && is_array( $posts ) ) {
				return $posts[0];
			}
		}

		$legacy_post = ! empty( $post_data['link'] ) ? $this->post_exists( $post_data['link'], $post_type ) : false;

		if ( ! $legacy_post ) {
			return false;
		}

		if ( empty( $strong_identifiers ) ) {
			return $legacy_post;
		}

		$legacy_identifiers = $this->get_post_import_identifiers( $legacy_post->ID );

		if ( empty( $legacy_identifiers ) ) {
			return false;
		}

		return array_intersect( $strong_identifiers, $legacy_identifiers ) ? $legacy_post : false;
	}

	/**
	 * Get the canonical import UID for a feed item.
	 *
	 * @since 0.2.8
	 *
	 * @param array $post_data Incoming RSS item data.
	 * @return string Import UID.
	 */
	protected function get_import_uid( $post_data ) {
		$identifiers = $this->get_import_identifiers( $post_data );

		return ! empty( $identifiers ) ? reset( $identifiers ) : '';
	}

	/**
	 * Get ordered import identifiers for a feed item.
	 *
	 * @since 0.2.8
	 *
	 * @param array $post_data Incoming RSS item data.
	 * @param bool  $include_link Whether to include the legacy item link fallback.
	 * @return array Import identifiers.
	 */
	protected function get_import_identifiers( $post_data, $include_link = true ) {
		$identifiers = array();
		$meta        = isset( $post_data['rss_item_meta'] ) && is_array( $post_data['rss_item_meta'] ) ? $post_data['rss_item_meta'] : array();

		if ( ! empty( $meta['guid']['value'] ) ) {
			$identifiers[] = $meta['guid']['value'];
		}

		if ( ! empty( $post_data['audio_url'] ) ) {
			$identifiers[] = $post_data['audio_url'];
		}

		if ( ! empty( $meta['enclosure']['url'] ) ) {
			$identifiers[] = $meta['enclosure']['url'];
		}

		if ( $include_link && ! empty( $post_data['link'] ) ) {
			$identifiers[] = $post_data['link'];
		}

		return $this->normalize_import_identifiers( $identifiers );
	}

	/**
	 * Get import identifiers saved on an existing post.
	 *
	 * @since 0.2.8
	 *
	 * @param int $post_id Post ID.
	 * @return array Import identifiers.
	 */
	protected function get_post_import_identifiers( $post_id ) {
		$identifiers = array(
			get_post_meta( $post_id, $this->prefix . 'import_uid', true ),
			get_post_meta( $post_id, $this->prefix . 'audio_url', true ),
		);
		$guid        = get_post_meta( $post_id, $this->prefix . 'rss_item_guid', true );
		$enclosure   = get_post_meta( $post_id, $this->prefix . 'rss_item_enclosure', true );

		if ( is_array( $guid ) && ! empty( $guid['value'] ) ) {
			$identifiers[] = $guid['value'];
		} elseif ( is_string( $guid ) ) {
			$identifiers[] = $guid;
		}

		if ( is_array( $enclosure ) && ! empty( $enclosure['url'] ) ) {
			$identifiers[] = $enclosure['url'];
		}

		return $this->normalize_import_identifiers( $identifiers );
	}

	/**
	 * Normalize import identifiers before comparing or storing them.
	 *
	 * @since 0.2.8
	 *
	 * @param array $identifiers Raw identifiers.
	 * @return array Normalized identifiers.
	 */
	protected function normalize_import_identifiers( $identifiers ) {
		$normalized = array();

		foreach ( $identifiers as $identifier ) {
			if ( ! is_scalar( $identifier ) ) {
				continue;
			}

			$identifier = trim( (string) $identifier );

			if ( '' === $identifier ) {
				continue;
			}

			$normalized[] = 0 === stripos( $identifier, 'http' ) ? esc_url_raw( $identifier ) : sanitize_text_field( $identifier );
		}

		return array_values( array_unique( array_filter( $normalized ) ) );
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
	public function post_exists( $url, $post_type = 'any' ) {
		$args = array(
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'post_type'      => $post_type ? $post_type : 'any',
			'meta_key'       => $this->prefix . 'original_url',
			'meta_value'     => esc_url_raw( $url ),
		);
		$posts = get_posts( $args );

		return $posts && is_array( $posts ) ? $posts[0] : false;
	}

	/**
	 * Validate an import destination post type.
	 *
	 * @since 0.2.4
	 *
	 * @param string $post_type Requested post type.
	 * @return string Valid post type.
	 */
	protected function get_import_post_type( $post_type ) {
		$post_type = $post_type ? sanitize_key( $post_type ) : $this->post_type();

		return post_type_exists( $post_type ) ? $post_type : $this->post_type();
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
