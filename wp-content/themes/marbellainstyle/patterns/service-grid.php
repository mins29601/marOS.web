<?php
/**
 * Title: Rejillas: servicios
 * Slug: marbellainstyle/service-grid
 * Categories: marbellainstyle
 * Description: Tarjetas de mis_service en el idioma actual (Polylang filtra la consulta).
 */
?>
<!-- wp:query {"queryId":0,"query":{"perPage":12,"pages":0,"offset":0,"postType":"mis_service","order":"asc","orderBy":"title","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"16rem"}} -->
<!-- wp:group {"className":"mis-card","layout":{"type":"default"}} -->
<div class="wp-block-group mis-card">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"service_summary"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"service_price_from"}}}}} --><p></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results --><!-- wp:paragraph --><p><?php echo esc_html( mis_t( 'Coming soon.', 'Próximamente.' ) ); ?></p><!-- /wp:paragraph --><!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
