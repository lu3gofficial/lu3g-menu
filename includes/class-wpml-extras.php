<?php
/**
 * WPML: campi traducibili di ogni voce extra (repeater "extras" del widget).
 * Caricato solo quando esiste WPML_Elementor_Module_With_Items (vedi class-wpml.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LU3G_Mobile_Menu_WPML_Extras extends WPML_Elementor_Module_With_Items {

	public function get_items_field() {
		return 'extras';
	}

	public function get_fields() {
		return array( 'label', 'phone', 'email', 'whatsapp_message', 'link' => array( 'url' ) );
	}

	protected function get_title( $field ) {
		$titles = array(
			'label'            => 'Menu mobile: testo della voce extra',
			'phone'            => 'Menu mobile: telefono',
			'email'            => 'Menu mobile: email',
			'whatsapp_message' => 'Menu mobile: messaggio WhatsApp',
			'url'              => 'Menu mobile: link della voce extra',
		);
		return isset( $titles[ $field ] ) ? $titles[ $field ] : '';
	}

	protected function get_editor_type( $field ) {
		if ( 'url' === $field ) {
			return 'LINK';
		}
		return 'whatsapp_message' === $field ? 'AREA' : 'LINE';
	}
}
