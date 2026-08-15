<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Migrations automatiques du ciblage, une fois chacune (option `mpp_db_version`
 * comparée à MPP_VERSION) — conçues pour tourner aussi bien en local qu'au futur
 * déploiement Kinsta, pas des scripts jetables.
 *
 * Historique du modèle de ciblage :
 * - v0.1.0 : mode exclusif (all / post_types / specific_posts / taxonomy_terms)
 * - v0.2.0 : groupes (OU) de filtres (ET), repeater imbriqué -- UI jugée confuse
 *   à l'usage (retour du 2026-08-15 : boutons "ajouter un groupe/filtre" peu clairs)
 * - v0.3.0 : liste plate de filtres + connecteur ET/OU par ligne -- même logique,
 *   présentation simplifiée, mais ambiguë dès 3 filtres ou plus (retour du
 *   2026-08-15 également : pas évident si "1 ET 2 OU 3" = (1 ET 2) OU 3 ou
 *   1 ET (2 OU 3) en lisant juste une liste de connecteurs)
 * - v0.4.0 (actuel) : retour aux groupes imbriqués (comme v0.2.0), mais avec des
 *   libellés différenciés sans ambiguïté ("+ Ajouter un groupe (OU)" au niveau
 *   racine vs "+ Ajouter un filtre à CE groupe" à l'intérieur de chaque groupe)
 *   et `min: 1` sur les deux niveaux pour ne jamais démarrer sur un état vide
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
		self::migrate_flat_conditions_to_groups();

		update_option( 'mpp_db_version', MPP_VERSION );
	}

	/**
	 * v0.1.0 → v0.4.0 direct : convertit l'ancien ciblage à mode exclusif
	 * (post_types/specific_posts/taxonomy_terms) en un groupe unique contenant un
	 * filtre équivalent dans `targeting_groups` — sans perte. Les popups déjà en
	 * "all" (ou déjà migrés en "filters") ne changent pas.
	 */
	private static function migrate_legacy_exclusive_mode(): void {
		foreach ( self::all_popup_ids() as $popup_id ) {
			$mode   = get_field( 'targeting_mode', $popup_id );
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

			update_field( 'targeting_groups', [ [ 'filters' => [ $filter ] ] ], $popup_id );
			update_field( 'targeting_mode', 'filters', $popup_id );
		}
	}

	/**
	 * v0.3.0 → v0.4.0 : reconstruit `targeting_groups` (groupes imbriqués) à partir
	 * de l'ancien `targeting_conditions` (liste plate + connecteur ET/OU) — "or"
	 * démarre un nouveau groupe, "and" (ou 1ère ligne) rejoint le groupe courant,
	 * même règle que MPP_Targeting avant ce changement.
	 *
	 * Lecture en `get_post_meta()` brut plutôt que `get_field()` : les champs ACF
	 * `field_mpp_targeting_conditions`/`field_mpp_condition_connector` ne sont plus
	 * enregistrés (remplacés par `field_mpp_targeting_groups` dans
	 * class-acf-fields.php) — `get_field()` sur un nom de champ qu'ACF ne connaît
	 * plus renvoie le compteur brut du repeater, pas les lignes (déjà rencontré et
	 * corrigé lors de la migration précédente, même piège).
	 */
	private static function migrate_flat_conditions_to_groups(): void {
		foreach ( self::all_popup_ids() as $popup_id ) {
			if ( get_field( 'targeting_mode', $popup_id ) !== 'filters' ) {
				continue;
			}
			if ( ! empty( get_field( 'targeting_groups', $popup_id ) ) ) {
				continue; // déjà en groupes (migré par migrate_legacy_exclusive_mode, ou déjà v0.4.0)
			}

			$count = (int) get_post_meta( $popup_id, 'targeting_conditions', true );
			if ( ! $count ) {
				continue;
			}

			$groups  = [];
			$current = [];

			for ( $i = 0; $i < $count; $i++ ) {
				$prefix      = "targeting_conditions_{$i}_";
				$connector   = get_post_meta( $popup_id, "{$prefix}connector", true ) ?: 'and';
				$filter_type = get_post_meta( $popup_id, "{$prefix}filter_type", true );
				if ( ! $filter_type ) {
					continue;
				}

				if ( $i > 0 && $connector === 'or' ) {
					if ( ! empty( $current ) ) {
						$groups[] = [ 'filters' => $current ];
					}
					$current = [];
				}

				$filter = [ 'filter_type' => $filter_type ];
				foreach ( [ 'filter_post_types', 'filter_posts', 'filter_taxonomy' ] as $suffix ) {
					$value = get_post_meta( $popup_id, "{$prefix}{$suffix}", true );
					if ( $value !== '' && $value !== false ) {
						$filter[ $suffix ] = $value;
					}
				}
				if ( $filter_type === 'taxonomy_term' && ! empty( $filter['filter_taxonomy'] ) ) {
					$taxonomy                             = $filter['filter_taxonomy'];
					$filter[ "filter_terms_{$taxonomy}" ] = get_post_meta( $popup_id, "{$prefix}filter_terms_{$taxonomy}", true );
				}

				$current[] = $filter;
			}
			if ( ! empty( $current ) ) {
				$groups[] = [ 'filters' => $current ];
			}

			if ( empty( $groups ) ) {
				continue;
			}

			update_field( 'targeting_groups', $groups, $popup_id );
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
