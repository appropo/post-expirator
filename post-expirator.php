<?php
/*
Plugin Name: Post Expirator
Plugin URI: http://wordpress.org/extend/plugins/post-expirator/
Description: Allows you to add an expiration date (minute) to posts which you can configure to either delete the post, change it to a draft, or update the post categories at expiration time.
Author: Aaron Axelsen
Version: 2.2-dev
Author URI: http://postexpirator.tuxdocs.net/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: post-expirator
Domain Path: /languages
*/

//avoid direct calls to this file
if ( ! function_exists( 'add_filter' ) ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( ! class_exists('Post_Expirator') ) {
	
	// Default Values
//	define('POSTEXPIRATOR_DATEFORMAT',__('l F jS, Y','post-expirator'));
//	define('POSTEXPIRATOR_TIMEFORMAT',__('g:ia','post-expirator'));
//	define('POSTEXPIRATOR_FOOTERCONTENTS',__('Post expires at EXPIRATIONTIME on EXPIRATIONDATE','post-expirator'));
	
//	define('POSTEXPIRATOR_FOOTERSTYLE','font-style: italic;');
//	define('POSTEXPIRATOR_FOOTERDISPLAY','0');
//	define('POSTEXPIRATOR_DEBUGDEFAULT','0');
//	define('POSTEXPIRATOR_EXPIREDEFAULT','null');
	
	add_action(
		'plugins_loaded', 
		array ( 'Post_Expirator', 'get_instance' )
	);
	
	class Post_Expirator {
		
		// Plugin instance
		protected static $instance = NULL;
		
		const VERSION ='2.2-dev';
		
		// var $default = array();
		
		public function __construct() {
//			if ( ! is_admin() )
//				return NULL;

			//$this->defaults	= array();
			$this->defaults = array(
				'dateformat'	=> __( 'l F jS, Y', 'post-expirator'),
				'timeformat'	=> __( 'g:ia', 'post-expirator'),
				'footercontents'=> __( 'Post expires at EXPIRATIONTIME on EXPIRATIONDATE', 'post-expirator'), 
				'footerstyle'	=> 'font-style: italic;',
				'footerdisplay'	=> '0',
				'debug'			=> '0',
				'expire'		=> NULL,
			);
			
			$this->load_classes();
			
			add_shortcode( 'postexpirator', array( &$this, 'shortcode'));

			add_action( 'manage_posts_custom_column', array( &$this, 'show_value'));
			add_action( 'manage_pages_custom_column', array( &$this, 'show_value'));
			add_action( 'add_meta_boxes', array( &$this, 'meta_custom'));
			add_action( 'save_post', array( &$this, 'update_post_meta'));
			add_action( 'postExpiratorExpire', array( &$this, 'expire'));
			add_action( 'the_content', array( &$this, 'add_footer', 0));
		
			load_plugin_textdomain( 'post-expirator', false, dirname( __FILE__ ) . '/languages/');
			
			// Activation, Deactivation + Upgrade processes
			register_deactivation_hook( __FILE__, array( 'Post_Expirator', 'activate'));
			register_deactivation_hook( __FILE__, array( 'Post_Expirator', 'deactivate'));
					
		}

		// Access this plugin’s working instance
		public static function get_instance() {	
			if ( NULL === self::$instance )
				self::$instance = new self;

			return self::$instance;
		}
		
		// load classes from INC path
		protected function load_classes() {
			foreach( glob( dirname( __FILE__ ) . '/inc/class.*.php' ) as $class ) {
				require_once $class;
			}
		}

		/**
		 * Adds hooks to get the meta box added to pages and custom post types
		 */
		function meta_custom() {
			$custom_post_types = get_post_types();
			array_push( $custom_post_types, 'page');
			
			foreach ( $custom_post_types as $t ) {
				$defaults = get_option('expirationdateDefaults'.ucfirst($t));
				
				if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
					add_meta_box(
						'expirationdatediv',
						__( 'Post Expirator', 'post-expirator'),
						array( &$this, 'meta_box'),
						$t,
						'side',
						'core'
					);
				}
			}
		}
		
		/**
		 * Actually adds the meta box
		 */
		function meta_box( $post ) { 
			// Get default month
			$expirationdatets = get_post_meta($post->ID,'_expiration-date',true);
			$firstsave = get_post_meta($post->ID,'_expiration-date-status',true);
			$default = '';
			$expireType = '';
			$defaults = get_option( 'expirationdateDefaults'.ucfirst($post->post_type));
			if (empty($expirationdatets)) {
				$default = get_option( 'expirationdateDefaultDate', $this->defaults->expire);
				if ($default == 'null') {
					$defaultmonth 	=	date_i18n('m');
					$defaultday 	=	date_i18n('d');
					$defaulthour 	=	date_i18n('H');
					$defaultyear 	=	date_i18n('Y');
					$defaultminute 	= 	date_i18n('i');

				} elseif ($default == 'custom') {
					$custom = get_option('expirationdateDefaultDateCustom');
					if ($custom === false) $ts = time();
					else {
						$tz = get_option('timezone_string');
						if ( $tz ) date_default_timezone_set( $tz );

						$ts = time() + (strtotime($custom) - time());

						if ( $tz ) date_default_timezone_set('UTC');
					}
					$defaultmonth 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'m');
					$defaultday 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'d');
					$defaultyear 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'Y');;
					$defaulthour 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'H');
					$defaultminute 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'i');
				} 

				$enabled = '';
				$disabled = ' disabled="disabled"';
				$categories = get_option('expirationdateCategoryDefaults');

				if (isset($defaults['expireType'])) {
					$expireType = $defaults['expireType'];
				}

				if (isset($defaults['autoEnable']) && ($firstsave !== 'saved') && ($defaults['autoEnable'] === true || $defaults['autoEnable'] == 1)) { 
					$enabled = ' checked="checked"'; 
					$disabled='';
				} 
			} else {
				$defaultmonth 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),'m');
				$defaultday 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),'d');
				$defaultyear 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),'Y');
				$defaulthour 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),'H');
				$defaultminute 	=	$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),'i');
				$enabled 	= 	' checked="checked"';
				$disabled 	= 	'';
				$opts 		= 	get_post_meta($post->ID,'_expiration-date-options',true);
				if (isset($opts['expireType'])) {
							$expireType = $opts['expireType'];
				}
				$categories = isset($opts['category']) ? $opts['category'] : false;
			}

			$rv = array();
			$rv[] = '<p><input type="checkbox" name="enable-expirationdate" id="enable-expirationdate" value="checked"'.$enabled.' onclick="expirationdate_ajax_add_meta(\'enable-expirationdate\')" />';
			$rv[] = '<label for="enable-expirationdate">'.__('Enable Post Expiration','post-expirator').'</label></p>';

			if ($default == 'publish') {
				$rv[] = '<em>'.__('The published date/time will be used as the expiration value','post-expirator').'</em><br/>';
			} else {
				$rv[] = '<table><tr>';
				$rv[] = '<th style="text-align: left;">'.__('Year','post-expirator').'</th>';
				$rv[] = '<th style="text-align: left;">'.__('Month','post-expirator').'</th>';
				$rv[] = '<th style="text-align: left;">'.__('Day','post-expirator').'</th>';
				$rv[] = '</tr><tr>';
				$rv[] = '<td>';	
				$rv[] = '<select name="expirationdate_year" id="expirationdate_year"'.$disabled.'>';
				$currentyear = date('Y');

				if ($defaultyear < $currentyear) $currentyear = $defaultyear;

				for($i = $currentyear; $i < $currentyear + 8; $i++) {
					if ($i == $defaultyear)
						$selected = ' selected="selected"';
					else
						$selected = '';
					$rv[] = '<option'.$selected.'>'.($i).'</option>';
				}
				$rv[] = '</select>';
				$rv[] = '</td><td>';
				$rv[] = '<select name="expirationdate_month" id="expirationdate_month"'.$disabled.'>';

				for($i = 1; $i <= 12; $i++) {
					if ($defaultmonth == date_i18n('m',mktime(0, 0, 0, $i, 1, date_i18n('Y'))))
						$selected = ' selected="selected"';
					else
						$selected = '';
					$rv[] = '<option value="'.date_i18n('m',mktime(0, 0, 0, $i, 1, date_i18n('Y'))).'"'.$selected.'>'.date_i18n('F',mktime(0, 0, 0, $i, 1, date_i18n('Y'))).'</option>';
				}

				$rv[] = '</select>';	 
				$rv[] = '</td><td>';
				$rv[] = '<input type="text" id="expirationdate_day" name="expirationdate_day" value="'.$defaultday.'" size="2"'.$disabled.' />,';
				$rv[] = '</td></tr><tr>';
				$rv[] = '<th style="text-align: left;"></th>';
				$rv[] = '<th style="text-align: left;">'.__('Hour','post-expirator').'('.date_i18n('T',mktime(0, 0, 0, $i, 1, date_i18n('Y'))).')</th>';
				$rv[] = '<th style="text-align: left;">'.__('Minute','post-expirator').'</th>';
				$rv[] = '</tr><tr>';
				$rv[] = '<td>@</td><td>';
				$rv[] = '<select name="expirationdate_hour" id="expirationdate_hour"'.$disabled.'>';

				for($i = 1; $i <= 24; $i++) {
					if ($defaulthour == date_i18n('H',mktime($i, 0, 0, date_i18n('n'), date_i18n('j'), date_i18n('Y'))))
						$selected = ' selected="selected"';
					else
						$selected = '';
					$rv[] = '<option value="'.date_i18n('H',mktime($i, 0, 0, date_i18n('n'), date_i18n('j'), date_i18n('Y'))).'"'.$selected.'>'.date_i18n('H',mktime($i, 0, 0, date_i18n('n'), date_i18n('j'), date_i18n('Y'))).'</option>';
				}

				$rv[] = '</select></td><td>';
				$rv[] = '<input type="text" id="expirationdate_minute" name="expirationdate_minute" value="'.$defaultminute.'" size="2"'.$disabled.' />';
				$rv[] = '</td></tr></table>';
			}
			$rv[] = '<input type="hidden" name="expirationdate_formcheck" value="true" />';
			echo implode("\n",$rv);

			echo '<br/>'.__('How to expire','post-expirator').': ';
			echo _postExpiratorExpireType(array('type' => $post->post_type, 'name'=>'expirationdate_expiretype','selected'=>$expireType,'disabled'=>$disabled,'onchange' => 'expirationdate_toggle_category(this)'));
			echo '<br/>';

			if ($post->post_type != 'page') {
				if (isset($expireType) && ($expireType == 'category' || $expireType == 'category-add' || $expireType == 'category-remove')) {
					$catdisplay = 'block';
				} else {
					$catdisplay = 'none';
				}
				echo '<div id="expired-category-selection" style="display: '.$catdisplay.'">';
				echo '<br/>'.__('Expiration Categories','post-expirator').':<br/>';

				echo '<div class="wp-tab-panel" id="post-expirator-cat-list">';
				echo '<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">';
				$walker = new Walker_PostExpirator_Category_Checklist();
				if (!empty($disabled)) $walker->setDisabled();
				$taxonomies = get_object_taxonomies($post->post_type,'object');
					$taxonomies = wp_filter_object_list($taxonomies, array('hierarchical' => true));
				if (sizeof($taxonomies) == 0) {
					echo '<p>'.__('You must assign a heirarchical taxonomy to this post type to use this feature.','post-expirator').'</p>';
				} elseif (sizeof($taxonomies) > 1 && !isset($defaults['taxonomy'])) {
					echo '<p>'.__('More than 1 heirachical taxonomy detected.  You must assign a default taxonomy on the settings screen.','post-expirator').'</p>';
				} else {
					$keys = array_keys($taxonomies);
					$taxonomy = isset($defaults['taxonomy']) ? $defaults['taxonomy'] : $keys[0];
					wp_terms_checklist(0, array( 'taxonomy' => $taxonomy, 'walker' => $walker, 'selected_cats' => $categories, 'checked_ontop' => false ) );
					echo '<input type="hidden" name="taxonomy-heirarchical" value="'.$taxonomy.'" />';
				}
				echo '</ul>';
				echo '</div>';
				if (isset($taxonomy))
				echo '<p class="post-expirator-taxonomy-name">'.__('Taxonomy Name','post-expirator').': '.$taxonomy.'</p>';
				echo '</div>';
			}
			echo '<div id="expirationdate_ajax_result"></div>';
		}
		
		/**
		 * Check for Debug
		 */
		function debug() {
			$debug = get_option('expirationdateDebug');
			if ( $debug == 1 ) {
				if ( !defined('POSTEXPIRATOR_DEBUG') )
					define('POSTEXPIRATOR_DEBUG',1);
				// require_once(plugin_dir_path(__FILE__).'post-expirator-debug.php'); // Load Class
				return new Post_Expirator_Debug();
			} else {
				if ( !defined('POSTEXPIRATOR_DEBUG') ) 
					define( 'POSTEXPIRATOR_DEBUG', 0);
				return false;
			}
		}	
		
		function add_footer( $text ) {
			global $post;

			// Check to see if its enabled
			$displayFooter = get_option('expirationdateDisplayFooter');
			if ($displayFooter === false || $displayFooter == 0)
				return $text;

				$expirationdatets = get_post_meta($post->ID,'_expiration-date',true);
			if ( !is_numeric($expirationdatets) )
				return $text;

				$dateformat = get_option( 'expirationdateDefaultDateFormat', $this->defaults->dateformat);
				$timeformat = get_option( 'expirationdateDefaultTimeFormat', $this->defaults->timeformat);
				$expirationdateFooterContents = get_option( 'expirationdateFooterContents', $this->defaults->footercontents);
				$expirationdateFooterStyle = get_option( 'expirationdateFooterStyle', $this->defaults->footerstyle);

			$search = array(
				'EXPIRATIONFULL',
				'EXPIRATIONDATE',
				'EXPIRATIONTIME'
			);
			$replace = array(
				$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),"$dateformat $timeformat"),
				$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),$dateformat),
				$this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),$timeformat)
			);

			$add_to_footer = '<p style="'.$expirationdateFooterStyle.'">'.str_replace($search,$replace,$expirationdateFooterContents).'</p>';
			return $text.$add_to_footer;
		}
		
		// [postexpirator format="l F jS, Y g:ia" tz="foo"]
		function shortcode( $atts ) {
			global $post;

			$expirationdatets = get_post_meta($post->ID,'_expiration-date',true);
			if ( empty( $expirationdatets ) )
				return false;

			extract(shortcode_atts(array(
				'dateformat' => get_option( 'expirationdateDefaultDateFormat', $this->defaults->dateformat),
				'timeformat' => get_option( 'expirationdateDefaultTimeFormat', $this->defaults->timeformat),
				'type' => 'full',
				'tz' => date('T')
			), $atts));

			if (empty($dateformat)) {
				global $expirationdateDefaultDateFormat;
				$dateformat = $expirationdateDefaultDateFormat;		
			}

			if (empty($timeformat)) {
				global $expirationdateDefaultTimeFormat;
				$timeformat = $expirationdateDefaultTimeFormat;		
			}

			if ($type == 'full') 
				$format = $dateformat.' '.$timeformat;
			else if ($type == 'date')
				$format = $dateformat;
			else if ($type == 'time')
				$format = $timeformat;

			return $this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$expirationdatets),$format);
		}
		
		/**
		 * Get correct URL (HTTP or HTTPS)
		 */
		function get_blog_url() {
			if ( is_multisite() )	
				echo network_home_url('/');
			else
				echo home_url('/');
		}

		/**
		 * Called when post is saved - stores expiration-date meta value
		 */
		function update_post_meta( $id ) {
			// don't run the echo if this is an auto save
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
				return;

			// don't run the echo if the function is called for saving revision.
			$posttype = get_post_type($id);
			
			if ( $posttype == 'revision' )
				return;

			if ( !isset($_POST['expirationdate_formcheck']) )
				return;

			if ( isset($_POST['enable-expirationdate']) ) {
				$default = get_option( 'expirationdateDefaultDate', $this->defaults->expire);
				if ( $default == 'publish' ) {
						$month 	 = intval($_POST['mm']);
						$day 	 = intval($_POST['jj']);
						$year 	 = intval($_POST['aa']);
						$hour 	 = intval($_POST['hh']);
						$minute  = intval($_POST['mn']);
				} else {
						$month	 = intval($_POST['expirationdate_month']);
						$day 	 = intval($_POST['expirationdate_day']);
						$year 	 = intval($_POST['expirationdate_year']);
						$hour 	 = intval($_POST['expirationdate_hour']);
						$minute  = intval($_POST['expirationdate_minute']);
				}
				$category = isset($_POST['expirationdate_category']) ? $_POST['expirationdate_category'] : 0;

				$opts = array();
				$ts = get_gmt_from_date("$year-$month-$day $hour:$minute:0",'U');

				// Schedule/Update Expiration
				$opts['expireType'] = $_POST['expirationdate_expiretype'];
				$opts['id'] = $id;

				if ($opts['expireType'] == 'category' || $opts['expireType'] == 'category-add' || $opts['expireType'] == 'category-remove') {
					if (isset($category) && !empty($category)) {
						if (!empty($category)) {
							$opts['category'] = $category;
							$opts['categoryTaxonomy'] = $_POST['taxonomy-heirarchical'];
						}
					}
				}

				_scheduleExpiratorEvent( $id, $ts, $opts);
			} else {
				_unscheduleExpiratorEvent( $id);
			}
		}
		
		/**
		 * The new expiration function, to work with single scheduled events.
		 *
		 * This was designed to hopefully be more flexible for future tweaks/modifications to the architecture.
		 *
		 * @param array $opts - options to pass into the expiration process, in key/value format
		 */
		function expire( $id ) {
			$debug = Post_Expirator_Debug(); //check for/load debug

			if (empty($id)) { 
				if ( POSTEXPIRATOR_DEBUG ) 
					$debug->save(array('message' => 'No Post ID found - exiting'));
				return false;
			}

			if (is_null(get_post($id))) {
				if ( POSTEXPIRATOR_DEBUG ) 
					$debug->save(array('message' => $id.' -> Post does not exist - exiting'));
				return false;
			}

			$postoptions = get_post_meta( $id, '_expiration-date-options', true);
			extract($postoptions);

			// Check for default expire only if not passed in
			if ( empty($expireType) ) {
				$posttype = get_post_type($id);
				if ( $posttype == 'page' ) {
					$expireType = strtolower( get_option( 'expirationdateExpiredPageStatus', POSTEXPIRATOR_PAGESTATUS));
				} elseif ( $posttype == 'post' ) {
					$expireType = strtolower( get_option( 'expirationdateExpiredPostStatus', 'Draft'));
				} else {
					$expireType = apply_filters( 'postexpirator_custom_posttype_expire', $expireType, $posttype); //hook to set defaults for custom post types
				}
			}

			// Remove KSES - wp_cron runs as an unauthenticated user, which will by default trigger kses filtering,
			// even if the post was published by a admin user.  It is fairly safe here to remove the filter call since
			// we are only changing the post status/meta information and not touching the content.
			kses_remove_filters();

			// Do Work
			if ( $expireType == 'draft' ) {
				if (wp_update_post(array('ID' => $id, 'post_status' => 'draft')) == 0) {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
				} else {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
				}
			} elseif ( $expireType == 'private' ) {
				if (wp_update_post(array('ID' => $id, 'post_status' => 'private')) == 0) {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
				} else {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
				}
			} elseif ( $expireType == 'delete' ) {
				if (wp_delete_post($id) === false) {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
				} else {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
				}
			} elseif ( $expireType == 'category' ) {
				if ( !empty($category) ) {
					if (!isset($categoryTaxonomy) || $categoryTaxonomy == 'category') {
						if (wp_update_post(array('ID' => $id, 'post_category' => $category)) == 0) {
							if (POSTEXPIRATOR_DEBUG)
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if (POSTEXPIRATOR_DEBUG) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES REPLACED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($category),true)));
							}
						}
					} else {
						$terms = array_map('intval', $category);
						if (is_wp_error(wp_set_object_terms($id,$terms,$categoryTaxonomy,false))) {
							if (POSTEXPIRATOR_DEBUG)
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if (POSTEXPIRATOR_DEBUG) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES REPLACED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($category),true)));
							}
						}
					}
				} else {
					if (POSTEXPIRATOR_DEBUG)
						$debug->save(array('message' => $id.' -> CATEGORIES MISSING '.$expireType.' '.print_r($postoptions,true)));
				}
			} elseif ( $expireType == 'category-add' ) {
				if ( !empty($category) ) {
					if (!isset($categoryTaxonomy) || $categoryTaxonomy == 'category') {
						$cats = wp_get_post_categories($id);
						$merged = array_merge($cats,$category);
						if (wp_update_post(array('ID' => $id, 'post_category' => $merged)) == 0) {
							if (POSTEXPIRATOR_DEBUG)
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if (POSTEXPIRATOR_DEBUG) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES ADDED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($merged),true)));
							}
						}
					} else {
						$terms = array_map('intval', $category);
						if (is_wp_error(wp_set_object_terms($id,$terms,$categoryTaxonomy,true))) {
							if (POSTEXPIRATOR_DEBUG)
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if (POSTEXPIRATOR_DEBUG) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES ADDED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($category),true)));
							}
						}				
					}
				} else {
					if ( POSTEXPIRATOR_DEBUG ) 
						$debug->save(array('message' => $id.' -> CATEGORIES MISSING '.$expireType.' '.print_r($postoptions,true)));
				}
			} elseif ( $expireType == 'category-remove' ) {
				if ( !empty($category) ) {
					if ( !isset($categoryTaxonomy) || $categoryTaxonomy == 'category' ) {
						$cats = wp_get_post_categories($id);
						$merged = array();
						foreach ($cats as $cat) {
							if ( !in_array($cat,$category) ) {
								$merged[] = $cat;
							}
						}
						if ( wp_update_post(array('ID' => $id, 'post_category' => $merged)) == 0 ) {
							if ( POSTEXPIRATOR_DEBUG ) 
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if ( POSTEXPIRATOR_DEBUG ) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES REMOVED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($merged),true)));
							}
						}
					} else {
						$terms = wp_get_object_terms($id, $categoryTaxonomy, array('fields' => 'ids'));
						$merged = array();
						foreach ($terms as $term) {
							if (!in_array($term,$category)) {
								$merged[] = $term;
							}
						}
						$terms = array_map('intval', $merged);
						if (is_wp_error(wp_set_object_terms($id,$terms,$categoryTaxonomy,false))) {
							if ( POSTEXPIRATOR_DEBUG ) 
								$debug->save(array('message' => $id.' -> FAILED '.$expireType.' '.print_r($postoptions,true)));
						} else {
							if ( POSTEXPIRATOR_DEBUG ) {
								$debug->save(array('message' => $id.' -> PROCESSED '.$expireType.' '.print_r($postoptions,true)));
								$debug->save(array('message' => $id.' -> CATEGORIES REMOVED '.print_r(_postExpiratorGetCatNames($category),true)));
								$debug->save(array('message' => $id.' -> CATEGORIES COMPLETE '.print_r(_postExpiratorGetCatNames($category),true)));
							}
						}				
					}
				} else {
					if ( POSTEXPIRATOR_DEBUG )
						$debug->save(array('message' => $id.' -> CATEGORIES MISSING '.$expireType.' '.print_r($postoptions,true)));
				}
			}
		}

		/**
		 * Post Expirator Activation/Upgrade
		 */
		function upgrade() {

			// Check for current version, if not exists, run activation
			$version = get_option('postexpiratorVersion');
			if ($version === false) { //not installed, run default activation
				postexpirator_activate();
				update_option( 'postexpiratorVersion', self::ID);
			} else {
				if ( version_compare( $version, '1.6.1') == -1) {
					update_option( 'postexpiratorVersion', self::ID);
					update_option( 'expirationdateDefaultDate', $this->defaults->expire);
				}

				if ( version_compare( $version, '1.6.2') == -1) {
					update_option( 'postexpiratorVersion', self::ID);
				}

				if ( version_compare( $version, '2.0.0-rc1') == -1) {
					global $wpdb;

					// Schedule Events/Migrate Config
					$results = $wpdb->get_results($wpdb->prepare('select post_id, meta_value from ' . $wpdb->postmeta . ' as postmeta, '.$wpdb->posts.' as posts where postmeta.post_id = posts.ID AND postmeta.meta_key = %s AND postmeta.meta_value >= %d','expiration-date',time()));
					foreach ($results as $result) {
						wp_schedule_single_event( $result->meta_value, 'postExpiratorExpire', array( $result->post_id));
						$opts = array();
						$opts['id'] = $result->post_id;
						$posttype = get_post_type($result->post_id);
							if ($posttype == 'page') {
									$opts['expireType'] = strtolower( get_option('expirationdateExpiredPageStatus', 'Draft'));
								} else {
										$opts['expireType'] = strtolower( get_option('expirationdateExpiredPostStatus', 'Draft'));
						}

						$cat = get_post_meta($result->post_id,'_expiration-date-category',true);			
						if ((isset($cat) && !empty($cat))) {
							$opts['category'] = $cat;
							$opts['expireType'] = 'category';
						}
						update_post_meta( $result->post_id, '_expiration-date-options', $opts);
					}

					// update meta key to new format
					$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta SET meta_key = %s WHERE meta_key = %s",'_expiration-date','expiration-date'));

					// migrate defaults
					$pagedefault = get_option( 'expirationdateExpiredPageStatus');
					$postdefault = get_option( 'expirationdateExpiredPostStatus');
					if ($pagedefault) update_option( 'expirationdateDefaultsPage', array( 'expireType' => $pagedefault));
					if ($postdefault) update_option( 'expirationdateDefaultsPost', array( 'expireType' => $postdefault));

					delete_option( 'expirationdateCronSchedule');
					delete_option( 'expirationdateAutoEnabled');
					delete_option( 'expirationdateExpiredPageStatus');
					delete_option( 'expirationdateExpiredPostStatus');
					update_option( 'postexpiratorVersion', self::ID);
				}

				if ( version_compare( $version, '2.0.1') == -1 ) {
					// Forgot to do this in 2.0.0
					if (is_multisite()) {
						global $current_blog;
						wp_clear_scheduled_hook( 'expirationdate_delete_'.$current_blog->blog_id);
					} else
						wp_clear_scheduled_hook( 'expirationdate_delete');

					update_option( 'postexpiratorVersion', self::ID);
				}

				if ( version_compare( $version, '2.1.0') == -1 ) {
					update_option( 'postexpiratorVersion', self::ID);
				}

				if ( version_compare( $version, '2.1.1') == -1 ) {
					update_option( 'postexpiratorVersion', self::ID);
				}
			}
		}
		
		/** 
		 * Called at plugin activation
		 */
		function activate () {
			// Use underscores to separate words, and do not use uppercase 
			if ( get_option('expirationdateDefaultDateFormat') === false )
				update_option( 'expirationdateDefaultDateFormat', $this->defaults->dateformat);
			if ( get_option('expirationdateDefaultTimeFormat') === false )
				update_option( 'expirationdateDefaultTimeFormat', $this->defaults->timeformat);
			if ( get_option('expirationdateFooterContents') === false )
				update_option( 'expirationdateFooterContents', $this->defaults->footercontents);
			if ( get_option('expirationdateFooterStyle') === false )
				update_option( 'expirationdateFooterStyle', $this->defaults->footerstyle);
			if ( get_option('expirationdateDisplayFooter') === false )
				update_option( 'expirationdateDisplayFooter', $this->defaults->footerdisplay);
			if ( get_option('expirationdateDebug') === false )
				update_option( 'expirationdateDebug', $this->defaults->debug);
			if ( get_option('expirationdateDefaultDate') === false )
				update_option(' expirationdateDefaultDate', $this->defaults->expire);
		}
		
		/**
		 * Called at plugin deactivation
		 */
		function deactivate () {
			global $current_blog;
			delete_option('expirationdateExpiredPostStatus');
			delete_option('expirationdateExpiredPageStatus');
			delete_option('expirationdateDefaultDateFormat');
			delete_option('expirationdateDefaultTimeFormat');
			delete_option('expirationdateDisplayFooter');
			delete_option('expirationdateFooterContents');
			delete_option('expirationdateFooterStyle');
			delete_option('expirationdateCategory');
			delete_option('expirationdateCategoryDefaults');
			delete_option('expirationdateDebug');
			delete_option('postexpiratorVersion');
			delete_option('expirationdateCronSchedule');
			delete_option('expirationdateDefaultDate');
			delete_option('expirationdateDefaultDateCustom');
			delete_option('expirationdateAutoEnabled');
			delete_option('expirationdateDefaultsPage');
			delete_option('expirationdateDefaultsPost');
			## what about custom post types? - how to cleanup?
			if (is_multisite())
				wp_clear_scheduled_hook( 'expirationdate_delete_'.$current_blog->blog_id);
			else
				wp_clear_scheduled_hook( 'expirationdate_delete');
			require_once(plugin_dir_path(__FILE__).'post-expirator-debug.php');
			$debug = new Post_Expirator_Debug();
			$debug->removeDbTable();
		}
		
		/**
		 * TEMPORARY FUNCTION UNTIL TICKET http://core.trac.wordpress.org/ticket/20328 IS FIXED
		 */
		function get_date_from_gmt( $string, $format = 'Y-m-d H:i:s' ) {
			$tz = get_option('timezone_string');
			if ( $tz ) {
				$datetime = new DateTime( $string , new DateTimeZone('UTC') );
				$datetime->setTimezone( new DateTimeZone($tz) );
				$string_localtime = $datetime->format($format);
			} else {
				preg_match('#([0-9]{1,4})-([0-9]{1,2})-([0-9]{1,2}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})#', $string, $matches);
				$string_time = gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
				$string_localtime = gmdate($format, $string_time + get_option('gmt_offset')*3600);
			}
			return $string_localtime;
		}
		
		function _scheduleExpiratorEvent( $id, $ts, $opts ) {
			$debug = Post_Expirator_Debug(); //check for/load debug

			if (wp_next_scheduled('postExpiratorExpire',array($id)) !== false) {
				wp_clear_scheduled_hook('postExpiratorExpire',array($id)); //Remove any existing hooks
				if ( POSTEXPIRATOR_DEBUG ) 
					$debug->save(array('message' => $id.' -> UNSCHEDULED'));
			}

			wp_schedule_single_event($ts,'postExpiratorExpire',array($id)); 
			if ( POSTEXPIRATOR_DEBUG ) 
				$debug->save(array('message' => $id.' -> SCHEDULED at '.date_i18n('r',$ts).' '.'('.$ts.') with options '.print_r($opts,true)));

			// Update Post Meta
			update_post_meta( $id, '_expiration-date', $ts);
			update_post_meta( $id, '_expiration-date-options', $opts);
			update_post_meta( $id, '_expiration-date-status', 'saved');
		}

		function _unscheduleExpiratorEvent( $id ) {
			$debug = Post_Expirator_Debug(); // check for/load debug
			delete_post_meta($id, '_expiration-date'); 
			delete_post_meta($id, '_expiration-date-options');

			// Delete Scheduled Expiration
			if (wp_next_scheduled('postExpiratorExpire',array($id)) !== false) {
				wp_clear_scheduled_hook('postExpiratorExpire',array($id)); //Remove any existing hooks
				if ( POSTEXPIRATOR_DEBUG ) 
					$debug->save(array('message' => $id.' -> UNSCHEDULED'));
			}
			update_post_meta( $id, '_expiration-date-status', 'saved');
		}

		function _postExpiratorGetCatNames( $cats ) {
			$out = array();
			foreach ($cats as $cat) {
				$out[$cat] = get_the_category_by_id($cat);
			}
			return $out;
		}	

		function _postExpiratorExpireType( $opts ) {
			if ( empty($opts) )
				return false;

			extract($opts);
			if (!isset($name)) return false;
			if (!isset($id)) $id = $name;
			if (!isset($disabled)) $disabled = false;
			if (!isset($onchange)) $onchange = '';
			if (!isset($type)) $type = '';

			$rv = array();
			$rv[] = '<select name="'.$name.'" id="'.$id.'"'.($disabled == true ? ' disabled="disabled"' : '').' onchange="'.$onchange.'">';
			$rv[] = '<option value="draft" '. ($selected == 'draft' ? 'selected="selected"' : '') . '>'.__('Draft','post-expirator').'</option>';
			$rv[] = '<option value="delete" '. ($selected == 'delete' ? 'selected="selected"' : '') . '>'.__('Delete','post-expirator').'</option>';
			$rv[] = '<option value="private" '. ($selected == 'private' ? 'selected="selected"' : '') . '>'.__('Private','post-expirator').'</option>';
			if ($type != 'page') {
				$rv[] = '<option value="category" '. ($selected == 'category' ? 'selected="selected"' : '') . '>'.__('Category: Replace','post-expirator').'</option>';
				$rv[] = '<option value="category-add" '. ($selected == 'category-add' ? 'selected="selected"' : '') . '>'.__('Category: Add','post-expirator').'</option>';
				$rv[] = '<option value="category-remove" '. ($selected == 'category-remove' ? 'selected="selected"' : '') . '>'.__('Category: Remove','post-expirator').'</option>';
			}
			$rv[] = '</select>';
			return implode("<br/>/n",$rv);
		}

		function _postExpiratorTaxonomy( $opts ) {
			if ( empty($opts) )
				return false;

			extract($opts);
			if (!isset($name)) return false;
			if (!isset($id)) $id = $name;
			if (!isset($disabled)) $disabled = false;
			if (!isset($onchange)) $onchange = '';
			if (!isset($type)) $type = '';

			$taxonomies = get_object_taxonomies( $type, 'object');
			$taxonomies = wp_filter_object_list( $taxonomies, array( 'hierarchical' => true));

			if ( empty( $taxonomies ) )
				$disabled = true;

			$rv = array();
				$rv[] = '<select name="'.$name.'" id="'.$id.'"'.($disabled == true ? ' disabled="disabled"' : '').' onchange="'.$onchange.'">';
			foreach ($taxonomies as $taxonomy) {
				$rv[] = '<option value="'.$taxonomy->name.'" '. ($selected == $taxonomy->name ? 'selected="selected"' : '') . '>'.$taxonomy->name.'</option>';
			}

			$rv[] = '</select>';
			return implode("<br/>/n",$rv);
		}
		
	} // END class Post_Expirator
	
} // END if class_exists
