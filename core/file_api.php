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
 * File API
 *
 * @package CoreAPI
 * @subpackage FileAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses history_api.php
 * @uses project_api.php
 * @uses utility_api.php
 */

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'history_api.php' );
require_api( 'project_api.php' );
require_api( 'utility_api.php' );

$g_cache_file_count = array();

/**
 * Gets the filename without the bug id prefix.
 * @param string $p_filename filename
 * @return string
 */
function file_get_display_name( $p_filename ) {
	$t_array = explode( '-', $p_filename, 2 );

	# Check if it's a project document filename (doc-0000000-filename) or a bug attachment filename (0000000-filename).
	# For newer filenames, the filename in schema is correct. This is important to handle filenames with '-'s properly.
	$t_doc_match = '/^' . config_get( 'document_files_prefix' ) . '-\d{7}-/';
	$t_name = preg_split( $t_doc_match, $p_filename );
	if( isset( $t_name[1] ) ) {
		return $t_name[1];
	} else {
		$t_bug_match = '/^\d{7}-/';
		$t_name = preg_split( $t_bug_match, $p_filename );
		if( isset( $t_name[1] ) ) {
			return $t_name[1];
		} else {
			return $p_filename;
		}
	}
}

/**
 * Check the number of attachments a bug has (if any)
 * @param int $p_bug_id bug id
 * @return int
 */
function file_bug_attachment_count( $p_bug_id ) {
	global $g_cache_file_count;

	# First check if we have a cache hit
	if( isset( $g_cache_file_count[$p_bug_id] ) ) {
		return $g_cache_file_count[$p_bug_id];
	}

	# If there is no cache hit, check if there is anything in
	#   the cache. If the cache isn't empty and we didn't have
	#   a hit, then there are not attachments for this bug.
	if( count( $g_cache_file_count ) > 0 ) {
		return 0;
	}

	# Otherwise build the cache and return the attachment count
	#   for the given bug (if any).
	$t_query = "SELECT bug_id, COUNT(bug_id) AS attachments FROM {bug_file} GROUP BY bug_id";
	$t_result = db_query( $t_query );

	$t_file_count = 0;
	while( $t_row = db_fetch_array( $t_result ) ) {
		$g_cache_file_count[$t_row['bug_id']] = $t_row['attachments'];
		if( $p_bug_id == $t_row['bug_id'] ) {
			$t_file_count = $t_row['attachments'];
		}
	}

	# If no attachments are present, mark the cache to avoid
	#   repeated queries for this.
	if( count( $g_cache_file_count ) == 0 ) {
		$g_cache_file_count['_no_files_'] = -1;
	}

	return $t_file_count;
}

/**
 * Check if a specific bug has attachments
 * @param int $p_bug_id bug id
 * @return bool
 */
function file_bug_has_attachments( $p_bug_id ) {
	if( file_bug_attachment_count( $p_bug_id ) > 0 ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Check if the current user can view attachments for the specified bug.
 * @param int $p_bug_id bug id
 * @param int $p_uploader_user_id user id
 * @return bool
 */
function file_can_view_bug_attachments( $p_bug_id, $p_uploader_user_id = null ) {
	$t_uploaded_by_me = auth_get_current_user_id() === $p_uploader_user_id;
	$t_can_view = access_has_bug_level( config_get( 'view_attachments_threshold' ), $p_bug_id );
	$t_can_view = $t_can_view || ( $t_uploaded_by_me && config_get( 'allow_view_own_attachments' ) );
	return $t_can_view;
}

/**
 * Check if the current user can download attachments for the specified bug.
 * @param int $p_bug_id bug id
 * @param int $p_uploader_user_id user id
 * @return bool
 */
function file_can_download_bug_attachments( $p_bug_id, $p_uploader_user_id = null ) {
	$t_uploaded_by_me = auth_get_current_user_id() === $p_uploader_user_id;
	$t_can_download = access_has_bug_level( config_get( 'download_attachments_threshold' ), $p_bug_id );
	$t_can_download = $t_can_download || ( $t_uploaded_by_me && config_get( 'allow_download_own_attachments' ) );
	return $t_can_download;
}

/**
 * Check if the current user can delete attachments from the specified bug.
 * @param int $p_bug_id bug id
 * @param int $p_uploader_user_id user id
 * @return bool
 */
function file_can_delete_bug_attachments( $p_bug_id, $p_uploader_user_id = null ) {
	if( bug_is_readonly( $p_bug_id ) ) {
		return false;
	}
	$t_uploaded_by_me = auth_get_current_user_id() === $p_uploader_user_id;
	$t_can_delete = access_has_bug_level( config_get( 'delete_attachments_threshold' ), $p_bug_id );
	$t_can_delete = $t_can_delete || ( $t_uploaded_by_me && config_get( 'allow_delete_own_attachments' ) );
	return $t_can_delete;
}

/**
 * Get icon corresponding to the specified filename
 * returns an associative array with "url" and "alt" text.
 * @param string $p_display_filename filename
 * @return array
 */
function file_get_icon_url( $p_display_filename ) {
	$t_ext = mb_strtolower( pathinfo( $p_display_filename, PATHINFO_EXTENSION ) );

	if( $t_ext == '' ) {
		return array( 'url' => helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/fileicons/generic.png' ), 'alt' => $t_ext );
	}

	if( file_exists( 'themes/' . config_get( 'theme' ) . '/images/fileicons/' . $t_ext . '.png' ) ) {
		return array( 'url' => helper_mantis_url( 'themes/' . config_get( 'theme' ) . '/images/fileicons/' . $t_ext . '.png' ), 'alt' => $t_ext );
	} else {
		return array( 'url' => helper_mantis_url( 'img_ext.php?ext=' . $t_ext ), 'alt' => $t_ext );
	}
}

/**
 * Combines a path and a file name making sure that the separator exists.
 *
 * @param string $p_path The path.
 * @param string $p_filename The file name.
 * @return string The combined full path.
 */
function file_path_combine( $p_path, $p_filename ) {
	$t_path = rtrim($p_path, '/\\') . '/' . $p_filename;

	return $t_path;
}

/**
 * Normalizes the disk file path based on the following algorithm:
 * 1. If disk file exists, then return as is.
 * 2. If not, and a project path is available, then check with that, if exists return it.
 * 3. If not, then use default upload path, then check with that, if exists return it.
 * 4. If disk file does not include a path, then return expected path based on project path or default path.
 * 5. Otherwise return as is.
 *
 * @param string $p_diskfile  The disk file (full path or just filename).
 * @param integer $p_project_id The project id - shouldn't be 0 (ALL_PROJECTS).
 * @return string The normalized full path.
 */
function file_normalize_attachment_path( $p_diskfile, $p_project_id ) {
	if ( file_exists( $p_diskfile ) ) {
		return $p_diskfile;
	}

	$t_basename = basename( $p_diskfile );

	$t_expected_file_path = '';

	if ( $p_project_id != ALL_PROJECTS ) {
		$t_path = project_get_field( $p_project_id, 'file_path' );
		if ( !is_blank( $t_path ) ) {
			$t_diskfile = file_path_combine( $t_path, $t_basename );

			if ( file_exists( $t_diskfile ) ) {
				return $t_diskfile;
			}

			// if we don't find the file, then this is the path we want to return.
			$t_expected_file_path = $t_diskfile;
		}
	}

	$t_path = config_get( 'absolute_path_default_upload_folder' );
	if ( !is_blank( $t_path ) ) {
		$t_diskfile = file_path_combine( $t_path, $t_basename );

		if ( file_exists( $t_diskfile ) ) {
			return $t_diskfile;
		}

		// if the expected path not set to project directory, then set it to default directory.
		if ( is_blank( $t_expected_file_path ) ) {
			$t_expected_file_path = $t_diskfile;
		}
	}

	// if diskfile doesn't include a path, then use the expected filename.
	if ( ( strstr( $p_diskfile, '/' ) === false ||
	       strstr( $p_diskfile, '\\' ) === false ) &&
	     !is_blank( $t_expected_file_path ) ) {
	    return $t_expected_file_path;
	}

	// otherwise return as is.
	return $p_diskfile;
}

/**
 * Gets an array of attachments that are visible to the currently logged in user.
 * Each element of the array contains the following:
 * display_name - The attachment display name (i.e. file name dot extension)
 * size - The attachment size in bytes.
 * date_added - The date where the attachment was added.
 * can_download - true: logged in user has access to download the attachment, false: otherwise.
 * diskfile - The name of the file on disk.  Typically this is a hash without an extension.
 * download_url - The download URL for the attachment (only set if can_download is true).
 * exists - Applicable for DISK attachments.  true: file exists, otherwise false.
 * can_delete - The logged in user can delete the attachments.
 * preview - true: the attachment should be previewable, otherwise false.
 * type - Can be "image", "text" or empty for other types.
 * alt - The alternate text to be associated with the icon.
 * icon - array with icon information, contains 'url' and 'alt' elements.
 * @param int $p_bug_id bug id
 * @return array
 */
function file_get_visible_attachments( $p_bug_id ) {
	$t_attachment_rows = bug_get_attachments( $p_bug_id );
	$t_visible_attachments = array();

	$t_attachments_count = count( $t_attachment_rows );
	if( $t_attachments_count === 0 ) {
		return $t_visible_attachments;
	}

	$t_attachments = array();

	$t_preview_text_ext = config_get( 'preview_text_extensions' );
	$t_preview_image_ext = config_get( 'preview_image_extensions' );

	$image_previewed = false;
	for( $i = 0;$i < $t_attachments_count;$i++ ) {
		$t_row = $t_attachment_rows[$i];

		if ( !file_can_view_bug_attachments( $p_bug_id, (int)$t_row['user_id'] ) ) {
			continue;
		}

		$t_id = $t_row['id'];
		$t_filename = $t_row['filename'];
		$t_filesize = $t_row['filesize'];
		$t_diskfile = file_normalize_attachment_path( $t_row['diskfile'], bug_get_field( $p_bug_id, 'project_id' ) );
		$t_date_added = $t_row['date_added'];
		$t_downloaded = $t_row['downloaded'];

		$t_attachment = array();
		$t_attachment['id'] = $t_id;
		$t_attachment['display_name'] = file_get_display_name( $t_filename );
		$t_attachment['size'] = $t_filesize;
		$t_attachment['date_added'] = $t_date_added;
		$t_attachment['diskfile'] = $t_diskfile;
		$t_attachment['downloaded'] = $t_downloaded;

		$t_attachment['can_download'] = file_can_download_bug_attachments( $p_bug_id, (int)$t_row['user_id'] );
		$t_attachment['can_delete'] = file_can_delete_bug_attachments( $p_bug_id, (int)$t_row['user_id'] );

		if( $t_attachment['can_download'] ) {
			$t_attachment['download_url'] = "file_download.php?file_id=$t_id&type=bug";
		}

		if( $image_previewed ) {
			$image_previewed = false;
		}

		$t_attachment['exists'] = config_get( 'file_upload_method' ) != DISK || file_exists( $t_diskfile );
		$t_attachment['icon'] = file_get_icon_url( $t_attachment['display_name'] );

		$t_attachment['preview'] = false;
		$t_attachment['type'] = '';

		$t_ext = strtolower( pathinfo( $t_attachment['display_name'], PATHINFO_EXTENSION ) );
		$t_attachment['alt'] = $t_ext;

		if ( $t_attachment['exists'] && $t_attachment['can_download'] && $t_filesize != 0 && $t_filesize <= config_get( 'preview_attachments_inline_max_size' ) ) {
			if ( in_array( $t_ext, $t_preview_text_ext, true ) ) {
				$t_attachment['preview'] = true;
				$t_attachment['type'] = 'text';
			} else if ( in_array( $t_ext, $t_preview_image_ext, true ) ) {
				$t_attachment['preview'] = true;
				$t_attachment['type'] = 'image';
			}
		}

		$t_attachments[] = $t_attachment;
	}

	return $t_attachments;
}

/**
 * delete all files that are associated with the given bug
 * @param int $p_bug_id bug id
 * @return bool
 */
function file_delete_attachments( $p_bug_id ) {
	$t_method = config_get( 'file_upload_method' );

	# Delete files from disk
	$t_query = "SELECT diskfile, filename FROM {bug_file} WHERE bug_id=%d";
	$t_result = db_query( $t_query, array( $p_bug_id ) );

	$t_files = array();
	while ( $t_row = db_fetch_array( $t_result ) ) {
		$t_files[] = $t_row;
	}

	$t_file_count = count( $t_files );
	if ( $t_file_count === 0 )
		return true;

	if ( ( $t_method == DISK ) || ( $t_method == FTP ) ) {

		# there may be more than one file
		$t_ftp = 0;
		if ( FTP == $t_method )
			$t_ftp = file_ftp_connect();

		for ( $i = 0; $i < $t_file_count; $i++ ) {
			$t_file = $t_files[$i];

			$t_local_diskfile = file_normalize_attachment_path( $t_file['diskfile'], bug_get_field( $p_bug_id, 'project_id' ) );
			file_delete_local( $t_local_diskfile );

			if ( $t_method == FTP )
				file_ftp_delete( $t_ftp, $t_file['diskfile'] );
		}

		if ( $t_method == FTP )
			file_ftp_disconnect( $t_ftp );
	}

	# Delete the corresponding db records
	$t_query = "DELETE FROM {bug_file} WHERE bug_id=%d";
	db_query( $t_query, array( $p_bug_id ) );

	# db_query errors on failure so:
	return true;
}

/**
 * Delete files by project
 * @param int $p_project_id project id
 */
function file_delete_project_files( $p_project_id ) {
	$t_method = config_get( 'file_upload_method' );

	# Delete the file physically (if stored via DISK or FTP)
	if ( ( $t_method == DISK ) || ( $t_method == FTP ) ) {

		# Delete files from disk
		$t_query = 'SELECT diskfile, filename FROM {project_file} WHERE project_id=%d';
		$t_result = db_query( $t_query, array( (int) $p_project_id ) );

		$t_files = array();
		while ( $t_row = db_fetch_array( $t_result ) ) {
			$t_files[] = $t_row;
		}

		$t_ftp = 0;
		if ( $t_method == FTP )
			$t_ftp = file_ftp_connect();

		$t_file_count = count( $t_files );
		for ( $i = 0; $i < $t_file_count; $i++ ) {
			$t_file = $t_files[$i];

			$t_local_diskfile = file_normalize_attachment_path( $t_file['diskfile'], $p_project_id );
			file_delete_local( $t_local_diskfile );

			if ( $t_method == FTP )
				file_ftp_delete( $t_ftp, $t_file['diskfile'] );
		}

		if ( $t_method == FTP )
			file_ftp_disconnect( $t_ftp );
	}

	# Delete the corresponding db records
	$t_query = 'DELETE FROM {project_file} WHERE project_id=%d';
	db_query( $t_query, array( (int) $p_project_id ) );
}

/**
 * Delete all cached files that are older than configured number of days.
 */
function file_ftp_cache_cleanup() {
}

/**
 * Connect to ftp server using configured server address, user name, and password.
 * @throws MantisBT\Exception\FTP\ConnectionFailed
 * @return int
 */
function file_ftp_connect() {
	$conn_id = ftp_connect( config_get( 'file_upload_ftp_server' ) );
	$login_result = ftp_login( $conn_id, config_get( 'file_upload_ftp_user' ), config_get( 'file_upload_ftp_pass' ) );

	if(( !$conn_id ) || ( !$login_result ) ) {
		throw new MantisBT\Exception\FTP\ConnectionFailed();
	}

	return $conn_id;
}

/**
 * Put a file to the ftp server.
 * @param int $p_conn_id Resource id
 * @param string $p_remote_filename remote filename
 * @param string $p_local_filename local filename
 */
function file_ftp_put( $p_conn_id, $p_remote_filename, $p_local_filename ) {
	helper_begin_long_process();
	$upload = ftp_put( $p_conn_id, $p_remote_filename, $p_local_filename, FTP_BINARY );
}

/**
 * Get a file from the ftp server.
 * @param int $p_conn_id Resource id
 * @param string $p_local_filename local filename
 * @param string $p_remote_filename remote filename
 */
function file_ftp_get( $p_conn_id, $p_local_filename, $p_remote_filename ) {
	helper_begin_long_process();
	$download = ftp_get( $p_conn_id, $p_local_filename, $p_remote_filename, FTP_BINARY );
}

/**
 * Delete a file from the ftp server
 * @param int $p_conn_id Resource id
 * @param string $p_filename filename
 */
function file_ftp_delete( $p_conn_id, $p_filename ) {
	@ftp_delete( $p_conn_id, $p_filename );
}

/**
 * Disconnect from the ftp server
 * @param int $p_conn_id Resource id
 */
function file_ftp_disconnect( $p_conn_id ) {
	ftp_quit( $p_conn_id );
}

/**
 * Delete a local file even if it is read-only.
 * @param string $p_filename
 */
function file_delete_local( $p_filename ) {
	if( file_exists( $p_filename ) ) {
		chmod( $p_filename, 0775 );
		unlink( $p_filename );
	}
}

/**
 * Return the specified field value
 * @param int $p_file_id file id
 * @param string $p_field_name field name
 * @param string $p_table table name
 * @return string
 * @throws MantisBT\Exception\Database\FieldNotFound
 */
function file_get_field( $p_file_id, $p_field_name, $p_table = 'bug' ) {
	$t_bug_file_table = '{' . $p_table . '_file}';

	if( !db_field_exists( $p_field_name, $t_bug_file_table ) ) {
		throw new MantisBT\Exception\Database\FieldNotFound( $p_field_name );
	}

	$query = "SELECT $p_field_name FROM $t_bug_file_table WHERE id=%d";
	$result = db_query( $query, array( (int) $p_file_id ), 1 );

	return db_result( $result );
}

/**
 * Delete File
 * @param int $p_file_id file id
 * @param string $p_table table id
 * @return bool
 */
function file_delete( $p_file_id, $p_table = 'bug' ) {
	$t_upload_method = config_get( 'file_upload_method' );

	$c_file_id = (int)$p_file_id;
	$t_filename = file_get_field( $p_file_id, 'filename', $p_table );
	$t_diskfile = file_get_field( $p_file_id, 'diskfile', $p_table );

	if ( $p_table == 'bug' ) {
		$t_bug_id = file_get_field( $p_file_id, 'bug_id', $p_table );
		$t_project_id = bug_get_field( $t_bug_id, 'project_id' );
	} else {
		$t_project_id = file_get_field( $p_file_id, 'project_id', $p_table );
	}

	if(( DISK == $t_upload_method ) || ( FTP == $t_upload_method ) ) {
		if( FTP == $t_upload_method ) {
			$ftp = file_ftp_connect();
			file_ftp_delete( $ftp, $t_diskfile );
			file_ftp_disconnect( $ftp );
		}

		$t_local_disk_file = file_normalize_attachment_path( $t_diskfile, $t_project_id );
		if ( file_exists( $t_local_disk_file ) ) {
			file_delete_local( $t_local_disk_file );
		}
	}

	if( 'bug' == $p_table ) {
		# log file deletion
		history_log_event_special( $t_bug_id, FILE_DELETED, file_get_display_name( $t_filename ) );
	}

	$t_query = 'DELETE FROM {' . $p_table . '_file} WHERE id=%d';
	db_query( $t_query, array( $c_file_id ) );
	return true;
}

/**
 * File type check
 * @param string $p_file_name filename
 * @return bool
 */
function file_type_check( $p_file_name ) {
	$t_allowed_files = config_get( 'allowed_files' );
	$t_disallowed_files = config_get( 'disallowed_files' );;

	# grab extension
	$t_extension = pathinfo( $p_file_name, PATHINFO_EXTENSION );

	# check against disallowed files
	$t_disallowed_arr = explode( ',', $t_disallowed_files );
	foreach( $t_disallowed_arr as $t_val ) {
		if( 0 == strcasecmp( $t_val, $t_extension ) ) {
			return false;
		}
	}

	# if the allowed list is note populated then the file must be allowed
	if( is_blank( $t_allowed_files ) ) {
		return true;
	}

	# check against allowed files
	$t_allowed_arr = explode( ',', $t_allowed_files );
	foreach( $t_allowed_arr as $t_val ) {
		if( 0 == strcasecmp( $t_val, $t_extension ) ) {
			return true;
		}
	}

	return false;
}

/**
 * clean file name by removing sensitive characters and replacing them with underscores
 * @param string $p_filename filename
 * @return string
 */
function file_clean_name( $p_filename ) {
	return preg_replace( '/[\/*?"<>|\\ :&]/', "_", $p_filename );
}

/**
 * Generate a string to use as the identifier for the file
 * It is not guaranteed to be unique and should be checked
 * The string returned should be 32 characters in length
 * @param string $p_seed
 * @return string
 */
function file_generate_name( $p_seed ) {
	return md5( $p_seed . time() );
}

/**
 * Generate a UNIQUE string to use as the identifier for the file
 * The string returned should be 64 characters in length
 * @param string $p_seed seed
 * @param string $p_filepath filepath
 * @return string
 */
function file_generate_unique_name( $p_seed, $p_filepath ) {
	do {
		$t_string = file_generate_name( $p_seed );
	}
	while( !diskfile_is_name_unique( $t_string, $p_filepath ) );

	return $t_string;
}

/**
 * Return true if the diskfile name identifier is unique, false otherwise
 * @param string $p_name name
 * @param string $p_filepath file path
 * @return bool
 */
function diskfile_is_name_unique( $p_name, $p_filepath ) {
	$c_name = $p_filepath . $p_name;

	$query = 'SELECT COUNT(*) FROM {bug_file} WHERE diskfile=%s';
	$result = db_query( $query, array( $c_name ) );
	$t_count = db_result( $result );

	if( $t_count > 0 ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Return true if the file name identifier is unique, false otherwise
 * @param string $p_name name
 * @param int $p_bug_id bug id
 * @return bool
 */
function file_is_name_unique( $p_name, $p_bug_id ) {
	$query = "SELECT COUNT(*) FROM {bug_file} WHERE filename=%s AND bug_id=%d";
	$result = db_query( $query, array( $p_name, $p_bug_id ) );
	$t_count = db_result( $result );

	if( $t_count > 0 ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Add a file to the system using the configured storage method
 *
 * @param integer $p_bug_id the bug id
 * @param array $p_file the uploaded file info, as retrieved from gpc_get_file()
 * @param string $p_table table ('bug' or 'doc')
 * @param string $p_title file title
 * @param string $p_desc file description
 * @param int $p_user_id user id
 * @param int $p_date_added date added
 * @param bool $p_skip_bug_update skip bug last modification update (useful when importing bug attachments)
 * @throws MantisBT\Exception\UnknownException
 * @throws MantisBT\Exception\File\FileDuplicate
 * @throws MantisBT\Exception\File\FileMoveFailed
 * @throws MantisBT\Exception\File\FileTypeNotAllowed
 * @throws MantisBT\Exception\File\FileNoUpload
 * @throws MantisBT\Exception\File\FileTooBig
 */
function file_add( $p_bug_id, $p_file, $p_table = 'bug', $p_title = '', $p_desc = '', $p_user_id = null, $p_date_added = 0, $p_skip_bug_update = false ) {

	file_ensure_uploaded( $p_file );
	$t_file_name = $p_file['name'];
	$t_tmp_file = $p_file['tmp_name'];
	$c_date_added = $p_date_added <= 0 ? db_now() : (int)$p_date_added;

	if( !file_type_check( $t_file_name ) ) {
		throw new MantisBT\Exception\File\FileTypeNotAllowed();
	}

	if( !file_is_name_unique( $t_file_name, $p_bug_id ) ) {
		throw new MantisBT\Exception\File\FileDuplicate();
	}

	if( 'bug' == $p_table ) {
		$t_project_id = bug_get_field( $p_bug_id, 'project_id' );
		$t_bug_id = bug_format_id( $p_bug_id );
	} else {
		$t_project_id = helper_get_current_project();
		$t_bug_id = 0;
	}

	if( $p_user_id === null ) {
		$c_user_id = auth_get_current_user_id();
	} else {
		$c_user_id = (int)$p_user_id;
	}

	# prepare variables for insertion
	$c_bug_id = (int)$p_bug_id;
	$c_project_id = (int)$t_project_id;
	$c_file_type = $p_file['type'];
	$c_title = $p_title;
	$c_desc = $p_desc;

	if( $t_project_id == ALL_PROJECTS ) {
		$t_file_path = config_get( 'absolute_path_default_upload_folder' );
	} else {
		$t_file_path = project_get_field( $t_project_id, 'file_path' );
		if( $t_file_path == '' ) {
			$t_file_path = config_get( 'absolute_path_default_upload_folder' );
		}
	}
	$c_file_path = $t_file_path;
	$c_new_file_name = $t_file_name;

	$t_file_hash = ( 'bug' == $p_table ) ? $t_bug_id : config_get( 'document_files_prefix' ) . '-' . $t_project_id;
	$t_unique_name = file_generate_unique_name( $t_file_hash . '-' . $t_file_name, $t_file_path );
	$t_disk_file_name = $t_file_path . $t_unique_name;
	$c_unique_name = $t_unique_name;

	$t_file_size = filesize( $t_tmp_file );
	if( 0 == $t_file_size ) {
		throw new MantisBT\Exception\File\FileNoUpload();
	}
	$t_max_file_size = (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );
	if( $t_file_size > $t_max_file_size ) {
		throw new MantisBT\Exception\File\FileTooBig();
	}
	$c_file_size = (int)$t_file_size;

	$t_method = config_get( 'file_upload_method' );

	switch( $t_method ) {
		case FTP:
		case DISK:
			file_ensure_valid_upload_path( $t_file_path );

			if( !file_exists( $t_disk_file_name ) ) {
				if( FTP == $t_method ) {
					$conn_id = file_ftp_connect();
					file_ftp_put( $conn_id, $t_disk_file_name, $t_tmp_file );
					file_ftp_disconnect( $conn_id );
				}

				if( !move_uploaded_file( $t_tmp_file, $t_disk_file_name ) ) {
					throw new MantisBT\Exception\File\FileMoveFailed();
				}

				chmod( $t_disk_file_name, config_get( 'attachments_file_permissions' ) );

				$c_content = "''";
			} else {
				throw new MantisBT\Exception\File\FileDuplicate();
			}
			break;
		case DATABASE:
			$c_content = fread( fopen( $t_tmp_file, 'rb' ), $t_file_size );
			break;
		default:
			throw new MantisBT\Exception\UnknownException();
	}

	$c_id = ( 'bug' == $p_table ) ? $c_bug_id : $c_project_id;

	$query = "INSERT INTO {" . $p_table . "_file}
						(" . $p_table . "_id, title, description, diskfile, filename, folder, filesize, file_type, date_added, content, user_id)
					  VALUES
						(%d,%s,%s,%s,%s, %s, %d, %s, %d, %b, %d)";
	db_query( $query, array($c_id, $c_title, $c_desc, $c_unique_name, $c_new_file_name, $c_file_path, $c_file_size, $c_file_type, $c_date_added, $c_content, $c_user_id ) );

	if( 'bug' == $p_table ) {
		# updated the last_updated date
		if ( !$p_skip_bug_update ) {
			$result = bug_update_date( $p_bug_id );
		}

		# log new bug
		history_log_event_special( $p_bug_id, FILE_ADDED, $t_file_name );
	}
}

/**
 * Return true if file uploading is enabled (in our config and PHP's), false otherwise
 */
function file_is_uploading_enabled() {
	if( ini_get_bool( 'file_uploads' ) && ( ON == config_get( 'allow_file_upload' ) ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Check if the user can upload files for this project
 * return true if they can, false otherwise
 * the project defaults to the current project and the user to the current user
 * @param int $p_project_id project id
 * @param int $p_user_id user id
 * @return bool
 */
function file_allow_project_upload( $p_project_id = null, $p_user_id = null ) {
	if( null === $p_project_id ) {
		$p_project_id = helper_get_current_project();
	}
	if( null === $p_user_id ) {
		$p_user_id = auth_get_current_user_id();
	}
	return( file_is_uploading_enabled() && ( access_has_project_level( config_get( 'upload_project_file_threshold' ), $p_project_id, $p_user_id ) ) );
}

/**
 * Check if the user can upload files for this bug
 *  return true if they can, false otherwise
 *  the user defaults to the current user
 *
 *  if the bug null (the default) we answer whether the user can
 *   upload a file to a new bug in the current project
 * @param MantisBug $p_bug Bug object
 * @param int $p_user_id user id
 * @return bool
 */
function file_allow_bug_upload( $p_bug = null, $p_user_id = null ) {
	if( null === $p_user_id ) {
		$p_user_id = auth_get_current_user_id();
	}

	# If uploads are disbled just return false
	if( !file_is_uploading_enabled() ) {
		return false;
	}

	if( null === $p_bug ) {
		# new bug
		$t_project_id = helper_get_current_project();

		# the user must be the reporter if they're reporting a new bug
		$t_reporter = true;
	} else {
		# existing bug
		$t_project_id = bug_get_field( $p_bug->id, 'project_id' );

		# check if the user is the reporter of the bug
		$t_reporter = bug_is_user_reporter( $p_bug->id, $p_user_id );
	}

	if( $t_reporter && ( ON == config_get( 'allow_reporter_upload' ) ) ) {
		return true;
	}

	# Check the access level against the config setting
	return access_has_project_level( config_get( 'upload_bug_file_threshold' ), $t_project_id, $p_user_id );
}

/**
 * checks whether the specified upload path exists and is writable
 * @param string $p_upload_path upload path
 * @throws MantisBT\Exception\File\InvalidUploadPath
 */
function file_ensure_valid_upload_path( $p_upload_path ) {
	if( !file_exists( $p_upload_path ) || !is_dir( $p_upload_path ) || !is_writable( $p_upload_path ) || !is_readable( $p_upload_path ) ) {
		throw new MantisBT\Exception\File\InvalidUploadPath();
	}
}

/**
 * Ensure a file was uploaded
 *
 * This function perform various checks for determining if the upload was successful
 *
 * @param array $p_file the uploaded file info, as retrieved from gpc_get_file()
 * @throws MantisBT\Exception\File\FileNoUpload
 * @throws MantisBT\Exception\File\FileTooBig
 * @throws MantisBT\Exception\File\FileUploadFailed
 */
function file_ensure_uploaded( $p_file ) {
	switch( $p_file['error'] ) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			throw new MantisBT\Exception\File\FileTooBig();
			break;
		case UPLOAD_ERR_PARTIAL:
		case UPLOAD_ERR_NO_FILE:
			throw new MantisBT\Exception\File\FileNoUpload();
			break;
		default:
			break;
	}

	if(( '' == $p_file['tmp_name'] ) || ( '' == $p_file['name'] ) ) {
		throw new MantisBT\Exception\File\FileNoUpload();
	}
	if( !is_readable( $p_file['tmp_name'] ) ) {
		throw new MantisBT\Exception\File\FileUploadFailed();
	}
}

/**
 * Get file content
 *
 * @param int $p_file_id file id
 * @param string $p_type file type (either 'bug' or 'doc')
 * @return array|bool array containing file type and content or false on failure to retrieve file
 */
function file_get_content( $p_file_id, $p_type = 'bug' ) {
	# we handle the case where the file is attached to a bug
	# or attached to a project as a project doc.
	switch ( $p_type ) {
		case 'bug':
			$query = "SELECT * FROM {bug_file} WHERE id=%d";
			break;
		case 'doc':
			$query = "SELECT * FROM {project_file} WHERE id=%d";
			break;
		default:
			return false;
	}
	$result = db_query( $query, array( $p_file_id ) );
	$row = db_fetch_array( $result );

	if ( $f_type == 'bug' ) {
		$t_project_id = bug_get_field( $row['bug_id'], 'project_id' );
	} else {
		$t_project_id = $row['bug_id'];
	}

	# If finfo is available (always true for PHP >= 5.3.0) we can use it to determine the MIME type of files
	$finfo_available = false;
	if ( class_exists( 'finfo' ) ) {
		$t_info_file = config_get( 'fileinfo_magic_db_file' );

		if ( is_blank( $t_info_file ) ) {
			$finfo = new finfo( FILEINFO_MIME );
		} else {
			$finfo = new finfo( FILEINFO_MIME, $t_info_file );
		}

		if ( $finfo ) {
			$finfo_available = true;
		}
	}

	$t_content_type = $row['file_type'];

	switch ( config_get( 'file_upload_method' ) ) {
		case DISK:
			$t_local_disk_file = file_normalize_attachment_path( $row['diskfile'], $t_project_id );

			if ( file_exists( $t_local_disk_file ) ) {
				if ( $finfo_available ) {
					$t_file_info_type = $finfo->file( $t_local_disk_file );

					if ( $t_file_info_type !== false ) {
						$t_content_type = $t_file_info_type;
					}
				}
				return array( 'type' => $t_content_type, 'content' => file_get_contents( $t_local_disk_file ) );
			}
			return false;
			break;
		case FTP:
			$t_local_disk_file = file_normalize_attachment_path( $row['diskfile'], $t_project_id );

			if ( !file_exists( $t_local_disk_file ) ) {
				$ftp = file_ftp_connect();
				file_ftp_get ( $ftp, $t_local_disk_file, $row['diskfile'] );
				file_ftp_disconnect( $ftp );
			}

			if ( $finfo_available ) {
				$t_file_info_type = $finfo->file( $t_local_disk_file );

				if ( $t_file_info_type !== false ) {
					$t_content_type = $t_file_info_type;
				}
			}
			return array( 'type' => $t_content_type, 'content' => file_get_contents( $t_local_disk_file ) );
			break;
		default:
			if ( $finfo_available ) {
				$t_file_info_type = $finfo->buffer( $row['content'] );

				if ( $t_file_info_type !== false ) {
					$t_content_type = $t_file_info_type;
				}
			}
			return array( 'type' => $t_content_type, 'content' => $row['content'] );
			break;
	}
}

/**
 * Move any attachments as needed when a bug is moved from project to project.
 *
 * @param int $p_bug_id ID of bug containing attachments to be moved
 * @param int $p_project_id_to destination project ID for the bug
 * @return null
 * @throws MantisBT\Exception\File\FileMoveFailed
 * @throws MantisBT\Exception\File\FileDuplicate
 */
function file_move_bug_attachments( $p_bug_id, $p_project_id_to ) {
	$t_project_id_from = bug_get_field( $p_bug_id, 'project_id' );
	if ( $t_project_id_from == $p_project_id_to ) {
		return;
	}

	$t_method = config_get( 'file_upload_method' );
	if ( $t_method != DISK ) {
		return;
	}

	if ( !file_bug_has_attachments( $p_bug_id ) ) {
		return;
	}

	$t_path_from = project_get_field( $t_project_id_from, 'file_path' );
	if ( is_blank( $t_path_from ) ) {
		$t_path_from = config_get( 'absolute_path_default_upload_folder', null, null, $t_project_id_from );
	}
	file_ensure_valid_upload_path( $t_path_from );
	$t_path_to = project_get_field( $p_project_id_to, 'file_path' );
	if ( is_blank( $t_path_to ) ) {
		$t_path_to = config_get( 'absolute_path_default_upload_folder', null, null, $p_project_id_to );
	}
	file_ensure_valid_upload_path( $t_path_to );
	if ( $t_path_from == $t_path_to ) {
		return;
	}

	# Initialize the update query to update a single row
	$c_bug_id = (int)$p_bug_id;
	$query_disk_attachment_update = "UPDATE {bug_file} SET folder=%s WHERE bug_id=%d AND id =%d";

	$t_attachment_rows = bug_get_attachments( $p_bug_id );
	$t_attachments_count = count( $t_attachment_rows );
	for ( $i = 0; $i < $t_attachments_count; $i++ ) {
		$t_row = $t_attachment_rows[$i];
		$t_basename = basename( $t_row['diskfile'] );

		$t_disk_file_name_from = file_path_combine( $t_path_from, $t_basename );
		$t_disk_file_name_to = file_path_combine( $t_path_to, $t_basename );

		if ( !file_exists( $t_disk_file_name_to ) ) {
			chmod( $t_disk_file_name_from, 0775 );
			if ( !rename( $t_disk_file_name_from, $t_disk_file_name_to ) ) {
				if ( !copy( $t_disk_file_name_from, $t_disk_file_name_to ) ) {
					throw new MantisBT\Exception\File\FileMoveFailed();
				}
				file_delete_local( $t_disk_file_name_from );
			}
			chmod( $t_disk_file_name_to, config_get( 'attachments_file_permissions' ) );
			db_query( $query_disk_attachment_update, array( $t_path_to, $c_bug_id, $t_row['id'] ) );
		} else {
			throw new MantisBT\Exception\File\FileDuplicate();
		}
	}
}
