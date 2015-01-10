<?php
/*
  Plugin Name: Types - Complete Solution for Custom Fields and Types
  Plugin URI: http://wordpress.org/extend/plugins/types/
  Description: Define custom post types, custom taxonomy and custom fields.
  Author: OnTheGoSystems
  Author URI: http://www.onthegosystems.com
  Version: 1.6.4
 */
/**
 *
 * $HeadURL: http://plugins.svn.wordpress.org/types/tags/1.6.4/wpcf.php $
 * $LastChangedDate: 2014-11-18 06:52:14 +0000 (Tue, 18 Nov 2014) $
 * $LastChangedRevision: 1027716 $
 * $LastChangedBy: iworks $
 *
 */
// Added check because of activation hook and theme embedded code
if ( !defined( 'WPCF_VERSION' ) ) {
    /**
     * make sure that WPCF_VERSION in embedded/bootstrap.php is the same!
     */
    define( 'WPCF_VERSION', '1.6.4' );
}

define( 'WPCF_REPOSITORY', 'http://api.wp-types.com/' );

define( 'WPCF_ABSPATH', dirname( __FILE__ ) );
define( 'WPCF_RELPATH', plugins_url() . '/' . basename( WPCF_ABSPATH ) );
define( 'WPCF_INC_ABSPATH', WPCF_ABSPATH . '/includes' );
define( 'WPCF_INC_RELPATH', WPCF_RELPATH . '/includes' );
define( 'WPCF_RES_ABSPATH', WPCF_ABSPATH . '/resources' );
define( 'WPCF_RES_RELPATH', WPCF_RELPATH . '/resources' );

//Add installer
include dirname( __FILE__ ) . '/embedded/common/installer/loader.php';
WP_Installer_Setup($wp_installer_instance,  
array(
    'plugins_install_tab' => '1',
    'repositories_include' => array('toolset', 'wpml')	
));
		
require_once WPCF_INC_ABSPATH . '/constants.php';
/*
 * Since Types 1.2 we load all embedded code without conflicts
 */
require_once WPCF_ABSPATH . '/embedded/types.php';

require_once WPCF_ABSPATH . '/embedded/onthego-resources/onthegosystems-branding-loader.php';
ont_set_on_the_go_systems_uri_and_start(WPCF_RELPATH . '/embedded/onthego-resources/' );


// Plugin mode only hooks
add_action( 'plugins_loaded', 'wpcf_init' );

// init hook for module manager
add_action( 'init', 'wpcf_wp_init' );

register_activation_hook( __FILE__, 'wpcf_upgrade_init' );
register_deactivation_hook( __FILE__, 'wpcf_deactivation_hook' );

add_filter( 'plugin_action_links', 'wpcf_types_plugin_action_links', 10, 2 );

/**
 * Deactivation hook.
 *
 * Reset some of data.
 */
function wpcf_deactivation_hook()
{
    // Reset redirection
    delete_option( 'wpcf_types_plugin_do_activation_redirect', true );

    // Delete messages
    delete_option( 'wpcf-messages' );
}

/**
 * Main init hook.
 */
function wpcf_init()
{
    if ( !defined( 'EDITOR_ADDON_RELPATH' ) ) {
        define( 'EDITOR_ADDON_RELPATH', WPCF_RELPATH . '/embedded/common/visual-editor' );
    }

    if ( is_admin() ) {
	
        require_once WPCF_ABSPATH . '/admin.php';
    }
	
    
}

//Render Installer packages
function installer_content(){			
	echo '<div class="wrap">';
	$config['repository'] = array(); // required
	WP_Installer_Show_Products($config);			
	echo "</div>";			
}

/**
 * WP Main init hook.
 */
function wpcf_wp_init()
{
    if ( is_admin() ) {
	
		require_once WPCF_ABSPATH . '/admin.php';
		add_action('admin_menu', 'setup_installer');
		//Add submenu Installer to Types
		function setup_installer(){
			add_submenu_page('wpcf', 'Installer', 'Installer', 'manage_options', 'installer', 'installer_content');
		}
	}
}

/**
 * Include embedded code if not used in theme.
 *
 * We are actually calling this hook on after_setup_theme which is called
 * immediatelly before 'init'. However WP issues warnings because for some
 * action it strictly required 'init' hook to be used.
 *
 * @todo Revise this!
 */
/*
 * TODO 1.2.1 remove
 */
//function wpcf_init_embedded_code() {
//    if ( !defined( 'WPCF_EMBEDDED_ABSPATH' ) ) {
//        require_once WPCF_ABSPATH . '/embedded/types.php';
//    } else {
//        require_once WPCF_EMBEDDED_ABSPATH . '/types.php';
//    }
//
//    // TODO Better bootstrapping is ready to be added
//    // Make this check for now.
//    if ( did_action( 'init' ) > 0 ) {
//        wpcf_embedded_init();
//    } else {
//        add_action( 'init', 'wpcf_embedded_init' );
//    }
//}

/**
 * Upgrade hook.
 */
function wpcf_upgrade_init()
{
    wpcf_types_plugin_activate();
}

function wpcf_types_plugin_activate()
{
    add_option( 'wpcf_types_plugin_do_activation_redirect', true );
}

function wpcf_types_plugin_redirect()
{
    if ( get_option( 'wpcf_types_plugin_do_activation_redirect', false ) ) {
        delete_option( 'wpcf_types_plugin_do_activation_redirect' );
        wp_redirect( admin_url() . 'admin.php?page=wpcf-help' );
        exit;
    }
}

function wpcf_types_plugin_action_links($links, $file)
{
    $this_plugin = basename( WPCF_ABSPATH ) . '/wpcf.php';
    if ( $file == $this_plugin ) {
        $links[] = '<a href="admin.php?page=wpcf-help">' . __( 'Getting started',
                        'wpcf' ) . '</a>';
    }
    return $links;
}

/**
 * Checks if name is reserved.
 *
 * @param type $name
 * @return type
 */
function wpcf_is_reserved_name($name, $context, $check_pages = true)
{
    $name = strval( $name );
    /*
     *
     * If name is empty string skip page cause there might be some pages without name
     */
    if ( $check_pages && !empty( $name ) ) {
        global $wpdb;
        $page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type='page'",
                        sanitize_title( $name ) ) );
        if ( !empty( $page ) ) {
            return new WP_Error( 'wpcf_reserved_name', __( 'You cannot use this slug because there is already a page by that name. Please choose a different slug.',
                                    'wpcf' ) );
        }
    }

    // Add custom types
    $custom_types = (array) get_option( 'wpcf-custom-types', array() );
    $post_types = get_post_types();
    if ( !empty( $custom_types ) ) {
        $custom_types = array_keys( $custom_types );
        $post_types = array_merge( array_combine( $custom_types, $custom_types ),
                $post_types );
    }
    // Unset to avoid checking itself
    if ( $context == 'post_type' && isset( $post_types[$name] ) ) {
        unset( $post_types[$name] );
    }

    // Add taxonomies
    $custom_taxonomies = (array) get_option( 'wpcf-custom-taxonomies', array() );
    $taxonomies = get_taxonomies();
    if ( !empty( $custom_taxonomies ) ) {
        $custom_taxonomies = array_keys( $custom_taxonomies );
        $taxonomies = array_merge( array_combine( $custom_taxonomies,
                        $custom_taxonomies ), $taxonomies );
    }
    // Unset to avoid checking itself
    if ( $context == 'taxonomy' && isset( $taxonomies[$name] ) ) {
        unset( $taxonomies[$name] );
    }

    $reserved_names = wpcf_reserved_names();
    $reserved = array_merge( array_combine( $reserved_names, $reserved_names ),
            array_merge( $post_types, $taxonomies ) );

    return in_array( $name, $reserved ) ? new WP_Error( 'wpcf_reserved_name', __( 'You cannot use this slug because it is a reserved word, used by WordPress. Please choose a different slug.',
                            'wpcf' ) ) : false;
}

/**
 * Reserved names.
 *
 * @return type
 */
function wpcf_reserved_names()
{
    $reserved = array(
        'attachment',
        'attachment_id',
        'author',
        'author_name',
        'calendar',
        'cat',
        'category',
        'category__and',
        'category__in',
        'category__not_in',
        'category_name',
        'comments_per_page',
        'comments_popup',
        'cpage',
        'day',
        'debug',
        'error',
        'exact',
        'feed',
        'format',
        'hour',
        'link_category',
        'm',
        'minute',
        'monthnum',
        'more',
        'name',
        'nav_menu',
        'nopaging',
        'offset',
        'order',
        'orderby',
        'p',
        'page',
        'page_id',
        'paged',
        'pagename',
        'pb',
        'perm',
        'post',
        'post__in',
        'post__not_in',
        'post_format',
        'post_mime_type',
        'post_status',
        'post_tag',
        'post_type',
        'posts',
        'posts_per_archive_page',
        'posts_per_page',
        'preview',
        'robots',
        's',
        'search',
        'second',
        'sentence',
        'showposts',
        'static',
        'subpost',
        'subpost_id',
        'tag',
        'tag__and',
        'tag__in',
        'tag__not_in',
        'tag_id',
        'tag_slug__and',
        'tag_slug__in',
        'taxonomy',
        'tb',
        'term',
        'type',
        'w',
        'withcomments',
        'withoutcomments',
        'year',
        'lang',
//        'comments',
//        'blog',
//        'files'
    );

    return apply_filters( 'wpcf_reserved_names', $reserved );
}

add_action( 'icl_pro_translation_saved', 'wpcf_fix_translated_post_relationships' );

function wpcf_fix_translated_post_relationships($post_id)
{
    require_once WPCF_EMBEDDED_ABSPATH . '/includes/post-relationship.php';
    wpcf_post_relationship_set_translated_parent( $post_id );
    wpcf_post_relationship_set_translated_children( $post_id );
}

