<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Catalog_Category_Display {
	public function render_subcategory_description( $category ) {
		if ( empty( $category ) || empty( $category->description ) ) {
			return;
		}

		echo '<div class="barmbini-category-description">' . wp_kses_post( $category->description ) . '</div>';
	}
}