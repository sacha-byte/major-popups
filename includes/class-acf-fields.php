<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Champs ACF du CPT `mp_popup`, enregistrés en PHP (versionnés avec le code, jamais via l'UI —
 * même convention que major-master-v2/includes/class-acf-fields.php).
 *
 * Taxonomies choisies pour le ciblage (`category`, `filiere`, `prepa`, `matiere`, `ecole`) et
 * post types (`post`, `page`, `academique`, `master`, `prepa`) confirmés par audit direct de la
 * base DevKinsta (2026-08-11) — `academique` (8125 articles publiés) est le plus gros contenu du
 * site et la cible principale des popups existants.
 */
class MPP_ACF_Fields {

	const TAXONOMIES = [
		'category' => 'Catégorie',
		'filiere'  => 'Filière',
		'prepa'    => 'Prépa',
		'matiere'  => 'Matière',
		'ecole'    => 'École',
	];

	public static function init(): void {
		add_action( 'acf/init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Un champ "Termes" par taxonomie, sous-champ du repeater imbriqué "filtres"
		// (voir field_mpp_group_filters ci-dessous) — conditional_logic scopée au
		// sein d'une ligne de repeater.
		$filter_taxonomy_term_fields = [];
		foreach ( self::TAXONOMIES as $taxonomy => $label ) {
			$filter_taxonomy_term_fields[] = [
				'key'               => "field_mpp_filter_terms_{$taxonomy}",
				'name'              => "filter_terms_{$taxonomy}",
				'label'             => "Termes — {$label}",
				'type'              => 'taxonomy',
				'taxonomy'          => $taxonomy,
				'field_type'        => 'checkbox',
				'return_format'     => 'id',
				'conditional_logic' => [
					[
						[ 'field' => 'field_mpp_filter_type', 'operator' => '==', 'value' => 'taxonomy_term' ],
						[ 'field' => 'field_mpp_filter_taxonomy', 'operator' => '==', 'value' => $taxonomy ],
					],
				],
			];
		}

		acf_add_local_field_group( [
			'key'      => 'group_mpp_popup',
			'title'    => 'Configuration du popup',
			'fields'   => [
				[
					'key'         => 'field_mpp_embed_code',
					'name'        => 'embed_code',
					'label'       => 'Code d\'embed Lead Manager',
					'type'        => 'textarea',
					'rows'        => 3,
					'instructions' => 'Coller le code fourni par Lead Manager pour ce formulaire (slug se terminant en "-popup"), ex. <script src="https://app.2empower.com/static/embed-formulaire.js" data-slug="..."></script>',
				],
				[
					'key'   => 'field_mpp_lien_direct',
					'name'  => 'lien_direct',
					'label' => 'Lien direct (référence)',
					'type'  => 'url',
					'instructions' => 'Lien direct du formulaire sur app.2empower.com — informatif, non utilisé pour l\'affichage.',
				],
				[
					'key'   => 'field_mpp_tab_visuel',
					'label' => 'Visuel',
					'type'  => 'tab',
				],
				[
					'key'          => 'field_mpp_image',
					'name'         => 'image',
					'label'        => 'Image (couverture du guide, etc.)',
					'type'         => 'image',
					'return_format' => 'url',
					'preview_size' => 'medium',
					'instructions' => 'Si renseignée, le popup s\'affiche en 2 colonnes (image à gauche, formulaire à droite). Laisser vide pour garder une seule colonne.',
				],
				[
					'key'          => 'field_mpp_image_caption',
					'name'         => 'image_caption',
					'label'        => 'Légende sous l\'image',
					'type'         => 'text',
					'instructions' => 'Ex. "48 pages · PDF gratuit". Ignorée si pas d\'image.',
				],
				[
					'key'   => 'field_mpp_tab_trigger',
					'label' => 'Déclenchement',
					'type'  => 'tab',
				],
				[
					'key'     => 'field_mpp_trigger_type',
					'name'    => 'trigger_type',
					'label'   => 'Type de déclenchement',
					'type'    => 'select',
					'choices' => [
						'delay'  => 'Après un délai',
						'scroll' => 'Après un pourcentage de scroll',
					],
					'default_value' => 'delay',
				],
				[
					'key'               => 'field_mpp_trigger_delay_seconds',
					'name'              => 'trigger_delay_seconds',
					'label'             => 'Délai (secondes)',
					'type'              => 'number',
					'default_value'     => 20,
					'min'               => 0,
					'conditional_logic' => [ [ [ 'field' => 'field_mpp_trigger_type', 'operator' => '==', 'value' => 'delay' ] ] ],
				],
				[
					'key'               => 'field_mpp_trigger_scroll_percent',
					'name'              => 'trigger_scroll_percent',
					'label'             => 'Scroll (%)',
					'type'              => 'number',
					'default_value'     => 50,
					'min'               => 0,
					'max'               => 100,
					'conditional_logic' => [ [ [ 'field' => 'field_mpp_trigger_type', 'operator' => '==', 'value' => 'scroll' ] ] ],
				],
				[
					'key'   => 'field_mpp_tab_cookie',
					'label' => 'Cookie',
					'type'  => 'tab',
				],
				[
					'key'           => 'field_mpp_cookie_enabled',
					'name'          => 'cookie_enabled',
					'label'         => 'Se souvenir de la fermeture (cookie)',
					'type'          => 'true_false',
					'default_value' => 1,
					'ui'            => 1,
					'instructions'  => 'Si désactivé, le popup peut réapparaître à chaque page vue.',
				],
				[
					'key'               => 'field_mpp_cookie_days',
					'name'              => 'cookie_days',
					'label'             => 'Ne pas réafficher pendant (jours)',
					'type'              => 'number',
					'default_value'     => 30,
					'min'               => 1,
					'conditional_logic' => [ [ [ 'field' => 'field_mpp_cookie_enabled', 'operator' => '==', 'value' => '1' ] ] ],
				],
				[
					'key'   => 'field_mpp_tab_targeting',
					'label' => 'Ciblage',
					'type'  => 'tab',
				],
				[
					'key'     => 'field_mpp_targeting_mode',
					'name'    => 'targeting_mode',
					'label'   => 'Afficher sur',
					'type'    => 'select',
					'choices' => [
						'all'     => 'Tout le site',
						'filters' => 'Filtres (combinables)',
					],
					'default_value' => 'all',
				],
				[
					'key'               => 'field_mpp_targeting_groups',
					'name'              => 'targeting_groups',
					'label'             => 'Groupes de filtres (combinés en OU)',
					'type'              => 'repeater',
					'instructions'      => 'Le popup s\'affiche si AU MOINS UN groupe ci-dessous correspond entièrement à la page courante — c\'est le OU entre groupes. "+ Ajouter un groupe" crée une nouvelle alternative indépendante.',
					'button_label'      => '+ Ajouter un groupe (OU)',
					'layout'            => 'block',
					'min'               => 1,
					'conditional_logic' => [ [ [ 'field' => 'field_mpp_targeting_mode', 'operator' => '==', 'value' => 'filters' ] ] ],
					'sub_fields'        => [
						[
							'key'          => 'field_mpp_group_filters',
							'name'         => 'filters',
							'label'        => 'Filtres de ce groupe (combinés en ET)',
							'type'         => 'repeater',
							'instructions' => 'Ce groupe ne correspond que si TOUS les filtres ci-dessous sont vrais en même temps — c\'est le ET à l\'intérieur d\'un groupe. "+ Ajouter un filtre" en ajoute un à CE groupe précis.',
							'button_label' => '+ Ajouter un filtre à ce groupe',
							'layout'       => 'block',
							'min'          => 1,
							'sub_fields'   => array_merge( [
								[
									'key'           => 'field_mpp_filter_type',
									'name'          => 'filter_type',
									'label'         => 'Type de filtre',
									'type'          => 'select',
									'choices'       => [
										'post_type'      => 'Type de contenu',
										'specific_posts' => 'Contenus spécifiques',
										'taxonomy_term'  => 'Terme de taxonomie',
										'front_page'     => 'Page d\'accueil',
									],
									'default_value' => 'post_type',
								],
								[
									'key'               => 'field_mpp_filter_post_types',
									'name'              => 'filter_post_types',
									'label'             => 'Type(s) de contenu',
									'type'              => 'checkbox',
									'choices'           => [
										'post'       => 'Articles',
										'page'       => 'Pages',
										'academique' => 'Académique',
										'master'     => 'Master',
										'prepa'      => 'Prépa',
									],
									'conditional_logic' => [ [ [ 'field' => 'field_mpp_filter_type', 'operator' => '==', 'value' => 'post_type' ] ] ],
								],
								[
									'key'               => 'field_mpp_filter_posts',
									'name'              => 'filter_posts',
									'label'             => 'Contenus spécifiques',
									'type'              => 'relationship',
									'post_type'         => [ 'post', 'page', 'academique', 'master', 'prepa' ],
									'filters'           => [ 'search' ],
									'return_format'     => 'id',
									'conditional_logic' => [ [ [ 'field' => 'field_mpp_filter_type', 'operator' => '==', 'value' => 'specific_posts' ] ] ],
								],
								[
									'key'               => 'field_mpp_filter_taxonomy',
									'name'              => 'filter_taxonomy',
									'label'             => 'Taxonomie',
									'type'              => 'select',
									'choices'           => self::TAXONOMIES,
									'conditional_logic' => [ [ [ 'field' => 'field_mpp_filter_type', 'operator' => '==', 'value' => 'taxonomy_term' ] ] ],
								],
							], $filter_taxonomy_term_fields ),
						],
					],
				],
			],
			'location' => [
				[
					[ 'param' => 'post_type', 'operator' => '==', 'value' => 'mp_popup' ],
				],
			],
		] );
	}
}
