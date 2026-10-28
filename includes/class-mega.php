<?php
/**
 * Mega menu: impostazioni per singola voce in Aspetto → Menu.
 *
 * Ogni voce del menu riceve il riquadro "Mega menu LU3G" (vale per le voci di primo livello, nella barra desktop):
 * - Nessuno: tendina normale con le sotto-voci;
 * - Colonne: le sotto-voci diventano colonne (titolo), le loro sotto-voci i link della colonna;
 * - Template Elementor: la tendina mostra un template salvato (Template → Template salvati), con immagini, testi, pulsanti.
 * I dati stanno nel meta "_lu3g_mega" della voce. Con WPML e Polylang ogni lingua ha le sue voci: si impostano su ciascuna.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_Mobile_Menu_Mega {

	const META = '_lu3g_mega';

	public static function init() {
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save' ), 10, 2 );
	}

	/** Impostazioni di una voce: type ('' | 'columns' | 'template'), template (id), cols (0 = automatico). */
	public static function get( $item_id ) {
		$m = get_post_meta( (int) $item_id, self::META, true );
		$m = is_array( $m ) ? $m : array();
		return array(
			'type'     => isset( $m['type'] ) && in_array( $m['type'], array( 'columns', 'template' ), true ) ? $m['type'] : '',
			'template' => isset( $m['template'] ) ? absint( $m['template'] ) : 0,
			'cols'     => isset( $m['cols'] ) ? min( 6, absint( $m['cols'] ) ) : 0,
		);
	}

	/** Template Elementor salvati (titolo e tipo), per la tendina. */
	private static function templates() {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list  = array();
		$posts = get_posts( array(
			'post_type'        => 'elementor_library',
			'post_status'      => 'publish',
			'numberposts'      => 300,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );
		foreach ( $posts as $p ) {
			$type             = get_post_meta( $p->ID, '_elementor_template_type', true );
			$list[ $p->ID ] = $p->post_title . ( $type ? ' (' . $type . ')' : '' );
		}
		return $list;
	}

	public static function fields( $item_id, $item ) {
		$m     = self::get( $item_id );
		$id    = (int) $item_id;
		$types = array(
			''         => __( 'Nessuno (tendina normale)', 'lu3g-mobile-menu' ),
			'columns'  => __( 'Colonne dalle sotto-voci', 'lu3g-mobile-menu' ),
			'template' => __( 'Template Elementor', 'lu3g-mobile-menu' ),
		);
		?>
		<div class="lu3g-mega-fields description-wide" style="clear:both;margin:10px 0 6px;padding:8px 10px;background:#f6f7f7;border-left:3px solid #2271b1;">
			<input type="hidden" name="lu3g_mega_nonce" value="<?php echo esc_attr( wp_create_nonce( 'lu3g_mega' ) ); ?>">
			<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Mega menu LU3G', 'lu3g-mobile-menu' ); ?></strong>
				<span class="description"><?php esc_html_e( '— solo voci di primo livello, nella barra desktop del widget Menu LU3G', 'lu3g-mobile-menu' ); ?></span></p>
			<p class="description description-thin" style="margin-top:0;">
				<label><?php esc_html_e( 'Tipo', 'lu3g-mobile-menu' ); ?><br>
					<select name="lu3g_mega_type[<?php echo esc_attr( $id ); ?>]" class="widefat">
						<?php foreach ( $types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $m['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p class="description description-thin" style="margin-top:0;">
				<label><?php esc_html_e( 'Colonne (0 = automatico)', 'lu3g-mobile-menu' ); ?><br>
					<input type="number" min="0" max="6" class="widefat" name="lu3g_mega_cols[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $m['cols'] ); ?>">
				</label>
			</p>
			<p class="description description-wide" style="margin-top:0;">
				<label><?php esc_html_e( 'Template Elementor (per il tipo "Template Elementor")', 'lu3g-mobile-menu' ); ?><br>
					<select name="lu3g_mega_template[<?php echo esc_attr( $id ); ?>]" class="widefat">
						<option value="0"><?php esc_html_e( '— scegli un template —', 'lu3g-mobile-menu' ); ?></option>
						<?php foreach ( self::templates() as $tid => $title ) : ?>
							<option value="<?php echo esc_attr( $tid ); ?>" <?php selected( $m['template'], $tid ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
		</div>
		<?php
	}

	public static function save( $menu_id, $item_id ) {
		// solo dal salvataggio di Aspetto → Menu (i campi ci sono) e con il nonce di questo riquadro
		if ( ! isset( $_POST['lu3g_mega_type'][ $item_id ], $_POST['lu3g_mega_nonce'] ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lu3g_mega_nonce'] ) ), 'lu3g_mega' ) ) {
			return;
		}

		$type = sanitize_key( wp_unslash( $_POST['lu3g_mega_type'][ $item_id ] ) );
		$type = in_array( $type, array( 'columns', 'template' ), true ) ? $type : '';
		$tpl  = isset( $_POST['lu3g_mega_template'][ $item_id ] ) ? absint( $_POST['lu3g_mega_template'][ $item_id ] ) : 0;
		$cols = isset( $_POST['lu3g_mega_cols'][ $item_id ] ) ? min( 6, absint( $_POST['lu3g_mega_cols'][ $item_id ] ) ) : 0;

		if ( '' === $type ) {
			delete_post_meta( $item_id, self::META );
		} else {
			update_post_meta( $item_id, self::META, array( 'type' => $type, 'template' => $tpl, 'cols' => $cols ) );
		}
	}
}
