<?php
/**
 * Title: Footer
 * Slug: brra/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * @package brra
 */
?>
<!-- wp:group {"tagName":"footer","className":"brra-footer","backgroundColor":"footer","style":{"color":{"text":"#C9D2DE"},"typography":{"fontSize":"15px"}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group brra-footer has-footer-background-color has-text-color has-background" style="color:#C9D2DE;font-size:15px">
<!-- wp:columns {"align":"wide","style":{"spacing":{"padding":{"top":"56px","bottom":"28px"},"blockGap":{"left":"36px"}}}} -->
<div class="wp-block-columns alignwide" style="padding-top:56px;padding-bottom:28px">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:image {"width":"64px","sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full is-resized"><img src="<?php echo brra_asset( 'images/crest-white.png' ); ?>" alt="Bury &amp; Rochdale RA crest" style="width:64px"/></figure>
<!-- /wp:image -->
<!-- wp:paragraph -->
<p>Supporting, training and developing match officials since 1898.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph {"className":"brra-footer__head"} -->
<p class="brra-footer__head">FIND US</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>St Mary's Social Club<br>Pine Street, Radcliffe<br>M26 2WQ</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>First Thursday of the month, 7.00 for 7.30pm</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph {"className":"brra-footer__head"} -->
<p class="brra-footer__head">DOCUMENTS</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( home_url( '/documents/' ) ); ?>">Rules 2026</a><br><a href="<?php echo esc_url( home_url( '/documents/' ) ); ?>">Privacy Notice</a><br><a href="<?php echo esc_url( home_url( '/documents/' ) ); ?>">Data Protection Policy</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph {"className":"brra-footer__head"} -->
<p class="brra-footer__head">CONTACT</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><a href="mailto:mail@buryrefs.org.uk">mail@buryrefs.org.uk</a><br><a href="https://www.instagram.com/buryreferees/">Instagram @buryreferees</a><br><a href="https://www.facebook.com/profile.php?id=61565202158021">Facebook</a><br><a href="https://twitter.com/BuryReferees">X @BuryReferees</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
<!-- wp:group {"align":"wide","style":{"border":{"top":{"color":"#1C2836","width":"1px"}},"spacing":{"padding":{"top":"18px","bottom":"30px"}},"typography":{"fontSize":"13px"},"color":{"text":"#8E9BAD"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide has-text-color" style="border-top-color:#1C2836;border-top-width:1px;color:#8E9BAD;padding-top:18px;padding-bottom:30px;font-size:13px">
<!-- wp:paragraph -->
<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Bury &amp; Rochdale Referees' Association</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</footer>
<!-- /wp:group -->
