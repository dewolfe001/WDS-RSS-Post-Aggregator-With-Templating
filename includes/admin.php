<?php

// Our namespace.
namespace WebDevStudios\RSS_Post_Aggregator;

/**
 * Admin settings, cron scheduling, and bulk import controller.
 */
class RSS_Post_Aggregator_Admin {

	const OPTION_NAME = 'wds_rss_post_aggregator_settings';
	const CRON_HOOK   = 'wds_rss_post_aggregator_cron_import';

	/** @var RSS_Post_Aggregator_Feeds */
	protected $rss;

	/** @var RSS_Post_Aggregator_CPT */
	protected $cpt;

	/** @var RSS_Post_Aggregator_Taxonomy */
	protected $tax;

	public function __construct( $rss, $cpt, $tax ) {
		$this->rss = $rss;
		$this->cpt = $cpt;
		$this->tax = $tax;
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_feed_taxonomy_for_import_types' ), 20 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_post_wds_rss_manual_import', array( $this, 'handle_manual_import' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_import' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'reschedule_cron' ), 10, 2 );
	}

	public static function activate() {
		add_option( self::OPTION_NAME, self::default_settings(), '', 'no' );
		self::schedule_cron();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function default_settings() {
		return array(
			'import_post_type'      => 'rss-posts',
			'import_status'         => 'draft',
			'items_per_feed'        => 20,
			'cron_interval'         => 'hourly',
			'template'              => "{audio}\n{image}\n<div class=\"rss-item-summary\">{summary}</div>\n{meta}",
			'displayed_fields'      => array( 'title', 'pub_date', 'duration', 'explicit', 'episode', 'season' ),
			'media_player_position' => 'template',
			'meta_wrapper'          => '<dl class="rss-item-meta">{items}</dl>',
			'meta_item_template'    => '<dt>{label}</dt><dd>{value}</dd>',
		);
	}

	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );

		$settings['items_per_feed']   = max( 1, min( 20, (int) $settings['items_per_feed'] ) );
		$settings['displayed_fields'] = is_array( $settings['displayed_fields'] ) ? $settings['displayed_fields'] : array();

		return $settings;
	}

	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . $this->cpt->post_type(),
			esc_html__( 'RSS Import Settings', 'wds-rss-post-aggregator' ),
			esc_html__( 'Import Settings', 'wds-rss-post-aggregator' ),
			'manage_options',
			'wds-rss-import-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function cron_schedules( $schedules ) {
		$schedules['wds_rss_every_15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'wds-rss-post-aggregator' ),
		);
		$schedules['wds_rss_every_30_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 Minutes', 'wds-rss-post-aggregator' ),
		);

		return $schedules;
	}

	public static function schedule_cron() {
		$settings = self::get_settings();
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( 'disabled' === $settings['cron_interval'] ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $settings['cron_interval'], self::CRON_HOOK );
		}
	}

	public function reschedule_cron() {
		self::schedule_cron();
	}

	public function register_feed_taxonomy_for_import_types() {
		foreach ( $this->get_importable_post_types() as $post_type => $label ) {
			register_taxonomy_for_object_type( $this->tax->taxonomy(), $post_type );
		}
	}

	public function get_importable_post_types() {
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		$types      = array();

		foreach ( $post_types as $post_type => $object ) {
			if ( in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}

			$types[ $post_type ] = $object->labels->singular_name;
		}

		if ( ! isset( $types[ $this->cpt->post_type() ] ) ) {
			$types[ $this->cpt->post_type() ] = __( 'RSS Feed', 'wds-rss-post-aggregator' );
		}

		return apply_filters( 'rss_post_aggregator_importable_post_types', $types );
	}

	public function maybe_save_settings() {
		if ( empty( $_POST['wds_rss_settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wds_rss_settings_nonce'] ), 'wds_rss_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage RSS import settings.', 'wds-rss-post-aggregator' ) );
		}

		$settings = self::get_settings();
		$posted   = isset( $_POST['wds_rss_settings'] ) && is_array( $_POST['wds_rss_settings'] ) ? wp_unslash( $_POST['wds_rss_settings'] ) : array();
		$types    = $this->get_importable_post_types();
		$statuses = array( 'draft', 'publish', 'pending', 'private' );
		$intervals = array( 'disabled', 'wds_rss_every_15_minutes', 'wds_rss_every_30_minutes', 'hourly', 'twicedaily', 'daily' );

		$settings['import_post_type']      = isset( $posted['import_post_type'], $types[ $posted['import_post_type'] ] ) ? sanitize_key( $posted['import_post_type'] ) : $this->cpt->post_type();
		$settings['import_status']         = isset( $posted['import_status'] ) && in_array( $posted['import_status'], $statuses, true ) ? sanitize_key( $posted['import_status'] ) : 'draft';
		$settings['items_per_feed']        = isset( $posted['items_per_feed'] ) ? max( 1, min( 20, absint( $posted['items_per_feed'] ) ) ) : 20;
		$settings['cron_interval']         = isset( $posted['cron_interval'] ) && in_array( $posted['cron_interval'], $intervals, true ) ? sanitize_key( $posted['cron_interval'] ) : 'hourly';
		$settings['template']              = isset( $posted['template'] ) ? wp_kses_post( $posted['template'] ) : $settings['template'];
		$settings['media_player_position'] = isset( $posted['media_player_position'] ) && in_array( $posted['media_player_position'], array( 'template', 'before', 'after', 'none' ), true ) ? sanitize_key( $posted['media_player_position'] ) : 'template';
		$settings['meta_wrapper']          = isset( $posted['meta_wrapper'] ) ? wp_kses_post( $posted['meta_wrapper'] ) : $settings['meta_wrapper'];
		$settings['meta_item_template']    = isset( $posted['meta_item_template'] ) ? wp_kses_post( $posted['meta_item_template'] ) : $settings['meta_item_template'];
		$settings['displayed_fields']      = isset( $posted['displayed_fields'] ) ? array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) $posted['displayed_fields'] ) ) ) : array();

		update_option( self::OPTION_NAME, $settings, false );

		wp_safe_redirect( add_query_arg( array( 'post_type' => $this->cpt->post_type(), 'page' => 'wds-rss-import-settings', 'settings-updated' => 'true' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function render_settings_page() {
		$settings     = self::get_settings();
		$last_import  = get_option( 'wds_rss_post_aggregator_last_import', array() );
		$next_cron    = wp_next_scheduled( self::CRON_HOOK );
		$field_string = implode( ', ', $settings['displayed_fields'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RSS Import Settings', 'wds-rss-post-aggregator' ); ?></h1>
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'RSS import settings saved.', 'wds-rss-post-aggregator' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['manual-imported'] ) ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Manual import complete. Imported or updated %d RSS items.', 'wds-rss-post-aggregator' ), absint( $_GET['manual-imported'] ) ) ); ?></p></div>
			<?php endif; ?>

			<p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => $this->cpt->post_type(), $this->cpt->slug_to_redirect => 1 ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Open RSS Import Modal', 'wds-rss-post-aggregator' ); ?></a></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'wds_rss_save_settings', 'wds_rss_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wds-rss-import-post-type"><?php esc_html_e( 'Import RSS items as', 'wds-rss-post-aggregator' ); ?></label></th>
						<td><select id="wds-rss-import-post-type" name="wds_rss_settings[import_post_type]">
							<?php foreach ( $this->get_importable_post_types() as $post_type => $label ) : ?>
								<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $settings['import_post_type'], $post_type ); ?>><?php echo esc_html( $label . ' (' . $post_type . ')' ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr><th scope="row"><label for="wds-rss-import-status"><?php esc_html_e( 'New item status', 'wds-rss-post-aggregator' ); ?></label></th><td><select id="wds-rss-import-status" name="wds_rss_settings[import_status]"><?php foreach ( array( 'draft', 'publish', 'pending', 'private' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $settings['import_status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="wds-rss-items-per-feed"><?php esc_html_e( 'Items to poll per feed', 'wds-rss-post-aggregator' ); ?></label></th><td><input id="wds-rss-items-per-feed" type="number" min="1" max="20" name="wds_rss_settings[items_per_feed]" value="<?php echo esc_attr( $settings['items_per_feed'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="wds-rss-cron-interval"><?php esc_html_e( 'Automatic polling schedule', 'wds-rss-post-aggregator' ); ?></label></th><td><select id="wds-rss-cron-interval" name="wds_rss_settings[cron_interval]"><?php foreach ( array( 'disabled' => __( 'Disabled', 'wds-rss-post-aggregator' ), 'wds_rss_every_15_minutes' => __( 'Every 15 minutes', 'wds-rss-post-aggregator' ), 'wds_rss_every_30_minutes' => __( 'Every 30 minutes', 'wds-rss-post-aggregator' ), 'hourly' => __( 'Hourly', 'wds-rss-post-aggregator' ), 'twicedaily' => __( 'Twice Daily', 'wds-rss-post-aggregator' ), 'daily' => __( 'Daily', 'wds-rss-post-aggregator' ) ) as $interval => $label ) : ?><option value="<?php echo esc_attr( $interval ); ?>" <?php selected( $settings['cron_interval'], $interval ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><p class="description"><?php echo $next_cron ? esc_html( sprintf( __( 'Next scheduled import: %s', 'wds-rss-post-aggregator' ), wp_date( 'Y-m-d H:i:s', $next_cron ) ) ) : esc_html__( 'No import is currently scheduled.', 'wds-rss-post-aggregator' ); ?></p></td></tr>
					<tr><th scope="row"><label for="wds-rss-template"><?php esc_html_e( 'Imported item content template', 'wds-rss-post-aggregator' ); ?></label></th><td><textarea id="wds-rss-template" name="wds_rss_settings[template]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['template'] ); ?></textarea><p class="description"><?php esc_html_e( 'Available tokens: {title}, {summary}, {link}, {source}, {date}, {image}, {audio}, {meta}, and any RSS item meta key as {meta:key}. HTML is allowed.', 'wds-rss-post-aggregator' ); ?></p></td></tr>
					<tr><th scope="row"><label for="wds-rss-displayed-fields"><?php esc_html_e( 'Displayed RSS meta fields', 'wds-rss-post-aggregator' ); ?></label></th><td><input id="wds-rss-displayed-fields" type="text" class="large-text" name="wds_rss_settings[displayed_fields]" value="<?php echo esc_attr( $field_string ); ?>" /><p class="description"><?php esc_html_e( 'Comma or space separated meta keys to show in the {meta} token.', 'wds-rss-post-aggregator' ); ?></p></td></tr>
					<tr><th scope="row"><label for="wds-rss-media-position"><?php esc_html_e( 'Media player position', 'wds-rss-post-aggregator' ); ?></label></th><td><select id="wds-rss-media-position" name="wds_rss_settings[media_player_position]"><?php foreach ( array( 'template' => __( 'Use {audio} token', 'wds-rss-post-aggregator' ), 'before' => __( 'Before template', 'wds-rss-post-aggregator' ), 'after' => __( 'After template', 'wds-rss-post-aggregator' ), 'none' => __( 'Do not display', 'wds-rss-post-aggregator' ) ) as $position => $label ) : ?><option value="<?php echo esc_attr( $position ); ?>" <?php selected( $settings['media_player_position'], $position ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="wds-rss-meta-wrapper"><?php esc_html_e( 'Meta wrapper HTML', 'wds-rss-post-aggregator' ); ?></label></th><td><input id="wds-rss-meta-wrapper" type="text" class="large-text code" name="wds_rss_settings[meta_wrapper]" value="<?php echo esc_attr( $settings['meta_wrapper'] ); ?>" /><p class="description"><?php esc_html_e( 'Use {items} where generated meta rows should appear.', 'wds-rss-post-aggregator' ); ?></p></td></tr>
					<tr><th scope="row"><label for="wds-rss-meta-item-template"><?php esc_html_e( 'Meta item HTML', 'wds-rss-post-aggregator' ); ?></label></th><td><input id="wds-rss-meta-item-template" type="text" class="large-text code" name="wds_rss_settings[meta_item_template]" value="<?php echo esc_attr( $settings['meta_item_template'] ); ?>" /><p class="description"><?php esc_html_e( 'Use {label}, {key}, and {value} for each displayed meta field.', 'wds-rss-post-aggregator' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'wds-rss-post-aggregator' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wds_rss_manual_import" />
				<?php wp_nonce_field( 'wds_rss_manual_import', 'wds_rss_manual_import_nonce' ); ?>
				<?php submit_button( __( 'Run Manual Import Now', 'wds-rss-post-aggregator' ), 'secondary' ); ?>
			</form>
			<?php if ( is_array( $last_import ) && ! empty( $last_import ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Last import: %1$s. Imported or updated %2$d item(s).', 'wds-rss-post-aggregator' ), isset( $last_import['time'] ) ? $last_import['time'] : '', isset( $last_import['imported'] ) ? (int) $last_import['imported'] : 0 ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_manual_import() {
		if ( empty( $_POST['wds_rss_manual_import_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wds_rss_manual_import_nonce'] ), 'wds_rss_manual_import' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import RSS items.', 'wds-rss-post-aggregator' ) );
		}

		$result = $this->import_all_feeds();

		wp_safe_redirect( add_query_arg( array( 'post_type' => $this->cpt->post_type(), 'page' => 'wds-rss-import-settings', 'manual-imported' => (int) $result['imported'] ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function run_scheduled_import() {
		$this->import_all_feeds();
	}

	public function import_all_feeds() {
		$settings = self::get_settings();
		$feeds    = get_terms( array( 'taxonomy' => $this->tax->taxonomy(), 'hide_empty' => false ) );
		$result   = array( 'imported' => 0, 'feeds' => array() );

		if ( is_wp_error( $feeds ) || empty( $feeds ) ) {
			return $result;
		}

		foreach ( $feeds as $feed ) {
			$items = $this->rss->get_items( esc_url_raw( $feed->name ), array(
				'show_author'  => true,
				'show_date'    => true,
				'show_summary' => true,
				'show_image'   => true,
				'items'        => $settings['items_per_feed'],
				'cache_time'   => 0,
			) );

			if ( isset( $items['error'] ) ) {
				$result['feeds'][ $feed->term_id ] = $items['error'];
				continue;
			}

			foreach ( $items as $item ) {
				$report = $this->cpt->insert( $item, $feed->term_id, $settings['import_post_type'] );
				if ( is_array( $report ) && ! empty( $report['post_id'] ) && empty( $report['skipped'] ) ) {
					$result['imported']++;
				}
			}
		}

		update_option( 'wds_rss_post_aggregator_last_import', array( 'time' => current_time( 'mysql' ), 'imported' => $result['imported'] ), false );

		return $result;
	}

	public static function render_imported_content( $post_data ) {
		$settings = self::get_settings();
		$meta     = isset( $post_data['rss_item_meta'] ) && is_array( $post_data['rss_item_meta'] ) ? $post_data['rss_item_meta'] : array();
		$audio    = self::get_audio_markup( isset( $post_data['audio_url'] ) ? $post_data['audio_url'] : '' );
		$image    = ! empty( $post_data['image'] ) ? '<img class="rss-item-image" src="' . esc_url( $post_data['image'] ) . '" alt="" />' : '';
		$content  = $settings['template'];

		$tokens = array(
			'{title}'   => esc_html( isset( $post_data['title'] ) ? $post_data['title'] : '' ),
			'{summary}' => wp_kses_post( isset( $post_data['summary'] ) ? stripslashes( $post_data['summary'] ) : '' ),
			'{link}'    => esc_url( isset( $post_data['link'] ) ? $post_data['link'] : '' ),
			'{source}'  => esc_html( isset( $post_data['source'] ) ? $post_data['source'] : '' ),
			'{date}'    => esc_html( isset( $post_data['date'] ) ? $post_data['date'] : '' ),
			'{image}'   => $image,
			'{audio}'   => 'template' === $settings['media_player_position'] ? $audio : '',
			'{meta}'    => self::get_meta_markup( $meta, $settings ),
		);

		foreach ( $meta as $key => $value ) {
			$tokens[ '{meta:' . sanitize_key( $key ) . '}' ] = esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
		}

		$content = strtr( $content, $tokens );

		if ( 'before' === $settings['media_player_position'] && $audio ) {
			$content = $audio . $content;
		} elseif ( 'after' === $settings['media_player_position'] && $audio ) {
			$content .= $audio;
		}

		return wp_kses_post( $content );
	}

	protected static function get_audio_markup( $audio_url ) {
		$audio_url = esc_url_raw( $audio_url );
		return $audio_url ? '<div class="rss-post-audio">' . wp_audio_shortcode( array( 'src' => $audio_url ) ) . '</div>' : '';
	}

	protected static function get_meta_markup( $meta, $settings ) {
		if ( empty( $meta ) || empty( $settings['displayed_fields'] ) ) {
			return '';
		}

		$items = '';
		foreach ( $settings['displayed_fields'] as $field ) {
			if ( ! isset( $meta[ $field ] ) || '' === $meta[ $field ] ) {
				continue;
			}

			$value = is_scalar( $meta[ $field ] ) ? (string) $meta[ $field ] : wp_json_encode( $meta[ $field ] );
			$items .= strtr( $settings['meta_item_template'], array(
				'{key}'   => esc_attr( $field ),
				'{label}' => esc_html( ucwords( str_replace( '_', ' ', $field ) ) ),
				'{value}' => esc_html( $value ),
			) );
		}

		return $items ? wp_kses_post( str_replace( '{items}', $items, $settings['meta_wrapper'] ) ) : '';
	}
}
