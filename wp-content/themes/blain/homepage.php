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
			<?php while ( have_posts() ) : the_post(); ?>
                <div class="quotes">
                    <div class="col-md-3">
                        <?= types_render_field('left-hand-image', array("output" => "image")) ?>
                    </div>
                    <div class="col-md-9">
                        <?php
                            $images = get_post_meta(get_the_ID(), 'slider-images');
                            foreach ($images as $image) {
                                echo $image;
                            }
                        ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <!-- <a href="http://192.168.59.103:49153/wp-content/uploads/2014/11/blue_badge.jpg">
                        <img class="alignnone size-full wp-image-56" src="http://192.168.59.103:49153/wp-content/uploads/2014/11/blue_badge.jpg" alt="blue_badge" width="200" height="226" />
                    </a> -->
                </div>
                <div class="col-md-9">
                    <blockquote>John is perfect. You will be wowed!
                        <footer>Katey Hartwell, voted Top Travel Specialist 2000 - 2014 by Conde Nast Traveler Magazine</footer>
                    </blockquote>
                    <blockquote>John is the best! He knows everything; history, hotels, restaurants and scenery. He brings England alive like you cannot imagine. He's fun, interesting and a great storyteller. A day with him is like no other.

                        <footer>Stephen &amp; Nancy hales, USA</footer>
                    </blockquote>
                </div>
                <div class="clearfix visible-xs-block"></div>

                <?php $categories = get_categories(array('type' => 'page', 'hide_empty' => false)); ?>
                <?php foreach ($categories as $category) { ?>
                    <div class="col-xs-6 col-sm-4 tour">
                        <div class="caption">
                            <a href="<?= $category->slug ?>">
                                <h3><?= $category->name ?></h3>
                            </a>
                        </div>
                        <img width="250" src="<?php echo z_taxonomy_image_url($category->term_id); ?>" />
                    </div>
                <?php } ?>
                <div class="clearfix visible-xs-block"></div>
                <div class="col-xs-6 col-sm-4 tour">
                    <h4>Also a proud member of:</h4>
                </div>
                <div class="clearfix visible-xs-block"></div>
                <div class="row">
                    <div class="col-xs-6 col-sm-3">
                        <h5>The institute of tourist guiding</h5>
                    </div>
                    <div class="col-xs-6 col-sm-3">
                        <h5>The guild of registered tourist guide</h5>
                    </div>
                    <div class="col-xs-6 col-sm-3">
                        <h5>The association of professional tourist guides</h5>
                    </div>
                    <div class="col-xs-6 col-sm-3">
                        <h5>The driver-guides association</h5>
                    </div>
                </div>
            </div>

			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
