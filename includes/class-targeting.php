<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Évalue si un `mp_popup` doit s'afficher sur la requête courante.
 *
 * `targeting_mode` : `all` (tout le site) ou `filters` (`targeting_conditions`, une
 * liste PLATE de filtres, chacun portant un connecteur ET/OU relatif à la ligne
 * précédente — pas de repeater imbriqué, simplifié le 2026-08-15 suite à un retour
 * utilisateur sur la confusion de l'UI à groupes/filtres imbriqués). On reconstruit
 * en interne des groupes (ET) séparés par un connecteur OU : le popup matche si AU
 * MOINS UN groupe matche entièrement — même logique qu'avant, présentation admin
 * différente.
 */
class MPP_Targeting {

	public static function matches_current_request( int $popup_id ): bool {
		$mode = get_field( 'targeting_mode', $popup_id );

		if ( $mode === 'all' ) {
			return true; // couvre aussi la page d'accueil, les archives, etc.
		}

		$groups = self::conditions_to_groups( (array) get_field( 'targeting_conditions', $popup_id ) );
		if ( empty( $groups ) ) {
			return false; // mode "filters" sans aucun filtre configuré = ne matche rien, pas tout
		}

		if ( is_admin() ) {
			return false;
		}

		foreach ( $groups as $group ) {
			if ( self::group_matches( $group ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reconstruit des groupes (ET) à partir de la liste plate + connecteur : un
	 * connecteur "or" démarre un nouveau groupe, "and" (ou 1ère ligne) rejoint le
	 * groupe courant.
	 */
	private static function conditions_to_groups( array $conditions ): array {
		$groups = [];
		$current = [];

		foreach ( $conditions as $i => $condition ) {
			$connector = $condition['connector'] ?? 'and';
			if ( $i > 0 && $connector === 'or' ) {
				$groups[] = $current;
				$current  = [];
			}
			$current[] = $condition;
		}

		if ( ! empty( $current ) ) {
			$groups[] = $current;
		}

		return $groups;
	}

	/** Un groupe matche si tous ses filtres matchent (ET). */
	private static function group_matches( array $filters ): bool {
		foreach ( $filters as $filter ) {
			if ( ! self::filter_matches( (array) $filter ) ) {
				return false;
			}
		}
		return true;
	}

	private static function filter_matches( array $filter ): bool {
		$type = $filter['filter_type'] ?? '';

		if ( $type === 'front_page' ) {
			return is_front_page(); // seul type évaluable hors contenu singulier
		}

		if ( ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();

		switch ( $type ) {
			case 'post_type':
				$post_types = (array) ( $filter['filter_post_types'] ?? [] );
				return in_array( get_post_type( $post_id ), $post_types, true );

			case 'specific_posts':
				$ids = array_map( 'intval', (array) ( $filter['filter_posts'] ?? [] ) );
				return in_array( (int) $post_id, $ids, true );

			case 'taxonomy_term':
				$taxonomy = $filter['filter_taxonomy'] ?? '';
				if ( ! $taxonomy ) {
					return false;
				}
				$term_ids = (array) ( $filter[ "filter_terms_{$taxonomy}" ] ?? [] );
				if ( empty( $term_ids ) ) {
					return false;
				}
				return has_term( array_map( 'intval', $term_ids ), $taxonomy, $post_id );

			default:
				return false;
		}
	}

	/** Résumé lisible pour la colonne d'admin. */
	public static function describe( int $popup_id ): string {
		$mode = get_field( 'targeting_mode', $popup_id );

		if ( $mode === 'all' ) {
			return 'Tout le site';
		}

		$conditions = (array) get_field( 'targeting_conditions', $popup_id );
		if ( empty( $conditions ) ) {
			return '—';
		}

		$groups = self::conditions_to_groups( $conditions );

		return sprintf( '%d filtre(s), %d groupe(s)', count( $conditions ), count( $groups ) );
	}
}
