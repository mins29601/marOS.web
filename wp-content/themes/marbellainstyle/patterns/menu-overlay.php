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
<details class="mis-overlay"><summary><?php echo esc_html( mis_t( 'Menu', 'Menú' ) ); ?></summary>
<div class="mis-overlay__panel"><div class="mis-overlay__grid">
<div><?php echo do_shortcode( '[mis_menu location="primary"]' ); ?></div>
<div><?php echo do_shortcode( '[mis_menu location="overlay"]' ); ?><?php echo do_shortcode( '[mis_lang]' ); ?></div>
<div class="mis-contact-card">
<p class="mis-eyebrow"><?php echo esc_html( mis_t( 'Talk to us', 'Habla con nosotros' ) ); ?></p>
<p><strong>[Nombre]</strong> · <?php echo esc_html( mis_t( 'your contact in Marbella', 'tu contacto en Marbella' ) ); ?></p>
<p class="mis-muted"><?php echo esc_html( mis_t( 'Mon–Sat, 9:00–20:00', 'Lun–sáb, 9:00–20:00' ) ); ?></p>
<p><a href="https://wa.me/34600000000">WhatsApp</a> · <a href="tel:+34600000000"><?php echo esc_html( mis_t( 'Call', 'Llamar' ) ); ?></a></p>
<p><a href="https://www.instagram.com/">Instagram</a> · <a href="https://www.tiktok.com/">TikTok</a></p>
</div>
</div></div>
</details>
<!-- /wp:html -->
