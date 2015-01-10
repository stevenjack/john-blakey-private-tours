<?php

if ( ! class_exists( 'WP_FFPC' ) ) :

/* get the plugin abstract class*/
include_once ( dirname(__FILE__) . '/wp-common/plugin_abstract.php' );
/* get the common functions class*/
include_once ( dirname(__FILE__) .'/wp-ffpc-backend.php' );

/**
 * main wp-ffpc class
 *
 * @var string $acache_worker	advanced cache "worker" file, bundled with the plugin
 * @var string $acache	WordPress advanced-cache.php file location
 * @var string $nginx_sample	nginx sample config file, bundled with the plugin
 * @var string $acache_backend	backend driver file, bundled with the plugin
 * @var string $button_flush	flush button identifier
 * @var string $button_precache	precache button identifier
 * @var string $global_option	global options identifier
 * @var string $precache_logfile	Precache log file location
 * @var string $precache_phpfile	Precache PHP worker location
 * @var array $shell_possibilities	List of possible precache worker callers
 [TODO] finish list of vars
 */
class WP_FFPC extends PluginAbstract {
	const host_separator  = ',';
	const port_separator  = ':';
	const donation_id_key = 'hosted_button_id=';
	const global_config_var = '$wp_ffpc_config';
	const key_save = 'saved';
	const key_delete = 'deleted';
	const key_flush = 'flushed';
	const slug_flush = '&flushed=true';
	const key_precache = 'precached';
	const slug_precache = '&precached=true';
	const key_precache_disabled = 'precache_disabled';
	const slug_precache_disabled = '&precache_disabled=true';
	const precache_log = 'wp-ffpc-precache-log';
	const precache_timestamp = 'wp-ffpc-precache-timestamp';
	const precache_php = 'wp-ffpc-precache.php';
	const precache_id = 'wp-ffpc-precache-task';
	private $precache_message = '';
	private $precache_logfile = '';
	private $precache_phpfile = '';
	private $global_option = '';
	private $global_config_key = '';
	private $global_config = array();
	private $global_saved = false;
	private $acache_worker = '';
	private $acache = '';
	private $nginx_sample = '';
	private $acache_backend = '';
	private $button_flush;
	private $button_precache;
	private $select_cache_type = array ();
	private $select_invalidation_method = array ();
	private $select_schedules = array();
	private $valid_cache_type = array ();
	private $list_uri_vars = array();
	private $shell_function = false;
	private $shell_possibilities = array ();
	private $backend = NULL;
	private $scheduled = false;
	private $errors = array();

	/**
	 *
	 */
	public function plugin_post_construct () {
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_path( __FILE__ );

		$this->common_url = $this->plugin_url . self::common_slug;
		$this->common_dir = $this->plugin_dir . self::common_slug;

		$this->admin_css_handle = $this->plugin_constant . '-admin-css';
		$this->admin_css_url = $this->common_url . 'wp-admin.css';
	}

	/**
	 * init hook function runs before admin panel hook, themeing and options read
	 */
	public function plugin_pre_init() {
		/* advanced cache "worker" file */
		$this->acache_worker = $this->plugin_dir . $this->plugin_constant . '-acache.php';
		/* WordPress advanced-cache.php file location */
		$this->acache = WP_CONTENT_DIR . '/advanced-cache.php';
		/* nginx sample config file */
		$this->nginx_sample = $this->plugin_dir . $this->plugin_constant . '-nginx-sample.conf';
		/* backend driver file */
		$this->acache_backend = $this->plugin_dir . $this->plugin_constant . '-backend.php';
		/* flush button identifier */
		$this->button_flush = $this->plugin_constant . '-flush';
		/* precache button identifier */
		$this->button_precache = $this->plugin_constant . '-precache';
		/* global options identifier */
		$this->global_option = $this->plugin_constant . '-global';
		/* precache log */
		$this->precache_logfile = sys_get_temp_dir() . '/' . self::precache_log;
		/* this is the precacher php worker file */
		$this->precache_phpfile = sys_get_temp_dir() . '/' . self::precache_php;
		/* search for a system function */
		$this->shell_possibilities = array ( 'shell_exec', 'exec', 'system', 'passthru' );
		/* get disabled functions list */
		$disabled_functions = array_map('trim', explode(',', ini_get('disable_functions') ) );

		foreach ( $this->shell_possibilities as $possible ) {
			if ( function_exists ($possible) && ! ( ini_get('safe_mode') || in_array( $possible, $disabled_functions ) ) ) {
				/* set shell function */
				$this->shell_function = $possible;
				break;
			}
		}

		/* set global config key; here, because it's needed for migration */
		if ( $this->network )
			$this->global_config_key = 'network';
		else
			$this->global_config_key = $_SERVER['HTTP_HOST'];

		/* cache type possible values array */
		$this->select_cache_type = array (
			'apc' => __( 'APC' , $this->plugin_constant ),
			'apcu' => __( 'APCu' , $this->plugin_constant ),
			'memcache' => __( 'PHP Memcache' , $this->plugin_constant ),
			'memcached' => __( 'PHP Memcached' , $this->plugin_constant ),
			'redis' => __( 'Redis (experimental, it will break!)' , $this->plugin_constant ),
		);
		/* check for required functions / classes for the cache types */
		$this->valid_cache_type = array (
			'apc' => function_exists( 'apc_cache_info' ) ? true : false,
			'apcu' => function_exists( 'apcu_cache_info' ) ? true : false,
			'memcache' => class_exists ( 'Memcache') ? true : false,
			'memcached' => class_exists ( 'Memcached') ? true : false,
			'redis' => class_exists( 'Redis' ) ? true : false,
		);

		/* invalidation method possible values array */
		$this->select_invalidation_method = array (
			0 => __( 'flush cache' , $this->plugin_constant ),
			1 => __( 'only modified post' , $this->plugin_constant ),
			2 => __( 'modified post and all taxonomies' , $this->plugin_constant ),
			3 => __( 'modified post and posts index page' , $this->plugin_constant ),
		);

		/* map of possible key masks */
		$this->list_uri_vars = array (
			'$scheme' => __('The HTTP scheme (i.e. http, https).', $this->plugin_constant ),
			'$host' => __('Host in the header of request or name of the server processing the request if the Host header is not available.', $this->plugin_constant ),
			'$request_uri' => __('The *original* request URI as received from the client including the args', $this->plugin_constant ),
			'$remote_user' => __('Name of user, authenticated by the Auth Basic Module', $this->plugin_constant ),
			'$cookie_PHPSESSID' => __('PHP Session Cookie ID, if set ( empty if not )', $this->plugin_constant ),
			//'$cookie_COOKnginy IE' => __('Value of COOKIE', $this->plugin_constant ),
			//'$http_HEADER' => __('Value of HTTP request header HEADER ( lowercase, dashes converted to underscore )', $this->plugin_constant ),
			//'$query_string' => __('Full request URI after rewrites', $this->plugin_constant ),
			//'' => __('', $this->plugin_constant ),
		);

		/* get current wp_cron schedules */
		$wp_schedules = wp_get_schedules();
		/* add 'null' to switch off timed precache */
		$schedules['null'] = __( 'do not use timed precache' );
		foreach ( $wp_schedules as $interval=>$details ) {
			$schedules[ $interval ] = $details['display'];
		}
		$this->select_schedules = $schedules;

	}

	/**
	 * additional init, steps that needs the plugin options
	 *
	 */
	public function plugin_post_init () {

		/* initiate backend */
		$this->backend = new WP_FFPC_Backend ( $this->options );

		/* get all available post types *
		$post_types = get_post_types( );*/

		/* cache invalidation hooks */
		add_action(  'transition_post_status',  array( &$this->backend , 'clear_ng' ), 10, 3 );
		/*
		foreach ( $post_types as $post_type ) {
			add_action( 'new_to_publish_' .$post_type , array( &$this->backend , 'clear' ), 0 );
			add_action( 'draft_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
			add_action( 'pending_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
			add_action( 'private_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
			add_action( 'publish_' . $post_type , array( &$this->backend , 'clear' ), 0 );
		}
		*/

		/* comments invalidation hooks */
		if ( $this->options['comments_invalidate'] ) {
			add_action( 'comment_post', array( &$this->backend , 'clear' ), 0 );
			add_action( 'edit_comment', array( &$this->backend , 'clear' ), 0 );
			add_action( 'trashed_comment', array( &$this->backend , 'clear' ), 0 );
			add_action( 'pingback_post', array( &$this->backend , 'clear' ), 0 );
			add_action( 'trackback_post', array( &$this->backend , 'clear' ), 0 );
			add_action( 'wp_insert_comment', array( &$this->backend , 'clear' ), 0 );
			add_action( '', array( &$this->backend , 'clear' ), 0 );
		}

		/* invalidation on some other ocasions as well */
		add_action( 'switch_theme', array( &$this->backend , 'clear' ), 0 );
		add_action( 'deleted_post', array( &$this->backend , 'clear' ), 0 );
		add_action( 'edit_post', array( &$this->backend , 'clear' ), 0 );

		/* add filter for catching canonical redirects */
		if ( WP_CACHE )
			add_filter('redirect_canonical', 'wp_ffpc_redirect_callback', 10, 2);

		/* clean up schedule if needed */
		if ( !isset( $this->options['precache_schedule'] ) || $this->options['precache_schedule'] == 'null' ) {
			$this->log ( sprintf ( __( 'clearing scheduled hook %s', $this->plugin_constant ), self::precache_id ) );
		}

		/* add precache coldrun action */
		add_action( self::precache_id , array( &$this, 'precache_coldrun' ) );

		$settings_link = ' &raquo; <a href="' . $this->settings_link . '">' . __( 'WP-FFPC Settings', $this->plugin_constant ) . '</a>';
		/* check for errors */
		if ( ! WP_CACHE )
			$this->errors['no_wp_cache'] = __("WP_CACHE is disabled, plugin will not work that way. Please add `define ( 'WP_CACHE', true );` to wp-config.php", $this->plugin_constant ) . $settings_link;

		if ( ! $this->global_saved )
			$this->errors['no_global_saved'] = __("Plugin settings are not yet saved for the site, please save settings!", $this->plugin_constant) . $settings_link;

		if ( ! file_exists ( $this->acache ) )
			$this->errors['no_acache_saved'] = __("Advanced cache file is yet to be generated, please save settings!", $this->plugin_constant). $settings_link;

		if ( file_exists ( $this->acache ) && ! is_writable ( $this->acache ) )
			$this->errors['no_acache_write'] = __("Advanced cache file is not writeable!<br />Please change the permissions on the file: ", $this->plugin_constant) . $this->acache;

		foreach ( $this->valid_cache_type as $backend => $status ) {
			if ( $this->options['cache_type'] == $backend && ! $status ) {
				$this->errors['no_backend'] = sprintf ( __('%s cache backend activated but no PHP %s extension was found.<br />Please either use different backend or activate the module!', $this->plugin_constant), $backend, $backend );
			}
		}

		/* get the current runtime configuration for memcache in PHP because Memcache in binary mode is really problematic */
		if ( extension_loaded ( 'memcache' )  ) {
			$memcache_settings = ini_get_all( 'memcache' );
			if ( !empty ( $memcache_settings ) && $this->options['cache_type'] == 'memcache' )
			{
				$memcache_protocol = strtolower($memcache_settings['memcache.protocol']['local_value']);
				if ( $memcache_protocol == 'binary' ) {
					$this->errors['memcached_binary'] = __('WARNING: Memcache extension is configured to use binary mode. This is very buggy and the plugin will most probably not work correctly. <br />Please consider to change either to ASCII mode or to Memcached extension.', $this->plugin_constant );
				}
			}
		}

		foreach ( $this->errors as $e => $msg ) {
			$this->utils->alert ( $msg, LOG_WARNING, $this->network );
		}
	}

	/**
	 * activation hook function, to be extended
	 */
	public function plugin_activate() {
		/* we leave this empty to avoid not detecting WP network correctly */
	}

	/**
	 * deactivation hook function, to be extended
	 */
	public function plugin_deactivate () {
		/* remove current site config from global config */
		$this->update_global_config( true );
	}

	/**
	 * uninstall hook function, to be extended
	 */
	public function plugin_uninstall( $delete_options = true ) {
		/* delete advanced-cache.php file */
		unlink ( $this->acache );

		/* delete site settings */
		if ( $delete_options ) {
			$this->plugin_options_delete ();
		}
	}

	/**
	 * extending admin init
	 *
	 */
	public function plugin_extend_admin_init () {
		/* save parameter updates, if there are any */
		if ( isset( $_POST[ $this->button_flush ] ) && check_admin_referer ( $this->plugin_constant ) ) {
			/* remove precache log entry */
			$this->utils->_delete_option( self::precache_log  );
			/* remove precache timestamp entry */
			$this->utils->_delete_option( self::precache_timestamp );

			/* remove precache logfile */
			if ( @file_exists ( $this->precache_logfile ) ) {
				unlink ( $this->precache_logfile );
			}

			/* remove precache PHP worker */
			if ( @file_exists ( $this->precache_phpfile ) ) {
				unlink ( $this->precache_phpfile );
			}

			/* flush backend */
			$this->backend->clear( false, true );
			$this->status = 3;
			header( "Location: ". $this->settings_link . self::slug_flush );
		}

		/* save parameter updates, if there are any */
		if ( isset( $_POST[ $this->button_precache ] ) && check_admin_referer ( $this->plugin_constant ) ) {
			/* is no shell function is possible, fail */
			if ( $this->shell_function == false ) {
				$this->status = 5;
				header( "Location: ". $this->settings_link . self::slug_precache_disabled );
			}
			/* otherwise start full precache */
			else {
				$this->precache_message = $this->precache_coldrun();
				$this->status = 4;
				header( "Location: ". $this->settings_link . self::slug_precache );
			}
		}
	}

	/**
	 * admin help panel
	 */
	public function plugin_admin_help($contextual_help, $screen_id ) {

		/* add our page only if the screenid is correct */
		if ( strpos( $screen_id, $this->plugin_settings_page ) ) {
			$contextual_help = __('<p>Please visit <a href="http://wordpress.org/support/plugin/wp-ffpc">the official support forum of the plugin</a> for help.</p>', $this->plugin_constant );

			/* [TODO] give detailed information on errors & troubleshooting
			get_current_screen()->add_help_tab( array(
					'id'		=> $this->plugin_constant . '-issues',
					'title'		=> __( 'Troubleshooting' ),
					'content'	=> __( '<p>List of errors, possible reasons and solutions</p><dl>
						<dt>E#</dt><dd></dd>
					</ol>' )
			) );
			*/

		}

		return $contextual_help;
	}

	/**
	 * admin panel, the admin page displayed for plugin settings
	 */
	public function plugin_admin_panel() {
		/**
		 * security, if somehow we're running without WordPress security functions
		 */
		if( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ){
			die( );
		}
		?>

		<div class="wrap">

		<script>
			jQuery(document).ready(function($) {
				jQuery( "#<?php echo $this->plugin_constant ?>-settings" ).tabs();
				jQuery( "#<?php echo $this->plugin_constant ?>-commands" ).tabs();
			});
		</script>

		<?php

		/* display donation form */
		$this->plugin_donation_form();

		/**
		 * if options were saved, display saved message
		 */
		if (isset($_GET[ self::key_save ]) && $_GET[ self::key_save ]=='true' || $this->status == 1) { ?>
			<div class='updated settings-error'><p><strong><?php _e( 'Settings saved.' , $this->plugin_constant ) ?></strong></p></div>
		<?php }

		/**
		 * if options were delete, display delete message
		 */
		if (isset($_GET[ self::key_delete ]) && $_GET[ self::key_delete ]=='true' || $this->status == 2) { ?>
			<div class='error'><p><strong><?php _e( 'Plugin options deleted.' , $this->plugin_constant ) ?></strong></p></div>
		<?php }

		/**
		 * if options were saved
		 */
		if (isset($_GET[ self::key_flush ]) && $_GET[ self::key_flush ]=='true' || $this->status == 3) { ?>
			<div class='updated settings-error'><p><strong><?php _e( "Cache flushed." , $this->plugin_constant ); ?></strong></p></div>
		<?php }

		/**
		 * if options were saved, display saved message
		 */
		if ( ( isset($_GET[ self::key_precache ]) && $_GET[ self::key_precache ]=='true' ) || $this->status == 4) { ?>
		<div class='updated settings-error'><p><strong><?php _e( 'Precache process was started, it is now running in the background, please be patient, it may take a very long time to finish.' , $this->plugin_constant ) ?></strong></p></div>
		<?php }

		/**
		 * the admin panel itself
		 */
		?>

		<h2><?php echo $this->plugin_name ; _e( ' settings', $this->plugin_constant ) ; ?></h2>

		<div class="updated">
			<p><strong><?php _e ( 'Driver: ' , $this->plugin_constant); echo $this->options['cache_type']; ?></strong></p>
			<?php
			/* only display backend status if memcache-like extension is running */
			if ( strstr ( $this->options['cache_type'], 'memcache') ) {
				?><p><?php
				_e( '<strong>Backend status:</strong><br />', $this->plugin_constant );

				/* we need to go through all servers */
				$servers = $this->backend->status();
				if ( is_array( $servers ) && !empty ( $servers ) ) {
					foreach ( $servers as $server_string => $status ) {
						echo $server_string ." => ";

						if ( $status == 0 )
							_e ( '<span class="error-msg">down</span><br />', $this->plugin_constant );
						elseif ( ( $this->options['cache_type'] == 'memcache' && $status > 0 )  || $status == 1 )
							_e ( '<span class="ok-msg">up & running</span><br />', $this->plugin_constant );
						else
							_e ( '<span class="error-msg">unknown, please try re-saving settings!</span><br />', $this->plugin_constant );
					}
				}

				?></p><?php
			} ?>
		</div>
		<form autocomplete="off" method="post" action="#" id="<?php echo $this->plugin_constant ?>-settings" class="plugin-admin">

			<?php wp_nonce_field( $this->plugin_constant ); ?>
			<ul class="tabs">
				<li><a href="#<?php echo $this->plugin_constant ?>-type" class="wp-switch-editor"><?php _e( 'Cache type', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-debug" class="wp-switch-editor"><?php _e( 'Debug & in-depth', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-exceptions" class="wp-switch-editor"><?php _e( 'Cache exceptions', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-servers" class="wp-switch-editor"><?php _e( 'Backend settings', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-nginx" class="wp-switch-editor"><?php _e( 'nginx', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-precache" class="wp-switch-editor"><?php _e( 'Precache & precache log', $this->plugin_constant ); ?></a></li>
			</ul>

			<fieldset id="<?php echo $this->plugin_constant ?>-type">
			<legend><?php _e( 'Set cache type', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<label for="cache_type"><?php _e('Select backend', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<select name="cache_type" id="cache_type">
						<?php $this->print_select_options ( $this->select_cache_type , $this->options['cache_type'], $this->valid_cache_type ) ?>
					</select>
					<span class="description"><?php _e('Select backend storage driver', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="expire"><?php _e('Expiration time for posts', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="number" name="expire" id="expire" value="<?php echo $this->options['expire']; ?>" />
					<span class="description"><?php _e('Sets validity time of post entry in seconds, including custom post types and pages.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="expire_taxonomy"><?php _e('Expiration time for taxonomy', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="number" name="expire_taxonomy" id="expire_taxonomy" value="<?php echo $this->options['expire_taxonomy']; ?>" />
					<span class="description"><?php _e('Sets validity time of taxonomy entry in seconds, including custom taxonomy.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="expire_home"><?php _e('Expiration time for home', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="number" name="expire_home" id="expire_home" value="<?php echo $this->options['expire_home']; ?>" />
					<span class="description"><?php _e('Sets validity time of home.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="charset"><?php _e('Charset', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="charset" id="charset" value="<?php echo $this->options['charset']; ?>" />
					<span class="description"><?php _e('Charset of HTML and XML (pages and feeds) data.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="invalidation_method"><?php _e('Cache invalidation method', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<select name="invalidation_method" id="invalidation_method">
						<?php $this->print_select_options ( $this->select_invalidation_method , $this->options['invalidation_method'] ) ?>
					</select>
					<div class="description"><?php _e('Select cache invalidation method.', $this->plugin_constant); ?>
						<ol>
							<?php
							$invalidation_method_description = array(
								'clears everything in storage, <strong>including values set by other applications</strong>',
								'clear only the modified posts entry, everything else remains in cache',
								'removes all taxonomy term cache ( categories, tags, home, etc ) and the modified post as well<br><strong>Caution! Slows down page/post saving when there are many tags.</strong>',
								'clear cache for modified post and posts index page'
							);
							foreach ($this->select_invalidation_method AS $current_key => $current_invalidation_method) {
								printf('<li><em>%1$s</em> - %2$s</li>', $current_invalidation_method, $invalidation_method_description[$current_key]);
							} ?>
						</ol>
					</div>
				</dd>

				<dt>
					<label for="comments_invalidate"><?php _e('Invalidate on comment actions', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="comments_invalidate" id="comments_invalidate" value="1" <?php checked($this->options['comments_invalidate'],true); ?> />
					<span class="description"><?php _e('Trigger cache invalidation when a comments is posted, edited, trashed. ', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="prefix_data"><?php _e('Data prefix', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="prefix_data" id="prefix_data" value="<?php echo $this->options['prefix_data']; ?>" />
					<span class="description"><?php _e('Prefix for HTML content keys, can be used in nginx.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!<br />If you are caching with nginx, you should update your nginx configuration and reload nginx after changing this value.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="prefix_meta"><?php _e('Meta prefix', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="prefix_meta" id="prefix_meta" value="<?php echo $this->options['prefix_meta']; ?>" />
					<span class="description"><?php _e('Prefix for meta content keys, used only with PHP processing.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="key"><?php _e('Key scheme', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="key" id="key" value="<?php echo $this->options['key']; ?>" />
					<span class="description"><?php _e('Key layout; <strong>use the guide below to change it</strong>.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!<br />If you are caching with nginx, you should update your nginx configuration and reload nginx after changing this value.', $this->plugin_constant); ?><?php ?></span>
					<dl class="description"><?php
					foreach ( $this->list_uri_vars as $uri => $desc ) {
						echo '<dt>'. $uri .'</dt><dd>'. $desc .'</dd>';
					}
					?></dl>
				</dd>

			</dl>
			</fieldset>

			<fieldset id="<?php echo $this->plugin_constant ?>-debug">
			<legend><?php _e( 'Debug & in-depth settings', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<label for="pingback_header"><?php _e('Enable X-Pingback header preservation', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="pingback_header" id="pingback_header" value="1" <?php checked($this->options['pingback_header'],true); ?> />
					<span class="description"><?php _e('Preserve X-Pingback URL in response header.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="log"><?php _e("Enable logging", $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="log" id="log" value="1" <?php checked($this->options['log'],true); ?> />
					<span class="description"><?php _e('Enables log messages; if <a href="http://codex.wordpress.org/WP_DEBUG">WP_DEBUG</a> is enabled, notices and info level is displayed as well, otherwie only ERRORS are logged.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="response_header"><?php _e("Add X-Cache-Engine header", $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="response_header" id="response_header" value="1" <?php checked($this->options['response_header'],true); ?> />
					<span class="description"><?php _e('Add X-Cache-Engine HTTP header to HTTP responses.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="generate_time"><?php _e("Add HTML debug comment", $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="generate_time" id="generate_time" value="1" <?php checked($this->options['generate_time'],true); ?> />
					<span class="description"><?php _e('Adds comment string including plugin name, cache engine and page generation time to every generated entry before closing <body> tag.', $this->plugin_constant); ?></span>
				</dd>

			</dl>
			</fieldset>

			<fieldset id="<?php echo $this->plugin_constant ?>-exceptions">
			<legend><?php _e( 'Set cache additions/excepions', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<label for="cache_loggedin"><?php _e('Enable cache for logged in users', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="cache_loggedin" id="cache_loggedin" value="1" <?php checked($this->options['cache_loggedin'],true); ?> />
					<span class="description"><?php _e('Cache pages even if user is logged in.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<?php _e("Excludes", $this->plugin_constant); ?></label>
				<dd>
					<table style="width:100%">
						<thead>
							<tr>
								<th style="width:16%; text-align:left"><label for="nocache_home"><?php _e("Exclude home", $this->plugin_constant); ?></label></th>
								<th style="width:16%; text-align:left"><label for="nocache_feed"><?php _e("Exclude feeds", $this->plugin_constant); ?></label></th>
								<th style="width:16%; text-align:left"><label for="nocache_archive"><?php _e("Exclude archives", $this->plugin_constant); ?></label></th>
								<th style="width:16%; text-align:left"><label for="nocache_page"><?php _e("Exclude pages", $this->plugin_constant); ?></label></th>
								<th style="width:16%; text-align:left"><label for="nocache_single"><?php _e("Exclude singulars", $this->plugin_constant); ?></label></th>
								<th style="width:17%; text-align:left"><label for="nocache_dyn"><?php _e("Dynamic requests", $this->plugin_constant); ?></label></th>
							</tr>
						</thead>
						<tbody>
								<tr>
									<td>
										<input type="checkbox" name="nocache_home" id="nocache_home" value="1" <?php checked($this->options['nocache_home'],true); ?> />
										<span class="description"><?php _e('Never cache home.', $this->plugin_constant); ?>
									</td>
									<td>
										<input type="checkbox" name="nocache_feed" id="nocache_feed" value="1" <?php checked($this->options['nocache_feed'],true); ?> />
										<span class="description"><?php _e('Never cache feeds.', $this->plugin_constant); ?>
									</td>
									<td>
										<input type="checkbox" name="nocache_archive" id="nocache_archive" value="1" <?php checked($this->options['nocache_archive'],true); ?> />
										<span class="description"><?php _e('Never cache archives.', $this->plugin_constant); ?>
									</td>
									<td>
										<input type="checkbox" name="nocache_page" id="nocache_page" value="1" <?php checked($this->options['nocache_page'],true); ?> />
										<span class="description"><?php _e('Never cache pages.', $this->plugin_constant); ?>
									</td>
									<td>
										<input type="checkbox" name="nocache_single" id="nocache_single" value="1" <?php checked($this->options['nocache_single'],true); ?> />
										<span class="description"><?php _e('Never cache singulars.', $this->plugin_constant); ?>
									</td>
									<td>
										<input type="checkbox" name="nocache_dyn" id="nocache_dyn" value="1" <?php checked($this->options['nocache_dyn'],true); ?> />
					<span class="description"><?php _e('Exclude every URL with "?" in it.', $this->plugin_constant); ?></span>
									</td>
								</tr>
						</tbody>
					</table>

				<dt>
					<label for="nocache_cookies"><?php _e("Exclude based on cookies", $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="nocache_cookies" id="nocache_cookies" value="<?php if(isset( $this->options['nocache_cookies'] ) ) echo $this->options['nocache_cookies']; ?>" />
					<span class="description"><?php _e('Exclude content based on cookies names starting with this from caching. Separate multiple cookies names with commas.<br />If you are caching with nginx, you should update your nginx configuration and reload nginx after changing this value.', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="nocache_url"><?php _e("Don't cache following URL paths - use with caution!", $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<textarea name="nocache_url" id="nocache_url" rows="3" cols="100" class="large-text code"><?php
						if( isset( $this->options['nocache_url'] ) ) {
							echo $this->options['nocache_url'];
						}
					?></textarea>
					<span class="description"><?php _e('Regular expressions use you must! e.g. <em>pattern1|pattern2|etc</em>', $this->plugin_constant); ?></span>
				</dd>

			</dl>
			</fieldset>

			<fieldset id="<?php echo $this->plugin_constant ?>-servers">
			<legend><?php _e('Backend server settings', $this->plugin_constant); ?></legend>
			<dl>
				<dt>
					<label for="hosts"><?php _e('Hosts', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" name="hosts" id="hosts" value="<?php echo $this->options['hosts']; ?>" />
					<span class="description">
					<?php _e('List of backends, with the following syntax: <br />- in case of TCP based connections, list the servers as host1:port1,host2:port2,... . Do not add trailing , and always separate host and port with : .<br />- in2.0.0b1 case using unix sockets with the Memcache driver: unix:// ', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="memcached_binary"><?php _e('Enable memcached binary mode', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="checkbox" name="memcached_binary" id="memcached_binary" value="1" <?php checked($this->options['memcached_binary'],true); ?> />
					<span class="description"><?php _e('Some memcached proxies and implementations only support the ASCII protocol.', $this->plugin_constant); ?></span>
				</dd>

				<?php
				if ( strstr ( $this->options['cache_type'], 'memcached') && extension_loaded ( 'memcached' ) && version_compare( phpversion( 'memcached' ) , '2.0.0', '>=' ) || ( $this->options['cache_type'] == 'redis' ) ) { ?>
				<?php
					if ( ! ini_get('memcached.use_sasl') && ( !empty( $this->options['authuser'] ) || !empty( $this->options['authpass'] ) ) ) { ?>
						<div class="error"><p><strong><?php _e( 'WARNING: you\'ve entered username and/or password for memcached authentication ( or your browser\'s autocomplete did ) which will not work unless you enable memcached sasl in the PHP settings: add `memcached.use_sasl=1` to php.ini' , $this->plugin_constant ) ?></strong></p></div>
				<?php } ?>
				<dt>
					<label for="authuser"><?php _e('Authentication: username', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="text" autocomplete="off" name="authuser" id="authuser" value="<?php echo $this->options['authuser']; ?>" />
					<span class="description">
					<?php _e('Username for authentication with memcached backends', $this->plugin_constant); ?></span>
				</dd>

				<dt>
					<label for="authpass"><?php _e('Authentication: password', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<input type="password" autocomplete="off" name="authpass" id="authpass" value="<?php echo $this->options['authpass']; ?>" />
					<span class="description">
					<?php _e('Password for authentication with memcached backends - WARNING, the password will be stored plain-text since it needs to be used!', $this->plugin_constant); ?></span>
				</dd>
				<?php } ?>
			</dl>
			</fieldset>

			<fieldset id="<?php echo $this->plugin_constant ?>-nginx">
			<legend><?php _e('Sample config for nginx to utilize the data entries', $this->plugin_constant); ?></legend>
			<pre><?php echo $this->nginx_example(); ?></pre>
			</fieldset>

			<fieldset id="<?php echo $this->plugin_constant ?>-precache">
			<legend><?php _e('Precache settings & log from previous pre-cache generation', $this->plugin_constant); ?></legend>

				<dt>
					<label for="precache_schedule"><?php _e('Precache schedule', $this->plugin_constant); ?></label>
				</dt>
				<dd>
					<select name="precache_schedule" id="precache_schedule">
						<?php $this->print_select_options ( $this->select_schedules, $this->options['precache_schedule'] ) ?>
					</select>
					<span class="description"><?php _e('Schedule autorun for precache with WP-Cron', $this->plugin_constant); ?></span>
				</dd>

				<?php

				$gentime = $this->utils->_get_option( self::precache_timestamp, $this->network );
				$log = $this->utils->_get_option( self::precache_log, $this->network );

				if ( @file_exists ( $this->precache_logfile ) ) {
					$logtime = filemtime ( $this->precache_logfile );

					/* update precache log in DB if needed */
					if ( $logtime > $gentime ) {
						$log = file ( $this->precache_logfile );
						$this->utils->_update_option( self::precache_log , $log, $this->network );
						$this->utils->_update_option( self::precache_timestamp , $logtime, $this->network );
					}

				}

				if ( empty ( $log ) ) {
					_e('No precache log was found!', $this->plugin_constant);
				}
				else { ?>
					<p><strong><?php _e( 'Time of run: ') ?><?php echo date('r', $gentime ); ?></strong></p>
					<div  style="overflow: auto; max-height: 20em;"><table style="width:100%; border: 1px solid #ccc;">
						<thead><tr>
								<?php $head = explode( "	", array_shift( $log ));
								foreach ( $head as $column ) { ?>
									<th><?php echo $column; ?></th>
								<?php } ?>
						</tr></thead>
						<?php
						foreach ( $log as $line ) { ?>
							<tr>
								<?php $line = explode ( "	", $line );
								foreach ( $line as $column ) { ?>
									<td><?php echo $column; ?></td>
								<?php } ?>
							</tr>
						<?php } ?>
				</table></div>
			<?php } ?>
			</fieldset>

			<p class="clear">
				<input class="button-primary" type="submit" name="<?php echo $this->button_save ?>" id="<?php echo $this->button_save ?>" value="<?php _e('Save Changes', $this->plugin_constant ) ?>" />
			</p>

		</form>

		<form method="post" action="#" id="<?php echo $this->plugin_constant ?>-commands" class="plugin-admin" style="padding-top:2em;">

			<?php wp_nonce_field( $this->plugin_constant ); ?>

			<ul class="tabs">
				<li><a href="#<?php echo $this->plugin_constant ?>-precache" class="wp-switch-editor"><?php _e( 'Precache', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-flush" class="wp-switch-editor"><?php _e( 'Empty cache', $this->plugin_constant ); ?></a></li>
				<li><a href="#<?php echo $this->plugin_constant ?>-reset" class="wp-switch-editor"><?php _e( 'Reset settings', $this->plugin_constant ); ?></a></li>
			</ul>

			<fieldset id="<?php echo $this->plugin_constant ?>-precache">
			<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<?php if ( ( isset( $_GET[ self::key_precache_disabled ] ) && $_GET[ self::key_precache_disabled ] =='true' ) || $this->status == 5 || $this->shell_function == false ) { ?>
						<strong><?php _e( "Precache functionality is disabled due to unavailable system call function. <br />Since precaching may take a very long time, it's done through a background CLI process in order not to run out of max execution time of PHP. Please enable one of the following functions if you whish to use precaching: " , $this->plugin_constant ) ?><?php echo join( ',' , $this->shell_possibilities ); ?></strong>
					<?php }
					else { ?>
						<input class="button-secondary" type="submit" name="<?php echo $this->button_precache ?>" id="<?php echo $this->button_precache ?>" value="<?php _e('Pre-cache', $this->plugin_constant ) ?>" />
					<?php } ?>
				</dt>
				<dd>
					<span class="description"><?php _e('Start a background process that visits all permalinks of all blogs it can found thus forces WordPress to generate cached version of all the pages.<br />The plugin tries to visit links of taxonomy terms without the taxonomy name as well. This may generate 404 hits, please be prepared for these in your logfiles if you plan to pre-cache.', $this->plugin_constant); ?></span>
				</dd>
			</dl>
			</fieldset>
			<fieldset id="<?php echo $this->plugin_constant ?>-flush">
			<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<input class="button-warning" type="submit" name="<?php echo $this->button_flush ?>" id="<?php echo $this->button_flush ?>" value="<?php _e('Clear cache', $this->plugin_constant ) ?>" />
				</dt>
				<dd>
					<span class="description"><?php _e ( "Clear all entries in the storage, including the ones that were set by other processes.", $this->plugin_constant ); ?> </span>
				</dd>
			</dl>
			</fieldset>
			<fieldset id="<?php echo $this->plugin_constant ?>-reset">
			<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
			<dl>
				<dt>
					<input class="button-warning" type="submit" name="<?php echo $this->button_delete ?>" id="<?php echo $this->button_delete ?>" value="<?php _e('Reset options', $this->plugin_constant ) ?>" />
				</dt>
				<dd>
					<span class="description"><?php _e ( "Reset settings to defaults.", $this->plugin_constant ); ?> </span>
				</dd>
			</dl>
			</fieldset>
		</form>
		</div>
		<?php
	}

	/**
	 * extending options_save
	 *
	 */
	public function plugin_extend_options_save( $activating ) {

		/* schedule cron if posted */
		$schedule = wp_get_schedule( self::precache_id );
		if ( $this->options['precache_schedule'] != 'null' ) {
			/* clear all other schedules before adding a new in order to replace */
			wp_clear_scheduled_hook ( self::precache_id );
			$this->log ( __( 'Scheduling WP-CRON event', $this->plugin_constant ) );
			$this->scheduled = wp_schedule_event( time(), $this->options['precache_schedule'] , self::precache_id );
		}
		elseif ( ( !isset($this->options['precache_schedule']) || $this->options['precache_schedule'] == 'null' ) && !empty( $schedule ) ) {
			$this->log ( __('Clearing WP-CRON scheduled hook ' , $this->plugin_constant ) );
			wp_clear_scheduled_hook ( self::precache_id );
		}

		/* flush the cache when new options are saved, not needed on activation */
		if ( !$activating )
			$this->backend->clear(null, true);

		/* create the to-be-included configuration for advanced-cache.php */
		$this->update_global_config();

		/* create advanced cache file, needed only once or on activation, because there could be lefover advanced-cache.php from different plugins */
		if (  !$activating )
			$this->deploy_advanced_cache();

	}

	/**
	 * read hook; needs to be implemented
	 */
	public function plugin_extend_options_read( &$options ) {
		/*if ( strstr( $this->options['nocache_url']), '^wp-'  )wp_login_url()
		$this->options['nocache_url'] = */


		/* read the global options, network compatibility */
		$this->global_config = get_site_option( $this->global_option );

		/* check if current site present in global config */
		if ( !empty ( $this->global_config[ $this->global_config_key ] ) )
			$this->global_saved = true;

		$this->global_config[ $this->global_config_key ] = $options;
	}

	/**
	 * options delete hook; needs to be implemented
	 */
	public function plugin_extend_options_delete(  ) {
		delete_site_option ( $this->global_option );
	}

	/**
	 * need to do migrations from previous versions of the plugin
	 *
	 */
	public function plugin_options_migrate( &$options ) {

		if ( version_compare ( $options['version'] , $this->plugin_version, '<' ) ) {
			/* cleanup possible leftover files from previous versions */
			$check = array ( 'advanced-cache.php', 'nginx-sample.conf', 'wp-ffpc.admin.css', 'wp-ffpc-common.php' );
			foreach ( $check as $fname ) {
				$fname = $this->plugin_dir . $fname;
				if ( file_exists ( $fname ) )
					unlink ( $fname );
			}

			/* look for previous config leftovers */
			$try = get_site_option( $this->plugin_constant );
			/* network option key changed, remove & migrate the leftovers if there's any */
			if ( !empty ( $try ) && $this->network ) {
				/* clean it up, we don't use it anymore */
				delete_site_option ( $this->plugin_constant );

				if ( empty ( $options ) && array_key_exists ( $this->global_config_key, $try ) ) {
					$options = $try [ $this->global_config_key ];
				}
				elseif ( empty ( $options ) && array_key_exists ( 'host', $try ) ) {
					$options = $try;
				}
			 }

			/* updating from version <= 0.4.x */
			if ( !empty ( $options['host'] ) ) {
				$options['hosts'] = $options['host'] . ':' . $options['port'];
			}
			/* migrating from version 0.6.x */
			elseif ( is_array ( $options ) && array_key_exists ( $this->global_config_key , $options ) ) {
				$options = $options[ $this->global_config_key ];
			}

			/* renamed options */
			if ( isset ( $options['syslog'] ) )
				$options['log'] = $options['syslog'];
			if ( isset ( $options['debug'] ) )
				$options['response_header'] = $options['debug'];
		}
	}

	/**
	 * advanced-cache.php creator function
	 *
	 */
	private function deploy_advanced_cache( ) {

		/* in case advanced-cache.php was already there, remove it */
		if ( @file_exists( $this->acache ))
			unlink ($this->acache);

		/* is deletion was unsuccessful, die, we have no rights to do that, fail */
		if ( @file_exists( $this->acache ))
			return false;

		/* if no active site left no need for advanced cache :( */
		if ( empty ( $this->global_config ) )
			return false;

		/* add the required includes and generate the needed code */
		$string[] = "<?php";
		$string[] = self::global_config_var . ' = ' . var_export ( $this->global_config, true ) . ';' ;
		//$string[] = "include_once ('" . $this->acache_backend . "');";
		$string[] = "include_once ('" . $this->acache_worker . "');";
		$string[] = "?>";

		/* write the file and start caching from this point */
		return file_put_contents( $this->acache, join( "\n" , $string ) );
	}

	/**
	 * function to generate working example from the nginx sample file
	 *
	 * @return string nginx config file
	 *
	 */
	private function nginx_example () {
		/* read the sample file */
		$nginx = file_get_contents ( $this->nginx_sample );

		/* replace the data prefix with the configured one */
		$to_replace = array ( 'DATAPREFIX' , 'SERVERROOT', 'SERVERLOG' );
		$replace_with = array ( $this->options['prefix_data'] . $this->options['key'] , ABSPATH, $_SERVER['SERVER_NAME'] );
		$nginx = str_replace ( $to_replace , $replace_with , $nginx );

		/* set upstream servers from configured servers, best to get from the actual backend */
		$servers = $this->backend->get_servers();
		$nginx_servers = '';
		if ( is_array ( $servers )) {
			foreach ( array_keys( $servers ) as $server ) {
				$nginx_servers .= "		server ". $server .";\n";
			}
		}
		else {
			$nginx_servers .= "		server ". $servers .";\n";
		}
		$nginx = str_replace ( 'MEMCACHED_SERVERS' , $nginx_servers , $nginx );

		$loggedincookies = join('|', $this->backend->cookies );
		/* this part is not used when the cache is turned on for logged in users */
		$loggedin = '
			if ($http_cookie ~* "'. $loggedincookies .'" ) {
				set $memcached_request 0;
			}';

		/* add logged in cache, if valid */
		if ( ! $this->options['cache_loggedin'])
			$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , $loggedin , $nginx );
		else
			$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , '' , $nginx );

		/* nginx can skip caching for visitors with certain cookies specified in the options */
		if( $this->options['nocache_cookies'] ) {
			$cookies = str_replace( ",","|", $this->options['nocache_cookies'] );
			$cookies = str_replace( " ","", $cookies );
			$cookie_exception = '# avoid cache for cookies specified
			if ($http_cookie ~* ' . $cookies . ' ) {
				set $memcached_request 0;
			}';
			$nginx = str_replace ( 'COOKIES_EXCEPTION' , $cookie_exception , $nginx );
		} else {
			$nginx = str_replace ( 'COOKIES_EXCEPTION' , '' , $nginx );
		}

		/* add custom response header if specified in the options */
		if( $this->options['response_header'] ){
			$response_header =  'add_header X-Cache-Engine "WP-FFPC with ' . $this->options['cache_type'] .' via nginx";';
			$nginx = str_replace ( 'RESPONSE_HEADER' , $response_header , $nginx );
		} else{
			$nginx = str_replace ( 'RESPONSE_HEADER' , '' , $nginx );
		}

		return $nginx;
	}

	/**
	 * function to update global configuration
	 *
	 * @param boolean $remove_site Bool to remove or add current config to global
	 *
	 */
	private function update_global_config ( $remove_site = false ) {

		/* remove or add current config to global config */
		if ( $remove_site ) {
			unset ( $this->global_config[ $this->global_config_key ] );
		}
		else {
			$this->global_config[ $this->global_config_key ] = $this->options;
		}

		/* deploy advanced-cache.php */
		$this->deploy_advanced_cache ();

		/* save options to database */
		update_site_option( $this->global_option , $this->global_config );
	}


	/**
	 * generate cache entry for every available permalink, might be very-very slow,
	 * therefore it starts a background process
	 *
	 */
	private function precache ( &$links ) {

		/* double check if we do have any links to pre-cache */
		if ( !empty ( $links ) && !$this->precache_running() )  {

			$out = '<?php
				$links = ' . var_export ( $links , true ) . ';

				echo "permalink\tgeneration time (s)\tsize ( kbyte )\n";
				foreach ( $links as $permalink => $dummy ) {
					$starttime = explode ( " ", microtime() );
					$starttime = $starttime[1] + $starttime[0];

						$page = file_get_contents( $permalink );
						$size = round ( ( strlen ( $page ) / 1024 ), 2 );

					$endtime = explode ( " ", microtime() );
					$endtime = round( ( $endtime[1] + $endtime[0] ) - $starttime, 2 );

					echo $permalink . "\t" .  $endtime . "\t" . $size . "\n";
					unset ( $page, $size, $starttime, $endtime );
					sleep( 1 );
				}
				unlink ( "'. $this->precache_phpfile .'" );
			?>';

			file_put_contents ( $this->precache_phpfile, $out  );
			/* call the precache worker file in the background */
			$shellfunction = $this->shell_function;
			$shellfunction( 'php '. $this->precache_phpfile .' >'. $this->precache_logfile .' 2>&1 &' );
		}

	}

	/**
	 * check is precache is still ongoing
	 *
	 */
	private function precache_running () {
		$return = false;

		/* if the precache file exists, it did not finish running as it should delete itself on finish */
		if ( file_exists ( $this->precache_phpfile )) {
			$return = true;
		}
		/*
		 [TODO] cross-platform process check; this is *nix only
		else {
			$shellfunction = $this->shell_function;
			$running = $shellfunction( "ps aux | grep \"". $this->precache_phpfile ."\" | grep -v grep | awk '{print $2}'" );
			if ( is_int( $running ) && $running != 0 ) {
				$return = true;
			}
		}
		*/

		return $return;
	}

	/**
	 * run full-site precache
	 */
	public function precache_coldrun () {

		/* container for links to precache, well be accessed by reference */
		$links = array();

		/* when plugin is  network wide active, we need to pre-cache for all link of all blogs */
		if ( $this->network ) {
			/* list all blogs */
			global $wpdb;
			$pfix = empty ( $wpdb->base_prefix ) ? 'wp' : $wpdb->base_prefix;
			$blog_list = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ". $pfix ."_blogs ORDER BY blog_id", '' ) );

			foreach ($blog_list as $blog) {
				if ( $blog->archived != 1 && $blog->spam != 1 && $blog->deleted != 1) {
					/* get permalinks for this blog */
					$this->precache_list_permalinks ( $links, $blog->blog_id );
				}
			}
		}
		else {
			/* no network, better */
			$this->precache_list_permalinks ( $links, false );
		}

		/* double check if we do have any links to pre-cache */
		if ( !empty ( $links ) )  {
			$this->precache ( $links );
		}
	}

	/**
	 * gets all post-like entry permalinks for a site, returns values in passed-by-reference array
	 *
	 */
	private function precache_list_permalinks ( &$links, $site = false ) {
		/* $post will be populated when running throught the posts */
		global $post;
		include_once ( ABSPATH . "wp-load.php" );

		/* if a site id was provided, save current blog and change to the other site */
		if ( $site !== false ) {
			$current_blog = get_current_blog_id();
			switch_to_blog( $site );

			$url = $this->_site_url( $site );
			//$url = get_blog_option ( $site, 'siteurl' );
			if ( substr( $url, -1) !== '/' )
				$url = $url . '/';

			$links[ $url ] = true;
		}

		/* get all published posts */
		$args = array (
			'post_type' => 'any',
			'posts_per_page' => -1,
			'post_status' => 'publish',
		);
		$posts = new WP_Query( $args );

		/* get all the posts, one by one  */
		while ( $posts->have_posts() ) {
			$posts->the_post();

			/* get the permalink for currently selected post */
			switch ($post->post_type) {
				case 'revision':
				case 'nav_menu_item':
					break;
				case 'page':
					$permalink = get_page_link( $post->ID );
					break;
				/*
				 * case 'post':
					$permalink = get_permalink( $post->ID );
					break;
				*/
				case 'attachment':
					$permalink = get_attachment_link( $post->ID );
					break;
				default:
					$permalink = get_permalink( $post->ID );
				break;
			}

			/* in case the bloglinks are relative links add the base url, site specific */
			$baseurl = empty( $url ) ? $this->utils->_site_url() : $url;
			if ( !strstr( $permalink, $baseurl ) ) {
				$permalink = $baseurl . $permalink;
			}

			/* collect permalinks */
			$links[ $permalink ] = true;

		}

		$this->backend->taxonomy_links ( $links );

		/* just in case, reset $post */
		wp_reset_postdata();

		/* switch back to original site if we navigated away */
		if ( $site !== false ) {
			switch_to_blog( $current_blog );
		}
	}

	/**
	 * log wrapper to include options
	 *
	 */
	public function log ( $message, $log_level = LOG_NOTICE ) {
		if ( !isset ( $this->options['log'] ) || $this->options['log'] != 1 )
			return false;
		else
			$this->utils->log ( $this->plugin_constant, $message, $log_level );
	}

}

endif;
