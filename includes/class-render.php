<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sélectionne les popups publiés qui matchent la page courante et injecte, en `wp_footer`,
 * le markup caché (code d'embed) + une config JSON par popup. `major-popups.js` décide ensuite,
 * côté client, lequel déclencher en premier (cookie déjà posé, délai, scroll).
 */
class MPP_Render {

	/** Rempli par matching_popups(), lu par MPP_Assets pour savoir s'il faut enqueue les assets. */
	private static array $matched = [];

	public static function init(): void {
		add_action( 'wp_footer', [ __CLASS__, 'render' ] );
	}

	public static function matching_popups(): array {
		if ( ! empty( self::$matched ) ) {
			return self::$matched;
		}

		$popups = get_posts( [
			'post_type'      => 'mp_popup',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		self::$matched = array_values( array_filter( $popups, [ 'MPP_Targeting', 'matches_current_request' ] ) );

		return self::$matched;
	}

	public static function render(): void {
		$popup_ids = self::matching_popups();
		if ( empty( $popup_ids ) ) {
			return;
		}

		$configs = [];

		foreach ( $popup_ids as $popup_id ) {
			$trigger_type = get_field( 'trigger_type', $popup_id ) ?: 'delay';

			$configs[] = [
				'id'            => $popup_id,
				'triggerType'   => $trigger_type,
				'delaySeconds'  => (int) get_field( 'trigger_delay_seconds', $popup_id ),
				'scrollPercent' => (int) get_field( 'trigger_scroll_percent', $popup_id ),
				'cookieEnabled' => (bool) get_field( 'cookie_enabled', $popup_id ),
				'cookieDays'    => (int) get_field( 'cookie_days', $popup_id ),
				'cookieName'    => "mp_popup_{$popup_id}",
			];
			?>
			<template id="mpp-content-<?php echo (int) $popup_id; ?>"><?php echo get_field( 'embed_code', $popup_id ); // contenu admin de confiance, non échappé volontairement ?></template>
			<?php
		}
		?>
		<script type="application/json" id="mpp-config"><?php echo wp_json_encode( $configs ); ?></script>
		<?php
	}
}
