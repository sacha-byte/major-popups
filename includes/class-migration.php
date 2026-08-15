<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Migration automatique du ciblage vers le modèle groupes/filtres (v0.2.0).
 * Exécutée une fois (option `mpp_db_version` comparée à MPP_VERSION) — conçue pour
 * tourner aussi bien en local qu'au futur déploiement Kinsta, pas un script jetable.
 */
class MPP_Migration {

	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_run' ] );
	}

	public static function maybe_run(): void {
		if ( get_option( 'mpp_db_version' ) === MPP_VERSION ) {
			return;
		}
		self::migrate_targeting_to_groups();
		update_option( 'mpp_db_version', MPP_VERSION );
	}

	/**
	 * Convertit l'ancien ciblage à mode exclusif (post_types/specific_posts/
	 * taxonomy_terms) en un groupe unique contenant un filtre équivalent — sans
	 * perte, chacun de ces 3 anciens modes a un équivalent exact à un seul filtre
	 * dans le nouveau système. Les popups déjà en "all" (ou déjà migrés en
	 * "filters") ne changent pas.
	 */
	private static function migrate_targeting_to_groups(): void {
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return; // ACF pas encore chargé à ce stade -- se rejouera au prochain chargement
		}

		$popups = get_posts( [
			'post_type'      => 'mp_popup',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $popups as $popup_id ) {
			$mode = get_field( 'targeting_mode', $popup_id );
			$filter = null;

			switch ( $mode ) {
				case 'post_types':
					$filter = [
						'filter_type'       => 'post_type',
						'filter_post_types' => (array) get_field( 'targeting_post_types', $popup_id ),
					];
					break;

				case 'specific_posts':
					$filter = [
						'filter_type'  => 'specific_posts',
						'filter_posts' => (array) get_field( 'targeting_posts', $popup_id ),
					];
					break;

				case 'taxonomy_terms':
					$taxonomy = get_field( 'targeting_taxonomy', $popup_id );
					if ( $taxonomy ) {
						$filter = [
							'filter_type'                => 'taxonomy_term',
							'filter_taxonomy'            => $taxonomy,
							"filter_terms_{$taxonomy}"   => (array) get_field( "targeting_terms_{$taxonomy}", $popup_id ),
						];
					}
					break;

				default:
					continue 2; // 'all', 'filters' (déjà migré), ou vide -- rien à faire
			}

			if ( ! $filter ) {
				continue;
			}

			update_field( 'targeting_groups', [ [ 'filters' => [ $filter ] ] ], $popup_id );
			update_field( 'targeting_mode', 'filters', $popup_id );
		}
	}
}
