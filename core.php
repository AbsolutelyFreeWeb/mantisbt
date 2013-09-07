<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MantisBT Core
 *
 * Initialises the MantisBT core, connects to the database, starts plugins and
 * performs other global operations that either help initialise MantisBT or
 * are required to be executed on every page load.
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses authentication_api.php
 * @uses collapse_api.php
 * @uses compress_api.php
 * @uses config_api.php
 * @uses config_defaults_inc.php
 * @uses config_inc.php
 * @uses constant_inc.php
 * @uses crypto_api.php
 * @uses custom_constants_inc.php
 * @uses custom_functions_inc.php
 * @uses database_api.php
 * @uses event_api.php
 * @uses http_api.php
 * @uses plugin_api.php
 * @uses php_api.php
 * @uses user_pref_api.php
 * @uses wiki_api.php
 */

$g_request_time = microtime( true );

ob_start();

# Load supplied constants
require_once( dirname( __FILE__ ) . '/core/constant_inc.php' );

# Load user-defined constants (if required)
if ( file_exists( dirname( __FILE__ ) . '/custom_constants_inc.php' ) ) {
	require_once( dirname( __FILE__ ) . '/custom_constants_inc.php' );
}

$t_config_inc_found = false;

# Include default configuration settings
require_once( dirname( __FILE__ ) . '/config_defaults_inc.php' );

# config_inc may not be present if this is a new install
if ( file_exists( dirname( __FILE__ ) . '/config_inc.php' ) ) {
	require_once( dirname( __FILE__ ) . '/config_inc.php' );
	$t_config_inc_found = true;
}

# Allow an environment variable (defined in an Apache vhost for example)
# to specify a config file to load to override other local settings
$t_local_config = getenv( 'MANTIS_CONFIG' );
if ( $t_local_config && file_exists( $t_local_config ) ){
	require_once( $t_local_config );
	$t_config_inc_found = true;
}

if( $MantisConfig->path === null || $MantisConfig->short_path === null ) {
	get_mantis_url();
}

/**
 * Define a function to set mantis url from webserver settings
 */
function get_mantis_url() {
	global $MantisConfig;
	if ( isset ( $_SERVER['SCRIPT_NAME'] ) ) {
		$t_protocol = 'http';
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$t_protocol= $_SERVER['HTTP_X_FORWARDED_PROTO'];
		} else if ( isset( $_SERVER['HTTPS'] ) && ( !empty( $_SERVER['HTTPS'] ) ) && strtolower( $_SERVER['HTTPS'] ) != 'off' ) {
			$t_protocol = 'https';
		}

		# $_SERVER['SERVER_PORT'] is not defined in case of php-cgi.exe
		if ( isset( $_SERVER['SERVER_PORT'] ) ) {
			$t_port = ':' . $_SERVER['SERVER_PORT'];
			if ( ( ':80' == $t_port && 'http' == $t_protocol )
			  || ( ':443' == $t_port && 'https' == $t_protocol )) {
				$t_port = '';
			}
		} else {
			$t_port = '';
		}

		if ( isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) { // Support ProxyPass
			$t_hosts = explode( ',', $_SERVER['HTTP_X_FORWARDED_HOST'] );
			$t_host = $t_hosts[0];
		} else if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$t_host = $_SERVER['HTTP_HOST'];
		} else if ( isset( $_SERVER['SERVER_NAME'] ) ) {
			$t_host = $_SERVER['SERVER_NAME'] . $t_port;
		} else if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$t_host = $_SERVER['SERVER_ADDR'] . $t_port;
		} else {
			$t_host = 'localhost';
		}

		$t_self = $_SERVER['SCRIPT_NAME'];
		$t_self = filter_var($t_self, FILTER_SANITIZE_STRING);
		$t_path = str_replace( basename( $t_self ), '', $t_self );
		$t_path = basename( $t_path ) == "admin" ? rtrim( dirname( $t_path ), '/\\' ) . '/' : $t_path;
		$t_path = basename( $t_path ) == "manage" ? rtrim( dirname( $t_path ), '/\\' ) . '/' : $t_path;
		$t_path = basename( $t_path ) == "soap" ? rtrim( dirname( dirname( $t_path ) ), '/\\' ) . '/' : $t_path;
		if( strpos( $t_path, '&#' ) ) {
			echo 'Can not safely determine $g_path. Please set $g_path manually in config_inc.php';
			die;
		}
		if( $MantisConfig->path === null )
			$MantisConfig->path	= $t_protocol . '://' . $t_host . $t_path;
		if( $MantisConfig->short_path === null )
			$MantisConfig->short_path = $t_path;
	} else {
		echo 'Invalid server configuration detected. Please set $g_path manually in config_inc.php.';
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && ( stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false ) )
			echo ' Please try to add "fastcgi_param SCRIPT_NAME $fastcgi_script_name;" to the nginx server configuration.';
		die;
	}
}

/**
 * Define an API inclusion function to replace require_once
 *
 * @param string $p_api_name api file name
 */
function require_api( $p_api_name ) {
	static $s_api_included;
	global $MantisConfig;
	if ( !isset( $s_api_included[$p_api_name] ) ) {
		$t_existing_globals = get_defined_vars();
		require_once( $MantisConfig->core_path . $p_api_name );
		$t_new_globals = array_diff_key( get_defined_vars(), $GLOBALS, array( 't_existing_globals' => 0, 't_new_globals' => 0 ) );
		foreach ( $t_new_globals as $t_global_name => $t_global_value ) {
			global $$t_global_name;
		}
		extract( $t_new_globals );
		$s_api_included[$p_api_name] = 1;
	}
}

/**
 * Define an API inclusion function to replace require_once
 *
 * @param string $p_library_name lib file name
 */
function require_lib( $p_library_name ) {
	static $s_libraries_included;
	global $MantisConfig;
	if ( !isset( $s_libraries_included[$p_library_name] ) ) {
		$t_existing_globals = get_defined_vars();
		require_once( $MantisConfig->library_path . $p_library_name );
		$t_new_globals = array_diff_key( get_defined_vars(), $GLOBALS, array( 't_existing_globals' => 0, 't_new_globals' => 0 ) );
		foreach ( $t_new_globals as $t_global_name => $t_global_value ) {
			global $$t_global_name;
		}
		extract( $t_new_globals );
		$s_libraries_included[$p_library_name] = 1;
	}
}

/**
 * Define an autoload function to automatically load classes when referenced
 *
 * @param string $p_class class name
 */
function __autoload( $p_class ) {
	global $MantisConfig;

    if( strstr( $p_class, 'MantisBT\Exception' ) ) {
        $t_parts = explode( '\\', $p_class);
        $t_class = array_pop($t_parts);
        array_shift($t_parts);
        $t_name = implode( DIRECTORY_SEPARATOR, $t_parts ) . DIRECTORY_SEPARATOR;
        $t_require_path = $MantisConfig->class_path . $t_name . $t_class . '.class.php';
        if ( file_exists( $t_require_path ) ) {
            require_once( $t_require_path );
            return;
        }
    }

	$t_parts = explode( '_', $p_class );
	$t_count = sizeof( $t_parts );
	$t_class = $t_parts[$t_count-1];
	$t_name = implode( DIRECTORY_SEPARATOR, $t_parts ) . DIRECTORY_SEPARATOR;
	$t_require_path = $MantisConfig->class_path . $t_name . $t_parts[$t_count-1] . '.class.php'; 

	if ( file_exists( $t_require_path ) ) {
		require_once( $t_require_path );
		return;
	}

	unset( $t_parts[$t_count-1] );

	$t_name = implode( DIRECTORY_SEPARATOR, $t_parts ) . DIRECTORY_SEPARATOR;
	$t_require_path = $MantisConfig->class_path . $t_name . $t_class . '.class.php';

	if ( file_exists( $t_require_path ) ) {
		require_once( $t_require_path );
		return;
	}
	
	$t_require_path = $MantisConfig->class_path . $p_class . '.class.php';

	if ( file_exists( $t_require_path ) ) {
		require_once( $t_require_path );
		return;
	}
}

# Register the autoload function to make it effective immediately
spl_autoload_register( '__autoload' );

# Register the error handler
set_exception_handler(array('MantisError', 'exception_handler'));
set_error_handler(array('MantisError', 'error_handler'));
register_shutdown_function(array('MantisError', 'shutdown_error_handler'));

/* Guess the current locale from the Accept-Language header or fall back to
 * the default locale defined in config_inc.php. The core gettext text domain
 * will also be loaded so strings from here on in are translated into the
 * user's preferred language.
 *
 * TODO: also check for a locale override provided by a user cookie?
 *
 * TODO: make mention of a user override that is applied later on once a user
 *       identifies themselves by logging in?
 */
//use Locale\LocaleManager;

$localeManager = new Locale();

//try {
//	$localeManager->setLocale();
//} catch (LocaleNotSupportedByUser $e) {
//	$localeManager->setLocale($g_default_locale);
//}

//$localeManager->addTextDomain('core', LOCALE_PATH);
textdomain('core');

# Include PHP compatibility file
require_api( 'php_api.php' );

# Enforce our minimum PHP requirements
if( !(version_compare(PHP_VERSION, PHP_MIN_VERSION ) >= 0 ) ) {
	@ob_end_clean();
	echo '<strong>FATAL ERROR: Your version of PHP is too old. MantisBT requires PHP version ' . PHP_MIN_VERSION . ' or newer</strong><br />Your version of PHP is version ' . phpversion();
	die();
}

# Ensure that output is blank so far (output at this stage generally denotes
# that an error has occurred)
if ( ( $t_output = ob_get_contents() ) != '' ) {
	echo 'Possible Whitespace/Error in Configuration File - Aborting. Output so far follows:<br />';
	var_dump( $t_output );
	die;
}

# Start HTML compression handler (if enabled)
require_api( 'compress_api.php' );
compress_start_handler();

# If no configuration file exists, redirect the user to the admin page so
# they can complete installation and configuration of MantisBT
if ( false === $t_config_inc_found ) {
	if ( !( isset( $_SERVER['SCRIPT_NAME'] ) && ( 0 < strpos( $_SERVER['SCRIPT_NAME'], 'admin' ) ) ) ) {
		header( 'Content-Type: text/html' );
		header( "Location: admin/install.php" );
		exit;
	}
}

# Initialise cryptographic keys
require_api( 'crypto_api.php' );
crypto_init();

# Connect to the database
require_api( 'database_api.php' );
require_api( 'config_api.php' );

if ( !defined( 'MANTIS_MAINTENANCE_MODE' ) ) {
	db_connect( config_get_global( 'dsn', false ), $MantisConfig->hostname, $MantisConfig->db_username, $MantisConfig->db_password, $MantisConfig->database_name, $MantisConfig->db_options );
}

# Initialise plugins
if ( !defined( 'PLUGINS_DISABLED' ) && !defined( 'MANTIS_MAINTENANCE_MODE' ) ) {
	require_api( 'plugin_api.php' );
	plugin_init_installed();
}

# Initialise Wiki integration
if( config_get_global( 'wiki_enable' ) == ON ) {
	require_api( 'wiki_api.php' );
	wiki_init();
}

if ( !isset( $g_login_anonymous ) ) {
	$g_login_anonymous = true;
}

# Attempt to set the current timezone to the user's desired value
# Note that PHP 5.1 on RHEL/CentOS doesn't support the timezone functions
# used here so we just skip this action on RHEL/CentOS platforms.
if ( function_exists( 'timezone_identifiers_list' ) ) {
	if ( in_array ( config_get_global( 'default_timezone' ), timezone_identifiers_list() ) ) {
		// if a default timezone is set in config, set it here, else we use php.ini's value
		// having a timezone set avoids a php warning
		date_set_timezone( config_get_global( 'default_timezone' ) );
	} else {
		config_set_global( 'default_timezone', date_default_timezone_get(), true );
	}

	require_api( 'authentication_api.php' );
	if( auth_is_user_authenticated() ) {
		require_api( 'user_pref_api.php' );
		date_set_timezone( user_pref_get_pref( auth_get_current_user_id(), 'timezone' ) );
	}
}

if ( !defined( 'MANTIS_MAINTENANCE_MODE' ) ) {
	require_api( 'collapse_api.php' );
	collapse_cache_token();
}

# Load custom functions
require_api( 'custom_function_api.php' );
if ( file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'custom_functions_inc.php' ) ) {
	require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'custom_functions_inc.php' );
}

# Set HTTP response headers
require_api( 'http_api.php' );
http_all_headers();

# Signal plugins that the core system is loaded
if ( !defined( 'PLUGINS_DISABLED' ) && !defined( 'MANTIS_MAINTENANCE_MODE' ) ) {
	require_api( 'event_api.php' );
	event_signal( 'EVENT_CORE_READY' );
}
