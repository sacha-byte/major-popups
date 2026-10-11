<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Couche REST du plugin, ajoutée pour admin.2empower.com (panneau de rédaction hors WP,
 * cf. Module 7 "Simplification admin" de major-prepa-wp/ROADMAP.md, Niveau 3). Même logique
 * que major-master-v2/includes/class-rest-api.php : `mp_popup` n'a pas `show_in_rest` (CPT non
 * public, cf. class-cpt.php) et ses champs ACF (repeaters imbriqués groupes/filtres) ne se
 * prêtent de toute façon pas au REST générique — on sérialise nous-mêmes.
 *
 * IMPORTANT — non testé en conditions réelles (DevKinsta éteint au moment de l'écriture,
 * cf. CONTEXT.md du projet content-admin). À valider avant tout déploiement.
 */
class MPP_Rest_Api {

	const NS = 'mp/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function require_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	public static function register_routes(): void {
		$auth = [ __CLASS__, 'require_edit_posts' ];

		register_rest_route( self::NS, '/popups', [
			[ 'methods' => 'GET', 'callback' => [ __CLASS__, 'list_popups' ], 'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_popup' ], 'permission_callback' => $auth ],
		] );
		register_rest_route( self::NS, '/popups/(?P<id>\d+)', [
			[ 'methods' => 'GET', 'callback' => [ __CLASS__, 'get_popup' ], 'permission_callback' => $auth ],
			[ 'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_popup' ], 'permission_callback' => $auth ],
			[ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_popup' ], 'permission_callback' => $auth ],
		] );
		register_rest_route( self::NS, '/targeting-options', [
			'methods' => 'GET', 'callback' => [ __CLASS__, 'targeting_options' ], 'permission_callback' => $auth,
		] );
	}

	/* ============================================================
	   Sérialisation
	   ============================================================ */

	/** L'image ACF de `mp_popup` a `return_format => 'url'` (cf. class-acf-fields.php) :
	 * get_field() renvoie une simple URL, pas l'ID — on relit l'ID brut en meta pour pouvoir le
	 * renvoyer au format {id,url,alt} attendu par l'éditeur (cf. major-master-v2 image_to_array). */
	private static function image_field_to_array( int $post_id ): ?array {
		$attachment_id = (int) get_post_meta( $post_id, 'image', true );
		if ( ! $attachment_id ) return null;
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) return null;
		return [ 'id' => $attachment_id, 'url' => $url, 'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '' ];
	}

	private static function filter_to_array( array $filter ): array {
		$type = $filter['filter_type'] ?? 'post_type';
		switch ( $type ) {
			case 'specific_posts':
				return [ 'type' => 'specific_posts', 'postIds' => array_map( 'intval', (array) ( $filter['filter_posts'] ?? [] ) ) ];
			case 'taxonomy_term':
				$taxonomy = $filter['filter_taxonomy'] ?? 'category';
				return [
					'type'     => 'taxonomy_term',
					'taxonomy' => $taxonomy,
					'termIds'  => array_map( 'intval', (array) ( $filter[ "filter_terms_{$taxonomy}" ] ?? [] ) ),
				];
			case 'front_page':
				return [ 'type' => 'front_page' ];
			default:
				return [ 'type' => 'post_type', 'postTypes' => array_values( (array) ( $filter['filter_post_types'] ?? [] ) ) ];
		}
	}

	private static function filter_from_array( array $filter ): array {
		$type = $filter['type'] ?? 'post_type';
		$row = [ 'filter_type' => $type ];
		switch ( $type ) {
			case 'specific_posts':
				$row['filter_posts'] = array_map( 'intval', (array) ( $filter['postIds'] ?? [] ) );
				break;
			case 'taxonomy_term':
				$taxonomy = $filter['taxonomy'] ?? 'category';
				$row['filter_taxonomy'] = $taxonomy;
				$row[ "filter_terms_{$taxonomy}" ] = array_map( 'intval', (array) ( $filter['termIds'] ?? [] ) );
				break;
			case 'front_page':
				break;
			default:
				$row['filter_type'] = 'post_type';
				$row['filter_post_types'] = array_values( (array) ( $filter['postTypes'] ?? [] ) );
				break;
		}
		return $row;
	}

	private static function popup_to_array( WP_Post $post ): array {
		$trigger_type = get_field( 'trigger_type', $post->ID ) ?: 'delay';
		$trigger = 'scroll' === $trigger_type
			? [ 'type' => 'scroll', 'scrollPercent' => (int) get_field( 'trigger_scroll_percent', $post->ID ) ]
			: [ 'type' => 'delay', 'delaySeconds' => (int) get_field( 'trigger_delay_seconds', $post->ID ) ];

		$targeting_mode = get_field( 'targeting_mode', $post->ID ) ?: 'all';
		$targeting = [ 'mode' => 'all' ];
		if ( 'filters' === $targeting_mode ) {
			$groups = [];
			foreach ( (array) get_field( 'targeting_groups', $post->ID ) as $group ) {
				$filters = [];
				foreach ( (array) ( $group['filters'] ?? [] ) as $filter ) {
					$filters[] = self::filter_to_array( (array) $filter );
				}
				$groups[] = [ 'filters' => $filters ];
			}
			$targeting = [ 'mode' => 'filters', 'groups' => $groups ];
		}

		return [
			'id'           => $post->ID,
			'status'       => 'publish' === $post->post_status ? 'publish' : 'draft',
			'title'        => get_the_title( $post ),
			'embedCode'    => (string) get_field( 'embed_code', $post->ID ),
			'lienDirect'   => (string) get_field( 'lien_direct', $post->ID ),
			'image'        => self::image_field_to_array( $post->ID ),
			'imageCaption' => (string) get_field( 'image_caption', $post->ID ),
			'trigger'      => $trigger,
			'cookie'       => [
				'enabled' => (bool) get_field( 'cookie_enabled', $post->ID ),
				'days'    => (int) get_field( 'cookie_days', $post->ID ),
			],
			'targeting'    => $targeting,
		];
	}

	private static function apply_popup_fields( int $post_id, array $body ): void {
		if ( ! function_exists( 'update_field' ) ) return;

		if ( isset( $body['embedCode'] ) ) update_field( 'embed_code', $body['embedCode'], $post_id );
		if ( isset( $body['lienDirect'] ) ) update_field( 'lien_direct', $body['lienDirect'], $post_id );
		if ( array_key_exists( 'imageId', $body ) ) update_field( 'image', $body['imageId'] ?: null, $post_id );
		if ( isset( $body['imageCaption'] ) ) update_field( 'image_caption', $body['imageCaption'], $post_id );

		if ( isset( $body['trigger']['type'] ) ) {
			update_field( 'trigger_type', $body['trigger']['type'], $post_id );
			if ( 'scroll' === $body['trigger']['type'] ) {
				update_field( 'trigger_scroll_percent', (int) ( $body['trigger']['scrollPercent'] ?? 50 ), $post_id );
			} else {
				update_field( 'trigger_delay_seconds', (int) ( $body['trigger']['delaySeconds'] ?? 20 ), $post_id );
			}
		}

		if ( isset( $body['cookie'] ) ) {
			update_field( 'cookie_enabled', ! empty( $body['cookie']['enabled'] ), $post_id );
			update_field( 'cookie_days', (int) ( $body['cookie']['days'] ?? 30 ), $post_id );
		}

		if ( isset( $body['targeting']['mode'] ) ) {
			update_field( 'targeting_mode', $body['targeting']['mode'], $post_id );
			if ( 'filters' === $body['targeting']['mode'] ) {
				$groups = [];
				foreach ( (array) ( $body['targeting']['groups'] ?? [] ) as $group ) {
					$filters = [];
					foreach ( (array) ( $group['filters'] ?? [] ) as $filter ) {
						$filters[] = self::filter_from_array( (array) $filter );
					}
					$groups[] = [ 'filters' => $filters ];
				}
				update_field( 'targeting_groups', $groups, $post_id );
			}
		}
	}

	/* ============================================================
	   Routes
	   ============================================================ */

	public static function list_popups(): WP_REST_Response {
		$posts = get_posts( [
			'post_type'      => 'mp_popup',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$items = array_map( static function ( WP_Post $post ) {
			$trigger_type = get_field( 'trigger_type', $post->ID );
			$trigger = 'scroll' === $trigger_type
				? sprintf( 'Scroll %s%%', get_field( 'trigger_scroll_percent', $post->ID ) )
				: sprintf( 'Délai %ss', get_field( 'trigger_delay_seconds', $post->ID ) );

			return [
				'id'        => $post->ID,
				'status'    => 'publish' === $post->post_status ? 'publish' : 'draft',
				'title'     => get_the_title( $post ),
				'trigger'   => $trigger,
				'targeting' => MPP_Targeting::describe( $post->ID ),
			];
		}, $posts );

		return new WP_REST_Response( $items );
	}

	public static function get_popup( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'mp_popup' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Popup introuvable', [ 'status' => 404 ] );
		}
		return new WP_REST_Response( self::popup_to_array( $post ) );
	}

	public static function create_popup( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$post_id = wp_insert_post( [
			'post_type'   => 'mp_popup',
			'post_status' => 'publish' === ( $body['status'] ?? 'draft' ) ? 'publish' : 'draft',
			'post_title'  => sanitize_text_field( $body['title'] ?? '' ),
		], true );

		if ( is_wp_error( $post_id ) ) return $post_id;

		self::apply_popup_fields( $post_id, $body );
		return new WP_REST_Response( self::popup_to_array( get_post( $post_id ) ), 201 );
	}

	public static function update_popup( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'mp_popup' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Popup introuvable', [ 'status' => 404 ] );
		}

		$body = $request->get_json_params();
		$update = [ 'ID' => $post->ID ];
		if ( isset( $body['title'] ) ) $update['post_title'] = sanitize_text_field( $body['title'] );
		if ( isset( $body['status'] ) ) $update['post_status'] = 'publish' === $body['status'] ? 'publish' : 'draft';

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) return $result;

		self::apply_popup_fields( $post->ID, $body );
		return new WP_REST_Response( self::popup_to_array( get_post( $post->ID ) ) );
	}

	public static function delete_popup( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'mp_popup' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Popup introuvable', [ 'status' => 404 ] );
		}
		wp_trash_post( $post->ID );
		return new WP_REST_Response( [ 'deleted' => true ] );
	}

	public static function targeting_options(): WP_REST_Response {
		$post_types = [
			[ 'slug' => 'post', 'label' => 'Articles' ],
			[ 'slug' => 'page', 'label' => 'Pages' ],
			[ 'slug' => 'academique', 'label' => 'Académique' ],
			[ 'slug' => 'master', 'label' => 'Master' ],
			[ 'slug' => 'prepa', 'label' => 'Prépa' ],
		];

		$taxonomies = [];
		foreach ( MPP_ACF_Fields::TAXONOMIES as $slug => $label ) {
			$terms = get_terms( [ 'taxonomy' => $slug, 'hide_empty' => false, 'orderby' => 'name' ] );
			$taxonomies[ $slug ] = [
				'label' => $label,
				'terms' => array_map( static function ( WP_Term $term ) {
					return [ 'id' => $term->term_id, 'name' => $term->name ];
				}, is_wp_error( $terms ) ? [] : $terms ),
			];
		}

		return new WP_REST_Response( [ 'postTypes' => $post_types, 'taxonomies' => $taxonomies ] );
	}
}
