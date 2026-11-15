<?php
/**
 * Title: Rejillas: propiedades
 * Slug: marbellainstyle/property-grid
 * Categories: marbellainstyle
 * Description: Tarjetas de mis_property en el idioma actual (Polylang filtra la consulta).
 */
?>
<!-- wp:query {"queryId":0,"query":{"perPage":12,"pages":0,"offset":0,"postType":"mis_property","order":"asc","orderBy":"title","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
<!-- wp:group {"className":"mis-card mis-card--property","layout":{"type":"default"}} -->
<div class="wp-block-group mis-card mis-card--property">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/5"} /-->
<!-- wp:paragraph {"className":"mis-eyebrow","metadata":{"bindings":{"content":{"source":"maros/property","args":{"key":"area"}}}}} --><p class="mis-eyebrow"></p><!-- /wp:paragraph -->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"maros/property","args":{"key":"facts"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results --><!-- wp:paragraph --><p><?php echo esc_html( mis_t( 'Coming soon.', 'Próximamente.' ) ); ?></p><!-- /wp:paragraph --><!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
