<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Évalue si un `mp_popup` doit s'afficher sur la requête courante.
 *
 * `targeting_mode` : `all` (tout le site) ou `filters` (groupes de filtres, cf.
 * `targeting_groups` dans class-acf-fields.php). Un popup en mode `filters` matche
 * si AU MOINS UN groupe matche entièrement (OU entre groupes) ; un groupe matche
 * si TOUS ses filtres matchent (ET entre filtres du même groupe) — forme normale
 * disjonctive, même principe que Popup Maker/Elementor conditions.
 */
class MPP_Targeting {

	public static function matches_current_request( int $popup_id ): bool {
		$mode = get_field( 'targeting_mode', $popup_id );

		if ( $mode === 'all' ) {
			return true; // couvre aussi la page d'accueil, les archives, etc.
		}

		if ( is_admin() ) {
			return false;
		}

		$groups = (array) get_field( 'targeting_groups', $popup_id );
		if ( empty( $groups ) ) {
			return false; // mode "filters" sans aucun groupe configuré = ne matche rien, pas tout
		}

		foreach ( $groups as $group ) {
			if ( self::group_matches( (array) ( $group['filters'] ?? [] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/** Un groupe matche si tous ses filtres matchent (ET). Groupe vide = ne matche jamais. */
	private static function group_matches( array $filters ): bool {
		if ( empty( $filters ) ) {
			return false;
		}
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

		$groups = (array) get_field( 'targeting_groups', $popup_id );
		if ( empty( $groups ) ) {
			return '—';
		}

		$filter_count = 0;
		foreach ( $groups as $group ) {
			$filter_count += count( (array) ( $group['filters'] ?? [] ) );
		}

		return sprintf( '%d groupe(s), %d filtre(s)', count( $groups ), $filter_count );
	}
}
