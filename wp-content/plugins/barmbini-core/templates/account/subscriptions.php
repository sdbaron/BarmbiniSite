<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frequency_labels = array(
	'sofort'       => 'Sofort',
	'täglich'      => 'Täglich',
	'wöchentlich'  => 'Wöchentlich',
);
?>
<div class="barmbini-subscriptions">
	<h2>Abonnements</h2>
	<p>Sie können hier Benachrichtigungen für Neuigkeiten und Aktionen aktivieren oder beenden.</p>

	<form method="post" class="barmbini-subscriptions__form">
		<?php wp_nonce_field( 'barmbini_save_subscriptions', 'barmbini_subscriptions_nonce' ); ?>

		<section class="barmbini-subscriptions__section">
			<label class="barmbini-subscriptions__toggle">
				<input type="checkbox" name="news_enabled" value="1" <?php checked( ! empty( $settings['news_enabled'] ) ); ?>>
				<span>Neuigkeiten abonnieren</span>
			</label>
			<label>
				<span>Frequenz</span>
				<select name="news_frequency">
					<?php foreach ( $supported_frequencies as $frequency ) : ?>
						<option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $settings['news_frequency'], $frequency ); ?>><?php echo esc_html( $frequency_labels[ $frequency ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</section>

		<section class="barmbini-subscriptions__section">
			<label class="barmbini-subscriptions__toggle">
				<input type="checkbox" name="actions_enabled" value="1" <?php checked( ! empty( $settings['actions_enabled'] ) ); ?>>
				<span>Aktionen abonnieren</span>
			</label>
			<label>
				<span>Frequenz</span>
				<select name="actions_frequency">
					<?php foreach ( $supported_frequencies as $frequency ) : ?>
						<option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $settings['actions_frequency'], $frequency ); ?>><?php echo esc_html( $frequency_labels[ $frequency ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</section>

		<section class="barmbini-subscriptions__section barmbini-subscriptions__meta">
			<p>Keine Option ist vorausgewählt. Ihre Auswahl gilt nur für die hier aktivierten Benachrichtigungen.</p>
			<?php if ( ! empty( $settings['consent_at'] ) ) : ?>
				<p>Einwilligung erfasst am: <?php echo esc_html( $settings['consent_at'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $settings['updated_at'] ) ) : ?>
				<p>Zuletzt aktualisiert am: <?php echo esc_html( $settings['updated_at'] ); ?></p>
			<?php endif; ?>
		</section>

		<button type="submit" class="button">Abonnements speichern</button>
	</form>
</div>