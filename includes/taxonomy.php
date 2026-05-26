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
	 * Term meta key for automatic feed imports.
	 *
	 * @since 0.2.4
	 */
	const META_AUTO_IMPORT = '_rsspost_auto_import';

	/**
	 * Term meta key for import target post type.
	 *
	 * @since 0.2.4
	 */
	const META_TARGET_POST_TYPE = '_rsspost_target_post_type';

	/**
	 * Term meta key for the canonical feed URL.
	 *
	 * @since 0.2.7
	 */
	const META_FEED_URL = '_rsspost_feed_url';
	const META_DEFAULT_TAXONOMY = '_rsspost_default_taxonomy';
	const META_DEFAULT_TERM_ID = '_rsspost_default_term_id';
	const META_DEFAULT_POST_STATUS = '_rsspost_default_post_status';

	/**
	 * Default import target post type.
	 *
	 * @since 0.2.4
	 *
	 * @var string
	 */
	protected $default_post_type = '';

	/**
	 * Register Custom Post Types. See documentation in Taxonomy_Core, and in wp-includes/post.php
	 *
	 * @since 0.1.1
	 *
	 * @param string $tax_slug
	 * @param CPT_Core $cpt
	 */
	public function __construct( $tax_slug, $cpt ) {
		$this->default_post_type = $cpt->post_type();

		// Register this cpt
		parent::__construct(
			array( __( 'RSS Feed Link', 'wds-rss-post-aggregator' ), __( 'RSS Feed Links', 'wds-rss-post-aggregator' ), $tax_slug ),
			array(
				'show_admin_column' => false,
			),
			array( $this->default_post_type )
		);
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_taxonomy_for_feed_post_types' ), 20 );
		add_action( $this->taxonomy() . '_add_form_fields', array( $this, 'add_form_fields' ) );
		add_action( $this->taxonomy() . '_edit_form_fields', array( $this, 'edit_form_fields' ) );
		add_action( 'created_' . $this->taxonomy(), array( $this, 'save_term_fields' ) );
		add_action( 'edited_' . $this->taxonomy(), array( $this, 'save_term_fields' ) );
		add_filter( 'manage_edit-' . $this->taxonomy() . '_columns', array( $this, 'add_feed_url_column' ) );
		add_filter( 'manage_' . $this->taxonomy() . '_custom_column', array( $this, 'render_feed_url_column' ), 10, 3 );
	}

	/**
	 * Register the feed-link taxonomy for all selected destination post types.
	 *
	 * @since 0.2.4
	 */
	public function register_taxonomy_for_feed_post_types() {
		foreach ( $this->get_importable_post_types() as $post_type ) {
			register_taxonomy_for_object_type( $this->taxonomy(), $post_type );
		}
	}

	/**
	 * Render fields on the add-feed form.
	 *
	 * @since 0.2.4
	 */
	public function add_form_fields() {
		wp_nonce_field( 'rss_feed_link_settings', 'rss_feed_link_settings_nonce' );
		?>
		<div class="form-field term-rss-feed-url-wrap">
			<label for="rss-feed-url"><?php esc_html_e( 'RSS Feed address', 'wds-rss-post-aggregator' ); ?></label>
			<input type="url" id="rss-feed-url" name="rss_feed_url" value="" placeholder="https://example.com/feed.xml" />
			<p><?php esc_html_e( 'Enter the full RSS feed URL for this feed link. The name can stay descriptive; imports use this address.', 'wds-rss-post-aggregator' ); ?></p>
		</div>
		<div class="form-field term-rss-auto-import-wrap">
			<label for="rss-auto-import"><?php esc_html_e( 'Automatic import', 'wds-rss-post-aggregator' ); ?></label>
			<label>
				<input type="checkbox" id="rss-auto-import" name="rss_auto_import" value="1" checked="checked" />
				<?php esc_html_e( 'Fetch this feed hourly and import new items only.', 'wds-rss-post-aggregator' ); ?>
			</label>
		</div>
		<div class="form-field term-rss-target-post-type-wrap">
			<label for="rss-target-post-type"><?php esc_html_e( 'Import as post type', 'wds-rss-post-aggregator' ); ?></label>
			<?php $this->render_post_type_select( $this->default_post_type ); ?>
			<p><?php esc_html_e( 'Choose the WordPress post type created by scheduled imports for this feed.', 'wds-rss-post-aggregator' ); ?></p>
			<?php $this->render_template_settings_link(); ?>
		</div>
		<?php $this->render_default_assignment_fields( $this->default_post_type ); ?>
		<?php $this->render_default_post_status_field( 'draft' ); ?>
		<?php
	}

	/**
	 * Render fields on the edit-feed form.
	 *
	 * @since 0.2.4
	 *
	 * @param \WP_Term $term Current term.
	 */
	public function edit_form_fields( $term ) {
		$auto_import     = $this->is_auto_import_enabled( $term->term_id );
		$target_post_type = $this->get_target_post_type( $term->term_id );
		$feed_url         = $this->get_feed_url( $term );
		$default_taxonomy = $this->get_default_taxonomy( $term->term_id );
		$default_term_id  = $this->get_default_term_id( $term->term_id, $target_post_type, $default_taxonomy );
		$default_status   = $this->get_default_post_status( $term->term_id );
		wp_nonce_field( 'rss_feed_link_settings', 'rss_feed_link_settings_nonce' );
		?>
		<tr class="form-field term-rss-feed-url-wrap">
			<th scope="row"><label for="rss-feed-url"><?php esc_html_e( 'RSS Feed address', 'wds-rss-post-aggregator' ); ?></label></th>
			<td>
				<input type="url" id="rss-feed-url" name="rss_feed_url" value="<?php echo esc_attr( $feed_url ); ?>" class="regular-text" placeholder="https://example.com/feed.xml" />
				<p class="description"><?php esc_html_e( 'Enter the full RSS feed URL for this feed link. The name can stay descriptive; imports use this address.', 'wds-rss-post-aggregator' ); ?></p>
			</td>
		</tr>
		<tr class="form-field term-rss-auto-import-wrap">
			<th scope="row"><label for="rss-auto-import"><?php esc_html_e( 'Automatic import', 'wds-rss-post-aggregator' ); ?></label></th>
			<td>
				<label>
					<input type="checkbox" id="rss-auto-import" name="rss_auto_import" value="1" <?php checked( $auto_import ); ?> />
					<?php esc_html_e( 'Fetch this feed hourly and import new items only.', 'wds-rss-post-aggregator' ); ?>
				</label>
			</td>
		</tr>
		<tr class="form-field term-rss-target-post-type-wrap">
			<th scope="row"><label for="rss-target-post-type"><?php esc_html_e( 'Import as post type', 'wds-rss-post-aggregator' ); ?></label></th>
			<td>
				<?php $this->render_post_type_select( $target_post_type ); ?>
				<p class="description"><?php esc_html_e( 'Choose the WordPress post type created by scheduled imports for this feed.', 'wds-rss-post-aggregator' ); ?></p>
				<?php $this->render_template_settings_link( 'description' ); ?>
			</td>
		</tr>
		<tr class="form-field term-rss-manual-import-wrap">
			<th scope="row"><?php esc_html_e( 'Manual import', 'wds-rss-post-aggregator' ); ?></th>
			<td>
				<?php $this->render_manual_import_link( $term->term_id ); ?>
				<p class="description"><?php esc_html_e( 'Fetch this feed immediately and import any items that are not already present.', 'wds-rss-post-aggregator' ); ?></p>
			</td>
		</tr>
		<?php $this->render_default_assignment_fields( $target_post_type, $default_taxonomy, $default_term_id, true ); ?>
		<?php $this->render_default_post_status_field( $default_status, true ); ?>
		<?php
	}


	/**
	 * Render a link to the import template settings page.
	 *
	 * @since 0.2.5
	 *
	 * @param string $class Optional paragraph class.
	 */
	protected function render_template_settings_link( $class = '' ) {
		$settings_url = admin_url( 'edit.php?post_type=rss-posts&page=' . RSS_Post_Aggregator_Settings::PAGE_SLUG );
		$class_attr   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
		?>
		<p<?php echo $class_attr; ?>>
			<?php esc_html_e( 'Need to change imported content?', 'wds-rss-post-aggregator' ); ?>
			<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open template settings and token documentation.', 'wds-rss-post-aggregator' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Render a per-feed manual import link.
	 *
	 * @since 0.2.6
	 *
	 * @param int $term_id Feed term ID.
	 */
	protected function render_manual_import_link( $term_id ) {
		$capability = apply_filters( 'rss_post_aggregator_manual_import_capability', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'wds_rss_post_aggregator_import_feed',
					'feed_id' => absint( $term_id ),
				),
				admin_url( 'admin-post.php' )
			),
			'wds_rss_post_aggregator_import_feed_' . absint( $term_id )
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Import This Feed Now', 'wds-rss-post-aggregator' ); ?></a>
		<?php
	}

	/**
	 * Save feed import settings.
	 *
	 * @since 0.2.4
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_fields( $term_id ) {
		if ( ! isset( $_POST['rss_feed_link_settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['rss_feed_link_settings_nonce'] ), 'rss_feed_link_settings' ) ) {
			return;
		}

		$auto_import = isset( $_POST['rss_auto_import'] ) ? '1' : '0';
		$post_type   = isset( $_POST['rss_target_post_type'] ) ? sanitize_key( wp_unslash( $_POST['rss_target_post_type'] ) ) : $this->default_post_type;
		$feed_url    = isset( $_POST['rss_feed_url'] ) ? $this->normalize_feed_url( wp_unslash( $_POST['rss_feed_url'] ) ) : '';
		$taxonomy    = isset( $_POST['rss_default_taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['rss_default_taxonomy'] ) ) : '';
		$default_term_id = isset( $_POST['rss_default_term_id'] ) ? absint( $_POST['rss_default_term_id'] ) : 0;
		$post_status = isset( $_POST['rss_default_post_status'] ) ? sanitize_key( wp_unslash( $_POST['rss_default_post_status'] ) ) : 'draft';

		if ( ! in_array( $post_type, $this->get_importable_post_types(), true ) ) {
			$post_type = $this->default_post_type;
		}

		update_term_meta( $term_id, self::META_AUTO_IMPORT, $auto_import );
		update_term_meta( $term_id, self::META_TARGET_POST_TYPE, $post_type );
		update_term_meta( $term_id, self::META_DEFAULT_POST_STATUS, $this->sanitize_default_post_status( $post_status ) );

		$taxonomies = $this->get_assignable_taxonomies_for_post_type( $post_type );
		if ( in_array( $taxonomy, array_keys( $taxonomies ), true ) && $default_term_id && term_exists( $default_term_id, $taxonomy ) ) {
			update_term_meta( $term_id, self::META_DEFAULT_TAXONOMY, $taxonomy );
			update_term_meta( $term_id, self::META_DEFAULT_TERM_ID, $default_term_id );
		} else {
			delete_term_meta( $term_id, self::META_DEFAULT_TAXONOMY );
			delete_term_meta( $term_id, self::META_DEFAULT_TERM_ID );
		}

		if ( $feed_url ) {
			update_term_meta( $term_id, self::META_FEED_URL, esc_url_raw( $feed_url ) );
		} else {
			delete_term_meta( $term_id, self::META_FEED_URL );
		}
	}

	protected function render_default_assignment_fields( $post_type, $selected_taxonomy = '', $selected_term_id = 0, $table = false ) {
		$taxonomies = $this->get_assignable_taxonomies_for_post_type( $post_type );
		if ( ! $selected_taxonomy && ! empty( $taxonomies ) ) {
			$selected_taxonomy = key( $taxonomies );
		}
		$terms = $selected_taxonomy ? get_terms( array( 'taxonomy' => $selected_taxonomy, 'hide_empty' => false ) ) : array();
		$terms = is_wp_error( $terms ) ? array() : $terms;
		if ( $table ) {
			echo '<tr class="form-field"><th scope="row"><label for="rss-default-term-id">' . esc_html__( 'Default category/tag', 'wds-rss-post-aggregator' ) . '</label></th><td>';
		} else {
			echo '<div class="form-field"><label for="rss-default-term-id">' . esc_html__( 'Default category/tag', 'wds-rss-post-aggregator' ) . '</label>';
		}
		echo '<select id="rss-default-taxonomy" name="rss_default_taxonomy">';
		echo '<option value="">' . esc_html__( 'None', 'wds-rss-post-aggregator' ) . '</option>';
		foreach ( $taxonomies as $taxonomy => $tax_obj ) {
			echo '<option value="' . esc_attr( $taxonomy ) . '" ' . selected( $selected_taxonomy, $taxonomy, false ) . '>' . esc_html( $tax_obj->labels->singular_name ) . '</option>';
		}
		echo '</select> ';
		echo '<select id="rss-default-term-id" name="rss_default_term_id">';
		echo '<option value="0">' . esc_html__( 'None', 'wds-rss-post-aggregator' ) . '</option>';
		foreach ( $terms as $term ) {
			echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $selected_term_id, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Imported posts will automatically receive this taxonomy term.', 'wds-rss-post-aggregator' ) . '</p>';
		echo $table ? '</td></tr>' : '</div>';
	}

	protected function render_default_post_status_field( $selected = 'draft', $table = false ) {
		$statuses = array( 'draft' => __( 'Draft', 'wds-rss-post-aggregator' ), 'future' => __( 'Scheduled', 'wds-rss-post-aggregator' ), 'publish' => __( 'Publish', 'wds-rss-post-aggregator' ) );
		if ( $table ) {
			echo '<tr class="form-field"><th scope="row"><label for="rss-default-post-status">' . esc_html__( 'Default post status', 'wds-rss-post-aggregator' ) . '</label></th><td>';
		} else {
			echo '<div class="form-field"><label for="rss-default-post-status">' . esc_html__( 'Default post status', 'wds-rss-post-aggregator' ) . '</label>';
		}
		echo '<select id="rss-default-post-status" name="rss_default_post_status">';
		foreach ( $statuses as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $selected, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Choose how newly imported posts are saved.', 'wds-rss-post-aggregator' ) . '</p>';
		echo $table ? '</td></tr>' : '</div>';
	}

	public function get_assignable_taxonomies_for_post_type( $post_type ) {
		$all = get_object_taxonomies( $post_type, 'objects' );
		return array_filter( $all, function( $tax ) { return ! empty( $tax->show_ui ); } );
	}

	public function get_default_taxonomy( $term_id ) { return sanitize_key( get_term_meta( $term_id, self::META_DEFAULT_TAXONOMY, true ) ); }
	public function get_default_post_status( $term_id ) { return $this->sanitize_default_post_status( get_term_meta( $term_id, self::META_DEFAULT_POST_STATUS, true ) ); }
	public function get_default_term_id( $term_id, $post_type = '', $taxonomy = '' ) {
		$meta_term_id = absint( get_term_meta( $term_id, self::META_DEFAULT_TERM_ID, true ) );
		$taxonomy = $taxonomy ? $taxonomy : $this->get_default_taxonomy( $term_id );
		if ( ! $meta_term_id || ! $taxonomy || ! term_exists( $meta_term_id, $taxonomy ) ) { return 0; }
		if ( $post_type && ! in_array( $taxonomy, array_keys( $this->get_assignable_taxonomies_for_post_type( $post_type ) ), true ) ) { return 0; }
		return $meta_term_id;
	}
	protected function sanitize_default_post_status( $status ) {
		$status = sanitize_key( (string) $status );
		return in_array( $status, array( 'draft', 'future', 'publish' ), true ) ? $status : 'draft';
	}

	/**
	 * Get the configured RSS URL for a feed term.
	 *
	 * Falls back to URL term names for legacy feeds that were created before
	 * the dedicated RSS Feed address term meta existed.
	 *
	 * @since 0.2.10
	 *
	 * @param \WP_Term $term Feed term.
	 * @return string Feed URL, or an empty string when no valid URL is saved.
	 */
	public function get_feed_url( $term ) {
		$feed_url = get_term_meta( $term->term_id, self::META_FEED_URL, true );
		$feed_url = $feed_url ? $feed_url : $term->name;

		return $this->normalize_feed_url( $feed_url );
	}

	/**
	 * Normalize saved feed input into a usable URL.
	 *
	 * @since 0.2.10
	 *
	 * @param string $feed_url Feed URL.
	 * @return string Valid feed URL, or an empty string when it cannot be normalized.
	 */
	public function normalize_feed_url( $feed_url ) {
		$feed_url = esc_url_raw( trim( (string) $feed_url ) );
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
	 * Add the RSS Feed address column to the feed-link term list.
	 *
	 * @since 0.2.10
	 *
	 * @param array $columns Taxonomy list table columns.
	 * @return array Filtered columns.
	 */
	public function add_feed_url_column( $columns ) {
		$columns['rss_feed_url'] = __( 'RSS Feed address', 'wds-rss-post-aggregator' );

		return $columns;
	}

	/**
	 * Render the RSS Feed address column.
	 *
	 * @since 0.2.10
	 *
	 * @param string $output      Existing column output.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string Column output.
	 */
	public function render_feed_url_column( $output, $column_name, $term_id ) {
		if ( 'rss_feed_url' !== $column_name ) {
			return $output;
		}

		$term = get_term( $term_id, $this->taxonomy() );
		$url  = ( $term && ! is_wp_error( $term ) ) ? $this->get_feed_url( $term ) : '';

		if ( ! $url ) {
			return '&mdash;';
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $url ) );
	}

	/**
	 * Determine if a feed should be automatically imported.
	 *
	 * @since 0.2.4
	 *
	 * @param int $term_id Term ID.
	 * @return bool Whether automatic imports are enabled.
	 */
	public function is_auto_import_enabled( $term_id ) {
		$value = get_term_meta( $term_id, self::META_AUTO_IMPORT, true );

		return '' === $value || (bool) $value;
	}

	/**
	 * Get the destination post type for a feed.
	 *
	 * @since 0.2.4
	 *
	 * @param int $term_id Term ID.
	 * @return string Target post type.
	 */
	public function get_target_post_type( $term_id ) {
		$post_type = sanitize_key( get_term_meta( $term_id, self::META_TARGET_POST_TYPE, true ) );

		if ( ! $post_type || ! in_array( $post_type, $this->get_importable_post_types(), true ) ) {
			return $this->default_post_type;
		}

		return $post_type;
	}

	/**
	 * Get selected destination post types.
	 *
	 * @since 0.2.4
	 *
	 * @return string[] Post type names.
	 */
	protected function get_selected_target_post_types() {
		$terms      = get_terms( array( 'taxonomy' => $this->taxonomy(), 'hide_empty' => false ) );
		$post_types = array( $this->default_post_type );

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$post_types[] = $this->get_target_post_type( $term->term_id );
			}
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Render the import target post type select.
	 *
	 * @since 0.2.4
	 *
	 * @param string $selected Selected post type.
	 */
	protected function render_post_type_select( $selected ) {
		$post_types = $this->get_importable_post_types( 'objects' );
		?>
		<select id="rss-target-post-type" name="rss_target_post_type">
			<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
				<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $selected, $post_type ); ?>><?php echo esc_html( $post_type_object->labels->singular_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Get public post types that can receive imports.
	 *
	 * @since 0.2.4
	 *
	 * @param string $output Output format.
	 * @return array Post type names or objects.
	 */
	public function get_importable_post_types( $output = 'names' ) {
		$post_types = get_post_types( array( 'public' => true ), $output );

		if ( 'names' === $output ) {
			$post_types[ $this->default_post_type ] = $this->default_post_type;
			unset( $post_types['attachment'] );
		} else {
			unset( $post_types['attachment'] );
		}

		/**
		 * Filters the post types available as RSS import destinations.
		 *
		 * @since 0.2.4
		 *
		 * @param array                       $post_types Importable post types.
		 * @param string                      $output     Output format.
		 * @param RSS_Post_Aggregator_Taxonomy $taxonomy   Taxonomy instance.
		 */
		return apply_filters( 'rss_post_aggregator_importable_post_types', $post_types, $output, $this );
	}

}
