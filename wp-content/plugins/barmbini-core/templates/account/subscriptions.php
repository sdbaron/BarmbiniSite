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
	<p>Sie können hier Benachrichtigungen für Neuigkeiten, Rabatte und Produktkategorien aktivieren oder beenden.</p>

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
				<input type="checkbox" name="discount_enabled" value="1" <?php checked( ! empty( $settings['discount_enabled'] ) ); ?>>
				<span>Rabatte abonnieren</span>
			</label>
			<label>
				<span>Frequenz</span>
				<select name="discount_frequency">
					<?php foreach ( $supported_frequencies as $frequency ) : ?>
						<option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $settings['discount_frequency'], $frequency ); ?>><?php echo esc_html( $frequency_labels[ $frequency ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</section>

		<section class="barmbini-subscriptions__section">
			<label class="barmbini-subscriptions__toggle">
				<input type="checkbox" name="category_enabled" value="1" <?php checked( ! empty( $settings['category_enabled'] ) ); ?>>
				<span>Produktkategorien abonnieren</span>
			</label>

			<label>
				<span>Frequenz</span>
				<select name="category_frequency">
					<?php foreach ( $supported_frequencies as $frequency ) : ?>
						<option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $settings['category_frequency'], $frequency ); ?>><?php echo esc_html( $frequency_labels[ $frequency ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<div class="barmbini-subscriptions__categories">
				<?php foreach ( $product_categories as $category ) : ?>
					<label>
						<input type="checkbox" name="category_terms[]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, $settings['category_terms'], true ) ); ?>>
						<span><?php echo esc_html( $category->name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
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