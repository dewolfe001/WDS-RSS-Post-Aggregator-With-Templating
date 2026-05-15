<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;

/**
 * Plugin settings and import template helpers.
 */
class RSS_Post_Aggregator_Settings {

	/**
	 * Option key for plugin settings.
	 *
	 * @since 0.2.5
	 */
	const OPTION_NAME = 'wds_rss_post_aggregator_settings';

	/**
	 * Settings page slug.
	 *
	 * @since 0.2.5
	 */
	const PAGE_SLUG = 'wds-rss-post-aggregator-settings';

	/**
	 * Default post content template.
	 *
	 * @since 0.2.5
	 */
	const DEFAULT_CONTENT_TEMPLATE = '{summary}';

	/**
	 * Initiate hooks.
	 *
	 * @since 0.2.5
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page beneath the RSS Posts menu.
	 *
	 * @since 0.2.5
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=rss-posts',
			esc_html__( 'RSS Post Aggregator Settings', 'wds-rss-post-aggregator' ),
			esc_html__( 'Settings', 'wds-rss-post-aggregator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 0.2.5
	 */
	public function register_settings() {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		add_settings_section(
			'rss_post_aggregator_template_section',
			esc_html__( 'Import Template', 'wds-rss-post-aggregator' ),
			array( $this, 'render_template_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'content_template',
			esc_html__( 'Post content template', 'wds-rss-post-aggregator' ),
			array( $this, 'render_content_template_field' ),
			self::PAGE_SLUG,
			'rss_post_aggregator_template_section'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @since 0.2.5
	 *
	 * @param array $settings Incoming settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $settings ) {
		$defaults = $this->get_default_settings();
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'content_template' => isset( $settings['content_template'] ) && '' !== trim( $settings['content_template'] )
				? wp_kses_post( wp_unslash( $settings['content_template'] ) )
				: $defaults['content_template'],
		);
	}

	/**
	 * Get default settings.
	 *
	 * @since 0.2.5
	 *
	 * @return array Default settings.
	 */
	public function get_default_settings() {
		return array(
			'content_template' => self::DEFAULT_CONTENT_TEMPLATE,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @since 0.2.5
	 *
	 * @return array Settings.
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), $this->get_default_settings() );
	}

	/**
	 * Get the saved post content template.
	 *
	 * @since 0.2.5
	 *
	 * @return string Content template.
	 */
	public function get_content_template() {
		$settings = $this->get_settings();

		return (string) $settings['content_template'];
	}

	/**
	 * Render the settings page.
	 *
	 * @since 0.2.5
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RSS Post Aggregator Settings', 'wds-rss-post-aggregator' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual Import', 'wds-rss-post-aggregator' ); ?></h2>
			<p><?php esc_html_e( 'Run the same importer used by WP-Cron immediately for every feed with automatic import enabled.', 'wds-rss-post-aggregator' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wds_rss_post_aggregator_import_all" />
				<?php wp_nonce_field( 'wds_rss_post_aggregator_import_all' ); ?>
				<?php submit_button( __( 'Import Automatic Feeds Now', 'wds-rss-post-aggregator' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render import template section information.
	 *
	 * @since 0.2.5
	 */
	public function render_template_section() {
		?>
		<p><?php esc_html_e( 'Control the content that is created when RSS feed items are imported. Tokens are replaced for each RSS item before the post is saved.', 'wds-rss-post-aggregator' ); ?></p>
		<p><?php esc_html_e( 'Example:', 'wds-rss-post-aggregator' ); ?> <code>&lt;p&gt;{summary}&lt;/p&gt;&lt;p&gt;&lt;a href=&quot;{source_url}&quot;&gt;Read the original post&lt;/a&gt;&lt;/p&gt;</code></p>
		<h3><?php esc_html_e( 'Available template tokens', 'wds-rss-post-aggregator' ); ?></h3>
		<ul class="ul-disc">
			<?php foreach ( $this->get_template_tokens() as $token => $description ) : ?>
				<li><code><?php echo esc_html( $token ); ?></code> — <?php echo esc_html( $description ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render content template textarea.
	 *
	 * @since 0.2.5
	 */
	public function render_content_template_field() {
		?>
		<textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>[content_template]" rows="8" cols="80" class="large-text code"><?php echo esc_textarea( $this->get_content_template() ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Use plain text, HTML allowed by WordPress post content, and the tokens listed above. Leave blank to restore the default {summary} template.', 'wds-rss-post-aggregator' ); ?></p>
		<?php
	}

	/**
	 * Get token descriptions for admin documentation.
	 *
	 * @since 0.2.5
	 *
	 * @return array Token descriptions keyed by token.
	 */
	public function get_template_tokens() {
		return array(
			'{title}'               => __( 'RSS item title.', 'wds-rss-post-aggregator' ),
			'{summary}'             => __( 'Trimmed item summary used by the importer.', 'wds-rss-post-aggregator' ),
			'{source_url}'          => __( 'Original item URL.', 'wds-rss-post-aggregator' ),
			'{feed_url}'            => __( 'RSS feed URL.', 'wds-rss-post-aggregator' ),
			'{source}'              => __( 'Source host parsed from the feed URL.', 'wds-rss-post-aggregator' ),
			'{author}'              => __( 'RSS item author when available.', 'wds-rss-post-aggregator' ),
			'{date}'                => __( 'Formatted RSS item date.', 'wds-rss-post-aggregator' ),
			'{audio_url}'           => __( 'Podcast/audio enclosure URL.', 'wds-rss-post-aggregator' ),
			'{itunes_title}'        => __( 'iTunes title field.', 'wds-rss-post-aggregator' ),
			'{itunes_summary}'      => __( 'iTunes summary field.', 'wds-rss-post-aggregator' ),
			'{description}'         => __( 'Raw RSS description captured as retained item meta.', 'wds-rss-post-aggregator' ),
			'{content_encoded}'     => __( 'content:encoded field captured as retained item meta.', 'wds-rss-post-aggregator' ),
			'{pub_date}'            => __( 'RSS pubDate value.', 'wds-rss-post-aggregator' ),
			'{itunes_author}'       => __( 'iTunes author field.', 'wds-rss-post-aggregator' ),
			'{itunes_duration}'     => __( 'iTunes duration field.', 'wds-rss-post-aggregator' ),
			'{itunes_keywords}'     => __( 'iTunes keywords field.', 'wds-rss-post-aggregator' ),
			'{itunes_episode}'      => __( 'iTunes episode number.', 'wds-rss-post-aggregator' ),
			'{itunes_episode_type}' => __( 'iTunes episode type.', 'wds-rss-post-aggregator' ),
			'{itunes_explicit}'     => __( 'iTunes explicit value.', 'wds-rss-post-aggregator' ),
			'{enclosure_url}'       => __( 'Enclosure URL captured from retained item meta.', 'wds-rss-post-aggregator' ),
			'{enclosure_type}'      => __( 'Enclosure MIME type captured from retained item meta.', 'wds-rss-post-aggregator' ),
			'{enclosure_length}'    => __( 'Enclosure length captured from retained item meta.', 'wds-rss-post-aggregator' ),
		);
	}

	/**
	 * Render the post content template for a feed item.
	 *
	 * @since 0.2.5
	 *
	 * @param array $post_data RSS item data.
	 * @return string Rendered post content.
	 */
	public function render_post_content( $post_data ) {
		$template = $this->get_content_template();
		$tokens   = $this->get_template_values( $post_data );
		$content  = strtr( $template, $tokens );

		return apply_filters( 'rss_post_aggregator_rendered_post_content', $content, $post_data, $template, $tokens, $this );
	}

	/**
	 * Get template token values for a feed item.
	 *
	 * @since 0.2.5
	 *
	 * @param array $post_data RSS item data.
	 * @return array Token values keyed by token.
	 */
	protected function get_template_values( $post_data ) {
		$rss_item_meta = isset( $post_data['rss_item_meta'] ) && is_array( $post_data['rss_item_meta'] ) ? $post_data['rss_item_meta'] : array();
		$enclosure     = isset( $rss_item_meta['enclosure'] ) && is_array( $rss_item_meta['enclosure'] ) ? $rss_item_meta['enclosure'] : array();

		$values = array(
			'{title}'               => isset( $post_data['title'] ) ? $post_data['title'] : '',
			'{summary}'             => isset( $post_data['summary'] ) ? $post_data['summary'] : '',
			'{source_url}'          => isset( $post_data['link'] ) ? $post_data['link'] : '',
			'{feed_url}'            => isset( $post_data['rss_link'] ) ? $post_data['rss_link'] : '',
			'{source}'              => isset( $post_data['source'] ) ? $post_data['source'] : '',
			'{author}'              => isset( $post_data['author'] ) ? $post_data['author'] : '',
			'{date}'                => isset( $post_data['date'] ) ? $post_data['date'] : '',
			'{audio_url}'           => isset( $post_data['audio_url'] ) ? $post_data['audio_url'] : '',
			'{itunes_title}'        => isset( $rss_item_meta['itunes_title'] ) ? $rss_item_meta['itunes_title'] : '',
			'{itunes_summary}'      => isset( $rss_item_meta['itunes_summary'] ) ? $rss_item_meta['itunes_summary'] : '',
			'{description}'         => isset( $rss_item_meta['description'] ) ? $rss_item_meta['description'] : '',
			'{content_encoded}'     => isset( $rss_item_meta['content_encoded'] ) ? $rss_item_meta['content_encoded'] : '',
			'{pub_date}'            => isset( $rss_item_meta['pub_date'] ) ? $rss_item_meta['pub_date'] : '',
			'{itunes_author}'       => isset( $rss_item_meta['itunes_author'] ) ? $rss_item_meta['itunes_author'] : '',
			'{itunes_duration}'     => isset( $rss_item_meta['itunes_duration'] ) ? $rss_item_meta['itunes_duration'] : '',
			'{itunes_keywords}'     => isset( $rss_item_meta['itunes_keywords'] ) ? $rss_item_meta['itunes_keywords'] : '',
			'{itunes_episode}'      => isset( $rss_item_meta['itunes_episode'] ) ? $rss_item_meta['itunes_episode'] : '',
			'{itunes_episode_type}' => isset( $rss_item_meta['itunes_episode_type'] ) ? $rss_item_meta['itunes_episode_type'] : '',
			'{itunes_explicit}'     => isset( $rss_item_meta['itunes_explicit'] ) ? $rss_item_meta['itunes_explicit'] : '',
			'{enclosure_url}'       => isset( $enclosure['url'] ) ? $enclosure['url'] : '',
			'{enclosure_type}'      => isset( $enclosure['type'] ) ? $enclosure['type'] : '',
			'{enclosure_length}'    => isset( $enclosure['length'] ) ? $enclosure['length'] : '',
		);

		foreach ( $values as $token => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$values[ $token ] = wp_json_encode( $value );
				continue;
			}

			$values[ $token ] = RSS_Post_Aggregator::decode_entities( $value );
		}

		return apply_filters( 'rss_post_aggregator_template_values', $values, $post_data, $this );
	}
}
