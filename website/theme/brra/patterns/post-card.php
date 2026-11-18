<?php
/**
 * Title: News card
 * Slug: brra/post-card
 * Inserter: no
 *
 * @package brra
 */
?>
<!-- wp:group {"className":"brra-post-card is-style-card","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group brra-post-card is-style-card">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","style":{"spacing":{"margin":{"bottom":"0"}}}} /-->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"18px","right":"20px","bottom":"24px","left":"20px"},"blockGap":"8px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:18px;padding-right:20px;padding-bottom:24px;padding-left:20px">
<!-- wp:group {"style":{"spacing":{"blockGap":"6px"},"typography":{"fontSize":"13px","fontWeight":"700"}},"textColor":"gold-text","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group has-gold-text-color has-text-color" style="font-size:13px;font-weight:700">
<!-- wp:post-terms {"term":"category","separator":" · ","style":{"elements":{"link":{"color":{"text":"var:preset|color|gold-text"}}},"typography":{"textDecoration":"none"}}} /-->
<!-- wp:post-date {"format":"j M Y","style":{"typography":{"fontSize":"13px"}}} /-->
</div>
<!-- /wp:group -->
<!-- wp:post-title {"isLink":true,"level":3,"style":{"typography":{"fontSize":"18px","fontWeight":"800","lineHeight":"1.3"},"spacing":{"margin":{"top":"8px"}}}} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
