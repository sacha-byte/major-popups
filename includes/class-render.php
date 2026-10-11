<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sélectionne les popups publiés qui matchent la page courante et injecte, en `wp_footer`,
 * le markup caché (code d'embed, popups formulaire uniquement) + une config JSON par popup. `major-popups.js` décide ensuite,
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
			$content_type = get_field( 'content_type', $popup_id ) === 'visual' ? 'visual' : 'form';

			$config = [
				'id'            => $popup_id,
				'contentType'   => $content_type,
				'triggerType'   => $trigger_type,
				'delaySeconds'  => (int) get_field( 'trigger_delay_seconds', $popup_id ),
				'scrollPercent' => (int) get_field( 'trigger_scroll_percent', $popup_id ),
				'cookieEnabled' => (bool) get_field( 'cookie_enabled', $popup_id ),
				'cookieDays'    => (int) get_field( 'cookie_days', $popup_id ),
				'cookieName'    => "mp_popup_{$popup_id}",
			];

			if ( $content_type === 'visual' ) {
				$visual = self::visual_config( $popup_id );
				if ( ! $visual ) {
					continue; // visuel ou lien manquant : rien d'affichable
				}
				$configs[] = $config + $visual;
				continue;
			}

			$configs[] = $config + [
				'imageUrl'     => get_field( 'image', $popup_id ) ?: null,
				'imageCaption' => get_field( 'image_caption', $popup_id ) ?: '',
			];
			?>
			<template id="mpp-content-<?php echo (int) $popup_id; ?>"><?php echo get_field( 'embed_code', $popup_id ); // contenu admin de confiance, non échappé volontairement ?></template>
			<?php
		}
		?>
		<script type="application/json" id="mpp-config"><?php echo wp_json_encode( $configs ); ?></script>
		<?php
	}

	/**
	 * Popup "visuel cliquable" : pas de code d'embed, seulement une image de la médiathèque
	 * et un lien. On transmet src + srcset (le navigateur choisit la taille selon l'écran)
	 * et le ratio réel de l'image, pour que la carte ait la bonne hauteur dès l'ouverture
	 * et qu'un visuel portrait tienne toujours dans la hauteur de l'écran (cf. CSS .is-visual).
	 */
	private static function visual_config( int $popup_id ): ?array {
		$image_id = (int) get_field( 'visual_image', $popup_id );
		$link     = get_field( 'visual_link', $popup_id );
		$src      = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;

		if ( ! $src || ! $link ) {
			return null;
		}

		$full = wp_get_attachment_image_src( $image_id, 'full' );
		$alt  = get_field( 'visual_alt', $popup_id ) ?: get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return [
			'visualSrc'    => $src[0],
			'visualSrcset' => wp_get_attachment_image_srcset( $image_id, 'full' ) ?: '',
			'visualRatio'  => ( $full && $full[2] ) ? round( $full[1] / $full[2], 4 ) : 0.8,
			'visualAlt'    => $alt ?: '',
			'linkUrl'      => esc_url_raw( $link ),
			'linkNewTab'   => (bool) get_field( 'visual_new_tab', $popup_id ),
		];
	}
}
