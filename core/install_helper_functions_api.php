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
 * Install Helper Functions API
 *
 * @package CoreAPI
 * @subpackage InstallHelperFunctionsAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses database_api.php
 */

require_api( 'database_api.php' );

/**
 * Checks a PHP version number against the version of PHP currently in use
 * @param string $p_version Version string to compare
 * @return bool true if the PHP version in use is equal to or greater than the supplied version string
 */
function check_php_version( $p_version ) {
	if( $p_version == PHP_MIN_VERSION ) {
		return true;
	} else {
		if( function_exists( 'version_compare' ) ) {
			if( version_compare( phpversion(), PHP_MIN_VERSION, '>=' ) ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
/**
 * Check Database extensions currently supported by PHP
 * @param bool $p_list return as comma seperated list
 * @return bool
 */
function check_get_database_extensions( $p_list = false ) {
	$t_ext_array = get_loaded_extensions();
	$t_db = '';
	foreach( $t_ext_array as $t_ext) {
		$t_extl = strtolower( $t_ext );
		if( $t_extl == 'pdo' )
			continue;
		// pdo drivers
		if( substr( $t_extl, 0, 3 ) == 'pdo' ) {
			$t_db .= $t_extl . ',';
		}
		// non-pdo drivers
		switch ($t_extl) {
			default:
				continue;
		}
	}
	
	if( $p_list == true ) {
		return rtrim( $t_db, ',' );
	} else {
		if( $t_db != '' ) {
			return true;
		}
	}
	return false;
}

/**
 * Legacy pre-1.2 date function used for upgrading from datetime to integer
 * representation of dates in the database.
 * @return string Formatted date representing unixtime(0) + 1 second, ready for database insertion
 */
function db_null_date() {
	global $g_db;

	return $g_db->legacy_null_date();
}

/**
 * Legacy pre-1.2 date function used for upgrading from datetime to integer
 * representation of dates in the database. This function converts a formatted
 * datetime string to an that represents the number of seconds elapsed since
 * the Unix epoch.
 * @param string $p_date Formatted datetime string from a database
 * @param bool $p_gmt Whether to use UTC (true) or server timezone (false, default)
 * @return int Unix timestamp representation of a datetime string
 * @todo Review date handling
 */
function db_unixtimestamp( $p_date = null, $p_gmt = false ) {
	global $g_db;

	if( null !== $p_date ) {
		$p_timestamp = $g_db->legacy_timestamp( $p_date );
	} else {
		$p_timestamp = time();
	}
	return $p_timestamp;
}

/**
 * Migrate the legacy category data to the new category_id-based schema.
 */
function install_category_migrate() {
	$query = "SELECT project_id, category, user_id FROM {project_category} ORDER BY project_id, category";
	$t_category_result = db_query( $query );

	$query = "SELECT project_id, category FROM {bug} ORDER BY project_id, category";
	$t_bug_result = db_query( $query );

	$t_data = array();

	# Find categories specified by project
	while( $row = db_fetch_array( $t_category_result ) ) {
		$t_project_id = $row['project_id'];
		$t_name = $row['category'];
		$t_data[$t_project_id][$t_name] = $row['user_id'];
	}

	# Find orphaned categories from bugs
	while( $row = db_fetch_array( $t_bug_result ) ) {
		$t_project_id = $row['project_id'];
		$t_name = $row['category'];

		if ( !isset( $t_data[$t_project_id][$t_name] ) ) {
			$t_data[$t_project_id][$t_name] = 0;
		}
	}

	# In every project, go through all the categories found, and create them and update the bug
	foreach( $t_data as $t_project_id => $t_categories ) {
		$t_inserted = array();
		foreach( $t_categories as $t_name => $t_user_id ) {
			$t_lower_name = mb_strtolower( trim( $t_name ) );
			if ( !isset( $t_inserted[$t_lower_name] ) ) {
				$query = 'INSERT INTO {category} ( name, project_id, user_id ) VALUES ( %s, %d, %d )';
				db_query( $query, array( $t_name, $t_project_id, $t_user_id ) );
				$t_category_id = db_insert_id( '{category}' );
				$t_inserted[$t_lower_name] = $t_category_id;
			} else {
				$t_category_id = $t_inserted[$t_lower_name];
			}

			$t_query = "UPDATE {bug} SET category_id=%d WHERE project_id=%d AND category=%s";
			db_query( $t_query, array( $t_category_id, $t_project_id, $t_name ) );
		}
	}

	# return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Migrate the legacy date format.
 * @param array Array: [0] = tablename, [1] id column, [2] = old column, [3] = new column
 * @return int
 */
function install_date_migrate( $p_data) {
	// $p_data[0] = tablename, [1] id column, [2] = old column, [3] = new column
	$t_table = $p_data[0];
	$t_id_column = $p_data[1];

	if ( is_array( $p_data[2] ) ) {
		$t_old_column = implode( ',', $p_data[2] );
		$t_date_array = true;
		$t_cnt_fields = count( $p_data[2] );
		$t_pairs = array();
		foreach( $p_data[3] as $var ) {
			array_push( $t_pairs, "$var=%s" ) ;
		}
		$t_new_column = implode( ',', $t_pairs );
		$t_query = "SELECT $t_id_column, $t_old_column FROM $t_table";
	} else {
		$t_old_column = $p_data[2];
		$t_new_column = $p_data[3] . '=%d';
		$t_date_array = false;

		# The check for timestamp being = 1 is to make sure the field wasn't upgraded
		# already in a previous run - see bug #12601 for more details.
		$t_new_column_name = $p_data[3];
		$t_query = "SELECT $t_id_column, $t_old_column FROM $t_table WHERE $t_new_column_name = 1";
	}

	$t_result = db_query( $t_query );

	while( $row = db_fetch_array( $t_result ) ) {
		$t_id = (int)$row[$t_id_column];

		if( $t_date_array ) {
			for( $i=0; $i < $t_cnt_fields; $i++ ) {
				$t_old_value = $row[$p_data[2][$i]];

				if( is_numeric( $t_old_value ) ) {
					return 1; // Fatal: conversion may have already been run. If it has been run, proceeding will wipe timestamps from db
				}

				$t_new_value[$i] = db_unixtimestamp($t_old_value);
				if ($t_new_value[$i] < 100000 ) {
					$t_new_value[$i] = 1;
				}
			}
			$t_values = $t_new_value;
			$t_values[] = $t_id;
		} else {
			$t_old_value = $row[$t_old_column];

			if( is_numeric( $t_old_value ) ) {
				return 1; // Fatal: conversion may have already been run. If it has been run, proceeding will wipe timestamps from db
			}

			$t_new_value = db_unixtimestamp($t_old_value);
			if ($t_new_value < 100000 ) {
				$t_new_value = 1;
			}
			$t_values = array( $t_new_value, $t_id);
		}

		$query = "UPDATE $t_table SET $t_new_column WHERE $t_id_column=%d";
		db_query( $query, $t_values );
	}

	# return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;

}

/**
 * Once upon a time multi-select custom field types (checkbox and multiselect)
 * were stored in the database in the format of "option1|option2|option3" where
 * they should have been stored in a format of "|option1|option2|option3|".
 * Additionally, radio custom field types were being stored in the database
 * with an unnecessary vertical pipe prefix and suffix when there is only ever
 * one possible value that can be assigned to a radio field.
 */
function install_correct_multiselect_custom_fields_db_format() {
	# Ensure multilist and checkbox custom field values have a vertical pipe |
	# as a prefix and suffix.
	$t_query = "SELECT v.field_id, v.bug_id, v.value from {custom_field_string} v
		LEFT JOIN {custom_field} c
		ON v.field_id = c.id
		WHERE (c.type = " . CUSTOM_FIELD_TYPE_MULTILIST . " OR c.type = " . CUSTOM_FIELD_TYPE_CHECKBOX . ")
			AND v.value != ''
			AND v.value NOT LIKE '|%|'";
	$t_result = db_query( $t_query );

	while( $t_row = db_fetch_array( $t_result ) ) {
		$c_field_id = (int)$t_row['field_id'];
		$c_bug_id = (int)$t_row['bug_id'];
		$c_value = '|' . rtrim( ltrim( $t_row['value'], '|' ), '|' ) . '|';
		$t_update_query = "UPDATE {custom_field_string}
			SET value = '$c_value'
			WHERE field_id = $c_field_id
				AND bug_id = $c_bug_id";
		db_query( $t_update_query );
	}

	# Remove vertical pipe | prefix and suffix from radio custom field values.
	$t_query = "SELECT v.field_id, v.bug_id, v.value from {custom_field_string} v
		LEFT JOIN {custom_field} c
		ON v.field_id = c.id
		WHERE c.type = " . CUSTOM_FIELD_TYPE_RADIO . "
			AND v.value != ''
			AND v.value LIKE '|%|'";
	$t_result = db_query( $t_query );

	while( $t_row = db_fetch_array( $t_result ) ) {
		$c_field_id = (int)$t_row['field_id'];
		$c_bug_id = (int)$t_row['bug_id'];
		$c_value = rtrim( ltrim( $t_row['value'], '|' ), '|' );
		$t_update_query = "UPDATE {custom_field_string}
			SET value = '$c_value'
			WHERE field_id = $c_field_id
				AND bug_id = $c_bug_id";
		db_query( $t_update_query );
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 *	The filters have been changed so the field names are the same as the database
 *	field names.  This updates any filters stored in the database to use the correct
 *	keys. The 'and_not_assigned' field is no longer used as it is replaced by the meta
 *	filter None.  This removes it from all filters.
 */
function install_stored_filter_migrate() {
	require_api( 'filter_api.php' );

	$t_cookie_version = config_get_global( 'cookie_version' );

	# convert filters to use the same value for the filter key and the form field
	$t_filter_fields['show_category'] = 'category_id';
	$t_filter_fields['show_severity'] = 'severity';
	$t_filter_fields['show_status'] = 'status';
	$t_filter_fields['show_priority'] = 'priority';
	$t_filter_fields['show_resolution'] = 'resolution';
	$t_filter_fields['show_build'] = 'build';
	$t_filter_fields['show_version'] = 'version';
	$t_filter_fields['user_monitor'] = 'monitor_user_id';
	$t_filter_fields['show_profile'] = 'profile_id';
	$t_filter_fields['do_filter_by_date'] = 'filter_by_date';
	$t_filter_fields['and_not_assigned'] = null;
	$t_filter_fields['sticky_issues'] = 'sticky';

	$t_query = "SELECT * FROM {filters}";
	$t_result = db_query( $t_query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_filter_arr = filter_deserialize( $t_row['filter_string'], $t_row['user_id'], $t_row['project_id'] );
		foreach( $t_filter_fields AS $t_old=>$t_new ) {
			if ( isset( $t_filter_arr[$t_old] ) ) {
				$t_value = $t_filter_arr[$t_old];
				unset( $t_filter_arr[$t_old] );
				if( !is_null( $t_new ) ) {
					$t_filter_arr[$t_new] = $t_value;
				}
			}
		}

		$t_filter_serialized = serialize( $t_filter_arr );
		$t_filter_string = $t_cookie_version . '#' . $t_filter_serialized;

		$t_update_query = 'UPDATE {filters} SET filter_string=%s WHERE id=%d';
		db_query( $t_update_query, array( $t_filter_string, $t_row['id'] ) );
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Schema update to do nothing - this allows a schema update to be removed from the install file
 * if added by mistake or no longer required for new installs/upgrades.
 * e.g. if a schema update inserted data directly into the database, and now the data will be
 * generated by a php function/configuration from the end user
 */
function install_do_nothing() {
	# return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Create default admin user if it does not exist
 * @param array $p_data User/password array
 * @return int
 */
function install_create_admin_if_not_exist( $p_data ) {
	$t_query = "SELECT count(*) FROM {user}";
	$t_result = db_query( $t_query );
	
	if ( db_result($t_result) != 0 ) {
		return 2;
	}
	
	$p_username = $p_data[0]; 
	$p_password = $p_data[1];
	$p_email = 'root@localhost';
	$t_seed = $p_email . $p_username;
	$t_cookie_string = auth_generate_unique_cookie_string( $t_seed );
	$t_password = auth_process_plain_password( $p_password );

	$query = "INSERT INTO {user}
				    ( username, email, password, date_created, last_visit, enabled,
				      protected, access_level, login_count, cookie_string, realname )
				  VALUES
				    ( %s, %s, %s, %d, %d, %d,
				      %d, %d, %d, %s, %s)";
	db_query( $query, array( $p_username, $p_email, $t_password, db_now(), db_now(), 1, 1, 90, 0, $t_cookie_string, '' ) );

	# Create preferences for the user
	$t_user_id = db_insert_id( '{user}' );
	
	if( $t_user_id === 1 ) {
		return 2;
	}  
}

/**
 * Schema update to migrate legacy columns format
 */
function install_update_export_columns() {
	$query = "SELECT project_id, user_id, access_reqd, type FROM {config} WHERE config_id = 'csv_columns' or config_id = 'excel_columns' group by project_id, user_id, access_reqd, type";

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$project_id = (int)$t_row['project_id'];
		$user_id = (int)$t_row['user_id'];
		$access_reqd = (int)$t_row['access_reqd'];
		$type = (int)$t_row['type'];

		$query = "SELECT value FROM {config} WHERE (config_id = 'csv_columns' or config_id = 'excel_columns') AND access_reqd=%d AND type=%d AND project_id=%d AND user_id=%d";
		$t_result2 = db_query( $query, array( $access_reqd, $type, $project_id, $user_id ) );
		$t_array = array();

		while( $t_row2 = db_fetch_array( $t_result2 ) ) {
			$t_array = array_merge( $t_array, unserialize( $t_row2['value'] ) );
		}

		$query = "INSERT INTO {config} (config_id, value, access_reqd, type, project_id, user_id ) VALUES ( %s,%s,%d,%d,%d,%d )";
		$t_value = serialize( array_values( array_unique( $t_array ) ) );
		db_query( $query, array( 'export_columns', $t_value, $access_reqd, $type, $project_id, $user_id ) );

		$query = "DELETE FROM {config} WHERE (config_id = 'csv_columns' or config_id = 'excel_columns') AND access_reqd=%d AND type=%d AND project_id=%d AND user_id=%d";
		db_query( $query, array( $access_reqd, $type, $project_id, $user_id ) );
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Schema update to migrate bug text
 */
function install_migrate_bug_text() {
	$query = "SELECT id, description, steps_to_reproduce, additional_information FROM {bug_text}";

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$text_id = (int)$t_row['id'];
		$description = $t_row['description'];
		$steps_to_reproduce = $t_row['steps_to_reproduce'];
		$additional_information = $t_row['additional_information'];

		$query = "UPDATE {bug} SET description=%s, steps_to_reproduce=%s, additional_information=%s WHERE bug_text_id=%d";
		db_query( $query, array( $description, $steps_to_reproduce, $additional_information, $text_id ) );

		$query = "DELETE FROM {bug_text} WHERE id=%d";
		db_query( $query, array( $text_id ) );
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Schema update to migrate bugnote text
 */
function install_migrate_bugnote_text() {
	$query = "SELECT id, note FROM {bugnote_text}";

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$text_id = (int)$t_row['id'];
		$note = $t_row['note'];

		$query = "UPDATE {bugnote} SET note=%s WHERE bugnote_text_id=%d";
		db_query( $query, array( $note, $text_id ) );

		$query = "DELETE FROM {bugnote_text} WHERE id=%d";
		db_query( $query, array( $text_id ) );
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

/**
 * Schema update to check that project hierarchy was valid
 */
function install_check_project_hierarchy() {
	$query = 'SELECT count(child_id) as count, child_id, parent_id FROM {project_hierarchy} GROUP BY child_id, parent_id';

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$count = (int)$t_row['count'];
		$child_id = (int)$t_row['child_id'];
		$parent_id = (int)$t_row['parent_id'];

		if( $count > 1 ) {
			$query = 'SELECT inherit_parent, child_id, parent_id FROM {project_hierarchy} WHERE child_id=%d AND parent_id=%d';
			
			$t_result2 = db_query( $query, array( $child_id, $parent_id ) );		
			// get first result for inherit_parent, discard the rest
			$t_row2 = db_fetch_array( $t_result2 );
			
			$inherit = $t_row2['inherit_parent'];
			
			db_query( 'DELETE FROM {project_hierarchy} WHERE child_id=%d AND parent_id=%d', array( $child_id, $parent_id ) );
			
			db_query( 'INSERT INTO {project_hierarchy} (child_id, parent_id, inherit_parent) VALUES (%d,%d,%d)', array( $child_id, $parent_id, $inherit ) );
		}
	}

	# Return 2 because that's what ADOdb/DataDict does when things happen properly
	return 2;
}

function install_check_duplicate_ids() {
	// check duplicate_id column in bugs table is blank
	$query = "SELECT id, duplicate_id FROM {bug} WHERE duplicate_id > 0";

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$bug_id = (int)$t_row['id'];
		$duplicate = $t_row['duplicate_id'];

		$query = "SELECT id FROM {bug_relationship} WHERE source_bug_id=%d AND destination_bug_id=%d AND relationship_type=%d";
		$t_result2 = db_query($query, array( $bug_id, $duplicate, 0 ) );
		$result = db_result( $t_result2 );

		if( $result > 0 ) {
			$query = "UPDATE {bug} SET duplicate_id=0 WHERE id=%d";
			db_query( $query, array( $bug_id ) );
		} else {
			// duplicate may have been deleted and only exist in history table so check history
			$query = "SELECT id FROM {bug_history} WHERE bug_id=%d AND old_value=%d AND type=%d";
			$t_result2 = db_query($query, array( $bug_id, 0, 18 ) );
			$result = db_result( $t_result2 );
			if( $result > 0 ) {
				$query = "UPDATE {bug} SET duplicate_id=0 WHERE id=%d";
				db_query( $query, array( $bug_id ) );
			}
		}
	}

	$query = "SELECT count(id) FROM {bug} WHERE duplicate_id > 0";
	$t_result = db_query( $query );
	$result = db_result( $t_result );

	if( $result == 0 ) {
		# Return 2 because that's what ADOdb/DataDict does when things happen properly
		return 2;
	}
}

// tidy_duplicate_id_history
function install_tidy_duplicate_id_history() {
	// Issues can not be duplicates of themselves [this was allowed in some old versions]
	$query = "DELETE FROM {bug_history} WHERE field_name='duplicate_id' AND bug_id=new_value";
	db_query( $query );

	$query = "SELECT bug_id, new_value FROM {bug_history} WHERE field_name='duplicate_id'";

	$t_result = db_query( $query );
	while( $t_row = db_fetch_array( $t_result ) ) {
		// there was a point in time we stored duplicate_id in history + added duplicate relationship
		// if the duplicate still exists, it's probably OK to delete the history record - on the basis that
		// there will be a relationship history record adding the duplicate.

		$query2 = "SELECT id FROM {bug_relationship} WHERE relationship_type=0 AND source_bug_id=%d AND destination_bug_id=%d";
		$t_result2 = db_query( $query2, array( $t_row['bug_id'], $t_row['new_value'] ) );
		$result = db_result( $t_result2 );
		if( $result > 0 ) {
			$query = "DELETE FROM {bug_history} WHERE field_name='duplicate_id' AND bug_id=%d and new_value=%d";
			db_query($query, array( $t_row['bug_id'], $t_row['new_value'] ) );
		}
	}

	// 2nd pass..
	$query = "SELECT * FROM {bug_history} WHERE field_name='duplicate_id' AND new_value=0";
	$t_result = db_query( $query ); // removal
	while( $t_row = db_fetch_array( $t_result ) ) {
		// look for duplicate_id's that got added then removed, and convert to relationship history for these

		$query2 = "SELECT * FROM {bug_history} WHERE field_name='duplicate_id' AND old_value=0"; // addition
		$t_result2 = db_query( $query2, array( $t_row['bug_id'], $t_row['new_value'] ) );
		$result = db_fetch_array( $t_result2 );
		if( !empty( $result ) ) {
			// insert relationship add record
			$query = "INSERT INTO {bug_history} (user_id, bug_id, field_name, old_value, new_value, type, date_modified)
						VALUES (%d,%d,%s,%s,%s,%d,%d) ";
			db_query($query, array( $result['user_id'], $result['bug_id'], '', 0, $result['new_value'], 18, $result['date_modified'] ) );

			// insert relationship delete record
			$query = "INSERT INTO {bug_history} (user_id, bug_id, field_name, old_value, new_value, type, date_modified)
						VALUES (%d,%d,%s,%s,%s,%d,%d) ";
			db_query($query, array( $t_row['user_id'], $t_row['bug_id'], '', 0, $t_row['old_value'], 19, $t_row['date_modified'] ) );

			// delete duplicate_id add record
			$query = "DELETE FROM {bug_history} WHERE field_name='duplicate_id' AND bug_id=%d and new_value=%d AND old_value=0";
			db_query($query, array( $result['bug_id'], $result['new_value'] ) );

			// delete duplicate_id delete record
			$query = "DELETE FROM {bug_history} WHERE field_name='duplicate_id' AND bug_id=%d and old_value=%d AND new_value=0";
			db_query($query, array( $t_row['bug_id'], $t_row['old_value'] ) );
		}
	}

	$query = "SELECT count(id) FROM {bug_history} WHERE field_name='duplicate_id'";
	$t_result = db_query( $query );
	$result = db_result( $t_result );

	if( $result == 0 ) {
		# Return 2 because that's what ADOdb/DataDict does when things happen properly
		return 2;
	}
}
