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
 * HTML API
 *
 * These functions control the HTML output of each page.
 *
 * This is the call order of these functions, should you need to figure out
 * which to modify or which to leave out:
 *
 * html_page_top1
 * 	html_begin
 * 	html_head_begin
 * 	html_css
 * 	html_content_type
 * 	html_rss_link
 * 	(html_meta_redirect)
 * 	html_title
 * html_page_top2
 * 	html_page_top2a
 * 	html_head_end
 * 	html_body_begin
 * 	html_top_banner
 * 	html_login_info
 * 	(print_project_menu_bar)
 * 	print_menu
 *
 * ...Page content here...
 *
 * html_page_bottom
 * 	(print_menu)
 * 	html_page_bottom1a
 * 	html_bottom_banner
 * 	html_footer
 * 	html_body_end
 * html_end
 *
 * @package CoreAPI
 * @subpackage HTMLAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses current_user_api.php
 * @uses database_api.php
 * @uses event_api.php
 * @uses file_api.php
 * @uses filter_api.php
 * @uses filter_constants_inc.php
 * @uses form_api.php
 * @uses helper_api.php
 * @uses news_api.php
 * @uses php_api.php
 * @uses print_api.php
 * @uses project_api.php
 * @uses rss_api.php
 * @uses string_api.php
 * @uses user_api.php
 * @uses utility_api.php
 */

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'database_api.php' );
require_api( 'event_api.php' );
require_api( 'file_api.php' );
require_api( 'filter_api.php' );
require_api( 'filter_constants_inc.php' );
require_api( 'form_api.php' );
require_api( 'helper_api.php' );
require_api( 'news_api.php' );
require_api( 'php_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'rss_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );
require_api( 'utility_api.php' );

$g_rss_feed_url = null;

$g_robots_meta = '';

# flag for error handler to skip header menus
$g_error_send_page_header = true;

$g_stylesheets_included = array();
$g_scripts_included = array();

/**
 * Sets the url for the rss link associated with the current page.
 * null: means no feed (default).
 * @param string $p_rss_feed_url rss feed url
 * @return null
 */
function html_set_rss_link( $p_rss_feed_url ) {
	if( OFF != config_get( 'rss_enabled' ) ) {
		global $g_rss_feed_url;
		$g_rss_feed_url = $p_rss_feed_url;
	}
}

/**
 * This method must be called before the html_page_top* methods.  It marks the page as not
 * for indexing.
 * @return null
 */
function html_robots_noindex() {
	global $g_robots_meta;
	$g_robots_meta = 'noindex,follow';
}

/**
 * Prints the link that allows auto-detection of the associated feed.
 * @return null
 */
function html_rss_link() {
	global $g_rss_feed_url;

	if( $g_rss_feed_url !== null ) {
		echo '<link rel="alternate" type="application/rss+xml" title="RSS" href="' . string_attribute( $g_rss_feed_url ) . "\" />\n";
	}
}

/**
 * Prints a <script> tag to include a JavaScript file.
 * @param string $p_filename Name of JavaScript file (with extension) to include
 * @return null
 */
function html_javascript_link( $p_filename) {
	echo "\t", '<script type="text/javascript" src="', helper_mantis_url( 'static/javascript/' . $p_filename ), '"></script>' . "\n";
}

/**
 * Defines the top of a HTML page
 * @param string $p_page_title html page title
 * @param string $p_redirect_url url to redirect to if necessary
 * @return null
 */
function html_page_top( $p_page_title = null, $p_redirect_url = null ) {
	html_page_top1( $p_page_title );
	if ( $p_redirect_url !== null ) {
		html_meta_redirect( $p_redirect_url );
	}
	html_page_top2();
}

/**
 * Print the part of the page that comes before meta redirect tags should be inserted
 * @param string $p_page_title page title
 * @return null
 */
function html_page_top1( $p_page_title = null ) {
	html_begin();
	html_head_begin();
	html_css();
	html_content_type();
	include( config_get( 'meta_include_file' ) );

	global $g_robots_meta;
	if ( !is_blank( $g_robots_meta ) ) {
		echo "\t", '<meta name="robots" content="', $g_robots_meta, '" />', "\n";
	}

	html_rss_link();

	echo "\t", '<link rel="shortcut icon" href="', helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/favicon.ico' ), '" type="image/x-icon" />', "\n";

	// Advertise the availability of the browser search plug-ins.
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Text Search" href="' . string_sanitize_url( 'browser_search_plugin.php?type=text', true) . '" />' . "\n";
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Issue Id" href="' . string_sanitize_url( 'browser_search_plugin.php?type=id', true) . '" />' . "\n";

	html_title( $p_page_title );
	html_head_javascript();
}

/**
 * Print the part of the page that comes after meta tags, but before the actual page content
 * @return null
 */
function html_page_top2() {
	html_page_top2a();

	if( !db_is_connected() ) {
		return;
	}

	if( auth_is_user_authenticated() ) {
		html_login_info();

		if( ON == config_get( 'show_project_menu_bar' ) ) {
			print_project_menu_bar();
			echo '<br />';
		}
	}
	print_menu();
	echo '<div id="content">', "\n";
	event_signal( 'EVENT_LAYOUT_CONTENT_BEGIN' );
}

/**
 * Print the part of the page that comes after meta tags and before the
 *  actual page content, but without login info or menus.  This is used
 *  directly during the login process and other times when the user may
 *  not be authenticated
 * @return null
 */
function html_page_top2a() {
	global $g_error_send_page_header;

	html_head_end();
	html_body_begin();
	$g_error_send_page_header = false;
	html_top_banner();
}

/**
 * Print the part of the page that comes below the page content
 * $p_file should always be the __FILE__ variable. This is passed to show source
 * @param string $p_file should always be the __FILE__ variable. This is passed to show source
 * @return null
 */
function html_page_bottom( $p_file = null ) {
	if( !db_is_connected() ) {
		return;
	}

	event_signal( 'EVENT_LAYOUT_CONTENT_END' );
	echo '</div>', "\n";
	if( config_get( 'show_footer_menu' ) ) {
		echo '<br />';
		print_menu();
	}

	html_page_bottom1a( $p_file );
}

/**
 * Print the part of the page that comes below the page content but leave off
 *  the menu.  This is used during the login process and other times when the
 *  user may not be authenticated.
 * @param string $p_file should always be the __FILE__ variable.
 * @return null
 */
function html_page_bottom1a( $p_file = null ) {
	if( null === $p_file ) {
		$p_file = basename( $_SERVER['SCRIPT_NAME'] );
	}

	html_bottom_banner();
	html_footer();
	html_body_end();
	html_end();
}

/**
 * (1) Print the document type and the opening <html> tag
 * @return null
 */
function html_begin() {
	echo '<?xml version="1.0" encoding="utf-8"?>', "\n";
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">', "\n";
	echo '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" >', "\n";
}

/**
 * (2) Begin the <head> section
 * @return null
 */
function html_head_begin() {
	echo '<head>', "\n";
}

/**
 * (3) Print the content-type
 * @return null
 */
function html_content_type() {
	echo "\t", '<meta http-equiv="Content-type" content="application/xhtml+xml; charset=UTF-8" />', "\n";
}

/**
 * (4) Print the window title
 * @param string $p_page_title window title
 * @return null
 */
function html_title( $p_page_title = null ) {
	$t_page_title = string_html_specialchars( $p_page_title );
	$t_title = string_html_specialchars( config_get( 'window_title' ) );
	echo "\t", '<title>';
	if( empty( $t_page_title ) ) {
		echo $t_title;
	} else {
		if( empty( $t_title ) ) {
			echo $t_page_title;
		} else {
			echo $t_page_title . ' - ' . $t_title;
		}
	}
	echo '</title>', "\n";
}

/**
 * Require a CSS file to be in html page headers
 * @param string $p_stylesheet_path path to CSS stylesheet
 */
function require_css( $p_stylesheet_path ) {
	global $g_stylesheets_included;
	$g_stylesheets_included[$p_stylesheet_path] = $p_stylesheet_path;
}

/**
 * (5) Print the link to include the css file
 * @return null
 */
function html_css() {
	global $g_stylesheets_included;
	html_css_link( 'default.css' );
	html_css_link( 'jquery-ui-1.10.0.custom.min.css' );
	html_css_link( 'common_config.php' );
	# Add right-to-left css if needed
	if ( _( 'ltr' ) == 'rtl' ) {
		html_css_link( 'rtl.css' );
	}
	foreach ( $g_stylesheets_included as $t_stylesheet_path ) {
		html_css_link ( $t_stylesheet_path );
	}
}

/**
 * Prints a css link
 * @param string $p_filename filename
 * @return null
 */
function html_css_link( $p_filename ) {
	echo "\t", '<link rel="stylesheet" type="text/css" href="', string_sanitize_url( helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/' . $p_filename ), true ), '" />' . "\n";
}


/**
 * (6) Print an HTML meta tag to redirect to another page
 * This function is optional and may be called by pages that need a redirect.
 * $p_time is the number of seconds to wait before redirecting.
 * If we have handled any errors on this page return false and don't redirect.
 *
 * @param string $p_url The page to redirect: has to be a relative path
 * @param integer $p_time seconds to wait for before redirecting
 * @param boolean $p_sanitize apply string_sanitize_url to passed url
 * @return boolean
 */
function html_meta_redirect( $p_url, $p_time = null, $p_sanitize = true ) {
	if( MantisError::error_handled() ) {
		return false;
	}

	if( null === $p_time ) {
		$p_time = user_pref_get_pref( auth_get_current_user_id(), 'redirect_delay' );
	}

	$t_url = config_get( 'path' );
	if( $p_sanitize ) {
		$t_url .= string_sanitize_url( $p_url );
	} else {
		$t_url .= $p_url;
	}

	$t_url = htmlspecialchars( $t_url );

	echo "\t<meta http-equiv=\"Refresh\" content=\"$p_time;URL=$t_url\" />\n";

	return true;
}

/**
 * Require a javascript file to be in html page headers
 * @param string $p_script_path path to Javascript file
 */
function require_js( $p_script_path ) {
	global $g_scripts_included;
	$g_scripts_included[$p_script_path] = $p_script_path;
}

/**
 * (6a) Javascript...
 * @return null
 */
function html_head_javascript() {
	if ( config_get( 'use_javascript' ) ) {
		global $g_scripts_included;
		echo "\t<script type=\"text/javascript\" src=\"" . helper_mantis_url( 'javascript_config.php' ) . '"></script>' . "\n";
		echo "\t<script type=\"text/javascript\" src=\"" . helper_mantis_url( 'javascript_translations.php' ) . '"></script>' . "\n";
		html_javascript_link( 'jquery-1.9.1.min.js' );
		html_javascript_link( 'jquery-ui-1.10.0.custom.min.js' );
		html_javascript_link( 'common.js' );
		foreach ( $g_scripts_included as $t_script_path ) {
			html_javascript_link( $t_script_path );
		}
	}
}

/**
 * (7) End the <head> section
 * @return null
 */
function html_head_end() {
	event_signal( 'EVENT_LAYOUT_RESOURCES' );

	echo '</head>', "\n";
}

/**
 * (8) Begin the <body> section
 * @return null
 */
function html_body_begin() {
	echo '<body>', "\n";
	echo '<div id="mantis">', "\n";

	event_signal( 'EVENT_LAYOUT_BODY_BEGIN' );
}

/**
 * (9) Print a user-defined banner at the top of the page if there is one.
 * @return null
 */
function html_top_banner() {
	$t_page = config_get( 'top_include_page' );
	$t_logo_url = config_get( 'logo_url' );

	if( is_blank( $t_logo_url ) ) {
		$t_show_url = false;
	} else {
		$t_show_url = true;
	}

	if( !is_blank( $t_page ) && file_exists( $t_page ) && !is_dir( $t_page ) ) {
		include( $t_page );
	} else {
		echo '<div id="banner">';
		if( $t_show_url ) {
			echo '<a id="logo-link" href="', config_get( 'logo_url' ), '">';
		}
		$t_logo_alt = string_html_specialchars( config_get( 'window_title' ) );
		$t_logo = helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/logo.png' );
		echo '<img id="logo-image" alt="' . $t_logo_alt . '" src="' . $t_logo . '" />';
		if( $t_show_url ) {
			echo '</a>';
		}
		echo '</div>';
	}

	event_signal( 'EVENT_LAYOUT_PAGE_HEADER' );
}

/**
 * (11) Print the user's account information
 * Also print the select box where users can switch projects
 * @return null
 */
function html_login_info() {
	$t_username = user_get_field( auth_get_current_user_id(), 'username' );
	$t_access_level = get_enum_element( 'access_levels', user_get_access_level() );
	$t_now = date( config_get( 'complete_date_format' ) );
	$t_realname = user_get_field( auth_get_current_user_id(), 'realname' );

	echo '<div id="login-info">' . "\n";
	if( user_is_anonymous( auth_get_current_user_id() ) ) {
		$t_return_page = $_SERVER['SCRIPT_NAME'];
		if( isset( $_SERVER['QUERY_STRING'] ) ) {
			$t_return_page .= '?' . $_SERVER['QUERY_STRING'];
		}

		$t_return_page = string_url( $t_return_page );

		echo '	<span id="logged-anon-label">' . _( 'Anonymous' ) . '</span>' . "\n";
		echo '	<span id="login-link"><a href="' . helper_mantis_url( 'login_page.php?return=' . $t_return_page ) . '">' . _( 'Login' ) . '</a></span>' . "\n";
		if( config_get_global( 'allow_signup' ) == ON ) {
			echo '	<span id="signup-link"><a href="' . helper_mantis_url( 'signup_page.php' ) . '">' . _('Signup for a new account') . '</a></span>' . "\n";
		}
	} else {
		echo '	<span id="logged-in-label">' . _( 'Logged in as' ) . '</span>' . "\n";
		echo '	<span id="logged-in-user">' . string_html_specialchars( $t_username ) . '</span>' . "\n";
		echo '	<span id="logged-in">' . "\n";
		echo !is_blank( $t_realname ) ?  '	<span id="logged-in-realname">' . string_html_specialchars( $t_realname ) . '</span>' . "\n" : ''; 
		echo '	<span id="logged-in-accesslevel" class="' . $t_access_level . '">' . $t_access_level . '</span>' . "\n";
		echo '	</span>' . "\n";
	}
	echo '</div>'. "\n";


	$t_show_project_selector = true;
	if( count( user_get_accessible_projects( auth_get_current_user_id() ) ) == 1 ) {

		// >1
		$t_project_ids = user_get_accessible_projects( auth_get_current_user_id() );
		$t_project_id = (int) $t_project_ids[0];
		if( count( user_get_accessible_subprojects( auth_get_current_user_id(), $t_project_id ) ) == 0 ) {
			$t_show_project_selector = false;
		}
	}

	if( OFF != config_get( 'rss_enabled' ) ) {
		echo '<div id="rss-feed">';
		# Link to RSS issues feed for the selected project, including authentication details.
		echo '<a href="' . htmlspecialchars( rss_get_issues_feed_url() ) . '">';
		echo '<img src="' . helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/rss.png' ) . '" alt="' . _( 'RSS' ) . '" title="' . _( 'RSS' ) . '" />';
		echo '</a>';
		echo '</div>';
	}

	if( $t_show_project_selector ) {
		echo '<form method="post" id="form-set-project" action="' . helper_mantis_url( 'set_project.php' ) . '">';
		echo '<fieldset id="project-selector">';
		# CSRF protection not required here - form does not result in modifications

		echo '<label for="form-set-project-id">' . _( 'Project' ) . '</label>';
		echo '<select id="form-set-project-id" name="project_id">';
		print_project_option_list( join( ';', helper_get_current_project_trace() ), true, null, true );
		echo '</select> ';
		echo '<input type="submit" class="button" value="' . _( 'Switch' ) . '" />';
		echo '</fieldset>';
		echo '</form>';
		echo '<div id="current-time">' . $t_now . '</div>';
	} else {
		echo '<div id="current-time-centered">' . $t_now . '</div>';
	}
}

/**
 * (12) Print a user-defined banner at the bottom of the page if there is one.
 * @return null
 */
function html_bottom_banner() {
	$t_page = config_get( 'bottom_include_page' );

	if( !is_blank( $t_page ) && file_exists( $t_page ) && !is_dir( $t_page ) ) {
		include( $t_page );
	}
}

/**
 * (13) Print the page footer information
 * @param string $p_file
 * @return null
 */
function html_footer( $p_file = null ) {
	global $g_queries_array, $g_request_time;

	# If a user is logged in, update their last visit time.
	# We do this at the end of the page so that:
	#  1) we can display the user's last visit time on a page before updating it
	#  2) we don't invalidate the user cache immediately after fetching it
	#  3) don't do this on the password verification or update page, as it causes the
	#    verification comparison to fail
	if ( auth_is_user_authenticated() && !user_is_anonymous( auth_get_current_user_id() ) && !( is_page_name( 'verify.php' ) || is_page_name( 'account_update.php' ) ) ) {
		$t_user_id = auth_get_current_user_id();
		user_update_last_visit( $t_user_id );
	}

	echo "<div id=\"footer\">\n";
	echo "\t<hr />\n";
	echo "\t<div id=\"powered-by-mantisbt-logo\">\n";
	$t_mantisbt_logo_url = helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/logo.png' );
	echo "\t\t<a href=\"http://www.mantisbt.org\" title=\"Mantis Bug Tracker: a free and open source web based bug tracking system.\"><img src=\"$t_mantisbt_logo_url\" width=\"145\" height=\"50\" alt=\"Powered by Mantis Bug Tracker: a free and open source web based bug tracking system.\" /></a>\n";
	echo "\t</div>\n";

	# Show optional user-specificed custom copyright statement
	$t_copyright_statement = config_get( 'copyright_statement' );
	if ( $t_copyright_statement ) {
		echo "\t<address id=\"user-copyright\">$t_copyright_statement</address>\n";
	}

	# Show MantisBT version and copyright statement
	$t_version_suffix = '';
	if ( config_get( 'show_version' ) ) {
		$t_version_suffix = htmlentities( ' ' . MANTIS_VERSION . config_get_global( 'version_suffix' ) );
	}
	echo "\t<address id=\"mantisbt-copyright\">Powered by <a href=\"http://www.mantisbt.org\" title=\"Mantis Bug Tracker: a free and open source web based bug tracking system.\">Mantis Bug Tracker</a> (MantisBT)$t_version_suffix. Copyright &copy; 2000 - " . date( 'Y' ) . " MantisBT contributors. Licensed under the terms of the <a href=\"http://www.gnu.org/licenses/old-licenses/gpl-2.0.html\" title=\"GNU General Public License (GPL) version 2\">GNU General Public License (GPL) version 2</a> or a later version.</address>\n";

	# Show contact information
	$t_webmaster_contact_information = sprintf( _( 'If you encounter problems accessing this bug tracker please <a href="mailto:%1$s" title="Contact the webmaster via e-mail.">contact us via e-mail</a> for assistance.' ), string_html_specialchars( config_get( 'webmaster_email' ) ) );
	echo "\t<address id=\"webmaster-contact-information\">$t_webmaster_contact_information</address>\n";

	event_signal( 'EVENT_LAYOUT_PAGE_FOOTER' );

	# Print horizontal rule if any debugging stats follow
	if ( config_get( 'show_timer' ) || config_get( 'show_memory_usage' ) || config_get( 'show_queries_count' ) ) {
		echo "\t<hr />\n";

		# Print the page execution time
		if ( config_get( 'show_timer' ) ) {
			$t_page_execution_time = sprintf( _( 'Page execution time: %1$s seconds' ), number_format( microtime( true ) - $g_request_time, 4 ) );
			echo "\t<p id=\"page-execution-time\">$t_page_execution_time</p>\n";
		}

		# Print the page memory usage
		if ( config_get( 'show_memory_usage' ) ) {
			$t_page_memory_usage = sprintf( _( 'Memory usage: %1 KiB' ), number_format( memory_get_peak_usage() / 1024 ) );
			echo "\t<p id=\"page-memory-usage\">$t_page_memory_usage</p>\n";
		}

		# Determine number of unique queries executed
		if ( config_get( 'show_queries_count' ) ) {
			$t_total_queries_count = count( $g_queries_array );
			$t_unique_queries_count = 0;
			$t_unique_queries = array();
			for ( $i = 0; $i < $t_total_queries_count; $i++ ) {
				if ( !in_array( $g_queries_array[$i][0], $t_unique_queries ) ) {
					$t_unique_queries_count++;
					$g_queries_array[$i][3] = false;
					array_push( $t_unique_queries, $g_queries_array[$i][0] );
				} else {
					$g_queries_array[$i][3] = true;
				}
			}

			$t_total_queries_executed = sprintf( _( 'Total queries executed: %1$d' ), $t_total_queries_count );
			echo "\t<p id=\"total-queries-count\">$t_total_queries_executed</p>\n";
		}
	}

	# Print table of log events
	log_print_to_page();

	echo "</div>\n";
}

/**
 * (14) End the <body> section
 * @return null
 */
function html_body_end() {
	event_signal( 'EVENT_LAYOUT_BODY_END' );

	echo '</div>', "\n";
	echo '</body>', "\n";
}

/**
 * (15) Print the closing <html> tag
 * @return null
 */
function html_end() {
	echo '</html>', "\n";
}

/**
 * Prepare an array of additional menu options from a config variable
 * @param string $p_config config name
 * @return array
 */
function prepare_custom_menu_options( $p_config ) {
	$t_custom_menu_options = config_get( $p_config );
	$t_options = array();

	foreach( $t_custom_menu_options as $t_custom_option ) {
		$t_access_level = $t_custom_option[1];
		if( access_has_project_level( $t_access_level ) ) {
			/* @TODO L10N: translation support for custom menu options */
			$t_caption = string_html_specialchars( /*langget*/ ( $t_custom_option[0] ) );
			$t_link = string_attribute( $t_custom_option[2] );
			$t_options[] = "<a href=\"$t_link\">$t_caption</a>";
		}
	}

	return $t_options;
}

/**
 * Print the main menu
 * @return null
 */
function print_menu() {
	if( auth_is_user_authenticated() ) {
		$t_protected = user_get_field( auth_get_current_user_id(), 'protected' );
		$t_current_project = helper_get_current_project();

		$t_menu_options = array();

		# Main Page
		if ( config_get( 'news_enabled' ) == ON ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'main_page.php' ) . '">' . _( 'Main' ) . '</a>';
		}

		# Plugin / Event added options
		$t_event_menu_options = event_signal( 'EVENT_MENU_MAIN_FRONT' );
		foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
			foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
				if( is_array( $t_callback_menu_options ) ) {
					$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
				} else {
					if ( !is_null( $t_callback_menu_options ) ) {
						$t_menu_options[] = $t_callback_menu_options;
					}
				}
			}
		}

		# My View
		$t_menu_options[] = '<a href="' . helper_mantis_url( 'my_view_page.php">' ) . _( 'My View' ) . '</a>';

		# View Bugs
		$t_menu_options[] = '<a href="' . helper_mantis_url( 'view_all_bug_page.php">' ) . _( 'View Issues' ) . '</a>';

		# Report Bugs
		if( access_has_project_level( config_get( 'report_bug_threshold' ) ) ) {
			$t_menu_options[] = string_get_bug_report_link();
		}

		# Changelog Page
		if( access_has_project_level( config_get( 'view_changelog_threshold' ) ) ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'changelog_page.php">' ) . _( 'Change Log' ) . '</a>';
		}

		# Roadmap Page
		if( access_has_project_level( config_get( 'roadmap_view_threshold' ) ) ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'roadmap_page.php">' ) . _( 'Roadmap' ) . '</a>';
		}

		# Summary Page
		if( access_has_project_level( config_get( 'view_summary_threshold' ) ) ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'summary_page.php">' ) . _( 'Summary' ) . '</a>';
		}

		# Project Documentation Page
		if( ON == config_get( 'enable_project_documentation' ) ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'proj_doc_page.php">' ) . _( 'Docs' ) . '</a>';
		}

		# Project Wiki
		if( config_get_global( 'wiki_enable' ) == ON ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'wiki.php?type=project&amp;id=' ) . $t_current_project . '">' . _( 'Wiki' ) . '</a>';
		}

		# Plugin / Event added options
		$t_event_menu_options = event_signal( 'EVENT_MENU_MAIN' );
		foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
			foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
				if( is_array( $t_callback_menu_options ) ) {
					$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
				} else {
					if ( !is_null( $t_callback_menu_options ) ) {
						$t_menu_options[] = $t_callback_menu_options;
					}
				}
			}
		}

		# Manage Users (admins) or Manage Project (managers) or Manage Custom Fields
		if( access_has_global_level( config_get( 'manage_site_threshold' ) ) ) {
			$t_link = helper_mantis_url( 'manage/overview_page.php' );
			$t_menu_options[] = '<a class="manage-menu-link" href="' . $t_link . '">' . _( 'Manage' ) . '</a>';
		} else {
			$t_show_access = min( config_get( 'manage_user_threshold' ), config_get( 'manage_project_threshold' ), config_get( 'manage_custom_fields_threshold' ) );
			if( access_has_global_level( $t_show_access ) || access_has_any_project( $t_show_access ) ) {
				$t_current_project = helper_get_current_project();
				if( access_has_global_level( config_get( 'manage_user_threshold' ) ) ) {
					$t_link = helper_mantis_url( 'manage_user_page.php' );
				} else {
					if( access_has_project_level( config_get( 'manage_project_threshold' ), $t_current_project ) && ( $t_current_project <> ALL_PROJECTS ) ) {
						$t_link = helper_mantis_url( 'manage_proj_edit_page.php?project_id=' ) . $t_current_project;
					} else {
						$t_link = helper_mantis_url( 'manage_proj_page.php' );
					}
				}
				$t_menu_options[] = "<a href=\"$t_link\">" . _( 'Manage' ) . '</a>';
			}
		}

		# News Page
		if ( news_is_enabled() && access_has_project_level( config_get( 'manage_news_threshold' ) ) ) {

			# Admin can edit news for All Projects (site-wide)
			if( ALL_PROJECTS != helper_get_current_project() || user_is_administrator( auth_get_current_user_id() ) ) {
				$t_menu_options[] = '<a href="' . helper_mantis_url( 'news_menu_page.php">' ) . _( 'Edit News' ) . '</a>';
			} else {
				$t_menu_options[] = '<a href="' . helper_mantis_url( 'login_select_proj_page.php">' ) . _( 'Edit News' ) . '</a>';
			}
		}

		# Account Page (only show accounts that are NOT protected)
		if( OFF == $t_protected ) {
			$t_menu_options[] = '<a class="account-menu-link" href="' . helper_mantis_url( 'account_page.php">' ) . _( 'My Account' ) . '</a>';
		}

		# Add custom options
		$t_custom_options = prepare_custom_menu_options( 'main_menu_custom_options' );
		$t_menu_options = array_merge( $t_menu_options, $t_custom_options );
		if( config_get( 'time_tracking_enabled' ) && config_get( 'time_tracking_with_billing' ) && access_has_global_level( config_get( 'time_tracking_reporting_threshold' ) ) ) {
			$t_menu_options[] = '<a href="' . helper_mantis_url( 'billing_page.php">' ) . _( 'Billing' ) . '</a>';
		}

		# Logout (no if anonymously logged in)
		if( !user_is_anonymous( auth_get_current_user_id() ) ) {
			$t_menu_options[] = '<a id="logout-link" href="' . helper_mantis_url( 'logout_page.php">' ) . _( 'Logout' ) . '</a>';
		}
		echo '<form method="get" action="' . helper_mantis_url( 'view.php" class="bug-jump-form">' );
		echo '<fieldset class="bug-jump">';
		# CSRF protection not required here - form does not result in modifications
		echo '<input type="text" name="id" size="10" class="small" />&#160;';

		echo '<input type="submit" class="button-small" value="' . _( 'Jump' ) . '" />&#160;';
		echo '</fieldset>';
		echo '</form>';
		echo '<div class="main-menu">';
		echo '<div>';
		echo '<ul class="menu">';
		echo '<li>';
		echo implode( $t_menu_options, "</li>\n<li>" );
		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}
}

/**
 * Print the menu bar with a list of projects to which the user has access
 * @return null
 */
function print_project_menu_bar() {
	$t_project_ids = user_get_accessible_projects( auth_get_current_user_id() );

	echo '<table class="width100" cellspacing="0">';
	echo '<tr>';
	echo '<td class="menu">';
	echo '<a href="' . helper_mantis_url( 'set_project.php?project_id=' . ALL_PROJECTS ) . '">' . _( 'All Projects' ) . '</a>';

	foreach( $t_project_ids as $t_id ) {
		echo ' | <a href="' . helper_mantis_url( 'set_project.php?project_id=' . $t_id ) . '">' . string_html_specialchars( project_get_field( $t_id, 'name' ) ) . '</a>';
		print_subproject_menu_bar( $t_id, $t_id . ';' );
	}

	echo '</td>';
	echo '</tr>';
	echo '</table>';
}

/**
 * Print the menu bar with a list of projects to which the user has access
 * @todo check parents param - set_project.php?project_id=' . $p_parents . $t_subproject
 * @param int $p_project_id project id
 * @param string $p_parents
 * @return null
 */
function print_subproject_menu_bar( $p_project_id, $p_parents = '' ) {
	$t_subprojects = user_get_accessible_subprojects( auth_get_current_user_id(), $p_project_id );
	$t_char = ':';
	foreach( $t_subprojects as $t_subproject ) {
		echo $t_char . ' <a href="' . helper_mantis_url( 'set_project.php?project_id=' . $p_parents . $t_subproject ) . '">' . string_html_specialchars( project_get_field( $t_subproject, 'name' ) ) . '</a>';
		print_subproject_menu_bar( $t_subproject, $p_parents . $t_subproject . ';' );
		$t_char = ',';
	}
}

/**
 * Print the menu for the graph summary section
 * @return null
 */
function print_summary_submenu() {
	# Plugin / Event added options
	$t_event_menu_options = event_signal( 'EVENT_SUBMENU_SUMMARY' );
	$t_menu_options = array();
	foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
		foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
			if( is_array( $t_callback_menu_options ) ) {
				$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
			} else {
				if ( !is_null( $t_callback_menu_options ) ) {
					$t_menu_options[] = $t_callback_menu_options;
				}
			}
		}
	}

	if( sizeof( $t_menu_options ) > 0 ) {
		echo '<div id="summary-submenu">';
		echo '<ul class="menu">';
		// Plugins menu items - these are cooked links
		foreach ( $t_menu_options as $t_menu_item ) {
			echo '<li>', $t_menu_item, '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}

/**
 * Print the menu for the manage section
 *
 * @param string $p_page specifies the current page name so it's link can be disabled
 * @return null
 */
function print_manage_menu( $p_page = '' ) {
	if( access_has_global_level( config_get( 'manage_user_threshold' ) ) ) {
		$t_pages['user_page.php'] = array( 'url'   => 'manage/user_page.php', 'label' => _('Manage Users') );
	}
	if( access_has_project_level( config_get( 'manage_project_threshold' ) ) ) {
		$t_pages['proj_page.php'] = array( 'url'   => 'manage/proj_page.php', 'label' => _('Manage Projects') );
	}
	if( access_has_project_level( config_get( 'tag_edit_threshold' ) ) ) {
		$t_pages['tags_page.php'] = array( 'url'   => 'manage/tags_page.php', 'label' => _('Manage Tags') );
	}
	if( access_has_global_level( config_get( 'manage_custom_fields_threshold' ) ) ) {
		$t_pages['custom_field_page.php'] = array( 'url'   => 'manage/custom_field_page.php', 'label' => _('Manage Custom Fields') );
	}
	if( access_has_global_level( config_get( 'manage_global_profile_threshold' ) ) ) {
		$t_pages['prof_menu_page.php'] = array( 'url'   => 'manage/prof_menu_page.php', 'label' => _('Manage Global Profiles') );
	}
	if( access_has_global_level( config_get( 'manage_plugin_threshold' ) ) ) {
		$t_pages['plugin_page.php'] = array( 'url'   => 'manage/plugin_page.php', 'label' => _('Manage Plugins') );
	}

	if ( access_has_project_level( config_get( 'manage_configuration_threshold' ) ) ) {
		if ( access_has_global_level( config_get( 'view_configuration_threshold' ) ) ) {
			$t_pages['adm_config_report.php'] = array( 'url'   => 'manage/adm_config_report.php', 'label' => _('Manage Configuration') );
		} else {
			$t_pages['adm_permissions_report.php'] = array( 'url'   => 'manage/adm_permissions_report.php', 'label' => _('Manage Configuration') );
		}
	}
	# Remove the link from the current page
	if ( isset( $t_pages[$p_page] ) ) {
		$t_pages[$p_page]['url'] = '';
	}

	# Plugin / Event added options
	/* @todo L10N: ensure that plugins are sending label as an already-translated string, not an old lang_api lookup key! */
	$t_event_menu_options = event_signal( 'EVENT_MENU_MANAGE' );
	$t_menu_options = array();
	foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
		foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
			if( is_array( $t_callback_menu_options ) ) {
				$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
			} else {
				if ( !is_null( $t_callback_menu_options ) ) {
					$t_menu_options[] = $t_callback_menu_options;
				}
			}
		}
	}

	echo '<div id="manage-menu">';
	echo '<ul class="menu">';
	foreach( $t_pages AS $t_page ) {
		if( $t_page['url'] == '' ) {
			echo "\t" . '<li>' . $t_page['label'] . '</li>' . "\n";
		} else {
			echo "\t" . '<li><a href="'. helper_mantis_url( $t_page['url'] ) .'">' . $t_page['label'] . '</a></li>' . "\n";
		}
	}

	// Plugins menu items - these are cooked links
	foreach( $t_menu_options as $t_menu_item ) {
		echo "\t" . '<li>', $t_menu_item, '</li>' . "\n";
	}

	echo '</ul>';
	echo '</div>';
}

/**
 * Print the menu for the manage configuration section
 * @param string $p_page specifies the current page name so it's link can be disabled
 * @return null
 */
function print_manage_config_menu( $p_page = '' ) {
	if ( !access_has_project_level( config_get( 'manage_configuration_threshold' ) ) ) {
		return;
	}

	$t_pages = array();

	if ( access_has_global_level( config_get( 'view_configuration_threshold' ) ) ) {
		$t_pages['adm_config_report.php'] = array( 'url'   => 'manage/adm_config_report.php',
		                                           'label' => _('Configuration Report') );
	}

	$t_pages['adm_permissions_report.php'] = array( 'url'   => 'manage/adm_permissions_report.php',
	                                                'label' => _('Permissions Report') );

	$t_pages['manage_config_work_threshold_page.php'] = array( 'url'   => 'manage/config_work_threshold_page.php',
	                                                           'label' => _('Workflow Thresholds') );

	$t_pages['manage_config_workflow_page.php'] = array( 'url'   => 'manage/config_workflow_page.php',
	                                                     'label' => _('Workflow Transitions') );

	if ( config_get( 'relationship_graph_enable' ) ) {
		$t_pages['manage_config_workflow_graph_page.php'] = array( 'url'   => 'manage/config_workflow_graph_page.php',
		                                                           'label' => _('Workflow Graph') );
	}

	$t_pages['manage_config_email_page.php'] = array( 'url'   => 'manage/config_email_page.php',
	                                                  'label' => _('E-mail Notifications') );

	$t_pages['manage_config_columns_page.php'] = array( 'url'   => 'manage/config_columns_page.php',
	                                                    'label' => _('Manage Columns') );

	# Remove the link from the current page
	if ( isset( $t_pages[$p_page] ) ) {
		$t_pages[$p_page]['url'] = '';
	}

	# Plugin / Event added options
	/* TODO L10N: ensure that plugins are sending label as an already-translated string, not an old lang_api lookup key! */
	$t_event_menu_options = event_signal( 'EVENT_MENU_MANAGE_CONFIG' );
	$t_menu_options = array();
	foreach ( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
		foreach ( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
			if ( is_array( $t_callback_menu_options ) ) {
				$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
			} else {
				if ( !is_null( $t_callback_menu_options ) ) {
					$t_menu_options[] = $t_callback_menu_options;
				}
			}
		}
	}

	echo '<br /><div id="manage-config-menu">';
	echo '<ul class="menu">';
	foreach ( $t_pages as $t_page ) {
		if( $t_page['url'] == '' ) {
			echo '<li>' . $t_page['label'] . '</li>';
		} else {
			echo '<li><a href="'. helper_mantis_url( $t_page['url'] ) .'">' . $t_page['label'] . '</a></li>';
		}
	}

	foreach ( $t_menu_options as $t_menu_item ) {
		echo '<li>', $t_menu_item, '</li>';
	}

	echo '</ul>';
	echo '</div>';
}

/**
 * Print the menu for the account section
 * @param string $p_page specifies the current page name so it's link can be disabled
 * @return null
 */
function print_account_menu( $p_page = '' ) {
	$t_pages['account_page.php'] = array( 'url'=>'account_page.php', 'label' => _('My Account') );
	$t_pages['account_prefs_page.php'] = array( 'url'=>'account_prefs_page.php', 'label' => _('Preferences') );
	$t_pages['account_manage_columns_page.php'] = array( 'url'=>'account_manage_columns_page.php', 'label' => _('Manage Columns') );

	if( config_get ( 'enable_profiles' ) == ON && access_has_project_level( config_get( 'add_profile_threshold' ) ) ) {
		$t_pages['account_prof_menu_page.php'] = array( 'url'=>'account_prof_menu_page.php', 'label' => _('Profiles') );
	}

	if( config_get( 'enable_sponsorship' ) == ON && access_has_project_level( config_get( 'view_sponsorship_total_threshold' ) ) && !user_is_anonymous( auth_get_current_user_id() ) ) {
		$t_pages['account_sponsor_page.php'] = array( 'url'=>'account_sponsor_page.php', 'label' => _('My Sponsorships') );
	}

	# Remove the link from the current page
	if ( isset( $t_pages[$p_page] ) ) {
		$t_pages[$p_page]['url'] = '';
	}

	# Plugin / Event added options
	/* @TODO L10N: ensure that plugins are sending label as an already-translated string, not an old lang_api lookup key! */
	$t_event_menu_options = event_signal( 'EVENT_MENU_ACCOUNT' );
	$t_menu_options = array();
	foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
		foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
			if( is_array( $t_callback_menu_options ) ) {
				$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
			} else {
				if ( !is_null( $t_callback_menu_options ) ) {
					$t_menu_options[] = $t_callback_menu_options;
				}
			}
		}
	}

	echo '<div id="account-menu">';
	echo '<ul class="menu">';
	foreach ( $t_pages as $t_page ) {
		if( $t_page['url'] == '' ) {
			echo '<li>' . $t_page['label'] . '</li>';
		} else {
			echo '<li><a href="'. helper_mantis_url( $t_page['url'] ) .'">' . $t_page['label'] . '</a></li>';
		}
	}

	// Plugins menu items - these are cooked links
	foreach ( $t_menu_options as $t_menu_item ) {
		echo '<li>', $t_menu_item, '</li>';
	}
	echo '</ul>';
	echo '</div>';
}

/**
 * Print the menu for the docs section
 * @param string $p_page specifies the current page name so it's link can be disabled
 * @return null
 */
function print_doc_menu( $p_page = '' ) {
	$t_documentation_html = config_get( 'manual_url' );
	$t_pages[$t_documentation_html] = array( 'url'=>$t_documentation_html, 'label' => _('User Documentation') );
	$t_pages['proj_doc_page.php'] = array( 'url'=>'proj_doc_page.php', 'label' => _('Project Documentation') );
	if( file_allow_project_upload() ) {
		$t_pages['proj_doc_add_page.php'] = array( 'url'=>'proj_doc_add_page.php', 'label' => _('Add File') );
	}

	# Remove the link from the current page
	if ( isset( $t_pages[$p_page] ) ) {
		$t_pages[$p_page]['url'] = '';
	}

	echo '<div id="doc-menu">';
	echo '<ul class="menu">';
	foreach ( $t_pages as $t_page ) {
		if( $t_page['url'] == '' ) {
			echo '<li>' . $t_page['label'] . '</li>';
		} else {
			echo '<li><a href="'. helper_mantis_url( $t_page['url'] ) .'">' . $t_page['label'] . '</a></li>';
		}
	}
	echo '</ul>';
	echo '</div>';
}

/**
 * Print the menu for the summary section
 * @param string $p_page specifies the current page name so it's link can be disabled
 * @return null
 */
function print_summary_menu( $p_page = '' ) {
	# Plugin / Event added options
	/* @TODO L10N: ensure that plugins are sending label as an already-translated string, not an old lang_api lookup key! */
	$t_event_menu_options = event_signal( 'EVENT_MENU_SUMMARY' );
	$t_menu_options = array();
	foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
		foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
			if( is_array( $t_callback_menu_options ) ) {
				$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
			} else {
				if ( !is_null( $t_callback_menu_options ) ) {
					$t_menu_options[] = $t_callback_menu_options;
				}
			}
		}
	}

	$t_pages['print_all_bug_page.php'] = array( 'url'=>'print_all_bug_page.php', 'label' => _('Print Reports') );
	$t_pages['summary_page.php'] = array( 'url'=>'summary_page.php', 'label' => _('Summary') );
	# Remove the link from the current page
	if ( isset( $t_pages[$p_page] ) ) {
		$t_pages[$p_page]['url'] = '';
	}

	echo '<div id="summary-menu">';
	echo '<ul class="menu">';

	foreach ( $t_pages as $t_page ) {
		if( $t_page['url'] == '' ) {
			echo '<li>' . $t_page['label'] . '</li>';
		} else {
			echo '<li><a href="'. helper_mantis_url( $t_page['url'] ) .'">' . $t_page['label'] . '</a></li>';
		}
	}

	// Plugins menu items - these are cooked links
	foreach ( $t_menu_options as $t_menu_item ) {
		echo '<li>', $t_menu_item, '</li>';
	}
	echo '</ul>';
	echo '</div>';
}

/**
 * Print the color legend for the status colors
 * @return null
 */
function html_status_legend() {
	# Don't show the legend if only one status is selected by the current filter
	$t_current_filter = current_user_get_bug_filter();
	if ( $t_current_filter === false ) {
		$t_current_filter = filter_get_default();
	}
	$t_simple_filter = $t_current_filter['_view_type'] == 'simple';
	if( $t_simple_filter ) {
		if( !filter_field_is_any( $t_current_filter[FILTER_PROPERTY_STATUS][0] ) ) {
			return null;
		}
	}

	$t_status_array = MantisEnum::getAssocArrayIndexedByValues( config_get( 'status_enum_string' ) );
	$t_status_names = MantisEnum::getAssocArrayIndexedByValues( _( config_get( 'status_enum_string' ) ) );
	$enum_count = count( $t_status_array );

	# read through the list and eliminate unused ones for the selected project
	# assumes that all status are are in the enum array
	$t_workflow = config_get( 'status_enum_workflow' );
	if( !empty( $t_workflow ) ) {
		foreach( $t_status_array as $t_status => $t_name ) {
			if( !isset( $t_workflow[$t_status] ) ) {

				# drop elements that are not in the workflow
				unset( $t_status_array[$t_status] );
			}
		}
	}

	# Remove status values that won't appear as a result of the current filter
	foreach( $t_status_array as $t_status => $t_name ) {
		if( $t_simple_filter ) {
			if( !filter_field_is_none( $t_current_filter[FILTER_PROPERTY_HIDE_STATUS][0] ) &&
				$t_status >= $t_current_filter[FILTER_PROPERTY_HIDE_STATUS][0] ) {
				unset( $t_status_array[$t_status] );
			}
		} else {
			if( !in_array( META_FILTER_ANY, $t_current_filter[FILTER_PROPERTY_STATUS] ) &&
				!in_array( $t_status, $t_current_filter[FILTER_PROPERTY_STATUS] ) ) {
				unset( $t_status_array[$t_status] );
			}
		}
	}

	# If there aren't at least two statuses showable by the current filter,
	# don't draw the status bar
	if( count( $t_status_array ) <= 1 ) {
		return null;
	}

	echo '<br />';
	echo '<table class="status-legend width100" cellspacing="1">';
	echo '<tr>';

	# draw the status bar
	$width = (int)( 100 / count( $t_status_array ) );
	$t_status_enum_string = config_get('status_enum_string' );
	foreach( $t_status_array as $t_status => $t_name ) {
		$t_val = $t_status_names[$t_status];
		$t_status_label = MantisEnum::getLabel( $t_status_enum_string, $t_status );

		echo "<td class=\"small-caption $t_status_label-color\">$t_val</td>";
	}

	echo '</tr>';
	echo '</table>';
	if( ON == config_get( 'status_percentage_legend' ) ) {
		html_status_percentage_legend();
	}
}

/**
 * Print the legend for the status percentage
 * @return null
 */
function html_status_percentage_legend() {
	$t_status_percents = get_percentage_by_status();
	$t_status_enum_string = config_get('status_enum_string' );
	$t_enum_values = MantisEnum::getValues( $t_status_enum_string );
	$enum_count = count( $t_enum_values );

	$t_bug_count = array_sum( $t_status_percents );

	if( $t_bug_count > 0 ) {
		echo '<br />';
		echo '<table class="width100" cellspacing="1">';
		echo '<tr>';
		echo '<td class="form-title" colspan="' . $enum_count . '">' . _( 'Issue Status Percentage' ) . '</td>';
		echo '</tr>';
		echo '<tr>';

		foreach ( $t_enum_values as $t_status ) {
			$t_percent = ( isset( $t_status_percents[$t_status] ) ?  $t_status_percents[$t_status] : 0 );

			if( $t_percent > 0 ) {
				$t_status_label = MantisEnum::getLabel( $t_status_enum_string, $t_status );
				echo "<td class=\"small-caption-center $t_status_label-color $t_status_label-percentage\">$t_percent%</td>";
			}
		}

		echo '</tr>';
		echo '</table>';
	}
}

/**
 * Print an html button inside a form
 * @param string $p_action Action
 * @param string $p_button_text Button Text
 * @param array $p_fields Fields
 * @param string $p_method Form submit method - default post
 * @return null
 */
function html_button( $p_action, $p_button_text, $p_fields = null, $p_method = 'post' ) {
	$t_form_name = explode( '.php', $p_action, 2 );
	$p_action = urlencode( $p_action );
	$p_button_text = string_attribute( $p_button_text );
	if( null === $p_fields ) {
		$p_fields = array();
	}

	if( strtolower( $p_method ) == 'get' ) {
		$t_method = 'get';
	} else {
		$t_method = 'post';
	}

	echo "<form method=\"$t_method\" action=\"$p_action\" class=\"action-button\">\n";
	echo "\t<fieldset>";
	# Add a CSRF token only when the form is being sent via the POST method
	if ( $t_method == 'post' ) {
		echo form_security_field( $t_form_name[0] );
	}

	foreach( $p_fields as $key => $val ) {
		$key = string_attribute( $key );
		$val = string_attribute( $val );

		echo "\t\t<input type=\"hidden\" name=\"$key\" value=\"$val\" />\n";
	}

	echo "\t\t<input type=\"submit\" class=\"button\" value=\"$p_button_text\" />\n";
	echo "\t</fieldset>";
	echo "</form>\n";
}

/**
 * Print a button to update the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_update( $p_bug ) {
	if( access_has_bug_level( config_get( 'update_bug_threshold' ), $p_bug->id ) ) {
		html_button( string_get_bug_update_page(), _( 'Edit' ), array( 'bug_id' => $p_bug->id ) );
	}
}

/**
 * Print Change Status to: button
 * This code is similar to print_status_option_list except
 * there is no masking, except for the current state
 *
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_change_status( $p_bug ) {
	$t_current_access = access_get_project_level( $p_bug->project_id );

	$t_enum_list = get_status_option_list( $t_current_access, $p_bug->status, false, ( $p_bug->reporter_id == auth_get_current_user_id() && ( ON == config_get( 'allow_reporter_close' ) ) ), $p_bug->project_id );

	if( count( $t_enum_list ) > 0 ) {

		# resort the list into ascending order after noting the key from the first element (the default)
		$t_default_arr = each( $t_enum_list );
		$t_default = $t_default_arr['key'];
		ksort( $t_enum_list );
		reset( $t_enum_list );

		echo "<form method=\"post\" action=\"bug_change_status_page.php\">";
		# CSRF protection not required here - form does not result in modifications

		$t_button_text = _( 'Change Status To:' );
		echo "<input type=\"submit\" class=\"button\" value=\"$t_button_text\" />";

		echo " <select name=\"new_status\">";

		# space at beginning of line is important
		foreach( $t_enum_list as $key => $val ) {
			echo "<option value=\"$key\" ";
			check_selected( $key, $t_default );
			echo ">$val</option>";
		}
		echo '</select>';

		$t_bug_id = string_attribute( $p_bug->id );
		echo "<input type=\"hidden\" name=\"id\" value=\"$t_bug_id\" />\n";

		echo "</form>\n";
	}
}

/**
 * Print Assign To: combo box of possible handlers
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_assign_to( $p_bug ) {
	# make sure status is allowed of assign would cause auto-set-status
	$t_status = $p_bug->status;

	# workflow implementation

	if( ON == config_get( 'auto_set_status_to_assigned' ) && !bug_check_workflow( $t_status, config_get( 'bug_assigned_status' ) ) ) {

		# workflow
		return;
	}

	# make sure current user has access to modify bugs.
	if( !access_has_bug_level( config_get( 'update_bug_assign_threshold', config_get( 'update_bug_threshold' ) ), $p_bug->id ) ) {
		return;
	}

	$t_reporter_id = $p_bug->reporter_id;
	$t_handler_id = $p_bug->handler_id;
	$t_current_user_id = auth_get_current_user_id();
	$t_new_status = ( ON == config_get( 'auto_set_status_to_assigned' ) ) ? config_get( 'bug_assigned_status' ) : $t_status;

	$t_options = array();
	$t_default_assign_to = null;

	if(( $t_handler_id != $t_current_user_id ) && ( access_has_bug_level( config_get( 'handle_bug_threshold' ), $p_bug->id, $t_current_user_id ) ) ) {
		$t_options[] = array(
			$t_current_user_id,
			'[' . _( 'Myself' ) . ']',
		);
		$t_default_assign_to = $t_current_user_id;
	}

	if(( $t_handler_id != $t_reporter_id ) && user_exists( $t_reporter_id ) && ( access_has_bug_level( config_get( 'handle_bug_threshold' ), $p_bug->id, $t_reporter_id ) ) ) {
		$t_options[] = array(
			$t_reporter_id,
			'[' . _( 'Reporter' ) . ']',
		);

		if( $t_default_assign_to === null ) {
			$t_default_assign_to = $t_reporter_id;
		}
	}

	echo "<form method=\"post\" action=\"bug_update.php\">";
	echo form_security_field( 'bug_update' );

	$t_button_text = _( 'Assign To:' );
	echo "<input type=\"submit\" class=\"button\" value=\"$t_button_text\" />";

	echo " <select name=\"handler_id\">";

	# space at beginning of line is important

	$t_already_selected = false;

	foreach( $t_options as $t_entry ) {
		$t_id = (int)$t_entry[0];
		$t_caption = string_attribute( $t_entry[1] );

		# if current user and reporter can't be selected, then select the first
		# user in the list.
		if( $t_default_assign_to === null ) {
			$t_default_assign_to = $t_id;
		}

		echo '<option value="' . $t_id . '" ';

		if(( $t_id == $t_default_assign_to ) && !$t_already_selected ) {
			check_selected( $t_id, $t_default_assign_to );
			$t_already_selected = true;
		}

		echo '>' . $t_caption . '</option>';
	}

	# allow un-assigning if already assigned.
	if( $t_handler_id != 0 ) {
		echo "<option value=\"0\"></option>";
	}

	$t_project_id = $p_bug->project_id;

	# 0 means currently selected
	print_assign_to_option_list( 0, $t_project_id );
	echo "</select>";

	$t_bug_id = string_attribute( $p_bug->id );
	echo "<input type=\"hidden\" name=\"bug_id\" value=\"$t_bug_id\" />\n";

	echo "</form>\n";
}

/**
 * Print a button to move the given bug to a different project
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_move( $p_bug ) {
	if( access_has_bug_level( config_get( 'move_bug_threshold' ), $p_bug->id ) ) {
		html_button( 'bug_actiongroup_page.php', _( 'Move' ), array( 'bug_arr[]' => $p_bug->id, 'action' => 'MOVE' ) );
	}
}

/**
 * Print a button to move the given bug to a different project
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_create_child( $p_bug ) {
	if( access_has_bug_level( config_get( 'update_bug_threshold' ), $p_bug->id ) ) {
		html_button( string_get_bug_report_url(), _( 'Clone' ), array( 'm_id' => $p_bug->id ) );
	}
}

/**
 * Print a button to reopen the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_reopen( $p_bug ) {
	$t_reopen_status = config_get( 'bug_reopen_status', null, null, $p_bug->project_id );

	if( access_has_bug_level( config_get( 'reopen_bug_threshold', null, null, $p_bug->project_id ), $p_bug->id ) ||
			(( $p_bug->reporter_id == auth_get_current_user_id() ) && ( ON == config_get( 'allow_reporter_reopen', null, null, $p_bug->project_id ) ) ) ) {
		html_button( 'bug_change_status_page.php', _( 'Reopen' ), array( 'id' => $p_bug->id, 'new_status' => $t_reopen_status, 'reopen_flag' => ON ) );
	}
}

/**
 * Print a button to monitor the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_monitor( $p_bug ) {
	if( access_has_bug_level( config_get( 'monitor_bug_threshold' ), $p_bug->id ) ) {
		html_button( 'bug_monitor_add.php', _( 'Monitor' ), array( 'bug_id' => $p_bug->id ) );
	}
}

/**
 * Print a button to un-monitor the given bug
 * no reason to ever disallow someone from un-monitoring a bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_unmonitor( $p_bug ) {
	html_button( 'bug_monitor_delete.php', _( 'End Monitoring' ), array( 'bug_id' => $p_bug->id ) );
}

/**
 * Print a button to stick the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_stick( $p_bug ) {
	if ( access_has_bug_level( config_get( 'set_bug_sticky_threshold' ), $p_bug->id ) ) {
		html_button( 'bug_stick.php', _( 'Stick' ), array( 'bug_id' => $p_bug->id, 'action' => 'stick' ) );
	}
}

/**
 * Print a button to un-stick the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_unstick( $p_bug ) {
	if ( access_has_bug_level( config_get( 'set_bug_sticky_threshold' ), $p_bug->id ) ) {
		html_button( 'bug_stick.php', _( 'Unstick' ), array( 'bug_id' => $p_bug->id, 'action' => 'unstick' ) );
	}
}

/**
 * Print a button to delete the given bug
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_button_bug_delete( $p_bug ) {
	if( access_has_bug_level( config_get( 'delete_bug_threshold' ), $p_bug->id ) ) {
		html_button( 'bug_actiongroup_page.php', _( 'Delete' ), array( 'bug_arr[]' => $p_bug->id, 'action' => 'DELETE' ) );
	}
}

/**
 * Print all buttons for view bug pages
 * @param MantisBug $p_bug Bug Object
 * @return null
 */
function html_buttons_view_bug_page( $p_bug ) {
	$p_bug_id = $p_bug->id;

	$t_resolved = config_get( 'bug_resolved_status_threshold' );
	$t_status = bug_get_field( $p_bug_id, 'status' );
	$t_readonly = bug_is_readonly( $p_bug_id );
	$t_sticky = config_get( 'set_bug_sticky_threshold' );

	echo '<table><tr class="vcenter">';
	if( !$t_readonly ) {
		# UPDATE button
		echo '<td class="center">';
		html_button_bug_update( $p_bug );
		echo '</td>';

		# ASSIGN button
		echo '<td class="center">';
		html_button_bug_assign_to( $p_bug );
		echo '</td>';
	}

	# Change status button/dropdown
	if ( !$t_readonly || config_get( 'allow_reporter_close' ) ) {
		echo '<td class="center">';
		html_button_bug_change_status( $p_bug );
		echo '</td>';
	}

	# MONITOR/UNMONITOR button
	if( !user_is_anonymous( auth_get_current_user_id() ) ) {
		echo '<td class="center">';
		if( user_is_monitoring_bug( auth_get_current_user_id(), $p_bug_id ) ) {
			html_button_bug_unmonitor( $p_bug );
		} else {
			html_button_bug_monitor( $p_bug );
		}
		echo '</td>';
	}

	# STICK/UNSTICK button
	if ( access_has_bug_level( $t_sticky, $p_bug_id ) ) {
		echo '<td class="center">';
		if ( !bug_get_field( $p_bug_id, 'sticky' ) ) {
			html_button_bug_stick( $p_bug );
		} else {
			html_button_bug_unstick( $p_bug );
		}
		echo '</td>';
	}

	if( !$t_readonly ) {
		# CREATE CHILD button
		echo '<td class="center">';
		html_button_bug_create_child( $p_bug );
		echo '</td>';
	}

	if( $t_resolved <= $t_status ) {
		# resolved is not the same as readonly
		echo '<td class="center">';

		# REOPEN button
		html_button_bug_reopen( $p_bug );
		echo '</td>';
	}

	# MOVE button
	echo '<td class="center">';
	html_button_bug_move( $p_bug );
	echo '</td>';

	# DELETE button
	echo '<td class="center">';
	html_button_bug_delete( $p_bug );
	echo '</td>';

	helper_call_custom_function( 'print_bug_view_page_custom_buttons', array( $p_bug_id ) );

	echo '</tr></table>';
}

/**
 * get the css class name for the given status, user and project
 * @param int $p_status
 * @param null $p_user
 * @param null $p_project
 * @return string
 */
function html_get_status_css_class( $p_status, $p_user = null, $p_project = null ) {
	return string_attribute( MantisEnum::getLabel( config_get('status_enum_string', null, $p_user, $p_project ), $p_status ) . '-color' );
}