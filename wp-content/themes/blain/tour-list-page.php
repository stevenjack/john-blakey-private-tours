<?php
/**
 * Template Name: Tour list page
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
                <?php $children = get_children(array('order' => 'ASC', 'post_parent' => get_the_ID())); ?>
                <?php $post = get_post(); ?>

			<div class="row">
                <div class="col-md-4">
                    <h1><?= $post->post_title ?></h1>
                    <?= types_render_field('tour-list-image', array("output" => "image")) ?></div>
                <div class="col-md-8"></p><?= apply_filters('the_content', $post->post_content) ?></p></div>
			</div>

            <?php foreach ($children as $child_id => $child) { ?>
                <h3><?php echo $child->post_title; ?></h3>
                <?php $tour_children = get_children(array('post_parent' => $child_id)); ?>
                <?php if (!empty($child->post_content)) { ?>
                    <p><?= apply_filters('the_content', $child->post_content); ?></p>
                <?php } ?>
                <ul>
                <?php foreach ($tour_children as $tour_child_id => $tour_child) { ?>
                    <li><a href="<?= $tour_child->guid ?>"><?= $tour_child->post_title ?></a></li>
                <?php } ?>
                </ul>
            <?php } ?>

			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
