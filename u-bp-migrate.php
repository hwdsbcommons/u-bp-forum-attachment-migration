<?php
/*
Plugin Name: u BP Forum Attachment Migration to bbPress
Description: Converts u BP Forum Attachment post data over to bbPress.
Version: 0.1
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
License: GPLv2 or later
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'bbp_includes', array( 'u_BP_Migrate', 'init' ) );

/**
 * u BP Forum Attachment Migration to bbPress.
 *
 * Handles conversion of older legacy forum attachment data for u BP Forum
 * Attachments over to the bbPress plugin.
 *
 * This plugin is only meant to bring attachment data over and display it.
 * This plugin does not handle uploads.
 */
class u_BP_Migrate {
	/**
	 * Internal ID for the older u BP Forum Attachments plugin.
	 */
	public $id = 'ubpfattach';

	/**
	 * Meta key used by the older u BP Forum Attachments plugin.
	 */
	public $meta_key = 'ubpfattach_attachments';

	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		/* constants **************************************************I**/
		$this->constants();

		/* hooks **************************************************I******/

		// Set up admin area if in the WP dashboard
		if ( is_admin() ) {
			add_filter( 'bbp_repair_list', array( $this, 'register_tool' ) );
		}

		// show attachment block
		add_action( 'bbp_theme_after_reply_content', array( $this, 'show_attachment_block' ) );

		// do downloads
		if ( ! class_exists( 'UBPForumAttachment' ) ) {
			add_action( 'bp_init', array( $this, 'do_download' ), 1 );
		}
	}

	/**
	 * Set up constants.
	 */
	protected function constants() {
		// constants
		if ( ! defined( 'U_BP_MIGRATE_BB_PREFIX' ) ) {
			define( 'U_BP_MIGRATE_BB_PREFIX', 'wp_bb_' );
		}

	}

	/**
	 * Register our tool with bbPress' repair tool list.
	 *
	 * @param array $retval Array of bbP repair tools.
	 * @return array
	 */
	public function register_tool( $retval ) {
		$retval[99] = array(
			'u-bp-forum-attachment',
			__( 'Repair u BuddyPress Forum Attachment postdata', 'u-bp-migrate' ),
			array( $this, 'convert' )
		);

		return $retval;
	}

	/**
	 * Callback to convert u BP Forum Attachments legacy data to bbPress data.
	 */
	public function convert() {
		global $wpdb;

		$bb_meta_table = constant( 'U_BP_MIGRATE_BB_PREFIX' ) . 'meta';
		$statement     = __( 'Repairing u BuddyPress Forum Attachment postdata&hellip; %s', 'u-bp-migrate' );

		// defaults
		$changed   = 0;
		$offset    = 0;
		$number    = 100;

		// Get old bbP data; limited to 100 entries at a time
		while ( $bbp_old_data = $wpdb->get_results(
				$wpdb->prepare( "
					SELECT post_id, meta_key, meta_value
					FROM $wpdb->postmeta
					WHERE meta_key = '_bbp_post_id'
						OR meta_key = '_bbp_old_topic_id'
					LIMIT %d,%d
					",
						$offset,
						$number
				 )
			)
		) {

			// get object IDs
			$object_ids = array_keys( array_flip(
				wp_list_pluck( $bbp_old_data, 'meta_value' )
			) );
			$object_ids = implode( ',', $object_ids );

			// get attachment data from legacy forums
			$a_data = $wpdb->get_results(
				$wpdb->prepare( "
					SELECT object_type, object_id, meta_value
					FROM {$bb_meta_table}
					WHERE meta_key = %s
						AND object_id IN ({$object_ids})
					",
						$this->meta_key
				 )
			);

			// reformat attachment data
			$attachment_data = array();
			foreach ( $a_data as $data ) {
				$attachment_data[ $data->object_type . '_' . $data->object_id ] = $data->meta_value;
			}

			unset( $a_data, $data );

			foreach ( $bbp_old_data as $post ) {
				switch ( $post->meta_key ) {
					// replies
					case '_bbp_post_id' :
						if ( ! empty( $attachment_data[ 'bb_post_' . $post->meta_value ] ) ) {
							// add legacy post data to new bbP post
							if ( add_post_meta(
								$post->post_id,
								$this->meta_key,
								$attachment_data[ 'bb_post_' . $post->meta_value ],
								true
							) ) {

								// Keep a count to display at the end
								++$changed;
							}
						}

						break;

					// topics
					case '_bbp_old_topic_id' :
						if ( ! empty( $attachment_data[ 'bb_topic_' . $post->meta_value ] ) ) {
							// add legacy post data to new bbP post
							if ( add_post_meta(
								$post->post_id,
								$this->meta_key,
								$attachment_data[ 'bb_topic_' . $post->meta_value ],
								true
							) ) {

								// Keep a count to display at the end
								++$changed;
							}
						}

						break;
				}
			}

			// Bump the offset for the next query iteration
			$offset = $offset + $number;
		}


		$result = sprintf( __( 'Complete! %s posts updated.', 'u-bp-migrate' ), bbp_number_format( $changed ) );
		return array( 0, sprintf( $statement, $result ) );
	}

	/**
	 * Shows u BP Forum Attachments if they exist underneath each bbPress post.
	 */
	public function show_attachment_block() {
		$attachments = get_post_meta( get_the_ID(), $this->meta_key, true );

		if ( empty( $attachments ) ) {
			return;
		}

		$attachments = json_decode( $attachments );

		/* following is mostly a copy-paste of the create_filebox() method from u BP */

		$ret = '<div class="clear"></div>';
		$ret .= '<table class="'.$this->id.'-attachments '.$this->id.'-filelist">';
		$ret .= '<strong>Legacy Attachments</strong>';

		$i = 0;
		foreach($attachments as $attachment){
			$x = explode('.', $attachment->filename);
			$ext = end($x);
			$is_image = ( $ext=='jpg' || $ext=='jpeg' || $ext=='gif' || $ext=='png' ) ? true : false;

			$download_url = add_query_arg(array(
				$this->id.'_download' => 'true',
				'_wpnonce' => wp_create_nonce($this->id.'_nonce'),
				'filename' => urlencode($attachment->filename),
			), '');

			$thumbnail = $attachment->url;
			if( !empty($attachment->thumbnail_filename) AND file_exists($this->get_upload_dir_path().$attachment->thumbnail_filename) ){
				$thumbnail = $this->get_upload_dir_url().$attachment->thumbnail_filename;
			}

			$even = ($i++%2==0) ? 'even' : '';

			$ret .= '<tr class="'.$even.'">';
			if( $is_image ) {
				$ret .= '<td class="thumb"><img src="'.$thumbnail.'" class="thumb">'.$t.'</td>';
			}else{
				$ret .= '<td class="thumb empty"></td>';
			}
			$ret .= '<td class="filename">'.$attachment->filename.'</td>';
			$ret .= '<td class="links"><a href="'.$download_url.'">'.__('Download', $this->id).'</a>';
			if( $is_image ) {
				$ret .= ' <span class="pipe"> | </span> ';
				$ret .= '<a href="'.$attachment->url.'" target="_blank" title="'.__('Open Image in New Window', $this->id).'">'.__('View', $this->id).'</a>';
			}
			$ret .= '</td></tr>';
		}
		$ret .= '</table>';

		echo $ret;
	}

	/* HELPER METHODS - copied from u BP */

	public function get_upload_dir_path(){
		$opts = get_option($this->id);
		$wp_upload_dir = wp_upload_dir();
		return $wp_upload_dir['basedir'].'/'.$opts['upload_dir'].'/';
	}

	public function get_upload_dir_url(){
		$opts = get_option($this->id);
		$wp_upload_dir = wp_upload_dir();
		return $wp_upload_dir['baseurl'].'/'.$opts['upload_dir'].'/';
	}

	/* THE FOLLOWING METHODS ARE USED ONLY IF U BP FORUM ATTACHMENT ISN'T ACTIVE
	 *
	 * They are directly copied over from the older plugin to maintain functionality.
	 */

	public function do_download() {
		if( isset($_GET[$this->id.'_download']) AND ($_GET[$this->id.'_download']==='true') ){
			if ( !wp_verify_nonce($_GET['_wpnonce'], $this->id.'_nonce') )
				wp_die(__('Your nonce did not verify.', $this->id));

			$filename = basename($_GET['filename']);
			$filepath = $this->get_upload_dir_path().$filename;

			if( empty($filename) || !file_exists($filepath) ) {
				wp_die(__('File does not exist', $this->id));
			}else{
				$this->_force_download($filename, file_get_contents($filepath));
			}

			exit;
		}
	}

	protected function _force_download($filename = '', $data = ''){
		if ($filename == '' OR $data == '')
			return false;

		if (FALSE === strpos($filename, '.'))
			return false;

		$rs = $this->_check_filetype($filename);
		$mime_type = $rs['type'];

		if( empty($mime_type) ){
			wp_die(__('Invalid file type'));

		}else{
			if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE){
				header('Content-Type: "'.$mime_type.'"');
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header("Content-Transfer-Encoding: binary");
				header('Pragma: public');
				header("Content-Length: ".strlen($data));
			}else{
				header('Content-Type: "'.$mime_type.'"');
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header("Content-Transfer-Encoding: binary");
				header('Expires: 0');
				header('Pragma: no-cache');
				header("Content-Length: ".strlen($data));
			}
			exit($data);
		}
	}

	protected function _check_filetype( $filename ) {
		$mimes = $this->_upload_mimes();
		$type = false;
		$ext = false;
		foreach ( $mimes as $ext_preg => $mime_match ) {
			$ext_preg = '!\.(' . $ext_preg . ')$!i';
			if ( preg_match( $ext_preg, $filename, $ext_matches ) ) {
				$type = $mime_match;
				$ext = $ext_matches[1];
				break;
			}
		}
		return compact( 'ext', 'type' );
	}

	protected function _upload_mimes($_mimes=''){
		$mimes = $this->get_all_mime_types();
		$opts = get_option($this->id);
		$exts = explode(',', preg_replace('/,\s*/', ',', $opts['allowed_file_type']));
		$allowed_mimes = array();
		foreach ( $exts as $ext ) {
			foreach ( $mimes as $ext_pattern => $mime ) {
				if ( $ext != '' && strpos( $ext_pattern, $ext ) !== false )
					$allowed_mimes[$ext_pattern] = $mime;
			}
		}
		return $allowed_mimes;
	}

	public function get_all_mime_types(){
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'bmp' => 'image/bmp',
			'tif|tiff' => 'image/tiff',
			'ico' => 'image/x-icon',
			'asf|asx|wax|wmv|wmx' => 'video/asf',
			'avi' => 'video/avi',
			'divx' => 'video/divx',
			'flv' => 'video/x-flv',
			'mov|qt' => 'video/quicktime',
			'mpeg|mpg|mpe' => 'video/mpeg',
			'txt|asc|c|cc|h' => 'text/plain',
			'csv' => 'text/csv',
			'tsv' => 'text/tab-separated-values',
			'ics' => 'text/calendar',
			'rtx' => 'text/richtext',
			'css' => 'text/css',
			'htm|html' => 'text/html',
			'mp3|m4a|m4b' => 'audio/mpeg',
			'mp4|m4v' => 'video/mp4',
			'ra|ram' => 'audio/x-realaudio',
			'wav' => 'audio/wav',
			'ogg|oga' => 'audio/ogg',
			'ogv' => 'video/ogg',
			'mid|midi' => 'audio/midi',
			'wma' => 'audio/wma',
			'mka' => 'audio/x-matroska',
			'mkv' => 'video/x-matroska',
			'rtf' => 'application/rtf',
			'js' => 'application/javascript',
			'pdf' => 'application/pdf',
			'doc|docx' => 'application/msword',
			'pot|pps|ppt|pptx|ppam|pptm|sldm|ppsm|potm' => 'application/vnd.ms-powerpoint',
			'wri' => 'application/vnd.ms-write',
			'xla|xls|xlsx|xlt|xlw|xlam|xlsb|xlsm|xltm' => 'application/vnd.ms-excel',
			'mdb' => 'application/vnd.ms-access',
			'mpp' => 'application/vnd.ms-project',
			'docm|dotm' => 'application/vnd.ms-word',
			'pptx|sldx|ppsx|potx' => 'application/vnd.openxmlformats-officedocument.presentationml',
			'xlsx|xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml',
			'docx|dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml',
			'onetoc|onetoc2|onetmp|onepkg' => 'application/onenote',
			'swf' => 'application/x-shockwave-flash',
			'class' => 'application/java',
			'tar' => 'application/x-tar',
			'zip' => 'application/zip',
			'gz|gzip' => 'application/x-gzip',
			'exe' => 'application/x-msdownload',
			// openoffice formats
			'odt' => 'application/vnd.oasis.opendocument.text',
			'odp' => 'application/vnd.oasis.opendocument.presentation',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			'odg' => 'application/vnd.oasis.opendocument.graphics',
			'odc' => 'application/vnd.oasis.opendocument.chart',
			'odb' => 'application/vnd.oasis.opendocument.database',
			'odf' => 'application/vnd.oasis.opendocument.formula',
			// wordperfect formats
			'wp|wpd' => 'application/wordperfect',
		);
	}
}
