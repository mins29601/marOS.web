<?php
/**
 * Title: Rejillas: experiencias
 * Slug: marbellainstyle/tour-grid
 * Categories: marbellainstyle
 * Description: Tarjetas de mis_tour en el idioma actual (Polylang filtra la consulta).
 */
?>
<!-- wp:query {"queryId":0,"query":{"perPage":12,"pages":0,"offset":0,"postType":"mis_tour","order":"asc","orderBy":"title","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
<!-- wp:group {"className":"mis-card","layout":{"type":"default"}} -->
<div class="wp-block-group mis-card">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"facts"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"price"}}}}} --><p></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results --><!-- wp:paragraph --><p><?php echo esc_html( mis_t( 'Coming soon.', 'Próximamente.' ) ); ?></p><!-- /wp:paragraph --><!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
