<?php

if (!class_exists('WP_FFPC_Backend')) :

include_once ( 'wp-common/plugin_utils.php');
/**
 *
 * @var string	$plugin_constant	Namespace of the plugin
 * @var mixed	$connection	Backend object storage variable
 * @var boolean	$alive		Alive flag of backend connection
 * @var array	$options	Configuration settings array
 * @var array	$status		Backends status storage
 * @var array	$cookies	Logged in cookies to search for
 * @var array	$urimap		Map to render key with
 * @var object	$utilities	Utilities singleton
 *
 */
class WP_FFPC_Backend {

	const host_separator  = ',';
	const port_separator  = ':';

	private $plugin_constant = 'wp-ffpc';
	private $connection = NULL;
	private $alive = false;
	private $options = array();
	private $status = array();
	public $cookies = array();
	private $urimap = array();
	private $utilities;

	/**
	* constructor
	*
	* @param mixed $config Configuration options
	* @param boolean $network WordPress Network indicator flah
	*
	*/
	public function __construct( $config ) {

		/* no config, nothing is going to work */
		if ( empty ( $config ) ) {
			return false;
			//die ( __translate__ ( 'WP-FFPC Backend class received empty configuration array, the plugin will not work this way', $this->plugin_constant ) );
		}

		/* set config */
		$this->options = $config;

		/* these are the list of the cookies to look for when looking for logged in user */
		$this->cookies = array ( 'comment_author_' , 'wordpressuser_' , 'wp-postpass_', 'wordpress_logged_in_' );

		/* make utilities singleton */
		$this->utilities = new PluginUtils();

		/* map the key with the predefined schemes */
		$ruser = isset ( $_SERVER['REMOTE_USER'] ) ? $_SERVER['REMOTE_USER'] : '';
		$ruri = isset ( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$rhost = isset ( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		$scookie = isset ( $_COOKIE['PHPSESSID'] ) ? $_COOKIE['PHPSESSID'] : '';

		$this->urimap = array(
			'$scheme' => str_replace ( '://', '', $this->utilities->replace_if_ssl ( 'http://' ) ),
			'$host' => $rhost,
			'$request_uri' => $ruri,
			'$remote_user' => $ruser,
			'$cookie_PHPSESSID' => $scookie,
		);

		/* split hosts entry to servers */
		$this->set_servers();

		/* call backend initiator based on cache type */
		$init = $this->proxy( 'init' );

		/* info level */
		$this->log (  __translate__('init starting', $this->plugin_constant ));
		$this->$init();

	}


	public static function parse_urimap($uri, $default_urimap=null) {
		$uri_parts = parse_url( $uri );

		$uri_map = array(
			'$scheme' => $uri_parts['scheme'],
			'$host' => $uri_parts['host'],
			'$request_uri' => $uri_parts['path']
		);

		if (is_array($default_urimap)) {
			$uri_map = array_merge($default_urimap, $uri_map);
		}

		return $uri_map;
	}

	public static function map_urimap($urimap, $subject) {
		return str_replace(array_keys($urimap), $urimap, $subject);
	}

	/*********************** PUBLIC / PROXY FUNCTIONS ***********************/

	/**
	 * build key to make requests with
	 *
	 * @param string $prefix prefix to add to prefix
	 *
	 */
	public function key ( &$prefix ) {
		/* data is string only with content, meta is not used in nginx */
		$key = $prefix . self::map_urimap($this->urimap, $this->options['key']);
		$this->log ( sprintf( __translate__( 'original key configuration: %s', $this->plugin_constant ),  $this->options['key'] ) );
		$this->log ( sprintf( __translate__( 'setting key to: %s', $this->plugin_constant ),  $key ) );
		return $key;
	}


	/**
	 * public get function, transparent proxy to internal function based on backend
	 *
	 * @param string $key Cache key to get value for
	 *
	 * @return mixed False when entry not found or entry value on success
	 */
	public function get ( &$key ) {

		/* look for backend aliveness, exit on inactive backend */
		if ( ! $this->is_alive() )
			return false;

		/* log the current action */
		$this->log ( sprintf( __translate__( 'get %s', $this->plugin_constant ),  $key ) );

		/* proxy to internal function */
		$internal = $this->proxy( 'get' );
		$result = $this->$internal( $key );

		if ( $result === false  )
			$this->log ( sprintf( __translate__( 'failed to get entry: %s', $this->plugin_constant ),  $key ) );

		return $result;
	}

	/**
	 * public set function, transparent proxy to internal function based on backend
	 *
	 * @param string $key Cache key to set with ( reference only, for speed )
	 * @param mixed $data Data to set ( reference only, for speed )
	 *
	 * @return mixed $result status of set function
	 */
	public function set ( &$key, &$data, $expire = false ) {

		/* look for backend aliveness, exit on inactive backend */
		if ( ! $this->is_alive() )
			return false;

		/* log the current action */
		$this->log ( sprintf( __translate__( 'set %s expiration time: %s', $this->plugin_constant ),  $key, $this->options['expire'] ) );

		/* expiration time based is based on type from now on */
		/* fallback */
		if ( $expire === false )
			$expire = empty ( $this->options['expire'] ) ? 0 : $this->options['expire'];

		if (( is_home() || is_feed() ) && isset($this->options['expire_home']))
			$expire = (int) $this->options['expire_home'];
		elseif (( is_tax() || is_category() || is_tag() || is_archive() ) && isset($this->options['expire_taxonomy']))
			$expire = (int) $this->options['expire_taxonomy'];

		/* proxy to internal function */
		$internal = $this->proxy( 'set' );
		$result = $this->$internal( $key, $data, $expire );

		/* check result validity */
		if ( $result === false )
			$this->log ( sprintf( __translate__( 'failed to set entry: %s', $this->plugin_constant ),  $key ), LOG_WARNING );

		return $result;
	}


	public function clear_ng ( $new_status, $old_status, $post ) {
		$this->clear ( $post->ID );
	}

	/**
	 * public get function, transparent proxy to internal function based on backend
	 *
	 * @param string $post_id	ID of post to invalidate
	 * @param boolean $force 	Force flush cache
	 * @param boolean $comment	Clear a single page based on comment trigger
	 *
	 */
	public function clear ( $post_id = false, $force = false ) {

		/* look for backend aliveness, exit on inactive backend */
		if ( ! $this->is_alive() )
			return false;

		/* exit if no post_id is specified */
		if ( empty ( $post_id ) && $force === false ) {
			$this->log (  __translate__('not clearing unidentified post ', $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* if invalidation method is set to full, flush cache */
		if ( ( $this->options['invalidation_method'] === 0 || $force === true ) ) {
			/* log action */
			$this->log (  __translate__('flushing cache', $this->plugin_constant ) );

			/* proxy to internal function */
			$internal = $this->proxy ( 'flush' );
			$result = $this->$internal();

			if ( $result === false )
				$this->log (  __translate__('failed to flush cache', $this->plugin_constant ), LOG_WARNING );

			return $result;
		}

		/* storage for entries to clear */
		$to_clear = array();

		/* clear taxonomies if settings requires it */
		if ( $this->options['invalidation_method'] == 2 ) {
			/* this will only clear the current blog's entries */
			$this->taxonomy_links( $to_clear );
		}

		/* clear pasts index page if settings requires it */
		if ( $this->options['invalidation_method'] == 3 ) {
			$posts_page_id = get_option( 'page_for_posts' );
			$post_type = get_post_type( $post_id );

			if ($post_type === 'post' && $posts_page_id != $post_id) {
				$this->clear($posts_page_id, $force);
			}
		}


		/* if there's a post id pushed, it needs to be invalidated in all cases */
		if ( !empty ( $post_id ) ) {

			/* need permalink functions */
			if ( !function_exists('get_permalink') )
				include_once ( ABSPATH . 'wp-includes/link-template.php' );

			/* get permalink */
			$permalink = get_permalink( $post_id );

			/* no path, don't do anything */
			if ( empty( $permalink ) && $permalink != false ) {
				$this->log ( sprintf( __translate__( 'unable to determine path from Post Permalink, post ID: %s', $this->plugin_constant ),  $post_id ), LOG_WARNING );
				return false;
			}

			/*
			 * It is possible that post/page is paginated with <!--nextpage-->
			 * Wordpress doesn't seem to expose the number of pages via API.
			 * So let's just count it.
			 */
			$content_post = get_post( $post_id );
			$content = $content_post->post_content;
			$number_of_pages = 1 + (int)preg_match_all('/<!--nextpage-->/', $content, $matches);

			$current_page_id = '';
			do {
				/* urimap */
				$urimap = self::parse_urimap($permalink, $this->urimap);
				$urimap['$request_uri'] = $urimap['$request_uri'] . ($current_page_id ? $current_page_id . '/' : '');

				$clear_cache_key = self::map_urimap($urimap, $this->options['key']);

				$to_clear[ $clear_cache_key ] = true;

				$current_page_id = 1+(int)$current_page_id;
			} while ($number_of_pages>1 && $current_page_id<=$number_of_pages);
		}

		/* Hook to custom clearing array. */
		$to_clear = apply_filters('wp_ffpc_to_clear_array', $to_clear, $post_id);

		foreach ( $to_clear as $link => $dummy ) {
			/* clear all feeds as well */
			$to_clear[ $link. 'feed' ] = true;
		}

		/* add data & meta prefixes */
		foreach ( $to_clear as $link => $dummy ) {
			unset ( $to_clear [ $link ]);
			$to_clear[ $this->options[ 'prefix_meta' ] . $link ] = true;
			$to_clear[ $this->options[ 'prefix_data' ] . $link ] = true;
		}

		/* run clear */
		$internal = $this->proxy ( 'clear' );
		$this->$internal ( $to_clear );
	}

	/**
	 * clear cache triggered by new comment
	 *
	 * @param $comment_id	Comment ID
	 * @param $comment_object	The whole comment object ?
	 */
	public function clear_by_comment ( $comment_id, $comment_object ) {
		if ( empty( $comment_id ) )
			return false;

		$comment = get_comment( $comment_id );
		$post_id = $comment->comment_post_ID;
		if ( !empty( $post_id ) )
			$this->clear ( $post_id );

		unset ( $comment );
		unset ( $post_id );
	}

	/**
	 * to collect all permalinks of all taxonomy terms used in invalidation & precache
	 *
	 * @param array &$links Passed by reference array that has to be filled up with the links
	 * @param mixed $site Site ID or false; used in WordPress Network
	 *
	 */
	public function taxonomy_links ( &$links, $site = false ) {

		if ( $site !== false ) {
			$current_blog = get_current_blog_id();
			switch_to_blog( $site );

			$url = get_blog_option ( $site, 'siteurl' );
			if ( substr( $url, -1) !== '/' )
				$url = $url . '/';

			$links[ $url ] = true;
		}

		/* we're only interested in public taxonomies */
		$args = array(
			'public'   => true,
		);

		/* get taxonomies as objects */
		$taxonomies = get_taxonomies( $args, 'objects' );

		if ( !empty( $taxonomies ) ) {
			foreach ( $taxonomies  as $taxonomy ) {
				/* reset array, just in case */
				$terms = array();

				/* get all the terms for this taxonomy, only if not empty */
				$sargs = array(
					'hide_empty'    => true,
					'fields'        => 'all',
					'hierarchical'  =>false,
				);
				$terms = get_terms ( $taxonomy->name , $sargs );

				if ( !empty ( $terms ) ) {
					foreach ( $terms as $term ) {
						/* get the permalink for the term */
						$link = get_term_link ( $term->slug, $taxonomy->name );
						/* add to container */
						$links[ $link ] = true;
						/* remove the taxonomy name from the link, lots of plugins remove this for SEO, it's better to include them than leave them out
						   in worst case, we cache some 404 as well
						*/
						$link = str_replace ( '/'.$taxonomy->rewrite['slug'], '', $link  );
						/* add to container */
						$links[ $link ] = true;
					}
				}
			}
		}

		/* switch back to original site if we navigated away */
		if ( $site !== false ) {
			switch_to_blog( $current_blog );
		}

	}

	/**
	 * get backend aliveness
	 *
	 * @return array Array of configured servers with aliveness value
	 *
	 */
	public function status () {

		/* look for backend aliveness, exit on inactive backend */
		if ( ! $this->is_alive() )
			return false;

		$internal = $this->proxy ( 'status' );
		$this->$internal();
		return $this->status;
	}

	/**
	 * backend proxy function name generator
	 *
	 * @return string Name of internal function based on cache_type
	 *
	 */
	private function proxy ( $method ) {
		return $this->options['cache_type'] . '_' . $method;
	}

	/**
	 * function to check backend aliveness
	 *
	 * @return boolean true if backend is alive, false if not
	 *
	 */
	private function is_alive() {
		if ( ! $this->alive ) {
			$this->log (  __translate__("backend is not active, exiting function ", $this->plugin_constant ) . __FUNCTION__, LOG_WARNING );
			return false;
		}

		return true;
	}

	/**
	 * split hosts string to backend servers
	 *
	 *
	 */
	private function set_servers () {
		/* replace servers array in config according to hosts field */
		$servers = explode( self::host_separator , $this->options['hosts']);

		$options['servers'] = array();

		foreach ( $servers as $snum => $sstring ) {

			$separator = strpos( $sstring , self::port_separator );
			$host = substr( $sstring, 0, $separator );
			$port = substr( $sstring, $separator + 1 );
			// unix socket failsafe
			if ( empty ($port) ) $port = 0;

			$this->options['servers'][$sstring] = array (
				'host' => $host,
				'port' => $port
			);
		}

	}

	/**
	 * get current array of servers
	 *
	 * @return array Server list in current config
	 *
	 */
	public function get_servers () {
		$r = isset ( $this->options['servers'] ) ? $this->options['servers'] : '';
		return $r;
	}

	/**
	 * log wrapper to include options
	 *
	 * @var mixed $message Message to log
	 * @var int $log_level Log level
	 */
	private function log ( $message, $log_level = LOG_NOTICE ) {
		if ( !isset ( $this->options['log'] ) || $this->options['log'] != 1 )
			return false;
		else
			$this->utilities->log ( $this->plugin_constant , $message, $log_level );
	}

	/*********************** END PUBLIC FUNCTIONS ***********************/
	/*********************** APC FUNCTIONS ***********************/
	/**
	 * init apc backend: test APC availability and set alive status
	 */
	private function apc_init () {
		/* verify apc functions exist, apc extension is loaded */
		if ( ! function_exists( 'apc_cache_info' ) ) {
			$this->log (  __translate__('APC extension missing', $this->plugin_constant ) );
			return false;
		}

		/* verify apc is working */
		if ( apc_cache_info("user",true) ) {
			$this->log (  __translate__('backend OK', $this->plugin_constant ) );
			$this->alive = true;
		}
	}

	/**
	 * health checker for APC
	 *
	 * @return boolean Aliveness status
	 *
	 */
	private function apc_status () {
		$this->status = true;
		return $this->alive;
	}

	/**
	 * get function for APC backend
	 *
	 * @param string $key Key to get values for
	 *
	 * @return mixed Fetched data based on key
	 *
	*/
	private function apc_get ( &$key ) {
		return apc_fetch( $key );
	}

	/**
	 * Set function for APC backend
	 *
	 * @param string $key Key to set with
	 * @param mixed $data Data to set
	 *
	 * @return boolean APC store outcome
	 */
	private function apc_set (  &$key, &$data, &$expire ) {
		return apc_store( $key , $data , $expire );
	}


	/**
	 * Flushes APC user entry storage
	 *
	 * @return boolean APC flush outcome status
	 *
	*/
	private function apc_flush ( ) {
		return apc_clear_cache('user');
	}

	/**
	 * Removes entry from APC or flushes APC user entry storage
	 *
	 * @param mixed $keys Keys to clear, string or array
	*/
	private function apc_clear ( &$keys ) {
		/* make an array if only one string is present, easier processing */
		if ( !is_array ( $keys ) )
			$keys = array ( $keys => true );

		foreach ( $keys as $key => $dummy ) {
			if ( ! apc_delete ( $key ) ) {
				$this->log ( sprintf( __translate__( 'Failed to delete APC entry: %s', $this->plugin_constant ),  $key ), LOG_WARNING );
				//throw new Exception ( __translate__('Deleting APC entry failed with key ', $this->plugin_constant ) . $key );
			}
			else {
				$this->log ( sprintf( __translate__( 'APC entry delete: %s', $this->plugin_constant ),  $key ) );
			}
		}
	}

	/*********************** END APC FUNCTIONS ***********************/
	/*********************** APCu FUNCTIONS ***********************/
	/**
	 * init apcu backend: test APCu availability and set alive status
	 */
	private function apcu_init () {
		/* verify apcu functions exist, apcu extension is loaded */
		if ( ! function_exists( 'apcu_cache_info' ) ) {
			$this->log (  __translate__('APCu extension missing', $this->plugin_constant ) );
			return false;
		}

		/* verify apcu is working */
		if ( apcu_cache_info("user") ) {
			$this->log (  __translate__('backend OK', $this->plugin_constant ) );
			$this->alive = true;
		}
	}

	/**
	 * health checker for APC
	 *
	 * @return boolean Aliveness status
	 *
	 */
	private function apcu_status () {
		$this->status = true;
		return $this->alive;
	}

	/**
	 * get function for APC backend
	 *
	 * @param string $key Key to get values for
	 *
	 * @return mixed Fetched data based on key
	 *
	*/
	private function apcu_get ( &$key ) {
		return apcu_fetch( $key );
	}

	/**
	 * Set function for APC backend
	 *
	 * @param string $key Key to set with
	 * @param mixed $data Data to set
	 *
	 * @return boolean APC store outcome
	 */
	private function apcu_set (  &$key, &$data, &$expire ) {
		return apcu_store( $key , $data , $expire );
	}


	/**
	 * Flushes APC user entry storage
	 *
	 * @return boolean APC flush outcome status
	 *
	*/
	private function apcu_flush ( ) {
		return apcu_clear_cache();
	}

	/**
	 * Removes entry from APC or flushes APC user entry storage
	 *
	 * @param mixed $keys Keys to clear, string or array
	*/
	private function apcu_clear ( &$keys ) {
		/* make an array if only one string is present, easier processing */
		if ( !is_array ( $keys ) )
			$keys = array ( $keys => true );

		foreach ( $keys as $key => $dummy ) {
			if ( ! apcu_delete ( $key ) ) {
				$this->log ( sprintf( __translate__( 'Failed to delete APC entry: %s', $this->plugin_constant ),  $key ), LOG_WARNING );
				//throw new Exception ( __translate__('Deleting APC entry failed with key ', $this->plugin_constant ) . $key );
			}
			else {
				$this->log ( sprintf( __translate__( 'APC entry delete: %s', $this->plugin_constant ),  $key ) );
			}
		}
	}

	/*********************** END APC FUNCTIONS ***********************/

	/*********************** MEMCACHED FUNCTIONS ***********************/
	/**
	 * init memcached backend
	 */
	private function memcached_init () {
		/* Memcached class does not exist, Memcached extension is not available */
		if (!class_exists('Memcached')) {
			$this->log (  __translate__(' Memcached extension missing, wp-ffpc will not be able to function correctly!', $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check for existing server list, otherwise we cannot add backends */
		if ( empty ( $this->options['servers'] ) && ! $this->alive ) {
			$this->log (  __translate__("Memcached servers list is empty, init failed", $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check is there's no backend connection yet */
		if ( $this->connection === NULL ) {
			$this->connection = new Memcached();

			/* use binary and not compressed format, good for nginx and still fast */
			$this->connection->setOption( Memcached::OPT_COMPRESSION , false );
                        if ($this->options['memcached_binary']){
                                $this->connection->setOption( Memcached::OPT_BINARY_PROTOCOL , true );
                        }

			if ( version_compare( phpversion( 'memcached' ) , '2.0.0', '>=' ) && ini_get( 'memcached.use_sasl' ) == 1 && isset($this->options['authpass']) && !empty($this->options['authpass']) && isset($this->options['authuser']) && !empty($this->options['authuser']) ) {
				$this->connection->setSaslAuthData ( $this->options['authuser'], $this->options['authpass']);
			}
		}

		/* check if initialization was success or not */
		if ( $this->connection === NULL ) {
			$this->log (  __translate__( 'error initializing Memcached PHP extension, exiting', $this->plugin_constant ) );
			return false;
		}

		/* check if we already have list of servers, only add server(s) if it's not already connected */
		$servers_alive = array();
		if ( !empty ( $this->status ) ) {
			$servers_alive = $this->connection->getServerList();
			/* create check array if backend servers are already connected */
			if ( !empty ( $servers ) ) {
				foreach ( $servers_alive as $skey => $server ) {
					$skey =  $server['host'] . ":" . $server['port'];
					$servers_alive[ $skey ] = true;
				}
			}
		}

		/* adding servers */
		foreach ( $this->options['servers'] as $server_id => $server ) {
			/* reset server status to unknown */
			$this->status[$server_id] = -1;

			/* only add servers that does not exists already  in connection pool */
			if ( !@array_key_exists($server_id , $servers_alive ) ) {
				$this->connection->addServer( $server['host'], $server['port'] );
				$this->log ( sprintf( __translate__( '%s added', $this->plugin_constant ),  $server_id ) );
			}
		}

		/* backend is now alive */
		$this->alive = true;
		$this->memcached_status();
	}

	/**
	 * sets current backend alive status for Memcached servers
	 *
	 */
	private function memcached_status () {
		/* server status will be calculated by getting server stats */
		$this->log (  __translate__("checking server statuses", $this->plugin_constant ));
		/* get server list from connection */
		$servers =  $this->connection->getServerList();

                foreach ( $servers as $server ) {
			$server_id = $server['host'] . self::port_separator . $server['port'];
			/* reset server status to offline */
			$this->status[$server_id] = 0;
                        if ($this->connection->set($this->plugin_constant, time())) {
				$this->log ( sprintf( __translate__( '%s server is up & running', $this->plugin_constant ),  $server_id ) );
				$this->status[$server_id] = 1;
			}
		}

	}

	/**
	 * get function for Memcached backend
	 *
	 * @param string $key Key to get values for
	 *
	*/
	private function memcached_get ( &$key ) {
		return $this->connection->get($key);
	}

	/**
	 * Set function for Memcached backend
	 *
	 * @param string $key Key to set with
	 * @param mixed $data Data to set
	 *
	 */
	private function memcached_set ( &$key, &$data, &$expire ) {
		$result = $this->connection->set ( $key, $data , $expire  );

		/* if storing failed, log the error code */
		if ( $result === false ) {
			$code = $this->connection->getResultCode();
			$this->log ( sprintf( __translate__( 'unable to set entry: %s', $this->plugin_constant ),  $key ) );
			$this->log ( sprintf( __translate__( 'Memcached error code: %s', $this->plugin_constant ),  $code ) );
			//throw new Exception ( __translate__('Unable to store Memcached entry ', $this->plugin_constant ) . $key . __translate__( ', error code: ', $this->plugin_constant ) . $code );
		}

		return $result;
	}

	/**
	 *
	 * Flush memcached entries
	 */
	private function memcached_flush ( ) {
		return $this->connection->flush();
	}


	/**
	 * Removes entry from Memcached or flushes Memcached storage
	 *
	 * @param mixed $keys String / array of string of keys to delete entries with
	*/
	private function memcached_clear ( &$keys ) {

		/* make an array if only one string is present, easier processing */
		if ( !is_array ( $keys ) )
			$keys = array ( $keys => true );

		foreach ( $keys as $key => $dummy ) {
			$kresult = $this->connection->delete( $key );

			if ( $kresult === false ) {
				$code = $this->connection->getResultCode();
				$this->log ( sprintf( __translate__( 'unable to delete entry: %s', $this->plugin_constant ),  $key ) );
				$this->log ( sprintf( __translate__( 'Memcached error code: %s', $this->plugin_constant ),  $code ) );
			}
			else {
				$this->log ( sprintf( __translate__( 'entry deleted: %s', $this->plugin_constant ),  $key ) );
			}
		}
	}
	/*********************** END MEMCACHED FUNCTIONS ***********************/

	/*********************** MEMCACHE FUNCTIONS ***********************/
	/**
	 * init memcache backend
	 */
	private function memcache_init () {
		/* Memcached class does not exist, Memcache extension is not available */
		if (!class_exists('Memcache')) {
			$this->log (  __translate__('PHP Memcache extension missing', $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check for existing server list, otherwise we cannot add backends */
		if ( empty ( $this->options['servers'] ) && ! $this->alive ) {
			$this->log (  __translate__("servers list is empty, init failed", $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check is there's no backend connection yet */
		if ( $this->connection === NULL )
			$this->connection = new Memcache();

		/* check if initialization was success or not */
		if ( $this->connection === NULL ) {
			$this->log (  __translate__( 'error initializing Memcache PHP extension, exiting', $this->plugin_constant ) );
			return false;
		}

		/* adding servers */
		foreach ( $this->options['servers'] as $server_id => $server ) {
				/* in case of unix socket */
			if ( $server['port'] === 0 )
				$this->status[$server_id] = $this->connection->connect ( 'unix:/' . $server['host'] );
			else
				$this->status[$server_id] = $this->connection->connect ( $server['host'] , $server['port'] );

			$this->log ( sprintf( __translate__( '%s added', $this->plugin_constant ),  $server_id ) );
		}

		/* backend is now alive */
		$this->alive = true;
		$this->memcache_status();
	}

	/**
	 * check current backend alive status for Memcached
	 *
	 */
	private function memcache_status () {
		/* server status will be calculated by getting server stats */
		$this->log (  __translate__("checking server statuses", $this->plugin_constant ));
		/* get servers statistic from connection */
		foreach ( $this->options['servers'] as $server_id => $server ) {
			if ( $server['port'] === 0 )
				$this->status[$server_id] = $this->connection->getServerStatus( $server['host'], 11211 );
			else
				$this->status[$server_id] = $this->connection->getServerStatus( $server['host'], $server['port'] );
			if ( $this->status[$server_id] == 0 )
				$this->log ( sprintf( __translate__( '%s server is down', $this->plugin_constant ),  $server_id ) );
			else
				$this->log ( sprintf( __translate__( '%s server is up & running', $this->plugin_constant ),  $server_id ) );
		}
	}

	/**
	 * get function for Memcached backend
	 *
	 * @param string $key Key to get values for
	 *
	*/
	private function memcache_get ( &$key ) {
		return $this->connection->get($key);
	}

	/**
	 * Set function for Memcached backend
	 *
	 * @param string $key Key to set with
	 * @param mixed $data Data to set
	 *
	 */
	private function memcache_set ( &$key, &$data, &$expire ) {
		$result = $this->connection->set ( $key, $data , 0 , $expire );
		return $result;
	}

	/**
	 *
	 * Flush memcached entries
	 */
	private function memcache_flush ( ) {
		return $this->connection->flush();
	}


	/**
	 * Removes entry from Memcached or flushes Memcached storage
	 *
	 * @param mixed $keys String / array of string of keys to delete entries with
	*/
	private function memcache_clear ( &$keys ) {
		/* make an array if only one string is present, easier processing */
		if ( !is_array ( $keys ) )
			$keys = array ( $keys => true );

		foreach ( $keys as $key => $dummy ) {
			$kresult = $this->connection->delete( $key );

			if ( $kresult === false ) {
				$this->log ( sprintf( __translate__( 'unable to delete entry: %s', $this->plugin_constant ),  $key ) );
			}
			else {
				$this->log ( sprintf( __translate__( 'entry deleted: %s', $this->plugin_constant ),  $key ) );
			}
		}
	}

	/*********************** END MEMCACHE FUNCTIONS ***********************/

	/*********************** REDIS FUNCTIONS ***********************/
	/**
	 * init memcache backend
	 */
	private function redis_init () {
		if (!class_exists('Redis')) {
			$this->log (  __translate__('PHP Redis extension missing', $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check for existing server list, otherwise we cannot add backends */
		if ( empty ( $this->options['servers'] ) && ! $this->alive ) {
			$this->log (  __translate__("servers list is empty, init failed", $this->plugin_constant ), LOG_WARNING );
			return false;
		}

		/* check is there's no backend connection yet */
		if ( $this->connection === NULL )
			$this->connection = new Redis();

		/* check if initialization was success or not */
		if ( $this->connection === NULL ) {
			$this->log (  __translate__( 'error initializing Redis extension, exiting', $this->plugin_constant ) );
			return false;
		}

		//$this->connection->setOption(Redis::OPT_PREFIX, $this->plugin_constant );

		/* adding server *
		foreach ( $this->options['servers'] as $server_id => $server ) {
			/* in case of unix socket *
			if ( $server['port'] === 0 ) {
				try {
					$this->status[$server_id] = $this->connection->connect ( $server['host'] );
				} catch ( RedisException $e ) {
					$this->log ( sprintf( __translate__( 'adding %s to the Redis pool failed, error: %s', $this->plugin_constant ),  $server['host'], $e ) );
				}
			}
			else {
				try {
					$this->status[$server_id] = $this->connection->connect ( $server['host'] , $server['port'] );
				} catch ( RedisException $e ) {
					$this->log ( sprintf( __translate__( 'adding %s:%s to the Redis pool failed, error: %s', $this->plugin_constant ),  $server['host'] , $server['port'], $e ) );
				}
			}


			$this->log ( sprintf( __translate__( 'server #%s added', $this->plugin_constant ),  $server_id ) );
		}*/

		/* adding server */
		$key = array_unshift ( array_keys ( $this->options['servers'] ));
		$server = array_unshift( $this->options['servers'] );

		try {
			if ( $server['port'] === 0 )
				$this->status[$key] = $this->connection->connect ( $server['host'] );
			else
				$this->status[$key] = $this->connection->connect ( $server['host'], $server['port'] );
		} catch ( RedisException $e ) {
			$this->log ( sprintf( __translate__( 'adding %s to the Redis pool failed, error: %s', $this->plugin_constant ),  $server['host'], $e ) );
		}

		$this->log ( sprintf( __translate__( 'server #%s added', $this->plugin_constant ),  $server_id ) );

		if ( !empty( $this->options['authpass'])) {
			$auth = $this->connection->auth( $this->options['authpass'] );
			if ( $auth == false ) {
				$this->log (  __translate__( 'Redis authentication failed, exiting', $this->plugin_constant ), LOG_WARNING );
				return false;
			}
		}

		/* backend is now alive */
		$this->alive = true;
		$this->redis_status();
	}

	/**
	 * check current backend alive status for Memcached
	 *
	 */
	private function redis_status () {
		/* server status will be calculated by getting server stats */
		$this->log (  __translate__("checking server statuses", $this->plugin_constant ));

		/* get servers statistic from connection */
		try {
			$this->connection->ping();
		} catch ( RedisException $e ) {
			$this->log ( sprintf( __translate__( 'Redis status check failed, error: %s', $this->plugin_constant ),  $e ) );
		}

		$this->log ( sprintf( __translate__( 'Redis is up', $this->plugin_constant ),  $server_id ) );
	}

	/**
	 * get function for Memcached backend
	 *
	 * @param string $key Key to get values for
	 *
	*/
	private function redis_get ( &$key ) {
		return $this->connection->get($key);
	}

	/**
	 * Set function for Memcached backend
	 *
	 * @param string $key Key to set with
	 * @param mixed $data Data to set
	 *
	 */
	private function redis_set ( &$key, &$data, &$expire ) {
		$result = $this->connection->set ( $key, $data , Array('nx', 'ex' => $expire) );
		return $result;
	}

	/**
	 *
	 * Flush memcached entries
	 */
	private function redis_flush ( ) {
		return $this->connection->flushDB();
	}


	/**
	 * Removes entry from Memcached or flushes Memcached storage
	 *
	 * @param mixed $keys String / array of string of keys to delete entries with
	*/
	private function redis_clear ( &$keys ) {
		/* make an array if only one string is present, easier processing */
		if ( !is_array ( $keys ) )
			$keys = array ( $keys => true );

		$kresults = $this->connection->delete( $keys );

		foreach ( $kresults as $key => $value ) {
			$this->log ( sprintf( __translate__( 'entry deleted: %s', $this->plugin_constant ),  $value ) );
		}
	}

	/*********************** END REDIS FUNCTIONS ***********************/


}

endif; ?>
