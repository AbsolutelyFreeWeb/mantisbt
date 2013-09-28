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
 * Manage Email Configuration
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses authentication_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses form_api.php
 * @uses helper_api.php
 * @uses html_api.php

 * @uses print_api.php
 * @uses project_api.php
 * @uses string_api.php
 */

require_once( '../core.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'form_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();

/**
 * array_merge_recursive2()
 *
 * Similar to array_merge_recursive but keyed-valued are always overwritten.
 * Priority goes to the 2nd array.
 *
 * @public yes
 * @param $p_array1 array
 * @param $p_array2 array
 * @return array
 */
function array_merge_recursive2( $p_array1, $p_array2 ) {
	if ( !is_array( $p_array1 ) || !is_array( $p_array2 ) ) {
		return $p_array2;
	}
	$t_merged_array = $p_array1;
	foreach ( $p_array2 as $t_key2 => $t_value2 ) {
		if ( array_key_exists( $t_key2, $t_merged_array ) && is_array( $t_value2 ) ) {
			$t_merged_array[$t_key2] = array_merge_recursive2( $t_merged_array[$t_key2], $t_value2 );
		} else {
			$t_merged_array[$t_key2] = $t_value2;
		}
	}
	return $t_merged_array;
}

/**
 * get_notify_flag cloned from email_notify_flag
 * Get the value associated with the specific action and flag.
 * For example, you can get the value associated with notifying "admin"
 * on action "new", i.e. notify administrators on new bugs which can be
 * ON or OFF.
 *
 * @param string $p_action action
 * @param string $p_flag flag
 * @return string
 */
function get_notify_flag( $p_action, $p_flag ) {
	global $t_notify_flags, $t_default_notify_flags;

	$t_val = OFF;
	if ( isset ( $t_notify_flags[$p_action][$p_flag] ) ) {
		$t_val = $t_notify_flags[$p_action][$p_flag];
	} else if ( isset ( $t_default_notify_flags[$p_flag] ) ) {
		$t_val = $t_default_notify_flags[$p_flag];
	}
	return $t_val;
}

/**
 * Return CSS for flag
 *
 * @param string $p_action action
 * @param string $p_flag flag
 * @return string
 */
function colour_notify_flag ( $p_action, $p_flag ) {
	global $t_notify_flags, $t_global_notify_flags, $t_file_notify_flags;

	$t_file = isset( $t_file_notify_flags[$p_action][$p_flag] ) ? ( $t_file_notify_flags[$p_action][$p_flag] ? 1 : 0 ): -1;
	$t_global = isset( $t_global_notify_flags[$p_action][$p_flag] ) ? ( $t_global_notify_flags[$p_action][$p_flag]  ? 1 : 0 ): -1;
	$t_project = isset( $t_notify_flags[$p_action][$p_flag] ) ? ( $t_notify_flags[$p_action][$p_flag]  ? 1 : 0 ): -1;

	$t_colour = '';
	if ( $t_global >= 0 ) {
		if ( $t_global != $t_file ) {
			$t_colour = ' class="colour-global" '; # all projects override
		}
	}
	if ( $t_project >= 0 ) {
		if ( $t_project != $t_global ) {
			$t_colour = ' class="colour-project" '; # project overrides
		}
	}
	return $t_colour;
}

/**
 * Get the value associated with the specific action and flag.
 *
 * @param string $p_action action
 * @param string $p_flag flag
 * @return string
 */
function show_notify_flag( $p_action, $p_flag ) {
	global $t_can_change_flags , $t_can_change_defaults;
	$t_flag = get_notify_flag( $p_action, $p_flag );
	if ( $t_can_change_flags || $t_can_change_defaults ) {
		$t_flag_name = $p_action . ':' . $p_flag;
		$t_set = $t_flag ? "checked=\"checked\"" : "";
		return "<input type=\"checkbox\" name=\"flag[]\" value=\"$t_flag_name\" $t_set />";
	} else {
		return ( $t_flag ? '<img src="' . helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/ok.png' ) . '" width="20" height="15" title="X" alt="X" />' : '&#160;' );
	}
}

/**
 * Get CSS for threshold flags
 *
 * @param string $p_access access
 * @param string $p_action action
 * @return string
 */
function colour_threshold_flag ( $p_access, $p_action ) {
	global $t_notify_flags, $t_global_notify_flags, $t_file_notify_flags;

	$t_file = ( $p_access >= $t_file_notify_flags[$p_action]['threshold_min'] )
					 && ( $p_access <= $t_file_notify_flags[$p_action]['threshold_max'] );
	$t_global = ( $p_access >= $t_global_notify_flags[$p_action]['threshold_min'] )
					 && ( $p_access <= $t_global_notify_flags[$p_action]['threshold_max'] );
	$t_project = ( $p_access >= $t_notify_flags[$p_action]['threshold_min'] )
					 && ( $p_access <= $t_notify_flags[$p_action]['threshold_max'] );

	$t_colour = '';
	if ( $t_global != $t_file ) {
		$t_colour = ' class="colour-global" '; # all projects override
	}
	if ( $t_project != $t_global ) {
		$t_colour = ' class="colour-project" '; # project overrides
	}
	return $t_colour;
}

/**
 * HTML for Show notify threshold
 *
 * @param string $p_access access
 * @param string $p_action action
 * @return string
 */
function show_notify_threshold( $p_access, $p_action ) {
	global $t_can_change_flags , $t_can_change_defaults;
	$t_flag = ( $p_access >= get_notify_flag( $p_action, 'threshold_min' ) )
		&& ( $p_access <= get_notify_flag( $p_action, 'threshold_max' ) );
	if ( $t_can_change_flags  || $t_can_change_defaults ) {
		$t_flag_name = $p_action . ':' . $p_access;
		$t_set = $t_flag ? "checked=\"checked\"" : "";
		return "<input type=\"checkbox\" name=\"flag_threshold[]\" value=\"$t_flag_name\" $t_set />";
	} else {
		return $t_flag ? '<img src="'.helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/ok.png' ).'" width="20" height="15" title="X" alt="X" />' : '&#160;';
	}
}

/**
 * HTML for email section
 *
 * @param string $p_section_name section name
 */
function get_section_begin_for_email( $p_section_name ) {
	$t_access_levels = MantisEnum::getValues( config_get( 'access_levels_enum_string' ) );
	echo '<table class="width100">';
	echo '<tr><td class="form-title-caps" colspan="' . ( count( $t_access_levels ) + 7 ) . '">' . $p_section_name . '</td></tr>' . "\n";
	echo '<tr><td class="form-title" width="30%" rowspan="2">' . _( 'Message' ) . '</td>';
	echo'<td class="form-title" style="text-align:center" rowspan="2">&#160;' . _( 'User who reported issue' ) . '&#160;</td>';
	echo '<td class="form-title" style="text-align:center" rowspan="2">&#160;' . _( 'User who is handling the issue' ) . '&#160;</td>';
	echo '<td class="form-title" style="text-align:center" rowspan="2">&#160;' . _( 'Users monitoring this issue' ) . '&#160;</td>';
	echo '<td class="form-title" style="text-align:center" rowspan="2">&#160;' . _( 'Users who added Issue Notes' ) . '&#160;</td>';
	echo '<td class="form-title" style="text-align:center" colspan="' . count( $t_access_levels ) . '">&#160;' . _( 'Access Levels' ) . '&#160;</td></tr><tr>';

	foreach( $t_access_levels as $t_access_level ) {
		echo '<td class="form-title" style="text-align:center">&#160;' . get_enum_element( 'access_levels', $t_access_level ) . '&#160;</td>';
	}

	echo '</tr>' . "\n";
}

/**
 * HTML for Row
 *
 * @param string $p_caption caption
 * @param string $p_message_type message type
 */
function get_capability_row_for_email( $p_caption, $p_message_type ) {
	$t_access_levels = MantisEnum::getValues( config_get( 'access_levels_enum_string' ) );

	echo '<tr><td>' . string_display( $p_caption ) . '</td>';
	echo '<td class="center"' . colour_notify_flag( $p_message_type, 'reporter' ) . '>' . show_notify_flag( $p_message_type, 'reporter' )  . '</td>';
	echo '<td class="center"' . colour_notify_flag( $p_message_type, 'handler' ) . '>' . show_notify_flag( $p_message_type, 'handler' ) . '</td>';
	echo '<td class="center"' . colour_notify_flag( $p_message_type, 'monitor' ) . '>' . show_notify_flag( $p_message_type, 'monitor' ) . '</td>';
	echo '<td class="center"' . colour_notify_flag( $p_message_type, 'bugnotes' ) . '>' . show_notify_flag( $p_message_type, 'bugnotes' ) . '</td>';

	foreach( $t_access_levels as $t_access_level ) {
		echo '<td class="center"' . colour_threshold_flag( $t_access_level, $p_message_type ) . '>' . show_notify_threshold( $t_access_level, $p_message_type ) . '</td>';
	}

	echo '</tr>' . "\n";
}

/**
 * HTML for email section end
 *
 */
function get_section_end_for_email() {
	echo '</table><br />' . "\n";
}

html_page_top( _( 'E-mail Notifications' ) );

print_manage_menu( 'adm_permissions_report.php' );
print_manage_config_menu( 'config_email_page.php' );

$t_access = user_get_access_level();
$t_project = helper_get_current_project();

# build a list of all of the actions
$t_actions = array( 'owner', 'reopened', 'deleted', 'bugnote' );
if( config_get( 'enable_sponsorship' ) == ON ) {
	$t_actions[] = 'sponsor';
}

$t_actions[] = 'relationship';

$t_statuses = MantisEnum::getAssocArrayIndexedByValues( config_get( 'status_enum_string' ) );
foreach( $t_statuses as $t_status ) {
	$t_actions[] =  $t_status;
}

# build a composite of the status flags, exploding the defaults
$t_global_default_notify_flags = config_get( 'default_notify_flags', null, null, ALL_PROJECTS );
$t_global_notify_flags = array();
foreach ( $t_global_default_notify_flags as $t_flag => $t_value ) {
   foreach ($t_actions as $t_action ) {
	   $t_global_notify_flags[$t_action][$t_flag] = $t_value;
   }
}
$t_global_notify_flags = array_merge_recursive2( $t_global_notify_flags, config_get( 'notify_flags', null, null, ALL_PROJECTS ) );

$t_file_default_notify_flags = config_get_global( 'default_notify_flags' );
$t_file_notify_flags = array();
foreach ( $t_file_default_notify_flags as $t_flag => $t_value ) {
   foreach ($t_actions as $t_action ) {
	   $t_file_notify_flags[$t_action][$t_flag] = $t_value;
   }
}
$t_file_notify_flags = array_merge_recursive2( $t_file_notify_flags, config_get_global( 'notify_flags' ) );

$t_default_notify_flags = config_get( 'default_notify_flags' );
$t_notify_flags = array();
foreach ( $t_default_notify_flags as $t_flag => $t_value ) {
   foreach ($t_actions as $t_action ) {
	   $t_notify_flags[$t_action][$t_flag] = $t_value;
   }
}
$t_notify_flags = array_merge_recursive2( $t_notify_flags, config_get( 'notify_flags' ) );

$t_can_change_flags = $t_access >= config_get_access( 'notify_flags' );
$t_can_change_defaults = $t_access >= config_get_access( 'default_notify_flags' );

echo '<br /><br />';

# Email notifications
if( config_get( 'enable_email_notification' ) == ON ) {

	if ( $t_can_change_flags  || $t_can_change_defaults ) {
		echo "<form id=\"mail_config_action\" method=\"post\" action=\"config_email_set.php\">\n";
		echo form_security_field( 'manage_config_email_set' );
	}

	if ( ALL_PROJECTS == $t_project ) {
		$t_project_title = _( 'Note: These configurations affect all projects, unless overridden at the project level.' );
	} else {
		$t_project_title = sprintf( _( 'Note: These configurations affect only the %s project.' ) , string_display( project_get_name( $t_project ) ) );
	}
	echo '<p class="bold">' . $t_project_title . '</p>' . "\n";
	echo '<p>' . _( 'In the table below, the following color code applies:' ) . '<br />';
	if ( ALL_PROJECTS <> $t_project ) {
		echo '<span class="colour-project">' . _( 'Project setting overrides others.' ) . '</span><br />';
	}
	echo '<span class="colour-global">' . _( 'All Project settings override default configuration.' ) . '</span></p>';

	get_section_begin_for_email( _( 'E-mail notification' ) );
#		get_capability_row_for_email( _( 'E-mail on New' ), 'new' );  # duplicate of status change to 'new'
	get_capability_row_for_email( _( 'E-mail on Change of Handler' ), 'owner' );
	get_capability_row_for_email( _( 'E-mail on Reopened' ), 'reopened' );
	get_capability_row_for_email( _( 'E-mail on Deleted' ), 'deleted' );
	get_capability_row_for_email( _( 'E-mail on Note Added' ), 'bugnote' );
	if( config_get( 'enable_sponsorship' ) == ON ) {
		get_capability_row_for_email( _( 'E-mail on Sponsorship changed' ), 'sponsor' );
	}

	get_capability_row_for_email( _( 'E-mail on Relationship changed' ), 'relationship' );

	$t_statuses = MantisEnum::getAssocArrayIndexedByValues( config_get( 'status_enum_string' ) );
	foreach ( $t_statuses as $t_status => $t_label ) {
		get_capability_row_for_email( _( 'Status changes to' ) . ' \'' . get_enum_element( 'status', $t_status ) . '\'', $t_label );
	}

	get_section_end_for_email();

	if ( $t_can_change_flags  || $t_can_change_defaults ) {
		echo '<p>' . _( 'Who can change notifications:' );
		echo '<select name="notify_actions_access">';
		print_enum_string_option_list( 'access_levels', config_get_access( 'notify_flags' ) );
		echo '</select> </p>';

		echo "<input type=\"submit\" class=\"button\" value=\"" . _( 'Update Configuration' ) . "\" />\n";

		echo "</form>\n";

		echo "<div class=\"right\"><form id=\"mail_config_action\" method=\"post\" action=\"config_revert.php\">\n";
		echo form_security_field( 'manage_config_revert' );
		echo "<input name=\"revert\" type=\"hidden\" value=\"notify_flags,default_notify_flags\" />";
		echo "<input name=\"project\" type=\"hidden\" value=\"$t_project\" />";
		echo "<input name=\"return\" type=\"hidden\" value=\"\" />";
		echo "<input type=\"submit\" class=\"button\" value=\"";
		if ( ALL_PROJECTS == $t_project ) {
			echo _( 'Delete All Projects Settings' );
		} else {
			echo _( 'Delete Project Specific Settings' );
		}
		echo "\" />\n";
		echo "</form></div>\n";
	}

}

html_page_bottom();
