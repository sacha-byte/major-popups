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
		add_filter( 'rocket_exclude_css', [ __CLASS__, 'exclude_from_minify' ] );
		add_filter( 'rocket_exclude_js', [ __CLASS__, 'exclude_from_minify' ] );
		add_filter( 'rocket_rucss_safelist', [ __CLASS__, 'rucss_safelist' ] );
	}

	/**
	 * "Remove Unused CSS" (activé sur ce site) génère un CSS "utilisé" par page en analysant
	 * le HTML statique — jamais exécuté pour les visiteurs connectés (ils reçoivent la CSS
	 * complète), mais bien pour les visiteurs anonymes. Nos classes (.mpp-overlay, .mpp-card,
	 * .mpp-close...) n'apparaissent JAMAIS dans le HTML statique : elles n'existent que dans
	 * le DOM créé par major-popups.js au moment du déclenchement. Sans cette liste blanche,
	 * RUCSS les retire — le popup se construit alors sans aucun style (position normale dans
	 * la page), et closeBtn.focus() fait défiler le navigateur jusqu'à ce bloc non stylé en
	 * bas de page. Symptôme observé (2026-08-12) : "popup remplacé par un scroll vers le bas
	 * de la page, le formulaire y apparaît" — uniquement déconnecté. Même piège que documenté
	 * dans major-master-v2/includes/class-cache-compat.php.
	 */
	public static function rucss_safelist( array $safelist ): array {
		return array_unique( array_merge( $safelist, [ 'mpp-' ] ) );
	}

	public static function exclude_from_delay_js( array $exclusions ): array {
		$exclusions[] = 'major-popups/assets/js/major-popups.js';
		return $exclusions;
	}

	/**
	 * `rocket_exclude_css`/`rocket_exclude_js` matchent en `preg_match` ANCRÉ (^...$) sur le
	 * path complet de l'URL — un motif sans `.*` en préfixe ne matche jamais (même piège que
	 * documenté dans major-master-v2/includes/class-cache-compat.php). Sans cette exclusion,
	 * WP Rocket combine/minifie nos fichiers dans un bundle qui ne se régénère pas
	 * automatiquement quand on modifie le fichier source — on itère encore sur ce plugin,
	 * pas de gain à en tirer pour l'instant.
	 */
	public static function exclude_from_minify( array $excluded ): array {
		$excluded[] = '.*major-popups/assets/(css|js)/major-popups\.(css|js)';
		return $excluded;
	}
}
