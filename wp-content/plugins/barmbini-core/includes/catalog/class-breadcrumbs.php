<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Catalog_Breadcrumbs {
	public function render() {
		if ( ! function_exists( 'woocommerce_breadcrumb' ) ) {
			return;
		}

		woocommerce_breadcrumb();
	}

	public function inject_sortiment_crumb( $crumbs ) {
		if ( is_page( 'sortiment' ) || is_shop() ) {
			return $crumbs;
		}

		$sortiment_page = get_page_by_path( 'sortiment' );

		if ( ! $sortiment_page ) {
			return $crumbs;
		}

		$sortiment_url = get_permalink( $sortiment_page );

		foreach ( $crumbs as $crumb ) {
			if ( isset( $crumb[1] ) && $crumb[1] === $sortiment_url ) {
				return $crumbs;
			}
		}

		array_splice( $crumbs, 1, 0, array( array( 'Sortiment', $sortiment_url ) ) );

		return $crumbs;
	}
}