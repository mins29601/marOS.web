<?php
/**
 * Pantalla «marOS» (Propiedades → marOS), avisos del escritorio y caja «Viene de marOS» en el
 * editor (spec 003, FR-008 y US1, escenario 5).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Admin {

	private const PAGE = 'maros-connector';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_maros_sync_now', array( __CLASS__, 'sync_now' ) );
		add_action( 'admin_post_maros_dismiss', array( __CLASS__, 'dismiss' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes_mis_property', array( __CLASS__, 'meta_box' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=mis_property',
			'marOS',
			'marOS',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function page_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => 'mis_property', 'page' => self::PAGE ), $args ), admin_url( 'edit.php' ) );
	}

	public static function sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'maros-connector' ), 403 );
		}
		check_admin_referer( 'maros_sync_now' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore
		}
		$force  = ! empty( $_POST['force'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$result = Maros_Scheduler::run_locked( static fn() => Maros_Sync::sync_all( false, true, $force ) );
		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();
		} else {
			$message = sprintf(
				/* translators: 1: created, 2: updated, 3: unchanged, 4: withdrawn, 5: errors */
				__( 'Sync finished: %1$d created, %2$d updated, %3$d unchanged, %4$d withdrawn, %5$d errors.', 'maros-connector' ),
				$result['created'],
				$result['updated'],
				$result['skipped'],
				$result['withdrawn'],
				count( $result['errors'] )
			);
		}
		set_transient( 'maros_admin_message_' . get_current_user_id(), $message, 60 );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	public static function dismiss(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'maros_dismiss' );
		$id      = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$notices = get_option( 'maros_notices', array() );
		unset( $notices[ $id ] );
		update_option( 'maros_notices', $notices, false );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public static function notices(): void {
		if ( current_user_can( 'manage_options' ) && 'mock' !== Maros_Client::env() && Maros_Log::is_stale() ) {
			$last = Maros_Log::last_ok();
			printf(
				'<div class="notice notice-error"><p><strong>marOS:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html(
					$last
						? sprintf( __( 'No data from marOS for more than 26 hours (last: %s). The site keeps showing the last data.', 'maros-connector' ), wp_date( 'j M H:i', $last ) )
						: __( 'Properties have never been synced with marOS.', 'maros-connector' )
				),
				esc_url( self::page_url() ),
				esc_html__( 'See status', 'maros-connector' )
			);
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		foreach ( get_option( 'maros_notices', array() ) as $id => $n ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html( sprintf( __( '%1$s is no longer publishable in marOS and has been moved to draft. Add a redirect for %2$s.', 'maros-connector' ), $n['name'], $n['url'] ) ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=maros_dismiss&id=' . rawurlencode( (string) $id ) ), 'maros_dismiss' ) ),
				esc_html__( 'Done', 'maros-connector' )
			);
		}
	}

	public static function render(): void {
		$env     = Maros_Client::env();
		$config  = Maros_Client::check_config();
		$message = get_transient( 'maros_admin_message_' . get_current_user_id() );
		delete_transient( 'maros_admin_message_' . get_current_user_id() );
		$queue   = Maros_Scheduler::queue();
		$fmt     = static fn( $ts ) => $ts ? wp_date( 'j M Y H:i', is_numeric( $ts ) ? (int) $ts : strtotime( (string) $ts ) ) : '—';
		?>
		<div class="wrap">
			<h1>marOS</h1>
			<?php if ( $message ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px">
				<tbody>
					<tr><th><?php esc_html_e( 'marOS environment', 'maros-connector' ); ?></th><td><code><?php echo esc_html( $env ); ?></code> <?php echo esc_html( Maros_Client::base() ); ?> <em>(wp-config.php)</em></td></tr>
					<tr><th><?php esc_html_e( 'API key', 'maros-connector' ); ?></th><td><?php echo Maros_Client::has_key() ? esc_html__( 'Configured', 'maros-connector' ) : esc_html__( 'Missing', 'maros-connector' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Webhook secret', 'maros-connector' ); ?></th><td><?php echo Maros_Webhook::secret() ? esc_html__( 'Configured', 'maros-connector' ) : esc_html__( 'Missing', 'maros-connector' ); ?> · <code><?php echo esc_html( rest_url( 'maros/v1/webhook' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Configuration', 'maros-connector' ); ?></th><td><?php echo is_wp_error( $config ) ? '<strong style="color:#b32d2e">' . esc_html( $config->get_error_message() ) . '</strong>' : esc_html__( 'OK', 'maros-connector' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Last answer from marOS', 'maros-connector' ); ?></th><td><?php echo esc_html( $fmt( Maros_Log::last_ok() ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Last full sync', 'maros-connector' ); ?></th><td><?php echo esc_html( $fmt( get_option( 'maros_last_full_sync', '' ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Next 5-minute run', 'maros-connector' ); ?></th><td><?php echo esc_html( $fmt( wp_next_scheduled( Maros_Scheduler::TICK ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Next nightly reconciliation', 'maros-connector' ); ?></th><td><?php echo esc_html( $fmt( wp_next_scheduled( Maros_Scheduler::NIGHTLY ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Queue', 'maros-connector' ); ?></th><td>
						<?php
						if ( ! $queue ) {
							esc_html_e( 'Empty', 'maros-connector' );
						}
						foreach ( $queue as $id => $item ) {
							printf( '<div><code>%s</code> · %s · %s %s</div>', esc_html( $id ), esc_html( $item['reason'] ?? '' ), esc_html( $fmt( $item['next'] ?? 0 ) ), $item['error'] ? '· <span style="color:#b32d2e">' . esc_html( $item['error'] ) . '</span>' : '' );
						}
						?>
					</td></tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1.5em 0">
				<input type="hidden" name="action" value="maros_sync_now">
				<?php wp_nonce_field( 'maros_sync_now' ); ?>
				<label><input type="checkbox" name="force" value="1"> <?php esc_html_e( 'Reload everything, even what has not changed', 'maros-connector' ); ?></label>
				<?php submit_button( __( 'Sync now', 'maros-connector' ), 'primary', 'submit', false, Maros_Scheduler::is_locked() ? array( 'disabled' => 'disabled' ) : array() ); ?>
			</form>

			<h2><?php esc_html_e( 'Properties', 'maros-connector' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th><?php esc_html_e( 'Property', 'maros-connector' ); ?></th><th>marOS</th><th><?php esc_html_e( 'Status', 'maros-connector' ); ?></th><th><?php esc_html_e( 'Calendar', 'maros-connector' ); ?></th></tr></thead>
				<tbody>
				<?php
				$seen = array();
				foreach ( Maros_Sync::all_posts() as $post_id ) {
					$maros_id = (string) get_post_meta( $post_id, 'maros_id', true );
					if ( ! $maros_id || isset( $seen[ $maros_id ] ) ) {
						continue;
					}
					$seen[ $maros_id ] = true;
					$cal               = Maros_Calendar::summary( $maros_id );
					printf(
						'<tr><td><a href="%s">%s</a></td><td><code>%s</code></td><td>%s%s</td><td>%s</td></tr>',
						esc_url( get_edit_post_link( $post_id ) ?? '' ),
						esc_html( get_the_title( $post_id ) ),
						esc_html( $maros_id ),
						esc_html( get_post_status_object( get_post_status( $post_id ) )->label ?? '' ),
						get_post_meta( $post_id, 'maros_withdrawn', true ) ? ' · <strong>' . esc_html__( 'not publishable in marOS', 'maros-connector' ) . '</strong>' : '',
						esc_html( sprintf( __( '%1$d days (%2$d free) · %3$s', 'maros-connector' ), $cal['days'], $cal['free'], $fmt( $cal['synced_at'] ? $cal['synced_at'] . ' UTC' : '' ) ) )
					);
				}
				?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent activity', 'maros-connector' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
				<?php foreach ( Maros_Log::recent( 30 ) as $entry ) : ?>
					<tr><td style="width:9em"><?php echo esc_html( $fmt( $entry['time'] ) ); ?></td><td<?php echo 'error' === $entry['level'] ? ' style="color:#b32d2e"' : ''; ?>><?php echo esc_html( $entry['message'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function meta_box(): void {
		add_meta_box( 'maros-data', __( 'From marOS', 'maros-connector' ), array( __CLASS__, 'render_meta_box' ), 'mis_property', 'side', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		$m      = static fn( string $k ) => get_post_meta( $post->ID, $k, true );
		$fields = array(
			__( 'marOS id', 'maros-connector' )        => $m( 'maros_id' ),
			__( 'Name in marOS', 'maros-connector' )   => $m( 'maros_name' ),
			__( 'Max. guests', 'maros-connector' )     => $m( 'maros_max_guests' ),
			__( 'Bedrooms', 'maros-connector' )        => $m( 'maros_bedrooms' ),
			__( 'Bathrooms', 'maros-connector' )       => $m( 'maros_bathrooms' ),
			__( 'Beds', 'maros-connector' )            => $m( 'maros_beds' ),
			__( 'Area', 'maros-connector' )            => $m( 'maros_area' ),
			__( 'Price from / night', 'maros-connector' ) => $m( 'maros_price_from' ) ? $m( 'maros_price_from' ) . ' ' . $m( 'maros_currency' ) : '—',
			__( 'Deposit', 'maros-connector' )         => $m( 'maros_deposit' ),
			__( 'Synced', 'maros-connector' )          => $m( 'maros_synced_at' ) ? wp_date( 'j M Y H:i', strtotime( $m( 'maros_synced_at' ) ) ) : '—',
		);
		echo '<p class="description">' . esc_html__( 'These fields come from marOS and cannot be edited here.', 'maros-connector' ) . '</p><table class="widefat striped"><tbody>';
		foreach ( $fields as $label => $value ) {
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( (string) $value ) );
		}
		echo '</tbody></table>';
		if ( $m( 'maros_withdrawn' ) ) {
			echo '<p><strong>' . esc_html__( 'Not publishable in marOS: keep it in draft.', 'maros-connector' ) . '</strong></p>';
		}
	}
}
