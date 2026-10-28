<?php
/**
 * Plugin Name:       LU3G Mobile Menu
 * Description:       Widget Elementor con menu hamburger per mobile: voci dal menu di WordPress con sottomenu su più livelli, voci extra (link, contatti, social, CTA), animazioni configurabili, accessibile, compatibile con WPML e Polylang.
 * Version:           1.0.0
 * Plugin URI:        https://github.com/lu3gofficial/lu3g-mobile-menu
 * Update URI:        https://github.com/lu3gofficial/lu3g-mobile-menu
 * Author:            LU3G Agenzia Web
 * Text Domain:       lu3g-mobile-menu
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Requires Plugins:  elementor
 * Elementor tested up to: 3.30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LU3G_MM_VERSION', '1.0.0' );
define( 'LU3G_MM_FILE', __FILE__ );
define( 'LU3G_MM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LU3G_MM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Avvio del plugin: controlla Elementor, registra asset, categoria e widget.
 */
final class LU3G_Mobile_Menu {

	/** Versione minima di Elementor (API elementor/widgets/register). */
	const MIN_ELEMENTOR = '3.5.0';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
	}

	public static function boot() {
		load_plugin_textdomain( 'lu3g-mobile-menu', false, dirname( plugin_basename( LU3G_MM_FILE ) ) . '/languages' );

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing' ) );
			return;
		}
		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_version' ) );
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );

		// WPML: testi del widget e voci extra traducibili con il Translation Editor
		require_once LU3G_MM_PATH . 'includes/class-wpml.php';
		LU3G_Mobile_Menu_WPML::init();
	}

	/**
	 * CSS e JS vengono solo registrati: Elementor li carica nelle pagine che contengono il widget
	 * (get_style_depends / get_script_depends del widget).
	 */
	public static function register_assets() {
		wp_register_style( 'lu3g-mobile-menu', LU3G_MM_URL . 'assets/css/mobile-menu.css', array(), LU3G_MM_VERSION );
		// dopo elementor-frontend, così nell'editor il widget si riaggancia a ogni modifica (hook element_ready)
		$deps = wp_script_is( 'elementor-frontend', 'registered' ) ? array( 'elementor-frontend' ) : array();
		wp_register_script( 'lu3g-mobile-menu', LU3G_MM_URL . 'assets/js/mobile-menu.js', $deps, LU3G_MM_VERSION, true );
	}

	/** Categoria "LU3G" nel pannello dei widget (condivisa con gli altri plugin LU3G). */
	public static function register_category( $elements_manager ) {
		$categories = method_exists( $elements_manager, 'get_categories' ) ? $elements_manager->get_categories() : array();
		if ( ! isset( $categories['lu3g'] ) ) {
			$elements_manager->add_category( 'lu3g', array( 'title' => 'LU3G', 'icon' => 'fa fa-plug' ) );
		}
	}

	public static function register_widget( $widgets_manager ) {
		require_once LU3G_MM_PATH . 'widgets/class-mobile-menu-widget.php';
		$widgets_manager->register( new LU3G_Mobile_Menu_Widget() );
	}

	/**
	 * Menu nella lingua attiva.
	 * - WPML: ogni traduzione del menu è un termine diverso, collegato all'originale → filtro wpml_object_id.
	 * - Polylang: i menu sono assegnati per lingua alle posizioni del tema; se il menu scelto è assegnato a una
	 *   posizione, si usa quello assegnato alla stessa posizione nella lingua corrente.
	 * Con la sorgente "Posizione del tema" le lingue le gestiscono già WPML e Polylang.
	 */
	public static function translate_menu_id( $menu_id ) {
		$menu_id = (int) $menu_id;
		if ( ! $menu_id ) {
			return 0;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			$menu_id = (int) apply_filters( 'wpml_object_id', $menu_id, 'nav_menu', true );
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$lang    = pll_current_language();
			$options = get_option( 'polylang' );
			if ( $lang && ! empty( $options['nav_menus'] ) && is_array( $options['nav_menus'] ) ) {
				// prima il tema attivo, poi gli altri
				$themes = $options['nav_menus'];
				$active = get_option( 'stylesheet' );
				if ( isset( $themes[ $active ] ) ) {
					$themes = array( $active => $themes[ $active ] ) + $themes;
				}
				foreach ( $themes as $locations ) {
					foreach ( (array) $locations as $langs ) {
						if ( ! is_array( $langs ) ) {
							continue;
						}
						if ( in_array( $menu_id, array_map( 'intval', $langs ), true ) && ! empty( $langs[ $lang ] ) ) {
							return (int) $langs[ $lang ];
						}
					}
				}
			}
		}

		return $menu_id;
	}

	public static function notice_missing() {
		echo '<div class="notice notice-warning"><p><strong>LU3G Mobile Menu</strong> ' . esc_html__( 'richiede Elementor attivo.', 'lu3g-mobile-menu' ) . '</p></div>';
	}

	public static function notice_version() {
		/* translators: %s: versione minima di Elementor */
		echo '<div class="notice notice-warning"><p><strong>LU3G Mobile Menu</strong> ' . esc_html( sprintf( __( 'richiede Elementor %s o successivo.', 'lu3g-mobile-menu' ), self::MIN_ELEMENTOR ) ) . '</p></div>';
	}
}

LU3G_Mobile_Menu::init();

/**
 * Aggiornamenti automatici dalle release GitHub di https://github.com/lu3gofficial/lu3g-mobile-menu (vedi includes/class-updater.php).
 * Fuori da ogni controllo su Elementor: il plugin si aggiorna anche dove Elementor è disattivato.
 */
require_once __DIR__ . '/includes/class-updater.php';
LU3G_Mobile_Menu_Updater::init( __FILE__ );
