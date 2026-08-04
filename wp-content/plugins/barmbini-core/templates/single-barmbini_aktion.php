<?php
/**
 * Single-Template für Aktionen (barmbini_aktion).
 *
 * Wird via template_include-Filter aus dem Plugin geladen.
 * Header und Footer kommen vom aktiven Theme (Kadence).
 *
 * @package Barmbini_Core
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="barmbini-single-promotion">
	<article <?php post_class( 'barmbini-single-promotion__article' ); ?>>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="barmbini-single-promotion__image">
				<?php the_post_thumbnail( 'large' ); ?>
			</div>
		<?php endif; ?>

		<h1 class="barmbini-single-promotion__title">
			<?php the_title(); ?>
		</h1>

		<?php
		$start_date = get_post_meta( get_the_ID(), '_barmbini_promotion_start_date', true );
		$end_date   = get_post_meta( get_the_ID(), '_barmbini_promotion_end_date', true );
		$today      = current_time( 'Y-m-d' );

		if ( $start_date && $end_date ) :
			?>
			<p class="barmbini-single-promotion__dates">
				Gültig vom <?php echo esc_html( date_i18n( 'j. F Y', strtotime( $start_date ) ) ); ?>
				bis zum <?php echo esc_html( date_i18n( 'j. F Y', strtotime( $end_date ) ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $end_date && $end_date < $today ) : ?>
			<div class="barmbini-single-promotion__expired">
				<strong>Hinweis:</strong> Diese Aktion ist beendet.
			</div>
		<?php endif; ?>

		<div class="barmbini-single-promotion__content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>

	</article>
</main>

<?php
get_footer();
