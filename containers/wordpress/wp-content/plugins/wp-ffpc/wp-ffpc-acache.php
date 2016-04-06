<?php
/**
 * advanced cache worker of WordPress plugin WP-FFPC
 */

/* check for WP cache enabled*/
if ( !WP_CACHE )
	return false;

/* check for config */
if (!isset($wp_ffpc_config))
	return false;

/* no cache for post request (comments, plugins and so on) */
if ($_SERVER["REQUEST_METHOD"] == 'POST')
	return false;

/**
 * Try to avoid enabling the cache if sessions are managed
 * with request parameters and a session is active
 */
if (defined('SID') && SID != '')
	return false;

/* request uri */
$wp_ffpc_uri = $_SERVER['REQUEST_URI'];


/* no cache for uri with query strings, things usually go bad that way */
if ( isset($wp_ffpc_config['nocache_dyn']) && !empty($wp_ffpc_config['nocache_dyn']) && stripos($wp_ffpc_uri, '?') !== false )
	return false;

/* no cache for robots.txt */
if ( stripos($wp_ffpc_uri, 'robots.txt') )
	return false;

/* multisite files can be too large for memcached */
if ( function_exists('is_multisite') && stripos($wp_ffpc_uri, '/files/') && is_multisite() )
	return false;

/* check if config is network active: use network config */
if (!empty ( $wp_ffpc_config['network'] ) )
	$wp_ffpc_config = $wp_ffpc_config['network'];
/* check if config is active for site : use site config */
elseif ( !empty ( $wp_ffpc_config[ $_SERVER['HTTP_HOST'] ] ) )
	$wp_ffpc_config = $wp_ffpc_config[ $_SERVER['HTTP_HOST'] ];
/* plugin config not found :( */
else
	return false;

/* check for cookies that will make us not cache the content, like logged in WordPress cookie */
if ( isset($wp_ffpc_config['nocache_cookies']) && !empty($wp_ffpc_config['nocache_cookies']) ) {
	$nocache_cookies = array_map('trim',explode(",", $wp_ffpc_config['nocache_cookies'] ) );

	if ( !empty( $nocache_cookies ) ) {
		foreach ($_COOKIE as $n=>$v) {
			/* check for any matches to user-added cookies to no-cache */
			foreach ( $nocache_cookies as $nocache_cookie ) {
				if( strpos( $n, $nocache_cookie ) === 0 ) {
					return false;
				}
			}
		}
	}
}

/* no cache for excluded URL patterns */
if ( isset($wp_ffpc_config['nocache_url']) && trim($wp_ffpc_config['nocache_url']) ) {
	$pattern = sprintf('#%s#', trim($wp_ffpc_config['nocache_url']));
	if ( preg_match($pattern, $wp_ffpc_uri) ) {
		return false;
	}
}


/* canonical redirect storage */
$wp_ffpc_redirect = null;
/* fires up the backend storage array with current config */
include_once ('wp-ffpc-backend.php');
$wp_ffpc_backend = new WP_FFPC_Backend( $wp_ffpc_config );


/* no cache for for logged in users unless it's set
   identifier cookies are listed in backend as var for easier usage
*/
if ( !isset($wp_ffpc_config['cache_loggedin']) || $wp_ffpc_config['cache_loggedin'] == 0 || empty($wp_ffpc_config['cache_loggedin']) ) {

	foreach ($_COOKIE as $n=>$v) {
		foreach ( $wp_ffpc_backend->cookies as $nocache_cookie ) {
			if( strpos( $n, $nocache_cookie ) === 0 ) {
				return false;
			}
		}
	}

}


/* will store time of page generation */
$wp_ffpc_gentime = 0;

/* backend connection failed, no caching :( */
if ( $wp_ffpc_backend->status() === false )
	return false;

/* try to get data & meta keys for current page */
$wp_ffpc_keys = array ( 'meta' => $wp_ffpc_config['prefix_meta'], 'data' => $wp_ffpc_config['prefix_data'] );
$wp_ffpc_values = array();

foreach ( $wp_ffpc_keys as $internal => $key ) {
	$key = $wp_ffpc_backend->key ( $key );
	$value = $wp_ffpc_backend->get ( $key );

	if ( ! $value ) {
		/* does not matter which is missing, we need both, if one fails, no caching */
		wp_ffpc_start();
		return;
	}
	else {
		/* store results */
		$wp_ffpc_values[ $internal ] = $value;
	}
}

/* serve cache 404 status */
if ( isset( $wp_ffpc_values['meta']['status'] ) &&  $wp_ffpc_values['meta']['status'] == 404 ) {
	header("HTTP/1.1 404 Not Found");
	/* if I kill the page serving here, the 404 page will not be showed at all, so we do not do that
	 * flush();
	 * die();
	 */
}

/* server redirect cache */
if ( isset( $wp_ffpc_values['meta']['redirect'] ) && $wp_ffpc_values['meta']['redirect'] ) {
	header('Location: ' . $wp_ffpc_values['meta']['redirect'] );
	/* cut the connection as fast as possible */
	flush();
	die();
}

/* page is already cached on client side (chrome likes to do this, anyway, it's quite efficient) */
if ( array_key_exists( "HTTP_IF_MODIFIED_SINCE" , $_SERVER ) && !empty( $wp_ffpc_values['meta']['lastmodified'] ) ) {
	$if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"]));
	/* check is cache is still valid */
	if ( $if_modified_since >= $wp_ffpc_values['meta']['lastmodified'] ) {
		header("HTTP/1.0 304 Not Modified");
		/* connection cut for faster serving */
		flush();
		die();
	}
}

/*** SERVING CACHED PAGE ***/

/* if we reach this point it means data was found & correct, serve it */
if (!empty ( $wp_ffpc_values['meta']['mime'] ) )
	header('Content-Type: ' . $wp_ffpc_values['meta']['mime']);

/* don't allow browser caching of page */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');

/* expire at this very moment */
header('Expires: ' . gmdate("D, d M Y H:i:s", time() ) . " GMT");

/* if shortlinks were set */
if (!empty ( $wp_ffpc_values['meta']['shortlink'] ) )
	header( 'Link:<'. $wp_ffpc_values['meta']['shortlink'] .'>; rel=shortlink' );

/* if last modifications were set (for posts & pages) */
if ( !empty($wp_ffpc_values['meta']['lastmodified']) )
	header( 'Last-Modified: ' . gmdate("D, d M Y H:i:s", $wp_ffpc_values['meta']['lastmodified'] ). " GMT" );

/* pingback urls, if existx */
if ( !empty( $wp_ffpc_values['meta']['pingback'] ) && $wp_ffpc_config['pingback_header'] )
	header( 'X-Pingback: ' . $wp_ffpc_values['meta']['pingback'] );

/* for debugging */
if ( $wp_ffpc_config['response_header'] )
	header( 'X-Cache-Engine: WP-FFPC with ' . $wp_ffpc_config['cache_type'] .' via PHP');

/* HTML data */
echo $wp_ffpc_values['data'];

flush();
die();

/*** END SERVING CACHED PAGE ***/


/*** GENERATING CACHE ENTRY ***/
/**
 * starts caching function
 *
 */
function wp_ffpc_start( ) {
	/* set start time */
	global $wp_ffpc_gentime;
	$mtime = explode ( " ", microtime() );
	$wp_ffpc_gentime = $mtime[1] + $mtime[0];

	/* start object "colleting" and pass it the the actual storer function  */
	ob_start('wp_ffpc_callback');
}

/**
 * callback function for WordPress redirect urls
 *
 */
function wp_ffpc_redirect_callback ($redirect_url, $requested_url) {
	global $wp_ffpc_redirect;
	$wp_ffpc_redirect = $redirect_url;
	return $redirect_url;
}

/**
 * write cache function, called when page generation ended
 */
function wp_ffpc_callback( $buffer ) {
	/* use global config */
	global $wp_ffpc_config;
	/* backend was already set up, try to use it */
	global $wp_ffpc_backend;
	/* check is it's a redirect */
	global $wp_ffpc_redirect;

	/* no is_home = error, WordPress functions are not availabe */
	if (!function_exists('is_home'))
		return $buffer;

	/* no <body> close tag = not HTML, also no <rss>, not feed, don't cache */
	if ( stripos($buffer, '</body>') === false && stripos($buffer, '</rss>') === false )
		return $buffer;

	/* reset meta to solve conflicts */
	$meta = array();

	/* trim unneeded whitespace from beginning / ending of buffer */
	$buffer = trim( $buffer );

	/* Can be a trackback or other things without a body.
	   We do not cache them, WP needs to get those calls. */
	if (strlen($buffer) == 0)
		return '';

	if ( is_home() )
		$meta['type'] = 'home';
	elseif (is_feed() )
		$meta['type'] = 'feed';
	elseif ( is_archive() )
		$meta['type'] = 'archive';
	elseif ( is_single() )
		$meta['type'] = 'single';
	elseif ( is_page() )
		$meta['type'] = 'page';
	else
		$meta['type'] = 'unknown';

	if ( $meta['type'] != 'unknown' ) {
		/* check if caching is disabled for page type */
		$nocache_key = 'nocache_'. $meta['type'];

		/* don't cache if prevented by rule */
		if ( $wp_ffpc_config[ $nocache_key ] == 1 ) {
			return $buffer;
		}
	}

	if ( is_404() )
		$meta['status'] = 404;

	/* redirect page */
	if ( $wp_ffpc_redirect != null)
		$meta['redirect'] =  $wp_ffpc_redirect;

	/* feed is xml, all others forced to be HTML */
	if ( is_feed() )
		$meta['mime'] = 'text/xml;charset=';
	else
		$meta['mime'] = 'text/html;charset=';

	/* set mimetype */
	$meta['mime'] = $meta['mime'] . $wp_ffpc_config['charset'];

	/* try if post is available
		if made with archieve, last listed post can make this go bad
	*/
	global $post;
	if ( !empty($post) && ( $meta['type'] == 'single' || $meta['type'] == 'page' ) && !empty ( $post->post_modified_gmt ) ) {
		/* get last modification data */
		$meta['lastmodified'] = strtotime ( $post->post_modified_gmt );

		/* get shortlink, if possible */
		if (function_exists('wp_get_shortlink')) {
			$shortlink = wp_get_shortlink( );
			if (!empty ( $shortlink ) )
				$meta['shortlink'] = $shortlink;
		}
	}

	/* store pingback url if pingbacks are enabled */
	if ( get_option ( 'default_ping_status' ) == 'open' )
		$meta['pingback'] = get_bloginfo('pingback_url');

	/* add generation info is option is set, but only to HTML */
	if ( $wp_ffpc_config['generate_time'] == '1' && stripos($buffer, '</body>') ) {
		global $wp_ffpc_gentime;
		$mtime = explode ( " ", microtime() );
		$wp_ffpc_gentime = ( $mtime[1] + $mtime[0] )- $wp_ffpc_gentime;

		$insertion = "\n<!-- \nWP-FFPC \n\tcache engine: ". $wp_ffpc_config['cache_type'] ."\n\tpage generation time: ". round( $wp_ffpc_gentime, 3 ) ." seconds\n\tgeneraton UNIX timestamp: ". time() . "\n\tgeneraton date: ". date( 'c' ) . "\n\tserver: ". $_SERVER['SERVER_ADDR'] . "\n-->\n";
		$index = stripos( $buffer , '</body>' );

		$buffer = substr_replace( $buffer, $insertion, $index, 0);
	}

	$prefix_meta = $wp_ffpc_backend->key ( $wp_ffpc_config['prefix_meta'] );
	$wp_ffpc_backend->set ( $prefix_meta, $meta );

	$prefix_data = $wp_ffpc_backend->key ( $wp_ffpc_config['prefix_data'] );

	//if ( $wp_ffpc_config['gzip'] && function_exists('gzencode') )

	$wp_ffpc_backend->set ( $prefix_data , $buffer );

	if ( !empty( $meta['status'] ) && $meta['status'] == 404 ) {
		header("HTTP/1.1 404 Not Found");
	}
	else {
		/* vital for nginx, make no problem at other places */
		header("HTTP/1.1 200 OK");
	}

	/* echoes HTML out */
	return $buffer;
}
/*** END GENERATING CACHE ENTRY ***/

?>
