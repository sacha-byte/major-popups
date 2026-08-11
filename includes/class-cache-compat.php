<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Le code d'embed Lead Manager est injecté dynamiquement en JS au moment où le popup s'affiche
 * (pas de <script> statique dans le HTML) — WP Rocket "Delay JS" ne le voit donc jamais au
 * moment de la réécriture du cache et n'a rien à exclure pour lui.
 *
 * En revanche, major-popups.js LUI-MÊME doit démarrer son minuteur dès le vrai chargement de
 * page : si WP Rocket le retarde jusqu'à une interaction (scroll/clic/mousemove), un délai
 * "20s" ne se déclenche jamais pour un visiteur qui ne bouge pas — cf. gotcha documenté dans
 * lead-manager/CONTEXT.md (WP Rocket "Delay JavaScript execution").
 */
class MPP_Cache_Compat {

	public static function init(): void {
		add_filter( 'rocket_delay_js_exclusions', [ __CLASS__, 'exclude_from_delay_js' ] );
	}

	public static function exclude_from_delay_js( array $exclusions ): array {
		$exclusions[] = 'major-popups/assets/js/major-popups.js';
		return $exclusions;
	}
}
