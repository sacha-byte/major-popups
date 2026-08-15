<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Migrations automatiques du ciblage, une fois chacune (option `mpp_db_version`
 * comparée à MPP_VERSION) — conçues pour tourner aussi bien en local qu'au futur
 * déploiement Kinsta, pas des scripts jetables.
 *
 * Historique du modèle de ciblage :
 * - v0.1.0 : mode exclusif (all / post_types / specific_posts / taxonomy_terms)
 * - v0.2.0 : groupes (OU) de filtres (ET), repeater imbriqué -- vite remplacé,
 *   l'UI à repeaters imbriqués s'est révélée confuse à l'usage (retour du
 *   2026-08-15 : boutons "ajouter un groupe/filtre" peu clairs)
 * - v0.3.0 (actuel) : liste plate de filtres, chacun avec un connecteur ET/OU
 *   relatif au filtre précédent -- même logique (des groupes ET sont reconstruits
 *   en interne, séparés par un connecteur OU), présentation admin simplifiée
 */
class MPP_Migration {

	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_run' ] );
	}

	public static function maybe_run(): void {
		if ( get_option( 'mpp_db_version' ) === MPP_VERSION ) {
			return;
		}
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return; // ACF pas encore chargé à ce stade -- se rejouera au prochain chargement
		}

		self::migrate_legacy_exclusive_mode();
		self::migrate_nested_groups();

		update_option( 'mpp_db_version', MPP_VERSION );
	}

	/**
	 * v0.1.0 → v0.3.0 direct : convertit l'ancien ciblage à mode exclusif
	 * (post_types/specific_posts/taxonomy_terms) en un unique filtre équivalent
	 * dans `targeting_conditions` — sans perte, chacun de ces 3 anciens modes a un
	 * équivalent exact à un seul filtre. Les popups déjà en "all" (ou déjà migrés
	 * en "filters") ne changent pas.
	 */
	private static function migrate_legacy_exclusive_mode(): void {
		foreach ( self::all_popup_ids() as $popup_id ) {
			$mode   = get_field( 'targeting_mode', $popup_id );
			$filter = null;

			switch ( $mode ) {
				case 'post_types':
					$filter = [
						'connector'         => 'and',
						'filter_type'       => 'post_type',
						'filter_post_types' => (array) get_field( 'targeting_post_types', $popup_id ),
					];
					break;

				case 'specific_posts':
					$filter = [
						'connector'    => 'and',
						'filter_type'  => 'specific_posts',
						'filter_posts' => (array) get_field( 'targeting_posts', $popup_id ),
					];
					break;

				case 'taxonomy_terms':
					$taxonomy = get_field( 'targeting_taxonomy', $popup_id );
					if ( $taxonomy ) {
						$filter = [
							'connector'                 => 'and',
							'filter_type'               => 'taxonomy_term',
							'filter_taxonomy'           => $taxonomy,
							"filter_terms_{$taxonomy}"  => (array) get_field( "targeting_terms_{$taxonomy}", $popup_id ),
						];
					}
					break;

				default:
					continue 2; // 'all', 'filters' (déjà migré), ou vide -- rien à faire
			}

			if ( ! $filter ) {
				continue;
			}

			update_field( 'targeting_conditions', [ $filter ], $popup_id );
			update_field( 'targeting_mode', 'filters', $popup_id );
		}
	}

	/**
	 * v0.2.0 → v0.3.0 : aplatit l'ancien `targeting_groups` (groupes de filtres
	 * imbriqués, format éphémère) en `targeting_conditions` (liste plate +
	 * connecteur) -- ne s'applique qu'aux popups déjà passés par le format v0.2.0
	 * (typiquement cet environnement local, qui a tourné les deux versions).
	 *
	 * Lecture en `get_post_meta()` brut plutôt que `get_field()` : les champs ACF
	 * `field_mpp_targeting_groups`/`field_mpp_group_filters` ne sont plus
	 * enregistrés (remplacés par `field_mpp_targeting_conditions` dans
	 * class-acf-fields.php) — `get_field()` sur un nom de champ qu'ACF ne connaît
	 * plus plante silencieusement (renvoie le compteur brut du repeater, pas les
	 * lignes). `get_post_meta()` core, lui, dé-sérialise correctement quel que
	 * soit l'état d'enregistrement ACF du moment.
	 */
	private static function migrate_nested_groups(): void {
		foreach ( self::all_popup_ids() as $popup_id ) {
			if ( get_field( 'targeting_mode', $popup_id ) !== 'filters' ) {
				continue;
			}
			if ( ! empty( get_field( 'targeting_conditions', $popup_id ) ) ) {
				continue; // déjà en liste plate (migré par migrate_legacy_exclusive_mode, ou déjà v0.3.0)
			}

			$group_count = (int) get_post_meta( $popup_id, 'targeting_groups', true );
			if ( ! $group_count ) {
				continue;
			}

			$conditions = [];
			for ( $gi = 0; $gi < $group_count; $gi++ ) {
				$filter_count = (int) get_post_meta( $popup_id, "targeting_groups_{$gi}_filters", true );

				for ( $fi = 0; $fi < $filter_count; $fi++ ) {
					$prefix      = "targeting_groups_{$gi}_filters_{$fi}_";
					$filter_type = get_post_meta( $popup_id, "{$prefix}filter_type", true );
					if ( ! $filter_type ) {
						continue;
					}

					$filter = [
						'connector'   => ( $fi === 0 ) ? 'or' : 'and',
						'filter_type' => $filter_type,
					];

					foreach ( [ 'filter_post_types', 'filter_posts', 'filter_taxonomy' ] as $suffix ) {
						$value = get_post_meta( $popup_id, "{$prefix}{$suffix}", true );
						if ( $value !== '' && $value !== false ) {
							$filter[ $suffix ] = $value;
						}
					}
					if ( $filter_type === 'taxonomy_term' && ! empty( $filter['filter_taxonomy'] ) ) {
						$taxonomy                              = $filter['filter_taxonomy'];
						$filter[ "filter_terms_{$taxonomy}" ]  = get_post_meta( $popup_id, "{$prefix}filter_terms_{$taxonomy}", true );
					}

					$conditions[] = $filter;
				}
			}

			if ( empty( $conditions ) ) {
				continue;
			}
			$conditions[0]['connector'] = 'and'; // 1ère ligne : connecteur ignoré, "and" par convention

			update_field( 'targeting_conditions', $conditions, $popup_id );
		}
	}

	private static function all_popup_ids(): array {
		return get_posts( [
			'post_type'      => 'mp_popup',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
	}
}
