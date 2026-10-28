<?php
/**
 * Compatibilità WPML: rende traducibili i testi del widget (etichette, voci extra) nel Translation Editor.
 * Le voci del menu di WordPress non passano da qui: si traducono con i menu (vedi LU3G_Mobile_Menu::translate_menu_id).
 * Polylang non ha bisogno di nulla: le pagine Elementor sono tradotte duplicandole, widget compreso.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_Mobile_Menu_WPML {

	public static function init() {
		add_filter( 'wpml_elementor_widgets_to_translate', array( __CLASS__, 'register' ) );
	}

	public static function register( $widgets ) {
		$widgets['lu3g-mobile-menu'] = array(
			'conditions' => array( 'widgetType' => 'lu3g-mobile-menu' ),
			'fields'     => array(
				array( 'field' => 'toggle_label', 'type' => 'Menu LU3G: testo del pulsante', 'editor_type' => 'LINE' ),
				array( 'field' => 'toggle_aria_label', 'type' => 'Menu LU3G: nome accessibile del pulsante', 'editor_type' => 'LINE' ),
				array( 'field' => 'panel_label', 'type' => 'Menu LU3G: nome del pannello', 'editor_type' => 'LINE' ),
				array( 'field' => 'close_label', 'type' => 'Menu LU3G: testo del pulsante chiudi', 'editor_type' => 'LINE' ),
				array( 'field' => 'slide_back_label', 'type' => 'Menu LU3G: testo del pulsante Indietro', 'editor_type' => 'LINE' ),
			),
		);

		// le voci del repeater richiedono la classe base di WPML (presente solo con WPML attivo)
		if ( class_exists( 'WPML_Elementor_Module_With_Items' ) ) {
			require_once __DIR__ . '/class-wpml-extras.php';
			$widgets['lu3g-mobile-menu']['integration-class'] = 'LU3G_Mobile_Menu_WPML_Extras';
		}

		return $widgets;
	}
}
