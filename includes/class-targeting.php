<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Évalue si un `mp_popup` doit s'afficher sur la requête courante, selon `targeting_mode`.
 */
class MPP_Targeting {

	public static function matches_current_request( int $popup_id ): bool {
		$mode = get_field( 'targeting_mode', $popup_id );

		if ( $mode === 'all' ) {
			return true; // couvre aussi la page d'accueil, les archives, etc.
		}

		if ( is_admin() || ! is_singular() ) {
			return false; // les autres modes ne savent évaluer que du contenu singulier
		}

		$post_id = get_queried_object_id();

		switch ( $mode ) {
			case 'post_types':
				$post_types = (array) get_field( 'targeting_post_types', $popup_id );
				return in_array( get_post_type( $post_id ), $post_types, true );

			case 'specific_posts':
				$ids = array_map( 'intval', (array) get_field( 'targeting_posts', $popup_id ) ); // return_format: id
				return in_array( (int) $post_id, $ids, true );

			case 'taxonomy_terms':
				$taxonomy = get_field( 'targeting_taxonomy', $popup_id );
				if ( ! $taxonomy ) {
					return false;
				}
				$term_ids = (array) get_field( "targeting_terms_{$taxonomy}", $popup_id );
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

		switch ( $mode ) {
			case 'all':
				return 'Tout le site';

			case 'post_types':
				$types = (array) get_field( 'targeting_post_types', $popup_id );
				return $types ? implode( ', ', $types ) : '—';

			case 'specific_posts':
				$posts = (array) get_field( 'targeting_posts', $popup_id );
				return count( $posts ) . ' contenu(s)';

			case 'taxonomy_terms':
				$taxonomy = get_field( 'targeting_taxonomy', $popup_id );
				$term_ids = (array) get_field( "targeting_terms_{$taxonomy}", $popup_id );
				return sprintf( '%s (%d terme(s))', $taxonomy, count( $term_ids ) );

			default:
				return '—';
		}
	}
}
