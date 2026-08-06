<?php
/**
 * Barmbini Core – Periodischer Cache-Refresh
 *
 * Leert den WP Fastest Cache in regelmäßigen Abständen (Standard: alle
 * 6 Stunden), damit zeitlich begrenzte Inhalte wie abgelaufene Aktionen
 * des CPT "barmbini_aktion" zuverlässig von der Startseite verschwinden.
 *
 * Hintergrund: Die Free-Version von WP Fastest Cache kennt keine native
 * Cache-Lebensdauer. Gecachte Seiten werden ausgeliefert, bis sie durch
 * Post-Update oder manuelles Leeren invalidiert werden. Da eine Aktion
 * rein durch Verstreichen des Enddatums ungültig wird (ohne Post-Update),
 * stößt WP Fastest Cache dort keine Cache-Invalidierung an. Dieser Job
 * sorgt für den zeitbasierten Refresh.
 *
 * @package Barmbini_Core
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Cache_Maintenance {

	const CRON_HOOK = 'barmbini_core_cache_maintenance';

	const SCHEDULE = 'barmbini_core_6_hours';

	const INTERVAL = 6 * HOUR_IN_SECONDS;

	/**
	 * Registriert die Cron-Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_maintenance' ) );
	}

	/**
	 * Registriert das benutzerdefinierte Intervall (alle 6 Stunden).
	 *
	 * @param array $schedules Bestehende Cron-Intervalle.
	 * @return array
	 */
	public function register_schedule( $schedules ) {
		if ( isset( $schedules[ self::SCHEDULE ] ) ) {
			return $schedules;
		}

		$schedules[ self::SCHEDULE ] = array(
			'interval' => self::INTERVAL,
			'display'  => 'Alle 6 Stunden',
		);

		return $schedules;
	}

	/**
	 * Plant das wiederkehrende Ereignis, falls noch nicht geplant.
	 *
	 * @return void
	 */
	public function schedule_event() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + self::INTERVAL, self::SCHEDULE, self::CRON_HOOK );
	}

	/**
	 * Leert den WP Fastest Cache und den Objekt-Cache.
	 *
	 * @return void
	 */
	public function run_maintenance() {
		// WP Fastest Cache über den offiziellen Hook leeren, falls aktiv.
		if ( has_action( 'wpfc_clear_all_cache' ) ) {
			do_action( 'wpfc_clear_all_cache' );
		}

		// Fallback: Cache-Ordner direkt leeren (falls das Plugin nicht reagiert hat).
		$cache_dir = WP_CONTENT_DIR . '/cache/all';

		if ( is_dir( $cache_dir ) ) {
			$this->delete_directory_contents( $cache_dir );
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Leert den Inhalt eines Verzeichnisses rekursiv (ohne das Verzeichnis selbst).
	 *
	 * @param string $dir Absoluter Pfad.
	 * @return void
	 */
	protected function delete_directory_contents( $dir ) {
		$items = glob( trailingslashit( $dir ) . '*' );

		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( is_dir( $item ) ) {
				$this->delete_directory_contents( $item );
				@rmdir( $item );
			} elseif ( is_file( $item ) ) {
				@unlink( $item );
			}
		}
	}
}
