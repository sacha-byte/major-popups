<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * N'enqueue CSS/JS que sur les pages où au moins un popup matche — pas de poids mort ailleurs.
 * Versionné par filemtime() (jamais une constante figée) — même leçon que major-master-v2.
 */
class MPP_Assets {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
	}

	public static function maybe_enqueue(): void {
		if ( empty( MPP_Render::matching_popups() ) ) {
			return;
		}

		$css = MPP_PATH . 'assets/css/major-popups.css';
		$js  = MPP_PATH . 'assets/js/major-popups.js';

		wp_enqueue_style( 'major-popups', MPP_URL . 'assets/css/major-popups.css', [], filemtime( $css ) );
		wp_enqueue_script( 'major-popups', MPP_URL . 'assets/js/major-popups.js', [], filemtime( $js ), true );
	}
}
