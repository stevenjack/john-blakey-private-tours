<?php
/**
 * Template Name: Home
 *
 * @package Blain
 */

get_header(); ?>

	<div id="tours" class="content-area">
        <main id="main" class="site-main" role="main">
			<?php while ( have_posts() ) : the_post(); ?>
                <?php $children = get_children(array('order' => 'ASC', 'post_parent' => get_the_ID())); ?>
                <?php $post = get_post(); ?>

                <?php foreach ($children as $child_id => $child) { ?>

                    <div class="col-xs-6 col-sm-4 tour">
                        <div class="caption">
                            <a href="<?= $tour_child->guid ?>"></a>
                            <h3>Windsor castle &amp; Hampton Court</h3>
                        </div>
                        <img src="http://192.168.59.103:49153/wp-content/uploads/2014/11/IMG_1222.jpg" alt="Windsor castle &amp; Hampton Courtr" />
                    </div>

			<?php endwhile; // end of the loop. ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
