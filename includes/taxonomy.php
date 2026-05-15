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
		wp_nonce_field( 'rss_feed_link_settings', 'rss_feed_link_settings_nonce' );
		?>
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

		if ( ! in_array( $post_type, $this->get_importable_post_types(), true ) ) {
			$post_type = $this->default_post_type;
		}

		update_term_meta( $term_id, self::META_AUTO_IMPORT, $auto_import );
		update_term_meta( $term_id, self::META_TARGET_POST_TYPE, $post_type );
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
