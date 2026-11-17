<?php
/**
 * Title: Menú en capa (cabecera)
 * Slug: marbellainstyle/menu-overlay
 * Categories: marbellainstyle
 * Inserter: no
 * Description: Botón «Menú» que abre una capa a pantalla completa (spec 005, menús v2).
 */
?>
<!-- wp:html -->
<details class="mis-overlay"><summary aria-label="<?php echo esc_attr( mis_t( 'Menu', 'Menú' ) ); ?>"><span class="mis-overlay__label"><?php echo esc_html( mis_t( 'Menu', 'Menú' ) ); ?></span></summary>
<div class="mis-overlay__panel">
<div class="mis-overlay__top"><?php echo do_shortcode( '[mis_logo variant="blanco"]' ); ?></div>
<div class="mis-overlay__grid">
<div><?php echo do_shortcode( '[mis_menu location="primary"]' ); ?></div>
<div><?php echo do_shortcode( '[mis_menu location="overlay"]' ); ?><?php echo do_shortcode( '[mis_lang]' ); ?></div>
<?php echo do_shortcode( '[mis_contact_card]' ); ?>
</div>
<div class="mis-overlay__cta"><a class="wp-element-button" href="<?php echo esc_url( mis_page_url( 'stay' ) ); ?>"><?php echo esc_html( mis_t( 'Book a home', 'Reservar una casa' ) ); ?></a></div>
</div>
</details>
<!-- /wp:html -->
