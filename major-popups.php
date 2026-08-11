<?php
/**
 * Plugin Name: Major Popups
 * Description: Remplace Popup Maker — popups pilotés par ciblage de pages, déclenchement (délai/scroll) et cookie, contenu fourni par les formulaires Lead Manager (app.2empower.com).
 * Version: 0.1.0
 * Author: 2Empower
 * Text Domain: major-popups
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MPP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MPP_URL', plugin_dir_url( __FILE__ ) );
define( 'MPP_VERSION', '0.1.0' );

require_once MPP_PATH . 'includes/class-cpt.php';
require_once MPP_PATH . 'includes/class-acf-fields.php';
require_once MPP_PATH . 'includes/class-targeting.php';
require_once MPP_PATH . 'includes/class-render.php';
require_once MPP_PATH . 'includes/class-assets.php';
require_once MPP_PATH . 'includes/class-cache-compat.php';

MPP_CPT::init();
MPP_ACF_Fields::init();
MPP_Render::init();
MPP_Assets::init();
MPP_Cache_Compat::init();
