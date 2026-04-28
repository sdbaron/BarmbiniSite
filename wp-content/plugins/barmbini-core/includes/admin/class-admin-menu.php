<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Admin_Menu {
	protected $settings;

	protected $log_repository;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Log_Repository $log_repository, Barmbini_Core_Queue_Repository $queue_repository ) {
		$this->settings         = $settings;
		$this->log_repository   = $log_repository;
		$this->queue_repository = $queue_repository;
	}

	public function register_pages() {
		add_submenu_page(
			'woocommerce',
			'Barmbini Benachrichtigungen',
			'Barmbini Benachrichtigungen',
			'manage_woocommerce',
			'barmbini-core-notifications',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$users         = $this->get_subscription_users();
		$recent_logs   = $this->log_repository->get_recent_logs( 30 );
		$recent_queue  = $this->queue_repository->get_recent_items( 30 );
		?>
		<div class="wrap">
			<h1>Barmbini Benachrichtigungen</h1>

			<h2>Aktive Abonnements</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Kunde</th>
						<th>E-Mail</th>
						<th>Neuigkeiten</th>
						<th>Rabatte</th>
						<th>Kategorien</th>
						<th>Zuletzt aktualisiert</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="6">Keine aktiven Abonnements gefunden.</td></tr>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<?php $settings = $this->settings->get_user_settings( $user->ID ); ?>
							<tr>
								<td><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></td>
								<td><?php echo esc_html( $user->user_email ); ?></td>
								<td><?php echo esc_html( ! empty( $settings['news_enabled'] ) ? $settings['news_frequency'] : '-' ); ?></td>
								<td><?php echo esc_html( ! empty( $settings['discount_enabled'] ) ? $settings['discount_frequency'] : '-' ); ?></td>
								<td><?php echo esc_html( $this->format_categories( $settings['category_terms'] ) ); ?></td>
								<td><?php echo esc_html( $settings['updated_at'] ?: '-' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2>Queue</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Kunde</th>
						<th>Typ</th>
						<th>Frequenz</th>
						<th>Status</th>
						<th>Geplant fuer</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_queue ) ) : ?>
						<tr><td colspan="6">Keine Queue-Eintraege vorhanden.</td></tr>
					<?php else : ?>
						<?php foreach ( $recent_queue as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['id'] ); ?></td>
								<td><?php echo esc_html( (string) $item['user_id'] ); ?></td>
								<td><?php echo esc_html( $item['event_type'] ); ?></td>
								<td><?php echo esc_html( $item['frequency'] ); ?></td>
								<td><?php echo esc_html( $item['status'] ); ?></td>
								<td><?php echo esc_html( $item['scheduled_for'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2>Versandlog</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Kunde</th>
						<th>Ereignis</th>
						<th>Modus</th>
						<th>Status</th>
						<th>Gesendet am</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_logs ) ) : ?>
						<tr><td colspan="6">Keine Versandprotokolle vorhanden.</td></tr>
					<?php else : ?>
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['id'] ); ?></td>
								<td><?php echo esc_html( (string) $log['user_id'] ); ?></td>
								<td><?php echo esc_html( $log['event_type'] ); ?></td>
								<td><?php echo esc_html( $log['delivery_mode'] ); ?></td>
								<td><?php echo esc_html( $log['status'] ); ?></td>
								<td><?php echo esc_html( $log['sent_at'] ?: '-' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	protected function get_subscription_users() {
		return get_users(
			array(
				'number'     => 50,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => Barmbini_Core_Subscription_Settings::NEWS_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => Barmbini_Core_Subscription_Settings::DISCOUNT_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => Barmbini_Core_Subscription_Settings::CATEGORY_ENABLED,
						'value' => '1',
					),
				),
			)
		);
	}

	protected function format_categories( $term_ids ) {
		$names = array();

		foreach ( array_filter( array_map( 'absint', (array) $term_ids ) ) as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );

			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		return empty( $names ) ? '-' : implode( ', ', $names );
	}
}