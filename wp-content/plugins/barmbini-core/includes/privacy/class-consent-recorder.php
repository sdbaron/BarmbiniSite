<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Consent_Recorder {
	public function record( $user_id, $previous_settings, $new_settings, $timestamp, $source ) {
		$settings = new Barmbini_Core_Subscription_Settings();

		$settings->update_timestamp( $user_id, $timestamp );

		$had_subscription = $settings->has_any_subscription( $previous_settings );
		$has_subscription = $settings->has_any_subscription( $new_settings );

		if ( ! $has_subscription ) {
			return;
		}

		if ( ! $had_subscription || ! get_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CONSENT_AT, true ) ) {
			$settings->update_consent( $user_id, $timestamp, $source );
		}

		$settings->refresh_unsubscribe_seed( $user_id );
	}
}