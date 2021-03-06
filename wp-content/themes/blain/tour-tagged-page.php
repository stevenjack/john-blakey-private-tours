<?php
/**
 * Template Name: Category
 *
 * @package Blain
 */

$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
$cat_details = get_category_by_slug($category);

query_posts(
    array (
        'category_name' => $category,
        'order' => 'ASC'
    )
);

function twentytwelve_wp_title($title, $sep) {
    global $cat_details;
    $site_description = get_bloginfo( 'description', 'display' );
    return "{$cat_details->name} $sep $site_description";
}
add_filter( 'wp_title', 'twentytwelve_wp_title', 10, 2);

get_header();

?>

    <div id="tour" class="content-area">
        <main id="main" class="site-main tour-main expanded" role="main">
            <header class="entry-header">
                <h1 class="entry-title"><?= $cat_details->name ?></h1>
            </header>
			<?php while ( have_posts() ) : the_post(); ?>
                <?php $post = get_post(); ?>
                <div class="row tour-item" id="post-<?= $post->ID ?>">
                    <div class="col-md-6">
                        <?= types_render_field('tour-image', array("post_id" => $post->ID, "output" => "image")) ?>
                    </div>
                    <div class="col-md-6">
                        <div class="title">
                            <div class="row">
                                <div class="col-md-9">
                                    <h4 class="inner-title"><?= $post->post_title ?></h4>
                                </div>
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                        <div class="blurb"><?= apply_filters('the_content', $post->post_content) ?></div>
                    </div>
                </div>

                <?= ($wp_query->current_post + 1 < $wp_query->post_count != 0) ? '<hr>' : '' ?>
			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
