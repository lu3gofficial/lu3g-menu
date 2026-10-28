<?php
/**
 * Widget Elementor "Menu LU3G" (nome interno lu3g-mobile-menu, invariato: i widget già inseriti nelle pagine restano validi).
 *
 * Struttura dell'HTML prodotto:
 *   .lu3g-mm                       radice (pulsante hamburger + livello del pannello)
 *     button.lu3g-mm__toggle       hamburger (aria-expanded / aria-controls)
 *     .lu3g-mm__layer              livello a schermo intero: overlay + pannello. Il JS lo sposta in fondo al <body>
 *                                  (dentro un contenitore con le classi Elementor del widget, così gli stili restano validi)
 *                                  perché un'intestazione con transform/filtri non blocchi il position: fixed
 *       .lu3g-mm__overlay
 *       .lu3g-mm__panel            role="dialog", inert quando è chiuso
 *         .lu3g-mm__head           logo (facoltativo) + pulsante chiudi
 *         .lu3g-mm__body           voci extra (prima) + nav del menu WP + voci extra (dopo)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

class LU3G_Mobile_Menu_Widget extends Widget_Base {

	/** Selettori ricorrenti (voci di primo livello e del sottomenu). */
	const TOP_ITEM = '{{WRAPPER}} .lu3g-mm__menu > .lu3g-mm__item';
	const TOP_LINK = '{{WRAPPER}} .lu3g-mm__menu > .lu3g-mm__item > .lu3g-mm__row > .lu3g-mm__link';
	const SUB_ITEM = '{{WRAPPER}} .lu3g-mm__sub > .lu3g-mm__item';
	const SUB_LINK = '{{WRAPPER}} .lu3g-mm__sub > .lu3g-mm__item > .lu3g-mm__row > .lu3g-mm__link';

	/** Impostazioni del rendering in corso. */
	private $s = array();
	/** Prefisso degli id HTML di questa istanza (aria-controls). */
	private $uid = '';
	/** Contatore per l'animazione in sequenza delle voci (variabile CSS --i). */
	private $anim_index = 0;
	/** Icona del sottomenu già pronta (HTML). */
	private $sub_icon_html = '';
	/** URL della pagina corrente, normalizzato (evidenziazione delle voci extra). */
	private $current_url = '';
	/** Sottomenu a slide (true) o a espansione (false). */
	private $slide = false;
	/** Menu letto una volta per rendering (pannello mobile e barra desktop): array o false. */
	private $menu_data = null;
	/** Indicatore delle tendine della barra desktop (HTML). */
	private $bar_indicator_html = '';

	public function get_name() {
		return 'lu3g-mobile-menu';
	}

	public function get_title() {
		return esc_html__( 'Menu LU3G', 'lu3g-mobile-menu' );
	}

	public function get_icon() {
		return 'eicon-menu-bar';
	}

	public function get_categories() {
		return array( 'lu3g' );
	}

	public function get_keywords() {
		return array( 'menu', 'hamburger', 'mobile', 'desktop', 'mega menu', 'nav', 'navigazione', 'lu3g' );
	}

	public function get_style_depends() {
		return array( 'lu3g-mobile-menu' );
	}

	public function get_script_depends() {
		return array( 'lu3g-mobile-menu' );
	}

	/* =====================================================================================================
	 * CONTROLLI
	 * ===================================================================================================== */

	protected function register_controls() {
		$this->controls_menu();
		$this->controls_desktop();
		$this->controls_extras();
		$this->controls_toggle();
		$this->controls_panel();
		$this->controls_logo();
		$this->controls_animation();
		$this->controls_behavior();

		$this->style_toggle();
		$this->style_close();
		$this->style_logo();
		$this->style_panel();
		$this->style_items();
		$this->style_sub_icon();
		$this->style_submenu();
		$this->style_slide();
		$this->style_extras();
		$this->style_bar();
		$this->style_dropdown();
		$this->style_mega();
		$this->style_bar_extras();
	}

	/** Menu di WordPress registrati (Aspetto → Menu). */
	private function get_menu_options() {
		$options = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ (string) $menu->term_id ] = $menu->name;
		}
		return $options;
	}

	/* ------------------------------------------------------------------------------------- Contenuto: menu */
	private function controls_menu() {
		$this->start_controls_section( 'section_menu', array( 'label' => esc_html__( 'Menu', 'lu3g-mobile-menu' ) ) );

		$menus = $this->get_menu_options();

		$this->add_control( 'menu_source', array(
			'label'   => esc_html__( 'Sorgente', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'menu',
			'options' => array(
				'menu'     => esc_html__( 'Menu specifico', 'lu3g-mobile-menu' ),
				'location' => esc_html__( 'Posizione del tema', 'lu3g-mobile-menu' ),
				'none'     => esc_html__( 'Nessuno (solo voci extra)', 'lu3g-mobile-menu' ),
			),
		) );

		if ( $menus ) {
			$this->add_control( 'menu', array(
				'label'       => esc_html__( 'Menu', 'lu3g-mobile-menu' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $menus,
				'default'     => (string) array_key_first( $menus ),
				'description' => esc_html__( 'Con WPML e Polylang viene mostrata la traduzione del menu nella lingua della pagina.', 'lu3g-mobile-menu' ),
				'condition'   => array( 'menu_source' => 'menu' ),
			) );
		} else {
			$this->add_control( 'menu_missing', array(
				'type'            => Controls_Manager::RAW_HTML,
				/* translators: %s: link alla pagina dei menu */
				'raw'             => sprintf( esc_html__( 'Nessun menu trovato. %s', 'lu3g-mobile-menu' ), '<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" target="_blank">' . esc_html__( 'Crea un menu', 'lu3g-mobile-menu' ) . '</a>' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array( 'menu_source' => 'menu' ),
			) );
		}

		$this->add_control( 'menu_location', array(
			'label'     => esc_html__( 'Posizione', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => get_registered_nav_menus(),
			'default'   => (string) array_key_first( (array) get_registered_nav_menus() ),
			'condition' => array( 'menu_source' => 'location' ),
		) );

		$this->add_control( 'depth', array(
			'label'       => esc_html__( 'Livelli mostrati', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 10,
			'default'     => 0,
			'description' => esc_html__( '0 = tutti i livelli.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none' ),
		) );

		$this->add_control( 'submenu_mode', array(
			'label'       => esc_html__( 'Apertura dei sottomenu', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SELECT,
			'default'     => 'accordion',
			'options'     => array(
				'accordion' => esc_html__( 'Espansione (sotto la voce)', 'lu3g-mobile-menu' ),
				'slide'     => esc_html__( 'Scorrimento a slide (con Indietro)', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( 'Con lo scorrimento ogni sottomenu entra da destra al posto del livello precedente.', 'lu3g-mobile-menu' ),
			'separator'   => 'before',
			'condition'   => array( 'menu_source!' => 'none' ),
		) );

		$this->add_control( 'slide_back_label', array(
			'label'     => esc_html__( 'Testo del pulsante Indietro', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => esc_html__( 'Indietro', 'lu3g-mobile-menu' ),
			'condition' => array( 'menu_source!' => 'none', 'submenu_mode' => 'slide' ),
		) );

		$this->add_control( 'slide_back_icon', array(
			'label'       => esc_html__( 'Icona Indietro', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::ICONS,
			'skin'        => 'inline',
			'label_block' => false,
			'description' => esc_html__( 'Vuota = freccia a sinistra.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none', 'submenu_mode' => 'slide' ),
		) );

		$this->add_control( 'slide_show_title', array(
			'label'       => esc_html__( 'Titolo della voce genitore', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'In cima al sottomenu, sotto Indietro; se la voce ha un link è cliccabile.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none', 'submenu_mode' => 'slide' ),
		) );

		$this->add_control( 'submenu_icon', array(
			'label'     => esc_html__( 'Icona dei sottomenu', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'chevron',
			'options'   => array(
				'chevron'       => esc_html__( 'Freccia giù', 'lu3g-mobile-menu' ),
				'chevron-right' => esc_html__( 'Freccia destra', 'lu3g-mobile-menu' ),
				'caret'         => esc_html__( 'Triangolo', 'lu3g-mobile-menu' ),
				'plus'          => esc_html__( 'Più / meno', 'lu3g-mobile-menu' ),
				'custom'        => esc_html__( 'Icona personalizzata', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( 'Con lo scorrimento a slide le frecce puntano sempre a destra.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none' ),
		) );

		$this->add_control( 'submenu_icon_closed', array(
			'label'     => esc_html__( 'Icona (chiuso)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::ICONS,
			'default'   => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
			'condition' => array( 'menu_source!' => 'none', 'submenu_icon' => 'custom' ),
		) );

		$this->add_control( 'submenu_icon_open', array(
			'label'     => esc_html__( 'Icona (aperto)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::ICONS,
			'default'   => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
			'condition' => array( 'menu_source!' => 'none', 'submenu_icon' => 'custom' ),
		) );

		$this->add_control( 'accordion', array(
			'label'       => esc_html__( 'Un sottomenu aperto alla volta', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'Aprendo un sottomenu si chiudono quelli dello stesso livello.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none', 'submenu_mode!' => 'slide' ),
		) );

		$this->add_control( 'expand_current', array(
			'label'       => esc_html__( 'Apri il ramo della pagina corrente', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'I sottomenu che contengono la pagina corrente partono aperti. Le voci con link "#" aprono il loro sottomenu.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'menu_source!' => 'none', 'submenu_mode!' => 'slide' ),
		) );

		$this->end_controls_section();
	}

	/* -------------------------------------------------------------------------------- Contenuto: desktop */
	private function controls_desktop() {
		$this->start_controls_section( 'section_desktop', array( 'label' => esc_html__( 'Desktop', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'desktop_mode', array(
			'label'       => esc_html__( 'Su desktop', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SELECT,
			'default'     => 'hidden',
			'options'     => array(
				'hidden'    => esc_html__( 'Nascosto (solo menu mobile)', 'lu3g-mobile-menu' ),
				'bar'       => esc_html__( 'Barra orizzontale con tendine', 'lu3g-mobile-menu' ),
				'hamburger' => esc_html__( 'Hamburger anche su desktop', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( 'Desktop = sopra il breakpoint di Comportamento → Breakpoint mobile. Sotto resta sempre l\'hamburger.', 'lu3g-mobile-menu' ),
		) );

		$bar = array( 'desktop_mode' => 'bar' );

		$this->add_control( 'desktop_trigger', array(
			'label'     => esc_html__( 'Apertura delle tendine', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'hover',
			'options'   => array(
				'hover' => esc_html__( 'Al passaggio del mouse', 'lu3g-mobile-menu' ),
				'click' => esc_html__( 'Al click', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( 'Sui touch (tablet) il primo tocco su una voce con tendina la apre, il secondo segue il link.', 'lu3g-mobile-menu' ),
			'condition' => $bar,
		) );

		$this->add_control( 'desktop_close_delay', array(
			'label'       => esc_html__( 'Ritardo di chiusura (ms)', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 1500,
			'step'        => 50,
			'default'     => 250,
			'description' => esc_html__( 'Quanto aspetta la tendina prima di chiudersi quando il mouse esce: evita chiusure accidentali.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'desktop_mode' => 'bar', 'desktop_trigger' => 'hover' ),
		) );

		$this->add_control( 'desktop_indicator', array(
			'label'     => esc_html__( 'Indicatore delle tendine', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'chevron',
			'options'   => array(
				'chevron' => esc_html__( 'Freccia', 'lu3g-mobile-menu' ),
				'caret'   => esc_html__( 'Triangolo', 'lu3g-mobile-menu' ),
				'plus'    => esc_html__( 'Più', 'lu3g-mobile-menu' ),
				'none'    => esc_html__( 'Nessuno', 'lu3g-mobile-menu' ),
			),
			'condition' => $bar,
		) );

		$this->add_control( 'desktop_drop_anim', array(
			'label'     => esc_html__( 'Animazione delle tendine', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'slide-up',
			'options'   => array(
				'fade'       => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'slide-up'   => esc_html__( 'Dal basso', 'lu3g-mobile-menu' ),
				'slide-down' => esc_html__( 'Dall\'alto', 'lu3g-mobile-menu' ),
				'zoom'       => esc_html__( 'Zoom', 'lu3g-mobile-menu' ),
				'flip'       => esc_html__( 'Rotazione 3D', 'lu3g-mobile-menu' ),
				'none'       => esc_html__( 'Nessuna', 'lu3g-mobile-menu' ),
			),
			'condition' => $bar,
		) );

		$this->add_control( 'desktop_drop_dur', array(
			'label'     => esc_html__( 'Durata tendine (ms)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 0,
			'max'       => 1500,
			'step'      => 50,
			'default'   => 250,
			'selectors' => array( '{{WRAPPER}}' => '--lu3g-mb-dur: {{VALUE}}ms;' ),
			'condition' => array( 'desktop_mode' => 'bar', 'desktop_drop_anim!' => 'none' ),
		) );

		$this->add_control( 'pointer', array(
			'label'     => esc_html__( 'Effetto al passaggio sulle voci', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'underline',
			'options'   => array(
				'none'       => esc_html__( 'Nessuno', 'lu3g-mobile-menu' ),
				'underline'  => esc_html__( 'Linea sotto', 'lu3g-mobile-menu' ),
				'overline'   => esc_html__( 'Linea sopra', 'lu3g-mobile-menu' ),
				'double'     => esc_html__( 'Doppia linea', 'lu3g-mobile-menu' ),
				'framed'     => esc_html__( 'Cornice', 'lu3g-mobile-menu' ),
				'background' => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'text'       => esc_html__( 'Testo', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( 'Vale anche per la voce della pagina corrente. Colori e spessore in Stile → Barra desktop.', 'lu3g-mobile-menu' ),
			'separator' => 'before',
			'condition' => $bar,
		) );

		$this->add_control( 'pointer_anim_line', array(
			'label'     => esc_html__( 'Animazione dell\'effetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'grow',
			'options'   => array(
				'fade'  => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'grow'  => esc_html__( 'Dal centro', 'lu3g-mobile-menu' ),
				'slide' => esc_html__( 'Da sinistra', 'lu3g-mobile-menu' ),
				'drop'  => esc_html__( 'Scende', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'desktop_mode' => 'bar', 'pointer' => array( 'underline', 'overline', 'double' ) ),
		) );

		$this->add_control( 'pointer_anim_frame', array(
			'label'     => esc_html__( 'Animazione dell\'effetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'fade',
			'options'   => array(
				'fade'   => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'grow'   => esc_html__( 'Si allarga', 'lu3g-mobile-menu' ),
				'shrink' => esc_html__( 'Si stringe', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'desktop_mode' => 'bar', 'pointer' => 'framed' ),
		) );

		$this->add_control( 'pointer_anim_bg', array(
			'label'     => esc_html__( 'Animazione dell\'effetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'sweep',
			'options'   => array(
				'fade'    => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'grow'    => esc_html__( 'Si allarga', 'lu3g-mobile-menu' ),
				'sweep'   => esc_html__( 'Da sinistra', 'lu3g-mobile-menu' ),
				'rise'    => esc_html__( 'Dal basso', 'lu3g-mobile-menu' ),
				'shutter' => esc_html__( 'Dal centro', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'desktop_mode' => 'bar', 'pointer' => 'background' ),
		) );

		$this->add_control( 'pointer_anim_text', array(
			'label'     => esc_html__( 'Animazione dell\'effetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'float',
			'options'   => array(
				'grow'   => esc_html__( 'Ingrandisce', 'lu3g-mobile-menu' ),
				'float'  => esc_html__( 'Sale', 'lu3g-mobile-menu' ),
				'sink'   => esc_html__( 'Scende', 'lu3g-mobile-menu' ),
				'skew'   => esc_html__( 'Inclina', 'lu3g-mobile-menu' ),
				'rotate' => esc_html__( 'Ruota', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'desktop_mode' => 'bar', 'pointer' => 'text' ),
		) );

		$this->add_control( 'mega_heading', array(
			'label'     => esc_html__( 'Mega menu', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => $bar,
		) );

		$this->add_control( 'mega_help', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Si attiva voce per voce in Aspetto → Menu: apri una voce di primo livello e nel riquadro "Mega menu LU3G" scegli "Colonne dalle sotto-voci" oppure un template Elementor.', 'lu3g-mobile-menu' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			'condition'       => $bar,
		) );

		$this->add_control( 'mega_width', array(
			'label'     => esc_html__( 'Larghezza del mega menu', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'container',
			'options'   => array(
				'container' => esc_html__( 'Come il contenitore della barra', 'lu3g-mobile-menu' ),
				'full'      => esc_html__( 'Tutto lo schermo', 'lu3g-mobile-menu' ),
				'custom'    => esc_html__( 'Personalizzata, centrata sulla voce', 'lu3g-mobile-menu' ),
			),
			'condition' => $bar,
		) );

		$this->add_responsive_control( 'mega_custom_width', array(
			'label'      => esc_html__( 'Larghezza', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'vw' ),
			'range'      => array(
				'px' => array( 'min' => 300, 'max' => 1600 ),
				'vw' => array( 'min' => 20, 'max' => 100 ),
			),
			'default'    => array( 'unit' => 'px', 'size' => 800 ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__mega' => 'width: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'desktop_mode' => 'bar', 'mega_width' => 'custom' ),
		) );

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------------------- Contenuto: extra */
	private function controls_extras() {
		$this->start_controls_section( 'section_extras', array( 'label' => esc_html__( 'Voci extra', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'extras_position', array(
			'label'   => esc_html__( 'Posizione', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'after',
			'options' => array(
				'before' => esc_html__( 'Prima del menu', 'lu3g-mobile-menu' ),
				'after'  => esc_html__( 'Dopo il menu', 'lu3g-mobile-menu' ),
			),
		) );

		$rep = new Repeater();

		$rep->add_control( 'type', array(
			'label'   => esc_html__( 'Tipo', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'link',
			'options' => array(
				'link'     => esc_html__( 'Link personalizzato', 'lu3g-mobile-menu' ),
				'phone'    => esc_html__( 'Telefono', 'lu3g-mobile-menu' ),
				'email'    => esc_html__( 'Email', 'lu3g-mobile-menu' ),
				'whatsapp' => esc_html__( 'WhatsApp', 'lu3g-mobile-menu' ),
				'social'   => esc_html__( 'Icona social', 'lu3g-mobile-menu' ),
				'cta'      => esc_html__( 'Pulsante CTA', 'lu3g-mobile-menu' ),
				'text'     => esc_html__( 'Testo', 'lu3g-mobile-menu' ),
			),
		) );

		$rep->add_control( 'show_on', array(
			'label'   => esc_html__( 'Mostra in', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'both',
			'options' => array(
				'both'    => esc_html__( 'Pannello mobile e barra desktop', 'lu3g-mobile-menu' ),
				'mobile'  => esc_html__( 'Solo pannello mobile', 'lu3g-mobile-menu' ),
				'desktop' => esc_html__( 'Solo barra desktop', 'lu3g-mobile-menu' ),
			),
		) );

		$rep->add_control( 'link_style', array(
			'label'     => esc_html__( 'Aspetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'menu',
			'options'   => array(
				'menu'    => esc_html__( 'Come le voci del menu', 'lu3g-mobile-menu' ),
				'contact' => esc_html__( 'Come i contatti (con icona)', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'type' => 'link' ),
		) );

		$rep->add_control( 'label', array(
			'label'       => esc_html__( 'Testo', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::TEXT,
			'dynamic'     => array( 'active' => true ),
			'label_block' => true,
			'description' => esc_html__( 'Per telefono ed email, se vuoto si mostra il numero o l\'indirizzo. Per i social è il nome letto dagli screen reader.', 'lu3g-mobile-menu' ),
		) );

		$rep->add_control( 'link', array(
			'label'     => esc_html__( 'Link', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::URL,
			'dynamic'   => array( 'active' => true ),
			'default'   => array( 'url' => '' ),
			'condition' => array( 'type' => array( 'link', 'social', 'cta' ) ),
		) );

		$rep->add_control( 'phone', array(
			'label'     => esc_html__( 'Numero di telefono', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::TEXT,
			'dynamic'   => array( 'active' => true ),
			'condition' => array( 'type' => 'phone' ),
		) );

		$rep->add_control( 'email', array(
			'label'     => esc_html__( 'Indirizzo email', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::TEXT,
			'dynamic'   => array( 'active' => true ),
			'condition' => array( 'type' => 'email' ),
		) );

		$rep->add_control( 'whatsapp', array(
			'label'       => esc_html__( 'Numero WhatsApp', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '393331234567',
			'description' => esc_html__( 'Con prefisso internazionale, senza + e senza spazi.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'type' => 'whatsapp' ),
		) );

		$rep->add_control( 'whatsapp_message', array(
			'label'     => esc_html__( 'Messaggio precompilato', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::TEXTAREA,
			'rows'      => 2,
			'condition' => array( 'type' => 'whatsapp' ),
		) );

		$rep->add_control( 'icon', array(
			'label'       => esc_html__( 'Icona', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::ICONS,
			'description' => esc_html__( 'Se vuota: icona predefinita per telefono, email, WhatsApp e social.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'type!' => 'text' ),
		) );

		$this->add_control( 'extras', array(
			'label'       => esc_html__( 'Voci', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => array(),
			'title_field' => '{{{ label || phone || email || whatsapp || type }}}',
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------ Contenuto: hamburger */
	private function controls_toggle() {
		$this->start_controls_section( 'section_toggle', array( 'label' => esc_html__( 'Icona hamburger', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'toggle_icon_type', array(
			'label'   => esc_html__( 'Icona', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'bars',
			'options' => array(
				'bars' => esc_html__( 'Linee animate', 'lu3g-mobile-menu' ),
				'icon' => esc_html__( 'Icona o SVG personalizzato', 'lu3g-mobile-menu' ),
			),
		) );

		$this->add_control( 'toggle_icon', array(
			'label'       => esc_html__( 'Icona (chiuso)', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::ICONS,
			'skin'        => 'inline',
			'label_block' => false,
			'default'     => array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ),
			'description' => esc_html__( '"Carica SVG" richiede il caricamento SVG attivo in Elementor → Impostazioni → Avanzate.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'toggle_icon_type' => 'icon' ),
		) );

		$this->add_control( 'toggle_icon_open', array(
			'label'       => esc_html__( 'Icona (aperto)', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::ICONS,
			'skin'        => 'inline',
			'label_block' => false,
			'default'     => array( 'value' => 'fas fa-times', 'library' => 'fa-solid' ),
			'description' => esc_html__( 'Vuota = resta la stessa icona.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'toggle_icon_type' => 'icon' ),
		) );

		$this->add_control( 'toggle_icon_effect', array(
			'label'     => esc_html__( 'Passaggio tra le icone', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'rotate',
			'options'   => array(
				'rotate' => esc_html__( 'Rotazione', 'lu3g-mobile-menu' ),
				'fade'   => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'zoom'   => esc_html__( 'Zoom', 'lu3g-mobile-menu' ),
				'flip'   => esc_html__( 'Rotazione 3D', 'lu3g-mobile-menu' ),
				'slide'  => esc_html__( 'Scorrimento verticale', 'lu3g-mobile-menu' ),
				'none'   => esc_html__( 'Nessuno', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'toggle_icon_type' => 'icon' ),
		) );

		$this->add_control( 'toggle_animation', array(
			'label'   => esc_html__( 'Animazione dell\'icona', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'squeeze',
			'options' => array(
				'x'           => esc_html__( 'Hamburger → X', 'lu3g-mobile-menu' ),
				'squeeze'     => esc_html__( 'Hamburger → X (a due tempi)', 'lu3g-mobile-menu' ),
				'spin'        => esc_html__( 'Hamburger → X con rotazione', 'lu3g-mobile-menu' ),
				'vortex'      => esc_html__( 'Hamburger → X a vortice', 'lu3g-mobile-menu' ),
				'elastic'     => esc_html__( 'Hamburger → X elastica', 'lu3g-mobile-menu' ),
				'plus'        => esc_html__( 'Hamburger → + → X', 'lu3g-mobile-menu' ),
				'collapse'    => esc_html__( 'Hamburger → X dal basso', 'lu3g-mobile-menu' ),
				'asym'        => esc_html__( 'Linee asimmetriche → X', 'lu3g-mobile-menu' ),
				'arrow'       => esc_html__( 'Hamburger → freccia a sinistra', 'lu3g-mobile-menu' ),
				'arrow-right' => esc_html__( 'Hamburger → freccia a destra', 'lu3g-mobile-menu' ),
				'minus'       => esc_html__( 'Hamburger → linea', 'lu3g-mobile-menu' ),
				'none'        => esc_html__( 'Nessuna', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'toggle_icon_type' => 'bars' ),
		) );

		$this->add_control( 'toggle_label', array(
			'label'       => esc_html__( 'Testo accanto all\'icona', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => esc_html__( 'Menu', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'toggle_label_position', array(
			'label'     => esc_html__( 'Posizione del testo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'right',
			'options'   => array(
				'left'  => esc_html__( 'A sinistra', 'lu3g-mobile-menu' ),
				'right' => esc_html__( 'A destra', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'toggle_label!' => '' ),
		) );

		$this->add_control( 'toggle_aria_label', array(
			'label'       => esc_html__( 'Nome accessibile', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => esc_html__( 'Menu', 'lu3g-mobile-menu' ),
			'description' => esc_html__( 'Letto dagli screen reader quando non c\'è testo visibile.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'toggle_label' => '' ),
		) );

		$this->add_responsive_control( 'toggle_align', array(
			'label'     => esc_html__( 'Allineamento', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => esc_html__( 'Centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => esc_html__( 'Destra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .lu3g-mm' => 'justify-content: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/* -------------------------------------------------------------------------------- Contenuto: pannello */
	private function controls_panel() {
		$this->start_controls_section( 'section_panel', array( 'label' => esc_html__( 'Pannello', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'panel_animation', array(
			'label'   => esc_html__( 'Apertura del pannello', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'slide-left',
			'options' => array(
				'slide-left'   => esc_html__( 'Slide da sinistra', 'lu3g-mobile-menu' ),
				'slide-right'  => esc_html__( 'Slide da destra', 'lu3g-mobile-menu' ),
				'slide-top'    => esc_html__( 'Slide dall\'alto', 'lu3g-mobile-menu' ),
				'slide-bottom' => esc_html__( 'Slide dal basso (bottom sheet)', 'lu3g-mobile-menu' ),
				'fade'         => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'flip'         => esc_html__( 'Rotazione 3D di lato', 'lu3g-mobile-menu' ),
				'card'         => esc_html__( 'Riquadro dall\'angolo', 'lu3g-mobile-menu' ),
				'fullscreen'   => esc_html__( 'Overlay a schermo intero', 'lu3g-mobile-menu' ),
				'zoom'         => esc_html__( 'Zoom a schermo intero', 'lu3g-mobile-menu' ),
				'circle'       => esc_html__( 'Cerchio dal pulsante', 'lu3g-mobile-menu' ),
				'curtain'      => esc_html__( 'Tenda dall\'alto', 'lu3g-mobile-menu' ),
				'blinds'       => esc_html__( 'Tenda da sinistra', 'lu3g-mobile-menu' ),
			),
			'description' => esc_html__( '"Cerchio dal pulsante" si apre a partire dall\'icona hamburger, ovunque si trovi.', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'panel_side', array(
			'label'     => esc_html__( 'Lato del pannello', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'left',
			'options'   => array(
				'left'  => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ),
				'right' => esc_html__( 'Destra', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'panel_animation' => array( 'fade', 'flip', 'card' ) ),
		) );

		$this->add_control( 'show_close', array(
			'label'       => esc_html__( 'Pulsante chiudi nel pannello', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'Se nascosto resta raggiungibile da tastiera (compare quando riceve il focus).', 'lu3g-mobile-menu' ),
			'separator'   => 'before',
		) );

		$this->add_control( 'close_icon', array(
			'label'     => esc_html__( 'Icona chiudi', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::ICONS,
			'condition' => array( 'show_close' => 'yes' ),
		) );

		$this->add_control( 'close_label', array(
			'label'   => esc_html__( 'Nome accessibile del pulsante chiudi', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Chiudi il menu', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'panel_label', array(
			'label'   => esc_html__( 'Nome accessibile del pannello', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Menu di navigazione', 'lu3g-mobile-menu' ),
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------------ Contenuto: logo */
	private function controls_logo() {
		$this->start_controls_section( 'section_logo', array( 'label' => esc_html__( 'Logo', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'show_logo', array(
			'label'       => esc_html__( 'Logo nel pannello', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => '',
			'description' => esc_html__( 'In alto, accanto al pulsante chiudi.', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'logo_source', array(
			'label'     => esc_html__( 'Immagine', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'site',
			'options'   => array(
				'site'   => esc_html__( 'Logo del sito', 'lu3g-mobile-menu' ),
				'custom' => esc_html__( 'Carica un\'immagine', 'lu3g-mobile-menu' ),
			),
			'condition' => array( 'show_logo' => 'yes' ),
		) );

		$this->add_control( 'logo_site_note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Il logo impostato in Elementor → Impostazioni del sito → Identità del sito (o in Aspetto → Personalizza).', 'lu3g-mobile-menu' ),
			'content_classes' => 'elementor-descriptor',
			'condition'       => array( 'show_logo' => 'yes', 'logo_source' => 'site' ),
		) );

		$this->add_control( 'logo_image', array(
			'label'     => esc_html__( 'Scegli immagine', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::MEDIA,
			'dynamic'   => array( 'active' => true ),
			'condition' => array( 'show_logo' => 'yes', 'logo_source' => 'custom' ),
		) );

		$this->add_control( 'logo_link', array(
			'label'       => esc_html__( 'Link alla home', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'Con WPML e Polylang porta alla home nella lingua corrente.', 'lu3g-mobile-menu' ),
			'condition'   => array( 'show_logo' => 'yes' ),
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------- Contenuto: animazioni */
	private function controls_animation() {
		$this->start_controls_section( 'section_animation', array( 'label' => esc_html__( 'Animazioni', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'duration', array(
			'label'       => esc_html__( 'Durata (ms)', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 2000,
			'step'        => 50,
			'default'     => 450,
			'description' => esc_html__( 'Pannello, overlay e icona hamburger. Chi ha attivato "riduci movimento" nel sistema non vede animazioni.', 'lu3g-mobile-menu' ),
			'selectors'   => array( '{{WRAPPER}}' => '--lu3g-mm-dur: {{VALUE}}ms;' ),
		) );

		$this->add_control( 'easing', array(
			'label'     => esc_html__( 'Easing', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'cubic-bezier(.77,0,.18,1)',
			'options'   => array(
				'ease'                         => 'Ease',
				'ease-in-out'                  => 'Ease in-out',
				'ease-out'                     => 'Ease out',
				'linear'                       => 'Linear',
				'cubic-bezier(.77,0,.18,1)'    => esc_html__( 'Morbido (quart)', 'lu3g-mobile-menu' ),
				'cubic-bezier(.16,1,.3,1)'     => esc_html__( 'Rapido in uscita (expo)', 'lu3g-mobile-menu' ),
				'cubic-bezier(.34,1.56,.64,1)' => esc_html__( 'Elastico (back)', 'lu3g-mobile-menu' ),
				'custom'                       => esc_html__( 'Personalizzato', 'lu3g-mobile-menu' ),
			),
			// "Personalizzato" usa il valore del campo qui sotto (variabile separata, così la tendina resta sempre visibile)
			'selectors_dictionary' => array( 'custom' => 'var(--lu3g-mm-ease-custom, ease)' ),
			'selectors'            => array( '{{WRAPPER}}' => '--lu3g-mm-ease: {{VALUE}};' ),
		) );

		$this->add_control( 'easing_custom', array(
			'label'       => esc_html__( 'Easing personalizzato', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'cubic-bezier(.5,0,.2,1)',
			'selectors'   => array( '{{WRAPPER}}' => '--lu3g-mm-ease-custom: {{VALUE}};' ),
			'condition'   => array( 'easing' => 'custom' ),
		) );

		$this->add_control( 'items_animation', array(
			'label'     => esc_html__( 'Ingresso delle voci', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'slide-up',
			'options'   => array(
				'none'       => esc_html__( 'Nessuno', 'lu3g-mobile-menu' ),
				'fade'       => esc_html__( 'Dissolvenza', 'lu3g-mobile-menu' ),
				'slide-up'   => esc_html__( 'Dal basso', 'lu3g-mobile-menu' ),
				'slide-down' => esc_html__( 'Dall\'alto', 'lu3g-mobile-menu' ),
				'slide-side' => esc_html__( 'Di lato', 'lu3g-mobile-menu' ),
				'zoom'       => esc_html__( 'Zoom', 'lu3g-mobile-menu' ),
				'blur'       => esc_html__( 'Sfocatura', 'lu3g-mobile-menu' ),
				'bounce'     => esc_html__( 'Rimbalzo', 'lu3g-mobile-menu' ),
				'flip'       => esc_html__( 'Rotazione 3D', 'lu3g-mobile-menu' ),
				'rotate'     => esc_html__( 'Rotazione', 'lu3g-mobile-menu' ),
				'skew'       => esc_html__( 'Inclinazione', 'lu3g-mobile-menu' ),
				'mask'       => esc_html__( 'Rivelazione dal basso', 'lu3g-mobile-menu' ),
			),
			'separator' => 'before',
		) );

		$this->add_control( 'stagger', array(
			'label'     => esc_html__( 'Ritardo tra una voce e l\'altra (ms)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 0,
			'max'       => 500,
			'step'      => 10,
			'default'   => 50,
			'selectors' => array( '{{WRAPPER}}' => '--lu3g-mm-stagger: {{VALUE}}ms;' ),
			'condition' => array( 'items_animation!' => 'none' ),
		) );

		$this->add_control( 'sub_duration', array(
			'label'     => esc_html__( 'Apertura sottomenu (ms)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 0,
			'max'       => 1500,
			'step'      => 50,
			'default'   => 300,
			'selectors' => array( '{{WRAPPER}}' => '--lu3g-mm-sub-dur: {{VALUE}}ms;' ),
			'separator' => 'before',
		) );

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------------- Contenuto: comportamento */
	private function controls_behavior() {
		$this->start_controls_section( 'section_behavior', array( 'label' => esc_html__( 'Comportamento', 'lu3g-mobile-menu' ) ) );

		$this->add_control( 'breakpoint', array(
			'label'       => esc_html__( 'Breakpoint mobile (px)', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 3000,
			'default'     => 1025,
			'description' => esc_html__( 'Sotto questa larghezza il widget è l\'hamburger; sopra vale Desktop → Su desktop. 0 = nessun breakpoint (barra ovunque, oppure hamburger ovunque se Su desktop è Nascosto). Con "Nascosto", nell\'editor l\'hamburger resta visibile.', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'close_overlay', array(
			'label'     => esc_html__( 'Chiudi al click sull\'overlay', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => 'yes',
			'separator' => 'before',
		) );

		$this->add_control( 'close_link', array(
			'label'   => esc_html__( 'Chiudi al click su un link', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'close_esc', array(
			'label'   => esc_html__( 'Chiudi con il tasto ESC', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'lock_scroll', array(
			'label'   => esc_html__( 'Blocca lo scroll della pagina', 'lu3g-mobile-menu' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'highlight_ancestors', array(
			'label'       => esc_html__( 'Evidenzia anche le voci genitore', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'description' => esc_html__( 'Oltre alla voce della pagina corrente, colora anche le voci che la contengono.', 'lu3g-mobile-menu' ),
		) );

		$this->add_control( 'z_index', array(
			'label'     => esc_html__( 'Z-index del pannello', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 0,
			'default'   => 9999,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__layer' => 'z-index: {{VALUE}};' ),
		) );

		$this->add_control( 'editor_open', array(
			'label'       => esc_html__( 'Pannello aperto nell\'editor', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => '',
			'description' => esc_html__( 'Solo per comodità mentre si modificano gli stili: sul sito il pannello parte sempre chiuso.', 'lu3g-mobile-menu' ),
			'separator'   => 'before',
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------------ Stile: hamburger */
	private function style_toggle() {
		$this->start_controls_section( 'style_toggle', array(
			'label' => esc_html__( 'Icona hamburger', 'lu3g-mobile-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$bars = array( 'toggle_icon_type' => 'bars' );

		$this->add_responsive_control( 'bar_width', array(
			'label'      => esc_html__( 'Dimensione', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 12, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm' => '--lu3g-mm-bar-w: {{SIZE}}{{UNIT}};' ),
			'condition'  => $bars,
		) );

		$this->add_responsive_control( 'bar_thickness', array(
			'label'      => esc_html__( 'Spessore delle linee', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm' => '--lu3g-mm-bar-h: {{SIZE}}{{UNIT}};' ),
			'condition'  => $bars,
		) );

		$this->add_responsive_control( 'bar_gap', array(
			'label'      => esc_html__( 'Distanza tra le linee', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm' => '--lu3g-mm-bar-gap: {{SIZE}}{{UNIT}};' ),
			'condition'  => $bars,
		) );

		$this->add_control( 'bar_radius', array(
			'label'      => esc_html__( 'Arrotondamento delle linee', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 10 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm' => '--lu3g-mm-bar-r: {{SIZE}}{{UNIT}};' ),
			'condition'  => $bars,
		) );

		$this->add_responsive_control( 'toggle_icon_size', array(
			'label'      => esc_html__( 'Dimensione', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 12, 'max' => 120 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'toggle_icon_type' => 'icon' ),
		) );

		$this->svg_recolor_control( 'toggle_svg_recolor', '{{WRAPPER}} .lu3g-mm__icon', array( 'toggle_icon_type' => 'icon' ) );

		$this->start_controls_tabs( 'toggle_tabs' );
		foreach ( array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__toggle' ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__toggle:hover, {{WRAPPER}} .lu3g-mm__toggle:focus-visible' ),
			'open'   => array( esc_html__( 'Aperto', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm.is-open .lu3g-mm__toggle' ),
		) as $state => $def ) {
			$this->start_controls_tab( 'toggle_tab_' . $state, array( 'label' => $def[0] ) );
			$this->add_control( 'toggle_color_' . $state, array(
				'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'color: {{VALUE}};' ),
			) );
			$this->add_control( 'toggle_bg_' . $state, array(
				'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'background-color: {{VALUE}};' ),
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_responsive_control( 'toggle_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'toggle_border',
			'selector' => '{{WRAPPER}} .lu3g-mm__toggle',
		) );

		$this->add_control( 'toggle_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'toggle_label_heading', array(
			'label'     => esc_html__( 'Testo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'toggle_label!' => '' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'      => 'toggle_label_typo',
			'selector'  => '{{WRAPPER}} .lu3g-mm__label',
			'condition' => array( 'toggle_label!' => '' ),
		) );

		$this->add_control( 'toggle_label_gap', array(
			'label'      => esc_html__( 'Distanza dall\'icona', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__toggle' => 'gap: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'toggle_label!' => '' ),
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------- Stile: pulsante chiudi */
	private function style_close() {
		$this->start_controls_section( 'style_close', array(
			'label'     => esc_html__( 'Pulsante chiudi', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_close' => 'yes' ),
		) );

		$this->add_control( 'close_size', array(
			'label'      => esc_html__( 'Dimensione', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 20, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__close' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: calc({{SIZE}}{{UNIT}} * .5);' ),
		) );

		$this->add_control( 'close_color', array(
			'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__close' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'close_color_hover', array(
			'label'     => esc_html__( 'Colore hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__close:hover, {{WRAPPER}} .lu3g-mm__close:focus-visible' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'close_bg', array(
			'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__close' => 'background-color: {{VALUE}};' ),
		) );

		$this->svg_recolor_control( 'close_svg_recolor', '{{WRAPPER}} .lu3g-mm__close', array() );

		$this->add_control( 'close_align', array(
			'label'     => esc_html__( 'Allineamento', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => esc_html__( 'Centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => esc_html__( 'Destra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__head' => 'justify-content: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/* ----------------------------------------------------------------------------------------- Stile: logo */
	private function style_logo() {
		$this->start_controls_section( 'style_logo', array(
			'label'     => esc_html__( 'Logo', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_logo' => 'yes' ),
		) );

		$this->add_responsive_control( 'logo_width', array(
			'label'      => esc_html__( 'Larghezza', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 30, 'max' => 400 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__logo img, {{WRAPPER}} .lu3g-mm__logo svg' => 'width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'logo_max_height', array(
			'label'      => esc_html__( 'Altezza massima', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 20, 'max' => 200 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__logo img, {{WRAPPER}} .lu3g-mm__logo svg' => 'max-height: {{SIZE}}{{UNIT}}; width: auto; max-width: 100%;' ),
		) );

		$this->add_responsive_control( 'head_spacing', array(
			'label'      => esc_html__( 'Distanza dalle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__head' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Interruttore che colora l'SVG caricato con il colore scelto nello stile (fill e stroke diventano currentColor).
	 * Le parti con fill="none" o stroke="none" restano trasparenti.
	 */
	private function svg_recolor_control( $id, $scope, array $condition ) {
		$this->add_control( $id, array(
			'label'        => esc_html__( 'Colora l\'SVG con il colore scelto', 'lu3g-mobile-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'description'  => esc_html__( 'Disattiva per mantenere i colori originali di un SVG caricato.', 'lu3g-mobile-menu' ),
			'selectors'    => array(
				$scope . ' svg[fill]:not([fill="none"]), ' . $scope . ' svg [fill]:not([fill="none"])'         => 'fill: currentColor;',
				$scope . ' svg[stroke]:not([stroke="none"]), ' . $scope . ' svg [stroke]:not([stroke="none"])' => 'stroke: currentColor;',
			),
			'condition'    => $condition,
		) );
	}

	/* ------------------------------------------------------------------------------------- Stile: pannello */
	private function style_panel() {
		$this->start_controls_section( 'style_panel', array(
			'label' => esc_html__( 'Pannello', 'lu3g-mobile-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( Group_Control_Background::get_type(), array(
			'name'     => 'panel_bg',
			'types'    => array( 'classic', 'gradient' ),
			'selector' => '{{WRAPPER}} .lu3g-mm__panel',
		) );

		$this->add_responsive_control( 'panel_width', array(
			'label'      => esc_html__( 'Larghezza', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%', 'vw' ),
			'range'      => array(
				'px' => array( 'min' => 200, 'max' => 800 ),
				'%'  => array( 'min' => 30, 'max' => 100 ),
				'vw' => array( 'min' => 30, 'max' => 100 ),
			),
			'selectors'  => array(
				'{{WRAPPER}} .lu3g-mm__panel' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%;',
				// il riquadro resta staccato dai bordi dello schermo
				'{{WRAPPER}} .lu3g-mm__layer[data-animation="card"] .lu3g-mm__panel' => 'max-width: calc(100% - 2 * var(--lu3g-mm-card-gap, 12px));',
			),
			'condition'  => array( 'panel_animation' => array( 'slide-left', 'slide-right', 'fade', 'flip', 'card' ) ),
			'separator'  => 'before',
		) );

		$this->add_responsive_control( 'panel_max_height', array(
			'label'      => esc_html__( 'Altezza massima', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'vh', '%' ),
			'range'      => array(
				'px' => array( 'min' => 200, 'max' => 1200 ),
				'vh' => array( 'min' => 20, 'max' => 100 ),
				'%'  => array( 'min' => 20, 'max' => 100 ),
			),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__panel' => 'max-height: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'panel_animation' => array( 'slide-top', 'slide-bottom', 'card' ) ),
		) );

		$this->add_control( 'card_gap', array(
			'label'      => esc_html__( 'Distanza dai bordi', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__layer' => '--lu3g-mm-card-gap: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'panel_animation' => 'card' ),
		) );

		$this->add_responsive_control( 'panel_radius', array(
			'label'       => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::DIMENSIONS,
			'size_units'  => array( 'px', '%' ),
			'description' => esc_html__( 'Predefinito: arrotondato per il bottom sheet e il riquadro, squadrato per gli altri.', 'lu3g-mobile-menu' ),
			'selectors'   => array( '{{WRAPPER}} .lu3g-mm__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'panel_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'panel_text_color', array(
			'label'       => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::COLOR,
			'description' => esc_html__( 'Colore di base di tutto il pannello; le sezioni sotto lo possono cambiare.', 'lu3g-mobile-menu' ),
			'selectors'   => array( '{{WRAPPER}} .lu3g-mm__panel' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'focus_color', array(
			'label'       => esc_html__( 'Colore del contorno di focus', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::COLOR,
			'description' => esc_html__( 'Compare solo navigando con la tastiera, non al tocco o al click.', 'lu3g-mobile-menu' ),
			'selectors'   => array( '{{WRAPPER}}' => '--lu3g-mm-focus: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'panel_shadow',
			'selector' => '{{WRAPPER}} .lu3g-mm__panel',
		) );

		$this->add_control( 'overlay_heading', array(
			'label'     => esc_html__( 'Overlay di sfondo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'overlay_color', array(
			'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__overlay' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'overlay_opacity', array(
			'label'     => esc_html__( 'Opacità', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__layer' => '--lu3g-mm-ov-op: {{SIZE}};' ),
		) );

		$this->add_control( 'overlay_blur', array(
			'label'      => esc_html__( 'Sfocatura della pagina', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__overlay' => '-webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}});' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Colori normale / hover / attivo per le voci (testo e sfondo).
	 * $link = selettore del link, $item = selettore della voce (per lo stato attivo).
	 */
	private function color_tabs( $prefix, $link, $item ) {
		$active = $item . '.is-current > .lu3g-mm__row > .lu3g-mm__link, '
			. str_replace( '{{WRAPPER}} ', '{{WRAPPER}} .lu3g-mm__layer--hl-anc ', $item ) . '.is-current-ancestor > .lu3g-mm__row > .lu3g-mm__link';
		$hover  = $item . ' > .lu3g-mm__row > .lu3g-mm__link:hover, ' . $item . ' > .lu3g-mm__row > .lu3g-mm__link:focus-visible';

		$this->start_controls_tabs( $prefix . '_tabs' );
		foreach ( array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), $link ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), $hover ),
			'active' => array( esc_html__( 'Attivo', 'lu3g-mobile-menu' ), $active ),
		) as $state => $def ) {
			$this->start_controls_tab( $prefix . '_tab_' . $state, array( 'label' => $def[0] ) );
			$this->add_control( $prefix . '_color_' . $state, array(
				'label'     => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'color: {{VALUE}};' ),
			) );
			$this->add_control( $prefix . '_bg_' . $state, array(
				'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'background-color: {{VALUE}};' ),
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
	}

	/** Divisori tra le voci: interruttore + colore + spessore. */
	private function divider_controls( $prefix, $item, $default ) {
		$this->add_control( $prefix . '_divider', array(
			'label'     => esc_html__( 'Divisori', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => $default,
			'separator' => 'before',
			'selectors' => array(
				$item . ':not(:last-child)' => 'border-bottom: var(--lu3g-mm-' . $prefix . '-div-w, 1px) solid var(--lu3g-mm-' . $prefix . '-div-c, rgba(0,0,0,.1));',
			),
		) );
		$this->add_control( $prefix . '_divider_color', array(
			'label'     => esc_html__( 'Colore divisori', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}}' => '--lu3g-mm-' . $prefix . '-div-c: {{VALUE}};' ),
			'condition' => array( $prefix . '_divider' => 'yes' ),
		) );
		$this->add_control( $prefix . '_divider_width', array(
			'label'      => esc_html__( 'Spessore divisori', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'selectors'  => array( '{{WRAPPER}}' => '--lu3g-mm-' . $prefix . '-div-w: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( $prefix . '_divider' => 'yes' ),
		) );
	}

	/* ------------------------------------------------------------------------------------- Stile: voci menu */
	private function style_items() {
		$this->start_controls_section( 'style_items', array(
			'label' => esc_html__( 'Voci di menu', 'lu3g-mobile-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		// sposta tutto il contenuto sotto l'intestazione (menu e voci extra) dentro il pannello
		$this->add_responsive_control( 'items_valign', array(
			'label'                => esc_html__( 'Posizione verticale', 'lu3g-mobile-menu' ),
			'type'                 => Controls_Manager::CHOOSE,
			'options'              => array(
				'top'    => array( 'title' => esc_html__( 'In alto', 'lu3g-mobile-menu' ), 'icon' => 'eicon-v-align-top' ),
				'center' => array( 'title' => esc_html__( 'Al centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-v-align-middle' ),
				'bottom' => array( 'title' => esc_html__( 'In basso', 'lu3g-mobile-menu' ), 'icon' => 'eicon-v-align-bottom' ),
			),
			'description'          => esc_html__( 'Vale per menu e voci extra insieme. Predefinito: in alto (al centro con l\'overlay a schermo intero).', 'lu3g-mobile-menu' ),
			'selectors_dictionary' => array(
				'top'    => 'margin-top: 0; margin-bottom: 0;',
				'center' => 'margin-top: auto; margin-bottom: auto;',
				'bottom' => 'margin-top: auto; margin-bottom: 0;',
			),
			'selectors'            => array( '{{WRAPPER}} .lu3g-mm__body' => '{{VALUE}}' ),
		) );

		$this->add_responsive_control( 'items_offset', array(
			'label'       => esc_html__( 'Spostamento verticale', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'vh' ),
			'range'       => array(
				'px' => array( 'min' => -300, 'max' => 300 ),
				'vh' => array( 'min' => -40, 'max' => 40 ),
			),
			'description' => esc_html__( 'Regolazione fine: valori positivi spostano le voci verso il basso, negativi verso l\'alto.', 'lu3g-mobile-menu' ),
			'selectors'   => array( '{{WRAPPER}} .lu3g-mm__body' => 'position: relative; top: {{SIZE}}{{UNIT}};' ),
			'separator'   => 'after',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'items_typo',
			'selector' => self::TOP_LINK,
		) );

		$this->add_responsive_control( 'items_align', array(
			'label'                => esc_html__( 'Allineamento', 'lu3g-mobile-menu' ),
			'type'                 => Controls_Manager::CHOOSE,
			'options'              => array(
				'left'   => array( 'title' => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => esc_html__( 'Centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => esc_html__( 'Destra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors_dictionary' => array(
				'left'   => 'justify-content: flex-start; text-align: left;',
				'center' => 'justify-content: center; text-align: center;',
				'right'  => 'justify-content: flex-end; text-align: right;',
			),
			'selectors'            => array( '{{WRAPPER}} .lu3g-mm__link' => '{{VALUE}}' ),
		) );

		$this->color_tabs( 'items', self::TOP_LINK, self::TOP_ITEM );

		$this->add_responsive_control( 'items_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( self::TOP_LINK => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->add_responsive_control( 'items_gap', array(
			'label'      => esc_html__( 'Spaziatura tra le voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__menu' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'items_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( self::TOP_LINK => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->divider_controls( 'items', self::TOP_ITEM, 'yes' );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------ Stile: icona sottomenu */
	private function style_sub_icon() {
		$this->start_controls_section( 'style_sub_icon', array(
			'label'     => esc_html__( 'Icona dei sottomenu', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'menu_source!' => 'none' ),
		) );

		$this->add_control( 'sub_icon_size', array(
			'label'      => esc_html__( 'Dimensione', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__sub-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'sub_icon_area', array(
			'label'       => esc_html__( 'Area cliccabile', 'lu3g-mobile-menu' ),
			'type'        => Controls_Manager::SLIDER,
			'size_units'  => array( 'px' ),
			'range'       => array( 'px' => array( 'min' => 24, 'max' => 80 ) ),
			'description' => esc_html__( 'Almeno 44px per toccarla comodamente.', 'lu3g-mobile-menu' ),
			'selectors'   => array(
				'{{WRAPPER}} .lu3g-mm__sub-toggle'                       => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				// nelle voci senza link l'icona sta dentro la riga: stessa larghezza, altezza della riga
				'{{WRAPPER}} .lu3g-mm__link--parent .lu3g-mm__sub-toggle' => 'height: auto;',
			),
		) );

		$this->add_control( 'sub_icon_color', array(
			'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__sub-icon' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'sub_icon_color_open', array(
			'label'     => esc_html__( 'Colore da aperto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} [aria-expanded="true"] .lu3g-mm__sub-icon' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'sub_icon_bg', array(
			'label'     => esc_html__( 'Sfondo del pulsante', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__sub-toggle' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'sub_icon_radius', array(
			'label'      => esc_html__( 'Raggio del pulsante', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__sub-toggle' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------------------- Stile: sottomenu */
	private function style_submenu() {
		$this->start_controls_section( 'style_submenu', array(
			'label'     => esc_html__( 'Sottomenu', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'menu_source!' => 'none' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'sub_typo',
			'selector' => self::SUB_LINK,
		) );

		$this->add_responsive_control( 'sub_indent', array(
			'label'      => esc_html__( 'Rientro per livello', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__sub' => 'padding-left: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'submenu_mode!' => 'slide' ),
		) );

		$this->color_tabs( 'sub', self::SUB_LINK, self::SUB_ITEM );

		$this->add_responsive_control( 'sub_padding', array(
			'label'      => esc_html__( 'Padding delle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( self::SUB_LINK => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->add_control( 'sub_bg', array(
			'label'     => esc_html__( 'Sfondo del sottomenu', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__sub-wrap' => 'background-color: {{VALUE}};' ),
		) );

		$this->divider_controls( 'sub', self::SUB_ITEM, '' );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------------- Stile: navigazione a slide */
	private function style_slide() {
		$this->start_controls_section( 'style_slide', array(
			'label'     => esc_html__( 'Indietro e titolo (slide)', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'menu_source!' => 'none', 'submenu_mode' => 'slide' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'back_typo',
			'selector' => '{{WRAPPER}} .lu3g-mm__back',
		) );

		$this->add_control( 'back_color', array(
			'label'     => esc_html__( 'Colore Indietro', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__back' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'back_color_hover', array(
			'label'     => esc_html__( 'Colore Indietro hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__back:hover, {{WRAPPER}} .lu3g-mm__back:focus-visible' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'back_icon_size', array(
			'label'      => esc_html__( 'Dimensione icona', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__back-icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'slide_title_heading', array(
			'label'     => esc_html__( 'Titolo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'slide_show_title' => 'yes' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'      => 'slide_title_typo',
			'selector'  => '{{WRAPPER}} .lu3g-mm__sub-title',
			'condition' => array( 'slide_show_title' => 'yes' ),
		) );

		$this->add_control( 'slide_title_color', array(
			'label'     => esc_html__( 'Colore titolo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__sub-title' => 'color: {{VALUE}};' ),
			'condition' => array( 'slide_show_title' => 'yes' ),
		) );

		$this->add_responsive_control( 'slide_head_spacing', array(
			'label'      => esc_html__( 'Distanza dalle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__sub-head' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->end_controls_section();
	}

	/* ----------------------------------------------------------------------------------- Stile: voci extra */
	private function style_extras() {
		$this->start_controls_section( 'style_extras', array(
			'label' => esc_html__( 'Voci extra', 'lu3g-mobile-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'extras_spacing', array(
			'label'      => esc_html__( 'Distanza dal menu', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array(
				'{{WRAPPER}} .lu3g-mm__extras--before' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .lu3g-mm__extras--after'  => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'extras_gap', array(
			'label'      => esc_html__( 'Distanza tra i gruppi', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__extras' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'extras_align', array(
			'label'     => esc_html__( 'Allineamento', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-left' ),
				'center'     => array( 'title' => esc_html__( 'Centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-center' ),
				'flex-end'   => array( 'title' => esc_html__( 'Destra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-right' ),
			),
			'selectors' => array(
				'{{WRAPPER}} .lu3g-mm__contacts, {{WRAPPER}} .lu3g-mm__cta-wrap' => 'align-items: {{VALUE}};',
				'{{WRAPPER}} .lu3g-mm__social' => 'justify-content: {{VALUE}};',
			),
		) );

		/* contatti e testi */
		$this->add_control( 'contacts_heading', array(
			'label'     => esc_html__( 'Contatti, link e testi', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'contacts_typo',
			'selector' => '{{WRAPPER}} .lu3g-mm__contact',
		) );

		$this->add_control( 'contacts_color', array(
			'label'     => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__contact' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'contacts_color_hover', array(
			'label'     => esc_html__( 'Colore del testo hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__contact-link:hover, {{WRAPPER}} .lu3g-mm__contact-link:focus-visible' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'contacts_icon_color', array(
			'label'     => esc_html__( 'Colore delle icone', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__contact-icon' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'contacts_icon_size', array(
			'label'      => esc_html__( 'Dimensione delle icone', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 48 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__contact-icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'contacts_icon_gap', array(
			'label'      => esc_html__( 'Distanza icona-testo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__contact-link, {{WRAPPER}} .lu3g-mm__contact-text-only' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'contacts_gap', array(
			'label'      => esc_html__( 'Spaziatura tra le voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__contacts' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		/* social */
		$this->add_control( 'social_heading', array(
			'label'     => esc_html__( 'Icone social', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'social_icon_size', array(
			'label'      => esc_html__( 'Dimensione icona', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 48 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__social-link' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'social_box_size', array(
			'label'      => esc_html__( 'Dimensione pulsante', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 20, 'max' => 90 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__social-link' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'social_gap', array(
			'label'      => esc_html__( 'Spaziatura', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__social' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'social_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__social-link' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->start_controls_tabs( 'social_tabs' );
		foreach ( array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__social-link' ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__social-link:hover, {{WRAPPER}} .lu3g-mm__social-link:focus-visible' ),
		) as $state => $def ) {
			$this->start_controls_tab( 'social_tab_' . $state, array( 'label' => $def[0] ) );
			$this->add_control( 'social_color_' . $state, array(
				'label'     => esc_html__( 'Colore icona', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'color: {{VALUE}};' ),
			) );
			$this->add_control( 'social_bg_' . $state, array(
				'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'background-color: {{VALUE}};' ),
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		/* CTA */
		$this->add_control( 'cta_heading', array(
			'label'     => esc_html__( 'Pulsante CTA', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'cta_typo',
			'selector' => '{{WRAPPER}} .lu3g-mm__cta',
		) );

		$this->add_control( 'cta_full', array(
			'label'     => esc_html__( 'Larghezza piena', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => 'yes',
			'selectors' => array( '{{WRAPPER}} .lu3g-mm__cta' => 'display: flex; width: 100%;' ),
		) );

		$this->start_controls_tabs( 'cta_tabs' );
		foreach ( array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__cta' ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mm__cta:hover, {{WRAPPER}} .lu3g-mm__cta:focus-visible' ),
		) as $state => $def ) {
			$this->start_controls_tab( 'cta_tab_' . $state, array( 'label' => $def[0] ) );
			$this->add_control( 'cta_color_' . $state, array(
				'label'     => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'color: {{VALUE}};' ),
			) );
			$this->add_control( 'cta_bg_' . $state, array(
				'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'background-color: {{VALUE}};' ),
			) );
			$this->add_control( 'cta_border_color_' . $state, array(
				'label'     => esc_html__( 'Colore del bordo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'border-color: {{VALUE}};' ),
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'      => 'cta_border',
			'selector'  => '{{WRAPPER}} .lu3g-mm__cta',
			'separator' => 'before',
		) );

		$this->add_control( 'cta_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'cta_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mm__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Schede normale / hover / attivo con selettori propri per testo e sfondo.
	 * $states = array( stato => array( etichetta, selettore testo, selettore sfondo ) ).
	 */
	private function state_tabs( $prefix, array $states ) {
		$this->start_controls_tabs( $prefix . '_tabs' );
		foreach ( $states as $state => $def ) {
			$this->start_controls_tab( $prefix . '_tab_' . $state, array( 'label' => $def[0] ) );
			$this->add_control( $prefix . '_color_' . $state, array(
				'label'     => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[1] => 'color: {{VALUE}};' ),
			) );
			$this->add_control( $prefix . '_bg_' . $state, array(
				'label'     => esc_html__( 'Sfondo', 'lu3g-mobile-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $def[2] => 'background-color: {{VALUE}};' ),
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
	}

	/** Selettori dei tre stati per le voci di un livello della barra ($item = selettore della voce). */
	private function bar_states( $item ) {
		$text = function ( $suffix ) use ( $item ) {
			return $item . $suffix . ' > .lu3g-mb__link, ' . $item . $suffix . ' > .lu3g-mb__toggle';
		};
		$link = function ( $suffix ) use ( $item ) {
			return $item . $suffix . ' > .lu3g-mb__link';
		};
		return array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), $text( '' ), $link( '' ) ),
			'hover'  => array(
				esc_html__( 'Hover', 'lu3g-mobile-menu' ),
				$text( ':hover' ) . ', ' . $text( '.is-open' ) . ', ' . $link( '' ) . ':focus-visible',
				$link( ':hover' ) . ', ' . $link( '.is-open' ) . ', ' . $link( '' ) . ':focus-visible',
			),
			'active' => array(
				esc_html__( 'Attivo', 'lu3g-mobile-menu' ),
				$text( '.is-current' ) . ', ' . str_replace( '{{WRAPPER}} ', '{{WRAPPER}} .lu3g-mb--hl-anc ', $text( '.is-current-ancestor' ) ),
				$link( '.is-current' ) . ', ' . str_replace( '{{WRAPPER}} ', '{{WRAPPER}} .lu3g-mb--hl-anc ', $link( '.is-current-ancestor' ) ),
			),
		);
	}

	/* ------------------------------------------------------------------------------ Stile: barra desktop */
	private function style_bar() {
		$this->start_controls_section( 'style_bar', array(
			'label'     => esc_html__( 'Barra desktop', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'desktop_mode' => 'bar' ),
		) );

		$top = '{{WRAPPER}} .lu3g-mb__menu > .lu3g-mb__item';

		$this->add_responsive_control( 'mb_align', array(
			'label'     => esc_html__( 'Allineamento', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start'    => array( 'title' => esc_html__( 'Sinistra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-left' ),
				'center'        => array( 'title' => esc_html__( 'Centro', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-center' ),
				'flex-end'      => array( 'title' => esc_html__( 'Destra', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-right' ),
				'space-between' => array( 'title' => esc_html__( 'Distribuito', 'lu3g-mobile-menu' ), 'icon' => 'eicon-h-align-stretch' ),
			),
			'selectors' => array( '{{WRAPPER}} .lu3g-mb, {{WRAPPER}} .lu3g-mb__menu' => 'justify-content: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'mb_gap', array(
			'label'      => esc_html__( 'Spaziatura tra le voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__menu' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'mb_typo',
			'selector' => $top . ' > .lu3g-mb__link',
		) );

		$this->state_tabs( 'mb', $this->bar_states( $top ) );

		$this->add_responsive_control( 'mb_padding', array(
			'label'      => esc_html__( 'Padding delle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $top . ' > .lu3g-mb__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->add_control( 'mb_radius', array(
			'label'      => esc_html__( 'Raggio del bordo delle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( $top . ' > .lu3g-mb__link' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'mb_indicator_size', array(
			'label'      => esc_html__( 'Dimensione indicatore', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 6, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__indicator' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'desktop_indicator!' => 'none' ),
		) );

		$this->add_control( 'pointer_style_heading', array(
			'label'     => esc_html__( 'Effetto al passaggio', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'pointer!' => array( 'none', 'text' ) ),
		) );

		$this->add_control( 'pointer_color', array(
			'label'     => esc_html__( 'Colore dell\'effetto', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb' => '--lu3g-mb-pc: {{VALUE}}; --lu3g-mb-pbg: {{VALUE}};' ),
			'condition' => array( 'pointer!' => array( 'none', 'text' ) ),
		) );

		$this->add_control( 'pointer_height', array(
			'label'      => esc_html__( 'Spessore', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 12 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb' => '--lu3g-mb-ph: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'pointer' => array( 'underline', 'overline', 'double', 'framed' ) ),
		) );

		$this->add_control( 'pointer_radius', array(
			'label'      => esc_html__( 'Raggio', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb' => '--lu3g-mb-pr: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'pointer' => array( 'framed', 'background' ) ),
		) );

		$this->add_control( 'pointer_dur', array(
			'label'     => esc_html__( 'Durata effetto (ms)', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 0,
			'max'       => 1500,
			'step'      => 50,
			'default'   => 300,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb' => '--lu3g-mb-pd: {{VALUE}}ms;' ),
			'condition' => array( 'pointer!' => 'none' ),
		) );

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------------- Stile: tendine desktop */
	private function style_dropdown() {
		$this->start_controls_section( 'style_dropdown', array(
			'label'     => esc_html__( 'Tendine desktop', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'desktop_mode' => 'bar' ),
		) );

		$sub = '{{WRAPPER}} .lu3g-mb__sub > .lu3g-mb__item';

		$this->add_control( 'drop_min_width', array(
			'label'      => esc_html__( 'Larghezza minima', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 120, 'max' => 500 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__dropdown:not(.lu3g-mb__mega)' => 'min-width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'drop_offset', array(
			'label'      => esc_html__( 'Distanza dalla barra', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb' => '--lu3g-mb-offset: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Background::get_type(), array(
			'name'     => 'drop_bg',
			'types'    => array( 'classic', 'gradient' ),
			'selector' => '{{WRAPPER}} .lu3g-mb__dropdown',
		) );

		$this->add_responsive_control( 'drop_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__dropdown:not(.lu3g-mb__mega)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'drop_border',
			'selector' => '{{WRAPPER}} .lu3g-mb__dropdown',
		) );

		$this->add_control( 'drop_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'drop_shadow',
			'selector' => '{{WRAPPER}} .lu3g-mb__dropdown',
		) );

		$this->add_control( 'drop_items_heading', array(
			'label'     => esc_html__( 'Voci', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'drop_typo',
			'selector' => $sub . ' > .lu3g-mb__link',
		) );

		$this->state_tabs( 'drop', $this->bar_states( $sub ) );

		$this->add_responsive_control( 'drop_link_padding', array(
			'label'      => esc_html__( 'Padding delle voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $sub . ' > .lu3g-mb__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'separator'  => 'before',
		) );

		$this->divider_controls( 'drop', $sub, '' );

		$this->end_controls_section();
	}

	/* -------------------------------------------------------------------------------- Stile: mega menu */
	private function style_mega() {
		$this->start_controls_section( 'style_mega', array(
			'label'     => esc_html__( 'Mega menu', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'desktop_mode' => 'bar' ),
		) );

		$this->add_control( 'mega_style_help', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Sfondo, bordo, raggio e ombra sono quelli di Stile → Tendine desktop.', 'lu3g-mobile-menu' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_responsive_control( 'mega_padding', array(
			'label'      => esc_html__( 'Padding (colonne)', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__mega--columns' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'mega_tpl_padding', array(
			'label'      => esc_html__( 'Padding (template)', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__mega--template' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'mega_gap', array(
			'label'      => esc_html__( 'Distanza tra le colonne', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__mega-cols' => 'column-gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'mega_title_heading', array(
			'label'     => esc_html__( 'Titoli delle colonne', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'mega_title_typo',
			'selector' => '{{WRAPPER}} .lu3g-mb__col-title',
		) );

		$this->add_control( 'mega_title_color', array(
			'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb__col-title' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'mega_title_color_hover', array(
			'label'     => esc_html__( 'Colore hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} a.lu3g-mb__col-title:hover, {{WRAPPER}} a.lu3g-mb__col-title:focus-visible' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'mega_title_spacing', array(
			'label'      => esc_html__( 'Distanza dai link', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__col-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'mega_link_heading', array(
			'label'     => esc_html__( 'Link delle colonne', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'mega_link_typo',
			'selector' => '{{WRAPPER}} .lu3g-mb__col-link',
		) );

		$this->add_control( 'mega_link_color', array(
			'label'     => esc_html__( 'Colore', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb__col-link' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'mega_link_color_hover', array(
			'label'     => esc_html__( 'Colore hover e attivo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} a.lu3g-mb__col-link:hover, {{WRAPPER}} a.lu3g-mb__col-link:focus-visible, {{WRAPPER}} a.lu3g-mb__col-link[aria-current="page"]' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'mega_link_spacing', array(
			'label'      => esc_html__( 'Spaziatura tra i link', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__col-links' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/* -------------------------------------------------------------------- Stile: voci extra nella barra */
	private function style_bar_extras() {
		$this->start_controls_section( 'style_bar_extras', array(
			'label'     => esc_html__( 'Voci extra nella barra', 'lu3g-mobile-menu' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'desktop_mode' => 'bar' ),
		) );

		$this->add_control( 'bx_help', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Le voci extra "come le voci del menu" usano lo stile della Barra desktop. Per scegliere cosa mostrare: Contenuto → Voci extra → Mostra in.', 'lu3g-mobile-menu' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'bx_gap', array(
			'label'      => esc_html__( 'Spaziatura tra le voci', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__extras' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'bx_spacing', array(
			'label'      => esc_html__( 'Distanza dal menu', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
			'selectors'  => array(
				'{{WRAPPER}} .lu3g-mb__extras--after'  => 'margin-left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .lu3g-mb__extras--before' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'bx_contacts_heading', array(
			'label'     => esc_html__( 'Contatti e testi', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'bx_contact_typo',
			'selector' => '{{WRAPPER}} .lu3g-mb__contact',
		) );

		$this->add_control( 'bx_contact_color', array(
			'label'     => esc_html__( 'Colore del testo', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb__contact' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'bx_contact_color_hover', array(
			'label'     => esc_html__( 'Colore hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} a.lu3g-mb__contact:hover, {{WRAPPER}} a.lu3g-mb__contact:focus-visible' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'bx_contact_icon_color', array(
			'label'     => esc_html__( 'Colore delle icone', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb__contact-icon' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'bx_contact_icon_size', array(
			'label'      => esc_html__( 'Dimensione delle icone', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__contact-icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'bx_social_heading', array(
			'label'     => esc_html__( 'Icone social', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'bx_social_icon_size', array(
			'label'      => esc_html__( 'Dimensione icona', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__social-link' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'bx_social_box', array(
			'label'      => esc_html__( 'Dimensione pulsante', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 16, 'max' => 70 ) ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__social-link' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'bx_social_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__social-link' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->state_tabs( 'bx_social', array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mb__social-link', '{{WRAPPER}} .lu3g-mb__social-link' ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mb__social-link:hover, {{WRAPPER}} .lu3g-mb__social-link:focus-visible', '{{WRAPPER}} .lu3g-mb__social-link:hover, {{WRAPPER}} .lu3g-mb__social-link:focus-visible' ),
		) );

		$this->add_control( 'bx_cta_heading', array(
			'label'     => esc_html__( 'Pulsante CTA', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'bx_cta_typo',
			'selector' => '{{WRAPPER}} .lu3g-mb__cta',
		) );

		$this->state_tabs( 'bx_cta', array(
			'normal' => array( esc_html__( 'Normale', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mb__cta', '{{WRAPPER}} .lu3g-mb__cta' ),
			'hover'  => array( esc_html__( 'Hover', 'lu3g-mobile-menu' ), '{{WRAPPER}} .lu3g-mb__cta:hover, {{WRAPPER}} .lu3g-mb__cta:focus-visible', '{{WRAPPER}} .lu3g-mb__cta:hover, {{WRAPPER}} .lu3g-mb__cta:focus-visible' ),
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'      => 'bx_cta_border',
			'selector'  => '{{WRAPPER}} .lu3g-mb__cta',
			'separator' => 'before',
		) );

		$this->add_control( 'bx_cta_border_hover', array(
			'label'     => esc_html__( 'Colore del bordo hover', 'lu3g-mobile-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lu3g-mb__cta:hover, {{WRAPPER}} .lu3g-mb__cta:focus-visible' => 'border-color: {{VALUE}};' ),
		) );

		$this->add_control( 'bx_cta_radius', array(
			'label'      => esc_html__( 'Raggio del bordo', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'bx_cta_padding', array(
			'label'      => esc_html__( 'Padding', 'lu3g-mobile-menu' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lu3g-mb__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/* =====================================================================================================
	 * RENDERING
	 * ===================================================================================================== */

	private function is_edit_mode() {
		return class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	private function yes( $key ) {
		return isset( $this->s[ $key ] ) && 'yes' === $this->s[ $key ];
	}

	private function pick( $key, array $allowed, $default ) {
		return isset( $this->s[ $key ] ) && in_array( $this->s[ $key ], $allowed, true ) ? $this->s[ $key ] : $default;
	}

	protected function render() {
		$this->s           = $this->get_settings_for_display();
		$this->uid         = 'lu3g-mm-' . $this->get_id();
		$this->anim_index  = 0;
		$this->current_url = $this->normalize_url( $this->request_url() );
		$this->menu_data   = null;
		$edit              = $this->is_edit_mode();
		$desktop           = $this->pick( 'desktop_mode', array( 'hidden', 'bar', 'hamburger' ), 'hidden' );

		$breakpoint = isset( $this->s['breakpoint'] ) ? absint( $this->s['breakpoint'] ) : 0;
		$panel_anim = $this->pick( 'panel_animation', array( 'slide-left', 'slide-right', 'slide-top', 'slide-bottom', 'fade', 'flip', 'card', 'fullscreen', 'zoom', 'circle', 'curtain', 'blinds' ), 'slide-left' );
		$items_anim = $this->pick( 'items_animation', array( 'none', 'fade', 'slide-up', 'slide-down', 'slide-side', 'zoom', 'blur', 'bounce', 'flip', 'rotate', 'skew', 'mask' ), 'slide-up' );
		$toggle_fx  = 'icon' === $this->pick( 'toggle_icon_type', array( 'bars', 'icon' ), 'bars' )
			? 'icon'
			: $this->pick( 'toggle_animation', array( 'x', 'squeeze', 'spin', 'vortex', 'elastic', 'plus', 'collapse', 'asym', 'arrow', 'arrow-right', 'minus', 'none' ), 'squeeze' );
		$label      = isset( $this->s['toggle_label'] ) ? trim( (string) $this->s['toggle_label'] ) : '';

		// opzioni lette dal JavaScript
		$opts = array(
			'overlayClose' => $this->yes( 'close_overlay' ),
			'linkClose'    => $this->yes( 'close_link' ),
			'esc'          => $this->yes( 'close_esc' ),
			'lock'         => $this->yes( 'lock_scroll' ),
			'accordion'    => $this->yes( 'accordion' ),
			'breakpoint'   => ( $edit && 'bar' !== $desktop ) ? 0 : $breakpoint,
			'editor'       => $edit,
			'editorOpen'   => $edit && $this->yes( 'editor_open' ),
		);

		$layer_class = array( 'lu3g-mm__layer' );
		if ( $this->yes( 'highlight_ancestors' ) ) {
			$layer_class[] = 'lu3g-mm__layer--hl-anc';
		}
		$this->slide = 'none' !== $this->s['menu_source'] && 'slide' === $this->pick( 'submenu_mode', array( 'accordion', 'slide' ), 'accordion' );
		if ( $this->slide ) {
			$layer_class[] = 'lu3g-mm__layer--slide';
		}

		// chi si vede sopra e sotto il breakpoint (CSS in linea, così non c'è nessun lampo prima del JavaScript):
		// - Nascosto: tutto il widget solo sotto il breakpoint (nell'editor resta visibile, per poterlo modificare);
		// - Barra: barra sopra, hamburger sotto (anche nell'editor, dove si prova con la vista desktop/mobile);
		// - Hamburger: hamburger ovunque, nessuna regola.
		$wrapper = '.elementor-element-' . $this->get_id();
		if ( 'bar' === $desktop ) {
			if ( $breakpoint > 0 ) {
				printf(
					'<style>@media (min-width:%1$dpx){%2$s .lu3g-mm{display:none!important}}@media (max-width:%3$dpx){%2$s .lu3g-mb{display:none!important}}</style>',
					(int) $breakpoint,
					esc_attr( $wrapper ),
					(int) $breakpoint - 1
				);
			} else {
				printf( '<style>%s .lu3g-mm{display:none!important}</style>', esc_attr( $wrapper ) );
			}
			$this->render_bar();
		} elseif ( 'hidden' === $desktop && $breakpoint > 0 && ! $edit ) {
			printf(
				'<style>@media (min-width:%1$dpx){%2$s{display:none!important}}</style>',
				(int) $breakpoint,
				esc_attr( $wrapper )
			);
		}

		$panel_id = $this->uid . '-panel';
		?>
		<div class="lu3g-mm" data-id="<?php echo esc_attr( $this->get_id() ); ?>" data-lu3g-mm="<?php echo esc_attr( wp_json_encode( $opts ) ); ?>">
			<button type="button" class="lu3g-mm__toggle lu3g-mm__toggle--<?php echo esc_attr( $toggle_fx ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>"<?php echo '' === $label ? ' aria-label="' . esc_attr( $this->s['toggle_aria_label'] ? $this->s['toggle_aria_label'] : __( 'Menu', 'lu3g-mobile-menu' ) ) . '"' : ''; ?>>
				<?php if ( '' !== $label && 'left' === $this->s['toggle_label_position'] ) : ?>
					<span class="lu3g-mm__label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
				<?php $this->render_toggle_icon( $toggle_fx ); ?>
				<?php if ( '' !== $label && 'left' !== $this->s['toggle_label_position'] ) : ?>
					<span class="lu3g-mm__label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
			</button>

			<div class="<?php echo esc_attr( implode( ' ', $layer_class ) ); ?>" data-animation="<?php echo esc_attr( $panel_anim ); ?>" data-side="<?php echo esc_attr( $this->pick( 'panel_side', array( 'left', 'right' ), 'left' ) ); ?>"<?php echo 'none' !== $items_anim ? ' data-items="' . esc_attr( $items_anim ) . '"' : ''; ?>>
				<div class="lu3g-mm__overlay" aria-hidden="true"></div>
				<div class="lu3g-mm__panel" id="<?php echo esc_attr( $panel_id ); ?>" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $this->s['panel_label'] ? $this->s['panel_label'] : __( 'Menu di navigazione', 'lu3g-mobile-menu' ) ); ?>" tabindex="-1" inert>
					<div class="lu3g-mm__head">
						<?php $this->render_logo(); ?>
						<button type="button" class="lu3g-mm__close<?php echo $this->yes( 'show_close' ) ? '' : ' lu3g-mm__close--sr'; ?>" aria-label="<?php echo esc_attr( $this->s['close_label'] ? $this->s['close_label'] : __( 'Chiudi il menu', 'lu3g-mobile-menu' ) ); ?>">
							<?php $this->render_close_icon(); ?>
						</button>
					</div>
					<div class="lu3g-mm__body">
						<?php
						$extras_after = 'before' !== $this->s['extras_position'];
						if ( ! $extras_after ) {
							$this->render_extras( 'before' );
						}
						$this->render_menu();
						if ( $extras_after ) {
							$this->render_extras( 'after' );
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Linee animate oppure icona/SVG scelti nel widget (due icone sovrapposte: chiuso e aperto). */
	private function render_toggle_icon( $type ) {
		if ( 'icon' !== $type ) {
			echo '<span class="lu3g-mm__bars" aria-hidden="true"><span></span><span></span><span></span></span>';
			return;
		}

		$closed = $this->icon_html( isset( $this->s['toggle_icon'] ) ? $this->s['toggle_icon'] : array() );
		if ( '' === $closed ) {
			$closed = $this->icon_html( array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ) );
		}
		$open   = $this->icon_html( isset( $this->s['toggle_icon_open'] ) ? $this->s['toggle_icon_open'] : array() );
		$effect = $this->pick( 'toggle_icon_effect', array( 'rotate', 'fade', 'zoom', 'flip', 'slide', 'none' ), 'rotate' );

		printf(
			'<span class="lu3g-mm__icon lu3g-mm__icon--%1$s%2$s" aria-hidden="true"><span class="lu3g-mm__icon-closed">%3$s</span>%4$s</span>',
			esc_attr( $effect ),
			'' === $open ? ' lu3g-mm__icon--single' : '',
			$closed, // phpcs:ignore WordPress.Security.EscapeOutput -- HTML di Icons_Manager
			'' === $open ? '' : '<span class="lu3g-mm__icon-open">' . $open . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}

	/** Logo del sito o immagine caricata, con link alla home nella lingua corrente. */
	private function render_logo() {
		if ( ! $this->yes( 'show_logo' ) ) {
			return;
		}

		$id  = 0;
		$url = '';
		if ( 'custom' === $this->s['logo_source'] ) {
			$id  = isset( $this->s['logo_image']['id'] ) ? (int) $this->s['logo_image']['id'] : 0;
			$url = isset( $this->s['logo_image']['url'] ) ? $this->s['logo_image']['url'] : '';
		} else {
			$id = (int) get_theme_mod( 'custom_logo' );
		}

		$name = get_bloginfo( 'name' );
		$alt  = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
		$alt  = '' !== $alt ? $alt : $name;

		if ( $id && wp_attachment_is_image( $id ) ) {
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'lu3g-mm__logo-img', 'alt' => $alt, 'loading' => 'lazy' ) );
		} elseif ( $url ) {
			$img = '<img class="lu3g-mm__logo-img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
		} else {
			if ( $this->is_edit_mode() ) {
				echo '<span class="lu3g-mm__logo lu3g-mm__notice">' . esc_html__( 'Nessun logo: impostalo nel sito o carica un\'immagine.', 'lu3g-mobile-menu' ) . '</span>';
			}
			return;
		}

		if ( $this->yes( 'logo_link' ) ) {
			if ( function_exists( 'pll_home_url' ) ) {
				$home = pll_home_url();
			} else {
				$home = apply_filters( 'wpml_home_url', home_url( '/' ) );
			}
			echo '<a class="lu3g-mm__logo" href="' . esc_url( $home ) . '">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- immagine da wp_get_attachment_image o già escapata
		} else {
			echo '<span class="lu3g-mm__logo">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	private function render_close_icon() {
		$icon = isset( $this->s['close_icon'] ) ? $this->s['close_icon'] : array();
		if ( ! empty( $icon['value'] ) ) {
			Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			return;
		}
		echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
	}

	/* ---------------------------------------------------------------------------------------- menu WordPress */

	/** Id del menu da mostrare, nella lingua corrente. */
	private function get_menu_id() {
		$source = isset( $this->s['menu_source'] ) ? $this->s['menu_source'] : 'menu';
		if ( 'none' === $source ) {
			return 0;
		}
		if ( 'location' === $source ) {
			// WPML e Polylang filtrano già le posizioni per lingua
			$locations = get_nav_menu_locations();
			$location  = isset( $this->s['menu_location'] ) ? $this->s['menu_location'] : '';
			return isset( $locations[ $location ] ) ? LU3G_Mobile_Menu::translate_menu_id( $locations[ $location ] ) : 0;
		}
		return LU3G_Mobile_Menu::translate_menu_id( isset( $this->s['menu'] ) ? $this->s['menu'] : 0 );
	}

	/**
	 * Menu di WordPress letto una sola volta per rendering e condiviso da pannello mobile e barra desktop:
	 * array( 'menu', 'tree' (voci per genitore), 'args', 'depth' ), oppure false se non c'è.
	 */
	private function load_menu() {
		if ( null !== $this->menu_data ) {
			return $this->menu_data;
		}
		$this->menu_data = false;

		$menu_id = $this->get_menu_id();
		$menu    = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $menu ) {
			return false;
		}

		$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
		if ( empty( $items ) ) {
			return false;
		}
		$items = array_values( array_filter( $items, function ( $item ) {
			return empty( $item->_invalid );
		} ) );

		// classi current-menu-item / current-menu-ancestor, come fa wp_nav_menu()
		if ( function_exists( '_wp_menu_item_classes_by_context' ) ) {
			_wp_menu_item_classes_by_context( $items );
		}

		$depth = isset( $this->s['depth'] ) ? absint( $this->s['depth'] ) : 0;
		$args  = (object) array(
			'menu'            => $menu,
			'theme_location'  => 'location' === $this->s['menu_source'] ? $this->s['menu_location'] : '',
			'container'       => '',
			'menu_class'      => 'lu3g-mm__menu',
			'menu_id'         => '',
			'echo'            => false,
			'fallback_cb'     => false,
			'before'          => '',
			'after'           => '',
			'link_before'     => '',
			'link_after'      => '',
			'items_wrap'      => '',
			'item_spacing'    => 'preserve',
			'depth'           => $depth,
			'walker'          => '',
			'lu3g_mobile_menu' => true,
		);
		// stesso filtro di wp_nav_menu(): i plugin che nascondono o modificano voci continuano a funzionare
		$items = apply_filters( 'wp_nav_menu_objects', $items, $args );

		$tree = array();
		foreach ( $items as $item ) {
			$tree[ (int) $item->menu_item_parent ][] = $item;
		}
		if ( empty( $tree[0] ) ) {
			return false;
		}

		$this->menu_data = array( 'menu' => $menu, 'tree' => $tree, 'args' => $args, 'depth' => $depth );
		return $this->menu_data;
	}

	/** Menu nel pannello mobile. */
	private function render_menu() {
		$data = $this->load_menu();
		if ( ! $data ) {
			if ( $this->is_edit_mode() && 'none' !== $this->s['menu_source'] ) {
				echo '<p class="lu3g-mm__notice">' . esc_html__( 'Seleziona un menu (o assegna un menu alla posizione scelta).', 'lu3g-mobile-menu' ) . '</p>';
			}
			return;
		}

		$this->sub_icon_html = $this->build_sub_icon();

		echo '<nav class="lu3g-mm__nav" aria-label="' . esc_attr( $data['menu']->name ) . '">';
		echo '<ul class="lu3g-mm__menu">';
		$this->render_items( $data['tree'], 0, 0, $data['depth'], $data['args'] );
		echo '</ul></nav>';
	}

	/** Voci di un livello, ricorsivo per i sottomenu. */
	private function render_items( array $tree, $parent_id, $level, $max_depth, $args ) {
		foreach ( $tree[ $parent_id ] as $item ) {
			$classes   = empty( $item->classes ) ? array() : array_filter( (array) $item->classes );
			$can_go    = 0 === $max_depth || $level + 1 < $max_depth;
			$has_kids  = $can_go && ! empty( $tree[ $item->ID ] );
			$current   = in_array( 'current-menu-item', $classes, true );
			$ancestor  = in_array( 'current-menu-ancestor', $classes, true ) || in_array( 'current-menu-parent', $classes, true );
			// a slide si parte sempre dal primo livello
			$open      = $has_kids && $ancestor && $this->yes( 'expand_current' ) && ! $this->slide;
			$url       = trim( (string) $item->url );
			$is_hash   = '' === $url || '#' === $url;
			$sub_id    = $this->uid . '-sub-' . $item->ID;

			$li = array( 'lu3g-mm__item', 'lu3g-mm__item--level-' . $level );
			foreach ( $classes as $c ) {
				$li[] = sanitize_html_class( $c );
			}
			if ( $has_kids ) {
				$li[] = 'lu3g-mm__item--has-sub';
			}
			if ( $current ) {
				$li[] = 'is-current';
			}
			if ( $ancestor ) {
				$li[] = 'is-current-ancestor';
			}
			if ( $open ) {
				$li[] = 'is-sub-open';
			}
			$style = '';
			if ( 0 === $level ) {
				$li[]  = 'lu3g-mm__anim';
				$style = ' style="--i:' . (int) $this->anim_index++ . '"';
			}

			$title = apply_filters( 'the_title', $item->title, $item->ID );
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $level );
			$title = wp_kses_post( $title );

			echo '<li class="' . esc_attr( implode( ' ', array_unique( $li ) ) ) . '"' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="lu3g-mm__row">';

			$expanded = $open ? 'true' : 'false';
			if ( $has_kids && $is_hash ) {
				// voce senza link: tutta la riga apre il sottomenu
				printf(
					'<button type="button" class="lu3g-mm__link lu3g-mm__link--parent" data-sub-toggle aria-expanded="%1$s" aria-controls="%2$s"><span class="lu3g-mm__text">%3$s</span><span class="lu3g-mm__sub-toggle">%4$s</span></button>',
					esc_attr( $expanded ),
					esc_attr( $sub_id ),
					$title, // phpcs:ignore WordPress.Security.EscapeOutput -- già filtrato con wp_kses_post
					$this->sub_icon_html // phpcs:ignore WordPress.Security.EscapeOutput -- HTML costruito qui
				);
			} else {
				$atts = array( 'class' => 'lu3g-mm__link', 'href' => $is_hash ? '#' : $url );
				if ( ! empty( $item->attr_title ) ) {
					$atts['title'] = $item->attr_title;
				}
				if ( ! empty( $item->target ) ) {
					$atts['target'] = $item->target;
				}
				$rel = empty( $item->xfn ) ? '' : $item->xfn;
				if ( '_blank' === $item->target && false === strpos( $rel, 'noopener' ) ) {
					$rel = trim( $rel . ' noopener' );
				}
				if ( $rel ) {
					$atts['rel'] = $rel;
				}
				if ( $current ) {
					$atts['aria-current'] = 'page';
				}
				echo '<a' . $this->attrs( $atts ) . '><span class="lu3g-mm__text">' . $title . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput

				if ( $has_kids ) {
					printf(
						'<button type="button" class="lu3g-mm__sub-toggle" data-sub-toggle aria-expanded="%1$s" aria-controls="%2$s" aria-label="%3$s">%4$s</button>',
						esc_attr( $expanded ),
						esc_attr( $sub_id ),
						/* translators: %s: nome della voce di menu */
						esc_attr( sprintf( __( 'Sottomenu di %s', 'lu3g-mobile-menu' ), wp_strip_all_tags( $title ) ) ),
						$this->sub_icon_html // phpcs:ignore WordPress.Security.EscapeOutput
					);
				}
			}
			echo '</div>';

			if ( $has_kids ) {
				echo '<div class="lu3g-mm__sub-wrap" id="' . esc_attr( $sub_id ) . '">';
				echo '<ul class="lu3g-mm__sub lu3g-mm__sub--level-' . (int) ( $level + 1 ) . '">';
				if ( $this->slide ) {
					$this->render_slide_head( $title, $is_hash ? '' : $url, $current );
				}
				$this->render_items( $tree, $item->ID, $level + 1, $max_depth, $args );
				echo '</ul></div>';
			}
			echo '</li>';
		}
	}

	/**
	 * Testata di un sottomenu a slide: pulsante Indietro e titolo della voce genitore (link se la voce ne ha uno).
	 * È il primo <li> del sottomenu, così sparisce insieme alle voci quando si apre il livello successivo.
	 */
	private function render_slide_head( $title, $url, $current ) {
		$label = isset( $this->s['slide_back_label'] ) && '' !== trim( (string) $this->s['slide_back_label'] )
			? $this->s['slide_back_label']
			: __( 'Indietro', 'lu3g-mobile-menu' );
		$icon  = $this->icon_html( isset( $this->s['slide_back_icon'] ) ? $this->s['slide_back_icon'] : array() );
		if ( '' === $icon ) {
			$icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		echo '<li class="lu3g-mm__sub-head">';
		printf(
			'<button type="button" class="lu3g-mm__back" data-sub-back><span class="lu3g-mm__back-icon" aria-hidden="true">%1$s</span><span class="lu3g-mm__back-text">%2$s</span></button>',
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fisso o HTML di Icons_Manager
			esc_html( $label )
		);
		if ( $this->yes( 'slide_show_title' ) ) {
			if ( '' !== $url ) {
				echo '<a class="lu3g-mm__sub-title" href="' . esc_url( $url ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . $title . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- titolo filtrato con wp_kses_post
			} else {
				echo '<span class="lu3g-mm__sub-title">' . $title . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</li>';
	}

	/** Icona di apertura dei sottomenu, secondo la scelta nel widget. */
	private function build_sub_icon() {
		$type = $this->pick( 'submenu_icon', array( 'chevron', 'chevron-right', 'caret', 'plus', 'custom' ), 'chevron' );
		$svg  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg>';

		switch ( $type ) {
			case 'plus':
				$inner = '';
				break;
			case 'caret':
				$inner = sprintf( $svg, '<path d="M6 9.5h12L12 16z" fill="currentColor"/>' );
				break;
			case 'chevron-right':
				$inner = sprintf( $svg, '<path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' );
				break;
			case 'custom':
				$inner = '<span class="lu3g-mm__sub-icon-closed">' . $this->icon_html( $this->s['submenu_icon_closed'] ) . '</span>'
					. '<span class="lu3g-mm__sub-icon-open">' . $this->icon_html( $this->s['submenu_icon_open'] ) . '</span>';
				break;
			default:
				$inner = sprintf( $svg, '<path d="M5 9l7 7 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' );
		}

		return '<span class="lu3g-mm__sub-icon lu3g-mm__sub-icon--' . esc_attr( $type ) . '" aria-hidden="true">' . $inner . '</span>';
	}

	/* ----------------------------------------------------------------------------------------- voci extra */

	/**
	 * Voci extra raggruppate per aspetto: voci consecutive dello stesso gruppo finiscono nello stesso elenco
	 * (es. le icone social in una riga, i contatti in colonna).
	 */
	private function render_extras( $position ) {
		$extras = $this->extras_for( 'mobile' );
		if ( ! $extras ) {
			return;
		}

		$groups = array();
		foreach ( $extras as $item ) {
			$html = $this->extra_html( $item );
			if ( '' === $html ) {
				continue;
			}
			$kind = $this->extra_group( $item );
			$last = count( $groups ) - 1;
			if ( $last >= 0 && $groups[ $last ]['kind'] === $kind && 'cta' !== $kind ) {
				$groups[ $last ]['html'] .= $html;
			} else {
				$groups[] = array( 'kind' => $kind, 'html' => $html );
			}
		}
		if ( ! $groups ) {
			return;
		}

		$wrappers = array(
			'menu'    => array( '<ul class="lu3g-mm__menu lu3g-mm__menu--extra">', '</ul>' ),
			'contact' => array( '<ul class="lu3g-mm__contacts">', '</ul>' ),
			'social'  => array( '<ul class="lu3g-mm__social">', '</ul>' ),
			'cta'     => array( '', '' ),
		);

		echo '<div class="lu3g-mm__extras lu3g-mm__extras--' . esc_attr( $position ) . '">';
		foreach ( $groups as $group ) {
			echo $wrappers[ $group['kind'] ][0] . $group['html'] . $wrappers[ $group['kind'] ][1]; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML costruito e filtrato in extra_html()
		}
		echo '</div>';
	}

	private function extra_group( array $item ) {
		$type = isset( $item['type'] ) ? $item['type'] : 'link';
		if ( 'link' === $type ) {
			return isset( $item['link_style'] ) && 'contact' === $item['link_style'] ? 'contact' : 'menu';
		}
		if ( 'social' === $type || 'cta' === $type ) {
			return $type;
		}
		return 'contact';
	}

	/** Voci extra da mostrare nel pannello mobile ('mobile') o nella barra desktop ('desktop'). */
	private function extras_for( $where ) {
		$extras = empty( $this->s['extras'] ) ? array() : (array) $this->s['extras'];
		return array_values( array_filter( $extras, function ( $item ) use ( $where ) {
			$show = isset( $item['show_on'] ) && '' !== $item['show_on'] ? $item['show_on'] : 'both';
			return 'both' === $show || $where === $show;
		} ) );
	}

	/**
	 * Dati di una voce extra, comuni a pannello e barra: tipo, testo, attributi del link, icona, pagina corrente.
	 * null se mancano i dati necessari (es. telefono vuoto).
	 */
	private function extra_parts( array $item ) {
		$type  = isset( $item['type'] ) ? $item['type'] : 'link';
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		$atts  = array();
		$text  = $label;

		switch ( $type ) {
			case 'phone':
				$number = isset( $item['phone'] ) ? trim( (string) $item['phone'] ) : '';
				if ( '' === $number ) {
					return null;
				}
				$atts['href'] = 'tel:' . preg_replace( '/[^0-9+]/', '', $number );
				$text         = '' !== $label ? $label : $number;
				break;

			case 'email':
				$email = isset( $item['email'] ) ? sanitize_email( $item['email'] ) : '';
				if ( ! $email ) {
					return null;
				}
				$atts['href'] = 'mailto:' . antispambot( $email );
				$text         = '' !== $label ? $label : antispambot( $email );
				break;

			case 'whatsapp':
				$number = isset( $item['whatsapp'] ) ? preg_replace( '/\D/', '', (string) $item['whatsapp'] ) : '';
				if ( '' === $number ) {
					return null;
				}
				$message        = isset( $item['whatsapp_message'] ) ? trim( (string) $item['whatsapp_message'] ) : '';
				$atts['href']   = 'https://wa.me/' . $number . ( '' !== $message ? '?text=' . rawurlencode( $message ) : '' );
				$atts['target'] = '_blank';
				$atts['rel']    = 'noopener';
				$text           = '' !== $label ? $label : 'WhatsApp';
				break;

			case 'text':
				if ( '' === $label ) {
					return null;
				}
				return array( 'type' => 'text', 'label' => $label, 'text' => $label, 'atts' => array(), 'icon' => '', 'current' => false );

			default: // link, social, cta
				$link = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
				if ( empty( $link['url'] ) ) {
					return null;
				}
				$atts['href'] = $link['url'];
				$rel          = array();
				if ( ! empty( $link['is_external'] ) ) {
					$atts['target'] = '_blank';
					$rel[]          = 'noopener';
				}
				if ( ! empty( $link['nofollow'] ) ) {
					$rel[] = 'nofollow';
				}
				if ( $rel ) {
					$atts['rel'] = implode( ' ', $rel );
				}
				if ( 'social' !== $type && '' === $label ) {
					return null;
				}
		}

		$current = $this->is_current_url( $atts['href'] );
		if ( $current ) {
			$atts['aria-current'] = 'page';
		}
		if ( 'social' === $type ) {
			$atts['aria-label'] = '' !== $label ? $label : $this->social_name( $atts['href'] );
		}

		return array(
			'type'    => $type,
			'label'   => $label,
			'text'    => $text,
			'atts'    => $atts,
			'icon'    => $this->extra_icon( $type, isset( $item['icon'] ) ? $item['icon'] : array() ),
			'current' => $current,
		);
	}

	/** HTML di una voce extra nel pannello mobile ('' se mancano i dati necessari). */
	private function extra_html( array $item ) {
		$p = $this->extra_parts( $item );
		if ( ! $p ) {
			return '';
		}
		$type    = $p['type'];
		$atts    = $p['atts'];
		$icon    = $p['icon'];
		$text    = $p['text'];
		$current = $p['current'];

		if ( 'text' === $type ) {
			return '<li class="lu3g-mm__contact lu3g-mm__contact-text-only lu3g-mm__anim"' . $this->anim_style() . '><span class="lu3g-mm__contact-text">' . esc_html( $text ) . '</span></li>';
		}

		switch ( $this->extra_group( $item ) ) {
			case 'menu':
				$atts['class'] = 'lu3g-mm__link';
				return '<li class="lu3g-mm__item lu3g-mm__item--level-0 lu3g-mm__item--extra lu3g-mm__anim' . ( $current ? ' is-current' : '' ) . '"' . $this->anim_style() . '><div class="lu3g-mm__row">'
					. '<a' . $this->attrs( $atts ) . '>' . ( $icon ? '<span class="lu3g-mm__link-icon" aria-hidden="true">' . $icon . '</span>' : '' ) . '<span class="lu3g-mm__text">' . esc_html( $text ) . '</span></a></div></li>';

			case 'social':
				$atts['class'] = 'lu3g-mm__social-link';
				return '<li class="lu3g-mm__social-item lu3g-mm__anim"' . $this->anim_style() . '><a' . $this->attrs( $atts ) . '>' . $icon . '</a></li>';

			case 'cta':
				$atts['class'] = 'lu3g-mm__cta';
				return '<div class="lu3g-mm__cta-wrap lu3g-mm__anim"' . $this->anim_style() . '><a' . $this->attrs( $atts ) . '>' . ( $icon ? '<span class="lu3g-mm__cta-icon" aria-hidden="true">' . $icon . '</span>' : '' ) . '<span class="lu3g-mm__cta-text">' . esc_html( $text ) . '</span></a></div>';

			default:
				$atts['class'] = 'lu3g-mm__contact-link';
				return '<li class="lu3g-mm__contact lu3g-mm__contact--' . esc_attr( $type ) . ' lu3g-mm__anim"' . $this->anim_style() . '><a' . $this->attrs( $atts ) . '>'
					. ( $icon ? '<span class="lu3g-mm__contact-icon" aria-hidden="true">' . $icon . '</span>' : '' )
					. '<span class="lu3g-mm__contact-text">' . esc_html( $text ) . '</span></a></li>';
		}
	}

	/* ------------------------------------------------------------------------------------ barra desktop */

	/**
	 * Barra orizzontale per il desktop: voci del menu con tendine (anche su più livelli) o mega menu,
	 * voci extra prima o dopo. Le tendine le apre il JS (mouse, click, tastiera, tocco).
	 */
	private function render_bar() {
		$data    = $this->load_menu();
		$pointer = $this->pick( 'pointer', array( 'none', 'underline', 'overline', 'double', 'framed', 'background', 'text' ), 'underline' );
		$anims   = array(
			'underline'  => array( 'pointer_anim_line', array( 'fade', 'grow', 'slide', 'drop' ), 'grow' ),
			'overline'   => array( 'pointer_anim_line', array( 'fade', 'grow', 'slide', 'drop' ), 'grow' ),
			'double'     => array( 'pointer_anim_line', array( 'fade', 'grow', 'slide', 'drop' ), 'grow' ),
			'framed'     => array( 'pointer_anim_frame', array( 'fade', 'grow', 'shrink' ), 'fade' ),
			'background' => array( 'pointer_anim_bg', array( 'fade', 'grow', 'sweep', 'rise', 'shutter' ), 'sweep' ),
			'text'       => array( 'pointer_anim_text', array( 'grow', 'float', 'sink', 'skew', 'rotate' ), 'float' ),
		);
		$anim = isset( $anims[ $pointer ] ) ? $this->pick( $anims[ $pointer ][0], $anims[ $pointer ][1], $anims[ $pointer ][2] ) : '';

		$opts = array(
			'trigger' => $this->pick( 'desktop_trigger', array( 'hover', 'click' ), 'hover' ),
			'delay'   => isset( $this->s['desktop_close_delay'] ) && '' !== $this->s['desktop_close_delay'] ? absint( $this->s['desktop_close_delay'] ) : 250,
			'mega'    => $this->pick( 'mega_width', array( 'container', 'full', 'custom' ), 'container' ),
		);

		$this->bar_indicator_html = $this->build_bar_indicator();
		$position                 = 'before' === $this->s['extras_position'] ? 'before' : 'after';
		$label                    = $data ? $data['menu']->name : ( $this->s['panel_label'] ? $this->s['panel_label'] : __( 'Menu di navigazione', 'lu3g-mobile-menu' ) );

		printf(
			'<nav class="lu3g-mb%1$s" data-lu3g-mb="%2$s" data-drop="%3$s" data-pointer="%4$s" data-pointer-anim="%5$s" aria-label="%6$s">',
			$this->yes( 'highlight_ancestors' ) ? ' lu3g-mb--hl-anc' : '',
			esc_attr( wp_json_encode( $opts ) ),
			esc_attr( $this->pick( 'desktop_drop_anim', array( 'fade', 'slide-up', 'slide-down', 'zoom', 'flip', 'none' ), 'slide-up' ) ),
			esc_attr( $pointer ),
			esc_attr( $anim ),
			esc_attr( $label )
		);

		if ( 'before' === $position ) {
			$this->render_bar_extras( 'before' );
		}
		echo '<ul class="lu3g-mb__menu">';
		if ( 'before' === $position ) {
			echo $this->bar_extra_menu_items(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML costruito ed escapato in extra_parts()/attrs()
		}
		if ( $data ) {
			$this->render_bar_items( $data['tree'], 0, 0, $data['depth'], $data['args'] );
		} elseif ( $this->is_edit_mode() && 'none' !== $this->s['menu_source'] ) {
			echo '<li class="lu3g-mm__notice">' . esc_html__( 'Seleziona un menu (o assegna un menu alla posizione scelta).', 'lu3g-mobile-menu' ) . '</li>';
		}
		if ( 'after' === $position ) {
			echo $this->bar_extra_menu_items(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
		if ( 'after' === $position ) {
			$this->render_bar_extras( 'after' );
		}
		echo '</nav>';
	}

	/** Titolo di una voce di menu, con gli stessi filtri di wp_nav_menu(). */
	private function item_title( $item, $args, $level ) {
		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $level );
		return wp_kses_post( $title );
	}

	/** Attributi del link di una voce di menu (href, target, rel, title, aria-current). */
	private function item_link_atts( $item, $class, $current ) {
		$url  = trim( (string) $item->url );
		$atts = array( 'class' => $class, 'href' => '' === $url ? '#' : $url );
		if ( ! empty( $item->attr_title ) ) {
			$atts['title'] = $item->attr_title;
		}
		if ( ! empty( $item->target ) ) {
			$atts['target'] = $item->target;
		}
		$rel = empty( $item->xfn ) ? '' : $item->xfn;
		if ( '_blank' === $item->target && false === strpos( $rel, 'noopener' ) ) {
			$rel = trim( $rel . ' noopener' );
		}
		if ( $rel ) {
			$atts['rel'] = $rel;
		}
		if ( $current ) {
			$atts['aria-current'] = 'page';
		}
		return $atts;
	}

	/** Voci di un livello della barra; ricorsivo per le tendine a più livelli. */
	private function render_bar_items( array $tree, $parent_id, $level, $max_depth, $args ) {
		foreach ( $tree[ $parent_id ] as $item ) {
			$classes  = empty( $item->classes ) ? array() : array_filter( (array) $item->classes );
			$can_go   = 0 === $max_depth || $level + 1 < $max_depth;
			$has_kids = $can_go && ! empty( $tree[ $item->ID ] );
			$current  = in_array( 'current-menu-item', $classes, true );
			$ancestor = in_array( 'current-menu-ancestor', $classes, true ) || in_array( 'current-menu-parent', $classes, true );
			$url      = trim( (string) $item->url );
			$is_hash  = '' === $url || '#' === $url;
			$drop_id  = $this->uid . '-d-' . $item->ID;

			// mega menu: solo sulle voci di primo livello
			$mega_html = '';
			$mega_cols = false;
			$cols      = 0;
			if ( 0 === $level ) {
				$mega = LU3G_Mobile_Menu_Mega::get( $item->ID );
				if ( 'template' === $mega['type'] ) {
					$mega_html = $this->mega_template_html( $mega['template'] );
				} elseif ( 'columns' === $mega['type'] && $has_kids ) {
					$mega_cols = true;
					$cols      = $mega['cols'];
				}
			}
			$has_drop = $has_kids || '' !== $mega_html;

			$li = array( 'lu3g-mb__item', 'lu3g-mb__item--level-' . $level );
			foreach ( $classes as $c ) {
				$li[] = sanitize_html_class( $c );
			}
			if ( $has_drop ) {
				$li[] = 'lu3g-mb__item--has-drop';
			}
			if ( '' !== $mega_html || $mega_cols ) {
				$li[] = 'lu3g-mb__item--mega';
			}
			if ( $current ) {
				$li[] = 'is-current';
			}
			if ( $ancestor ) {
				$li[] = 'is-current-ancestor';
			}

			$title = $this->item_title( $item, $args, $level );
			echo '<li class="' . esc_attr( implode( ' ', array_unique( $li ) ) ) . '">';

			if ( $has_drop && $is_hash ) {
				// voce senza link: è lei stessa il pulsante che apre la tendina
				printf(
					'<button type="button" class="lu3g-mb__link lu3g-mb__link--parent" data-mb-toggle aria-expanded="false" aria-controls="%1$s"><span class="lu3g-mb__text">%2$s</span>%3$s</button>',
					esc_attr( $drop_id ),
					$title, // phpcs:ignore WordPress.Security.EscapeOutput -- filtrato con wp_kses_post
					$this->bar_indicator_html // phpcs:ignore WordPress.Security.EscapeOutput -- HTML costruito qui
				);
			} else {
				echo '<a' . $this->attrs( $this->item_link_atts( $item, 'lu3g-mb__link', $current ) ) . '><span class="lu3g-mb__text">' . $title . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
				if ( $has_drop ) {
					// pulsante separato: da tastiera e al tocco apre la tendina senza seguire il link
					$indicator = '' !== $this->bar_indicator_html ? $this->bar_indicator_html : $this->bar_indicator_svg( 'chevron' );
					printf(
						'<button type="button" class="lu3g-mb__toggle%1$s" data-mb-toggle aria-expanded="false" aria-controls="%2$s" aria-label="%3$s">%4$s</button>',
						'' === $this->bar_indicator_html ? ' lu3g-mb__toggle--sr' : '',
						esc_attr( $drop_id ),
						/* translators: %s: nome della voce di menu */
						esc_attr( sprintf( __( 'Sottomenu di %s', 'lu3g-mobile-menu' ), wp_strip_all_tags( $title ) ) ),
						$indicator // phpcs:ignore WordPress.Security.EscapeOutput
					);
				}
			}

			if ( '' !== $mega_html ) {
				echo '<div class="lu3g-mb__dropdown lu3g-mb__mega lu3g-mb__mega--template" id="' . esc_attr( $drop_id ) . '">' . $mega_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- contenuto del template Elementor
			} elseif ( $mega_cols ) {
				echo '<div class="lu3g-mb__dropdown lu3g-mb__mega lu3g-mb__mega--columns" id="' . esc_attr( $drop_id ) . '">';
				echo '<ul class="lu3g-mb__mega-cols"' . ( $cols ? ' style="grid-template-columns:repeat(' . (int) $cols . ',minmax(0,1fr))"' : '' ) . '>';
				$this->render_mega_columns( $tree, $item->ID, $max_depth, $args );
				echo '</ul></div>';
			} elseif ( $has_kids ) {
				echo '<div class="lu3g-mb__dropdown" id="' . esc_attr( $drop_id ) . '"><ul class="lu3g-mb__sub">';
				$this->render_bar_items( $tree, $item->ID, $level + 1, $max_depth, $args );
				echo '</ul></div>';
			}
			echo '</li>';
		}
	}

	/** Mega menu a colonne: ogni sotto-voce è il titolo di una colonna, le sue sotto-voci sono i link. */
	private function render_mega_columns( array $tree, $parent_id, $max_depth, $args ) {
		foreach ( $tree[ $parent_id ] as $col ) {
			$classes = empty( $col->classes ) ? array() : array_filter( (array) $col->classes );
			$current = in_array( 'current-menu-item', $classes, true );
			$url     = trim( (string) $col->url );
			$title   = $this->item_title( $col, $args, 1 );

			echo '<li class="lu3g-mb__col' . ( $current ? ' is-current' : '' ) . '">';
			if ( '' === $url || '#' === $url ) {
				echo '<span class="lu3g-mb__col-title">' . $title . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<a' . $this->attrs( $this->item_link_atts( $col, 'lu3g-mb__col-title', $current ) ) . '>' . $title . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( ( 0 === $max_depth || $max_depth > 2 ) && ! empty( $tree[ $col->ID ] ) ) {
				echo '<ul class="lu3g-mb__col-links">';
				$this->render_mega_links( $tree, $col->ID, 2, $max_depth, $args );
				echo '</ul>';
			}
			echo '</li>';
		}
	}

	private function render_mega_links( array $tree, $parent_id, $level, $max_depth, $args ) {
		foreach ( $tree[ $parent_id ] as $item ) {
			$classes = empty( $item->classes ) ? array() : array_filter( (array) $item->classes );
			$current = in_array( 'current-menu-item', $classes, true );
			$title   = $this->item_title( $item, $args, $level );

			echo '<li>';
			echo '<a' . $this->attrs( $this->item_link_atts( $item, 'lu3g-mb__col-link', $current ) ) . '>' . $title . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ( 0 === $max_depth || $level + 1 < $max_depth ) && ! empty( $tree[ $item->ID ] ) ) {
				echo '<ul class="lu3g-mb__col-links">';
				$this->render_mega_links( $tree, $item->ID, $level + 1, $max_depth, $args );
				echo '</ul>';
			}
			echo '</li>';
		}
	}

	/**
	 * Contenuto di un template Elementor salvato, nella lingua corrente (WPML/Polylang).
	 * Protetto dalla ricorsione: un template che contiene questo stesso menu non si richiama all'infinito.
	 */
	private function mega_template_html( $template_id ) {
		static $stack = array();

		$id = (int) $template_id;
		if ( $id && has_filter( 'wpml_object_id' ) ) {
			$id = (int) apply_filters( 'wpml_object_id', $id, 'elementor_library', true );
		}
		if ( $id && function_exists( 'pll_get_post' ) ) {
			$translated = pll_get_post( $id );
			if ( $translated ) {
				$id = (int) $translated;
			}
		}
		if ( ! $id || in_array( $id, $stack, true ) || 'publish' !== get_post_status( $id ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}

		$stack[] = $id;
		$html    = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, true );
		array_pop( $stack );
		return (string) $html;
	}

	private function bar_indicator_svg( $type ) {
		if ( 'caret' === $type ) {
			return '<span class="lu3g-mb__indicator lu3g-mb__indicator--caret" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 9.5h12L12 16z" fill="currentColor"/></svg></span>';
		}
		if ( 'plus' === $type ) {
			return '<span class="lu3g-mb__indicator lu3g-mb__indicator--plus" aria-hidden="true"></span>';
		}
		return '<span class="lu3g-mb__indicator lu3g-mb__indicator--chevron" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
	}

	/** Indicatore scelto per le tendine ('' = nessuno). */
	private function build_bar_indicator() {
		$type = $this->pick( 'desktop_indicator', array( 'chevron', 'caret', 'plus', 'none' ), 'chevron' );
		return 'none' === $type ? '' : $this->bar_indicator_svg( $type );
	}

	/** Voci extra "come le voci del menu": nella barra diventano voci della barra (stesso stile, stessi effetti). */
	private function bar_extra_menu_items() {
		$out = '';
		foreach ( $this->extras_for( 'desktop' ) as $item ) {
			if ( 'menu' !== $this->extra_group( $item ) ) {
				continue;
			}
			$p = $this->extra_parts( $item );
			if ( ! $p ) {
				continue;
			}
			$atts          = $p['atts'];
			$atts['class'] = 'lu3g-mb__link';
			$out          .= '<li class="lu3g-mb__item lu3g-mb__item--level-0 lu3g-mb__item--extra' . ( $p['current'] ? ' is-current' : '' ) . '"><a' . $this->attrs( $atts ) . '>'
				. ( $p['icon'] ? '<span class="lu3g-mb__link-icon" aria-hidden="true">' . $p['icon'] . '</span>' : '' )
				. '<span class="lu3g-mb__text">' . esc_html( $p['text'] ) . '</span></a></li>';
		}
		return $out;
	}

	/** Altre voci extra nella barra: contatti e testi in linea, social in gruppo, pulsante CTA. */
	private function render_bar_extras( $position ) {
		$html   = '';
		$social = '';
		foreach ( $this->extras_for( 'desktop' ) as $item ) {
			$group = $this->extra_group( $item );
			if ( 'menu' === $group ) {
				continue;
			}
			$p = $this->extra_parts( $item );
			if ( ! $p ) {
				continue;
			}
			$atts = $p['atts'];
			if ( 'social' === $group ) {
				$atts['class'] = 'lu3g-mb__social-link';
				$social       .= '<li><a' . $this->attrs( $atts ) . '>' . $p['icon'] . '</a></li>';
				continue;
			}
			if ( '' !== $social ) {
				$html  .= '<ul class="lu3g-mb__social">' . $social . '</ul>';
				$social = '';
			}
			$icon = $p['icon'] ? '<span class="{class}" aria-hidden="true">' . $p['icon'] . '</span>' : '';
			if ( 'cta' === $group ) {
				$atts['class'] = 'lu3g-mb__cta';
				$html         .= '<a' . $this->attrs( $atts ) . '>' . str_replace( '{class}', 'lu3g-mb__cta-icon', $icon ) . '<span class="lu3g-mb__cta-text">' . esc_html( $p['text'] ) . '</span></a>';
			} elseif ( 'text' === $p['type'] ) {
				$html .= '<span class="lu3g-mb__contact lu3g-mb__contact--text">' . esc_html( $p['text'] ) . '</span>';
			} else {
				$atts['class'] = 'lu3g-mb__contact lu3g-mb__contact--' . $p['type'];
				$html         .= '<a' . $this->attrs( $atts ) . '>' . str_replace( '{class}', 'lu3g-mb__contact-icon', $icon ) . '<span class="lu3g-mb__contact-text">' . esc_html( $p['text'] ) . '</span></a>';
			}
		}
		if ( '' !== $social ) {
			$html .= '<ul class="lu3g-mb__social">' . $social . '</ul>';
		}
		if ( '' !== $html ) {
			echo '<div class="lu3g-mb__extras lu3g-mb__extras--' . esc_attr( $position ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML costruito ed escapato sopra
		}
	}

	/** Icona scelta o, se vuota, quella predefinita del tipo. */
	private function extra_icon( $type, $icon ) {
		if ( empty( $icon['value'] ) ) {
			$defaults = array(
				'phone'    => array( 'value' => 'fas fa-phone', 'library' => 'fa-solid' ),
				'email'    => array( 'value' => 'fas fa-envelope', 'library' => 'fa-solid' ),
				'whatsapp' => array( 'value' => 'fab fa-whatsapp', 'library' => 'fa-brands' ),
				'social'   => array( 'value' => 'fas fa-link', 'library' => 'fa-solid' ),
			);
			if ( ! isset( $defaults[ $type ] ) ) {
				return '';
			}
			$icon = $defaults[ $type ];
		}
		return $this->icon_html( $icon );
	}

	private function icon_html( $icon ) {
		if ( empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/** Nome del social ricavato dal dominio, per gli screen reader quando manca il testo. */
	private function social_name( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$host = preg_replace( '/^(www\.|m\.)/', '', strtolower( $host ) );
		return '' !== $host ? ucfirst( strtok( $host, '.' ) ) : __( 'Social', 'lu3g-mobile-menu' );
	}

	private function anim_style() {
		return ' style="--i:' . (int) $this->anim_index++ . '"';
	}

	/** Attributi HTML già escapati (href con esc_url). */
	private function attrs( array $atts ) {
		$out = '';
		foreach ( $atts as $name => $value ) {
			$value = 'href' === $name ? esc_url( $value ) : esc_attr( $value );
			$out  .= ' ' . $name . '="' . $value . '"';
		}
		return $out;
	}

	/* -------------------------------------------------------------------------------- pagina corrente */

	private function request_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
	}

	/** host + percorso, senza schema, query, ancora e barra finale. */
	private function normalize_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
		return strtolower( preg_replace( '/^www\./', '', $parts['host'] ) ) . $path;
	}

	private function is_current_url( $url ) {
		if ( '' === $this->current_url ) {
			return false;
		}
		// link relativi ("/contatti/"): si completano con l'indirizzo del sito
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}
		// tel:, mailto:, ancore e altri schemi non sono pagine
		if ( ! preg_match( '#^(https?:)?//#i', $url ) ) {
			return false;
		}
		return $this->normalize_url( $url ) === $this->current_url;
	}
}
