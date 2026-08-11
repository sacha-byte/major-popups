<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CPT `mp_popup` — un popup = un déclenchement/ciblage/cookie + un code d'embed Lead Manager.
 * Non public : uniquement un objet de configuration piloté depuis l'admin, pas une page consultable.
 */
class MPP_CPT {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_filter( 'manage_mp_popup_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_mp_popup_posts_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
	}

	public static function register(): void {
		register_post_type( 'mp_popup', [
			'label'        => 'Popups',
			'labels'       => [
				'name'          => 'Popups',
				'singular_name' => 'Popup',
				'add_new_item'  => 'Ajouter un popup',
				'edit_item'     => 'Modifier le popup',
				'not_found'     => 'Aucun popup',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-megaphone',
			'supports'     => [ 'title' ],
			'capability_type' => 'page',
		] );
	}

	public static function columns( array $columns ): array {
		$columns['mpp_trigger']  = 'Déclenchement';
		$columns['mpp_targeting'] = 'Ciblage';
		$columns['mpp_cookie']   = 'Cookie';
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mpp_trigger':
				$type = get_field( 'trigger_type', $post_id );
				if ( $type === 'scroll' ) {
					printf( 'Scroll %s%%', esc_html( get_field( 'trigger_scroll_percent', $post_id ) ) );
				} else {
					printf( 'Délai %ss', esc_html( get_field( 'trigger_delay_seconds', $post_id ) ) );
				}
				break;

			case 'mpp_targeting':
				echo esc_html( MPP_Targeting::describe( $post_id ) );
				break;

			case 'mpp_cookie':
				if ( get_field( 'cookie_enabled', $post_id ) ) {
					printf( '%s j', esc_html( get_field( 'cookie_days', $post_id ) ) );
				} else {
					echo '—';
				}
				break;
		}
	}
}
