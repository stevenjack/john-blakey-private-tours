<?php
/**
 * Template Name: Homepage
 *
 * @package Blain
 */

get_header(); ?>

<?php
    $children = null;
?>
	<div id="tours" class="content-area">
        <main id="main" class="site-main" role="main">
            <div class="quote">
                <div class="row">
                    <div class="col-md-1 quote-mark quote-mark-left">
                        <img src="/wp-content/themes/blain/images/quote_left.svg" />
                    </div>
                    <div class="col-md-10">
                        <span class="open-quote"></span>
                        <p>John is the best! He knows everything; history, hotels, restaurants and scenery. He brings England alive like you cannot imagine. He's fun, interesting and a great storyteller. A day with him is like no other.</p>
                        <div class="quotes"><em>Stephen & Nancy hales, USA</em></div>
                    </div>
                    <div class="col-md-1 quote-mark quote-mark-right">
                        <img src="/wp-content/themes/blain/images/quote_right.svg" />
                    </div>
                </div>
            </div>
			<?php while ( have_posts() ) : the_post(); ?>
                        <?= '';//types_render_field('slider-testimonial', array('separator' => '</blockquote><blockquote>')) ?>
                <?php $categories = get_categories(array('type' => 'page', 'hide_empty' => false)); ?>
                <div class="row region-tour">
                <?php foreach ($categories as $i => $category) { ?>
                    <?= ($i != 0 && (0 == $i % 3)) ? '</div><div class="row region-tour">' : '' ?>
                    <div class="col-sm-6 col-md-4 tour">
                        <div class="thumbnail">
                            <div class="caption">
                                <a class="title-link" href="/?page_id=143&category=<?= $category->slug ?>"
                                    <h3 class="thumbnail-title"><?= $category->name ?></h3>
                                </a>
                            </div>
                            <a class="image-link" href="/?page_id=143&category=<?= $category->slug ?>">
                                <img src="<?php echo z_taxonomy_image_url($category->term_id); ?>" alt="...">
                            </a>
                        </div>
                    </div>
                <?php } ?>
                </div>
                <div class="clearfix visible-xs-block"></div>
                <div class="col-xs-6 col-sm-4 tour">
                    <h4>Also a proud member of:</h4>
                </div>
                <div class="clearfix visible-xs-block"></div>
                <div class="row">
                    <ul>
                        <li>
                            <?= types_render_field('member-of', array('separator' => '</li><li>')) ?>
                        </li>
                    </ul>
                </div>
            </div>

			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
