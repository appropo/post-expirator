<?php

if ( ! function_exists( 'add_filter' ) ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

class Post_Expirator_Admin extends Post_Expirator {
	
	// Plugin instance
	protected static $instance = NULL;

	function __construct() {
		if ( ! is_admin() )
			return NULL;
		
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts'));
		// add_action( 'admin_init', array( &$this, 'upgrade'));
		add_action( 'admin_menu', array( &$this, 'admin_menu'));
		add_action( 'admin_notices', array( &$this, 'admin_notice'));
		
		add_filter( 'manage_posts_columns', array( &$this, 'add_column'), 10, 2);
		add_filter( 'manage_pages_columns', array( &$this, 'add_column_page'));		
		add_filter( 'plugin_action_links', array( &$this, 'plugin_action_links'), 10, 2);
	}
	
	// Access this plugin’s working instance
	public static function get_instance() {
		if ( NULL === self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
	
	/**
	 * Add CSS
	 */
	function admin_scripts() {
		wp_register_style( 'post-expirator-css', plugins_url( 'post-expirator.min.css', __FILE__), array(), self::VERSION);
		wp_enqueue_style( 'post-expirator-css');
		
		wp_register_script( 'post-expirator-js', plugins_url('post-expirator.min.js', __FILE__), array(), self::VERSION);
		wp_enqueue_style( 'post-expirator-js');
	}
	
	/**
	 * Add admin notice hook if cron schedule needs to be reset
	 */
	function admin_notice() {
		// Currently not used
	}
	
	/**
	 * Hook's to add plugin page menu
	 */
	function admin_menu() {
		$this->page = add_submenu_page(
			'options-general.php',
			__( 'Post Expirator Options',
				'post-expirator'),
			__( 'Post Expirator', 'post-expirator'),
			'manage_options',
			basename(__FILE__),
			array( &$this, 'menu')
		);
	}
	
	/**
	 * adds an 'Expires' column to the post display table.
	 */
	function add_column( $columns, $type ) {
		$defaults = get_option('expirationdateDefaults'.ucfirst($type));
		if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
			$columns['expirationdate'] = __( 'Expires', 'post-expirator');
		}
		return $columns;
	}

	/**
	 * adds an 'Expires' column to the page display table.
	 */
	function add_column_page( $columns ) {
		$defaults = get_option('expirationdateDefaultsPage');
		if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
			$columns['expirationdate'] = __( 'Expires', 'post-expirator');
		}
		return $columns;
	}

	/**
	 * fills the 'Expires' column of the post display table.
	 */
	function show_value( $column_name ) {
		global $post;
		$id = $post->ID;
		if ( $column_name === 'expirationdate' ) {
			$ed = get_post_meta( $id, '_expiration-date', true);
			echo ($ed ? $this->get_date_from_gmt(gmdate('Y-m-d H:i:s',$ed),get_option('date_format').' '.get_option('time_format')) : __( 'Never', 'post-expirator'));
		}
	}
	
	/**
	 * Build the menu for the options page
	 */
	function tabs( $tab ) {
		echo '<p>';
		if ( empty($tab) )
			$tab = 'general';
		echo '<a href="'.admin_url('options-general.php?page=post-expirator.php&tab=general').'"'.($tab == 'general' ? ' style="font-weight: bold; text-decoration:none;"' : '').'>'.__('General Settings','post-expirator').'</a> | ';
		echo '<a href="'.admin_url('options-general.php?page=post-expirator.php&tab=defaults').'"'.($tab == 'defaults' ? ' style="font-weight: bold; text-decoration:none;"' : '').'>'.__('Defaults','post-expirator').'</a> | ';
		echo '<a href="'.admin_url('options-general.php?page=post-expirator.php&tab=diagnostics').'"'.($tab == 'diagnostics' ? ' style="font-weight: bold; text-decoration:none;"' : '').'>'.__('Diagnostics','post-expirator').'</a>';
		echo ' | <a href="'.admin_url('options-general.php?page=post-expirator.php&tab=viewdebug').'"'.($tab == 'viewdebug' ? ' style="font-weight: bold; text-decoration:none;"' : '').'>'.__('View Debug Logs','post-expirator').'</a>';
		echo '</p><hr/>';
	}

	/**
	 *
	 */
	function menu() {
		$tab = isset($_GET['tab']) ? $_GET['tab'] : '';

		echo '<div class="wrap">';
		echo '<h2>'.__( 'Post Expirator Options', 'post-expirator').'</h2>';

		tabs( $tab );
		if (empty( $tab) || $tab == 'general' ) {
			tab_general();
		} elseif ( $tab == 'defaults' ) {
			tab_defaults();
		} elseif ( $tab == 'diagnostics' ) {
			tab_diagnostics();
		} elseif ($tab == 'viewdebug' ) {
			tab_viewdebug();
		}
		echo '</div>';
	}

	
	/**
	 * Show the Expiration Date options page
	 */
	function tab_general() {

		if (isset($_POST['expirationdateSave']) && $_POST['expirationdateSave']) {
			update_option('expirationdateDefaultDateFormat',$_POST['expired-default-date-format']);
			update_option('expirationdateDefaultTimeFormat',$_POST['expired-default-time-format']);
			update_option('expirationdateDisplayFooter',$_POST['expired-display-footer']);
			update_option('expirationdateFooterContents',$_POST['expired-footer-contents']);
			update_option('expirationdateFooterStyle',$_POST['expired-footer-style']);
			if (isset($_POST['expirationdate_category'])) update_option('expirationdateCategoryDefaults',$_POST['expirationdate_category']);
			update_option('expirationdateDefaultDate',$_POST['expired-default-expiration-date']);
			if ($_POST['expired-custom-expiration-date']) update_option('expirationdateDefaultDateCustom',$_POST['expired-custom-expiration-date']);
					echo "<div id='message' class='updated fade'><p>";
					_e('Saved Options!','post-expirator');
					echo "</p></div>";
		}

		// Get Option
		$expirationdateDefaultDateFormat = get_option( 'expirationdateDefaultDateFormat', $this->defaults->dateformat);
		$expirationdateDefaultTimeFormat = get_option( 'expirationdateDefaultTimeFormat', $this->defaults->timeformat);
		$expireddisplayfooter = get_option( 'expirationdateDisplayFooter', $this->defaults->footerdisplay);
		$expirationdateFooterContents = get_option( 'expirationdateFooterContents', $this->defaults->footercontents);
		$expirationdateFooterStyle = get_option( 'expirationdateFooterStyle', $this->defaults->footerstyle);
		$expirationdateDefaultDate = get_option( 'expirationdateDefaultDate', $this->defaults->expire);
		$expirationdateDefaultDateCustom = get_option( 'expirationdateDefaultDateCustom');

		$categories = get_option('expirationdateCategoryDefaults');

		$expireddisplayfooterenabled = '';
		$expireddisplayfooterdisabled = '';
		if ($expireddisplayfooter == 0)
			$expireddisplayfooterdisabled = 'checked="checked"';
		else if ($expireddisplayfooter == 1)
			$expireddisplayfooterenabled = 'checked="checked"';
		?>
		<p>
		<?php _e('The post expirator plugin sets a custom meta value, and then optionally allows you to select if you want the post changed to a draft status or deleted when it expires.','post-expirator'); ?>
		</p>
		<p>
		<?php _e('Valid [postexpirator] attributes:','post-expirator'); ?>
		<ul>
			<li><?php _e( 'type - defaults to full - valid options are full,date,time', 'post-expirator');?></li>
			<li><?php _e( 'dateformat - format set here will override the value set on the settings page', 'post-expirator');?></li>
			<li><?php _e( 'timeformat - format set here will override the value set on the settings page', 'post-expirator');?></li>
		</ul>
		</p>
		<form method="post" id="expirationdate_save_options">
			<h3><?php _e('Defaults','post-expirator'); ?></h3>
			<table class="form-table">
				<tr valign-"top">
					<th scope="row">
						<label for="expired-default-date-format">
							<?php _e( 'Date Format:', 'post-expirator');?>
						</label>
					</th>
					<td>
						<input type="text" name="expired-default-date-format" id="expired-default-date-format" value="<?php echo $expirationdateDefaultDateFormat ?>" size="25" /> (<?php echo date_i18n("$expirationdateDefaultDateFormat") ?>)
						<br/>
						<?php _e( 'The default format to use when displaying the expiration date within a post using the [postexpirator] shortcode or within the footer.  For information on valid formatting options, see: <a href="http://us2.php.net/manual/en/function.date.php" target="_blank">PHP Date Function</a>.', 'post-expirator'); ?>
					</td>
				</tr>
				<tr valign-"top">
					<th scope="row">
						<label for="expired-default-time-format">
							<?php _e( 'Time Format:', 'post-expirator');?>
						</label>
					</th>
					<td>
						<input type="text" name="expired-default-time-format" id="expired-default-time-format" value="<?php echo $expirationdateDefaultTimeFormat ?>" size="25" /> (<?php echo date_i18n("$expirationdateDefaultTimeFormat") ?>)
						<br/>
						<?php _e( 'The default format to use when displaying the expiration time within a post using the [postexpirator] shortcode or within the footer. For information on valid formatting options, see: <a href="http://us2.php.net/manual/en/function.date.php" target="_blank">PHP Date Function</a>.', 'post-expirator'); ?>
					</td>
				</tr>
				<tr valign-"top">
					<th scope="row">
						<label for="expired-default-expiration-date">
							<?php _e( 'Default Date/Time Duration:', 'post-expirator');?>
						</label>
					</th>
					<td>
						<select name="expired-default-expiration-date" id="expired-default-expiration-date" onchange="expirationdate_toggle_defaultdate(this)">
							<option value="null" <?php echo ($expirationdateDefaultDate == 'null') ? ' selected="selected"' : ''; ?>>
								<?php _e( 'None', 'post-expirator');?>
							</option>
							<option value="custom" <?php echo ($expirationdateDefaultDate == 'custom') ? ' selected="selected"' : ''; ?>>
								<?php _e( 'Custom', 'post-expirator');?>
							</option>
							<option value="publish" <?php echo ($expirationdateDefaultDate == 'publish') ? ' selected="selected"' : ''; ?>>
								<?php _e( 'Post/Page Publish Time', 'post-expirator');?>
							</option>
						</select>
						<br/>
						<?php _e( 'Set the default expiration date to be used when creating new posts and pages. Defaults to none.', 'post-expirator'); ?>
						<?php $show = ($expirationdateDefaultDate == 'custom') ? 'block' : 'none'; ?>
						<div id="expired-custom-container" style="display: <?php echo $show; ?>;">
							<br/>
							<label for="expired-custom-expiration-date">Custom:</label> <input type="text" value="<?php echo $expirationdateDefaultDateCustom; ?>" name="expired-custom-expiration-date" id="expired-custom-expiration-date" />
							<br/>
							<?php _e( 'Set the custom value to use for the default expiration date.  For information on formatting, see <a href="http://php.net/manual/en/function.strtotime.php">PHP strtotime function</a>.', 'post-expirator'); ?>
						</div>
					</td>
				</tr>
			</table>
			<h3><?php _e( 'Category Expiration', 'post-expirator');?></h3>
			<table class="form-table">
				<tr valign-"top">
					<th scope="row">
						<?php _e('Default Expiration Category','post-expirator');?>:
					</th>
					<td>
						<?php
							echo '<div class="wp-tab-panel" id="post-expirator-cat-list">';
							echo '<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">';
							$walker = new Walker_PostExpirator_Category_Checklist();
							wp_terms_checklist(0, array( 'taxonomy' => 'category', 'walker' => $walker, 'selected_cats' => $categories, 'checked_ontop' => false ) );
							echo '</ul>';
							echo '</div>';
						?>
						<br/>
						<?php _e( "Set's the default expiration category for the post.", 'post-expirator');?>
					</td>
				</tr>
			</table>

			<h3><?php _e( 'Post Footer Display', 'post-expirator');?></h3>
			<p>
				<?php _e( 'Enabling this below will display the expiration date automatically at the end of any post which is set to expire.', 'post-expirator');?>
			</p>
			<table class="form-table">
				<tr valign-"top">
					<th scope="row">
						<?php _e( 'Show in post footer?', 'post-expirator');?>
					</th>
					<td>
						<input type="radio" name="expired-display-footer" id="expired-display-footer-true" value="1" <?php echo $expireddisplayfooterenabled ?>/> <label for="expired-display-footer-true"><?php _e('Enabled','post-expirator');?></label> 
						<input type="radio" name="expired-display-footer" id="expired-display-footer-false" value="0" <?php echo $expireddisplayfooterdisabled ?>/> <label for="expired-display-footer-false"><?php _e('Disabled','post-expirator');?></label>
						<br/>
						<?php _e( 'This will enable or disable displaying the post expiration date in the post footer.', 'post-expirator');?>
					</td>
				</tr>
				<tr valign-"top">
					<th scope="row">
						<label for="expired-footer-contents">
							<?php _e( 'Footer Contents:', 'post-expirator');?>
						</label>
					</th>
					<td>
						<textarea id="expired-footer-contents" name="expired-footer-contents" rows="3" cols="50"><?php echo $expirationdateFooterContents; ?></textarea>
						<br/>
						<?php _e( 'Enter the text you would like to appear at the bottom of every post that will expire.  The following placeholders will be replaced with the post expiration date in the following format:', 'post-expirator');?>
						<ul>
							<li>EXPIRATIONFULL -> <?php echo date_i18n("$expirationdateDefaultDateFormat $expirationdateDefaultTimeFormat") ?></li>
							<li>EXPIRATIONDATE -> <?php echo date_i18n("$expirationdateDefaultDateFormat") ?></li>
							<li>EXPIRATIONTIME -> <?php echo date_i18n("$expirationdateDefaultTimeFormat") ?></li>
						</ul>
					</td>
				</tr>
				<tr valign-"top">
					<th scope="row">
						<label for="expired-footer-style">
							<?php _e( 'Footer Style:', 'post-expirator');?>
						</label>
					</th>
					<td>
						<input type="text" name="expired-footer-style" id="expired-footer-style" value="<?php echo $expirationdateFooterStyle ?>" size="25" />
						(<span style="<?php echo $expirationdateFooterStyle ?>"><?php _e('This post will expire on','post-expirator');?> <?php echo date_i18n("$expirationdateDefaultDateFormat $expirationdateDefaultTimeFormat"); ?></span>)
						<br/>
						<?php _e( 'The inline css which will be used to style the footer text.', 'post-expirator');?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="expirationdateSave" class="button-primary" value="<?php _e( 'Save Changes');?>" />
			</p>
		</form>
		<?php
	}

	function tab_defaults() {
		$debug = postExpiratorDebug();
		$types = get_post_types(array('public' => true, '_builtin' => false));
		array_unshift($types,'post','page');

		if ( isset($_POST['expirationdateSaveDefaults']) ) {
			$defaults = array();
			foreach ($types as $type) {
				if (isset($_POST['expirationdate_expiretype-'.$type])) {
					$defaults[$type]['expireType'] = $_POST['expirationdate_expiretype-'.$type];
				}
				if (isset($_POST['expirationdate_autoenable-'.$type])) {
					$defaults[$type]['autoEnable'] = intval($_POST['expirationdate_autoenable-'.$type]);
				}
				if (isset($_POST['expirationdate_taxonomy-'.$type])) {
					$defaults[$type]['taxonomy'] = $_POST['expirationdate_taxonomy-'.$type];
				}
				if (isset($_POST['expirationdate_activemeta-'.$type])) {
					$defaults[$type]['activeMetaBox'] = $_POST['expirationdate_activemeta-'.$type];
				}

				//Save Settings
				update_option( 'expirationdateDefaults'.ucfirst($type),$defaults[$type]);		
			}
		}

		?>
		<form method="post">
			<h3><?php _e('Default Expiration Values','post-expirator');?></h3>
			<p>
				<?php _e( 'Use the values below to set the default actions/values to be used for each for the corresponding post types. These values can all be overwritten when creating/editing the post/page.', 'post-expirator'); ?>
			</p>
			<?php
			foreach ( $types as $type ) {
				$defaults = get_option('expirationdateDefaults'.ucfirst($type));
				if (isset($defaults['autoEnable']) && $defaults['autoEnable'] == 1) {
					$expiredautoenabled = 'checked = "checked"';
					$expiredautodisabled = '';
				} else {
					$expiredautoenabled = '';
					$expiredautodisabled = 'checked = "checked"';
				}
				if (isset($defaults['activeMetaBox']) && $defaults['activeMetaBox'] == 'inactive') {
					$expiredactivemetaenabled = '';
					$expiredactivemetadisabled = 'checked = "checked"';
				} else {
					$expiredactivemetaenabled = 'checked = "checked"';
					$expiredactivemetadisabled = '';
				} 
				print '<h4>Expiration values for: '.$type.'</h4>';
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="expirationdate_activemeta-<?php echo $type ?>"><?php _e('Active:','post-expirator');?></label>
						</th>
						<td>
							<input type="radio" name="expirationdate_activemeta-<?php echo $type ?>" id="expirationdate_activemeta-true-<?php echo $type ?>" value="active" <?php echo $expiredactivemetaenabled ?>/> <label for="expired-active-meta-true"><?php _e('Active','post-expirator');?></label> 
							<input type="radio" name="expirationdate_activemeta-<?php echo $type ?>" id="expirationdate_activemeta-false-<?php echo $type ?>" value="inactive" <?php echo $expiredactivemetadisabled ?>/> <label for="expired-active-meta-false"><?php _e('Inactive','post-expirator');?></label>
							<br/>
							<?php _e( 'Select whether the post expirator meta box is active for this post type.', 'post-expirator');?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="expirationdate_expiretype-<?php echo $type ?>">
								<?php _e('How to expire:','post-expirator'); ?>
							</label>
						</th>
						<td>
							<?php echo _postExpiratorExpireType(array('name'=>'expirationdate_expiretype-'.$type,'selected' => $defaults['expireType'])); ?>
							</select>	
							<br/>
							<?php _e('Select the default expire action for the post type.','post-expirator');?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="expirationdate_autoenable-<?php echo $type ?>">
								<?php _e( 'Auto-Enable?', 'post-expirator');?>
							</label>
						</th>
						<td>
							<input type="radio" name="expirationdate_autoenable-<?php echo $type ?>" id="expirationdate_autoenable-true-<?php echo $type ?>" value="1" <?php echo $expiredautoenabled ?>/> <label for="expired-auto-enable-true"><?php _e('Enabled','post-expirator');?></label> 
							<input type="radio" name="expirationdate_autoenable-<?php echo $type ?>" id="expirationdate_autoenable-false-<?php echo $type ?>" value="0" <?php echo $expiredautodisabled ?>/> <label for="expired-auto-enable-false"><?php _e('Disabled','post-expirator');?></label>
							<br/>
							<?php _e( 'Select whether the post expirator is enabled for all new posts.', 'post-expirator');?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="expirationdate_taxonomy-<?php echo $type ?>">
								<?php _e( 'Taxonomy (hierarchical):', 'post-expirator'); ?>
							</label>
						</th>
						<td>
							<?php echo _postExpiratorTaxonomy(array('type' => $type, 'name'=>'expirationdate_taxonomy-'.$type,'selected' => $defaults['taxonomy'])); ?>
							</select>	
							<br/>
							<?php _e( 'Select the hierarchical taxonomy to be used for "category" based expiration.', 'post-expirator');?>
						</td>
					</tr>
				</table>
				<?php
			}
			?>
			<p class="submit">
				<input type="submit" name="expirationdateSaveDefaults" class="button-primary" value="<?php _e( 'Save Changes');?>" />
			</p>
		</form>
		<?php
		}

		function tab_diagnostics() {
			if ( isset($_POST['debugging-disable']) ) {
				update_option( 'expirationdateDebug', 0);
				echo '<div id="message" class="updated fade"><p>' . __( 'Debugging Disabled', 'post-expirator') . '</p></div>';
			} elseif ( isset($_POST['debugging-enable']) ) {
				update_option( 'expirationdateDebug', 1);
				echo '<div id="message" class="updated fade"><p>' . __( 'Debugging Enabled', 'post-expirator') . '</p></div>';
			} elseif ( isset($_POST['purge-debug']) ) {
				$debug = new postExpiratorDebug();
				$debug->purge();
				echo '<div id="message" class="updated fade"><p>' . __( 'Debugging Table Emptied', 'post-expirator') . '</p></div>';
			}

			$debug = postExpiratorDebug();
			?>
			<form method="post" id="tab_Upgrade">
				<h3><?php _e( 'Advanced Diagnostics', 'post-expirator');?></h3>
				<table class="form-table">		
					<tr valign="top">
						<th scope="row">
							<label for="postexpirator-log">
								<?php _e( 'Post Expirator Debug Logging:', 'post-expirator');?>
							</label>
						</th>
						<td>
							<?php
							if (POSTEXPIRATOR_DEBUG) { 
								echo __( 'Status: Enabled', 'post-expirator').'<br/>';
								echo '<input type="submit" class="button" name="debugging-disable" id="debugging-disable" value="'.__( 'Disable Debugging', 'post-expirator').'" />';
							} else {
								echo __( 'Status: Disabled', 'post-expirator').'<br/>';
								echo '<input type="submit" class="button" name="debugging-enable" id="debugging-enable" value="'.__( 'Enable Debugging', 'post-expirator').'" />';
							}
							?>
							<br/>
							<a href="<?php echo admin_url('options-general.php?page=post-expirator.php&tab=viewdebug') ?>"><?php _e( 'View Debug Logs', 'post-expirator');?></a>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e( 'Purge Debug Log:', 'post-expirator');?>
						</th>
						<td>
							<input type="submit" class="button" name="purge-debug" id="purge-debug" value="<?php _e( 'Purge Debug Log', 'post-expirator');?>" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="cron-schedule">
								<?php _e( 'Current Cron Schedule:', 'post-expirator');?>
							</label>
						</th>
						<td>
							<?php _e( 'The below table will show all currently scheduled cron events with the next run time.', 'post-expirator');?><br/>
							<table>
								<tr>
									<th style="width: 200px;"><?php _e( 'Date', 'post-expirator');?></th>
									<th style="width: 200px;"><?php _e( 'Event', 'post-expirator');?></th>
									<th style="width: 500px;"><?php _e( 'Arguments / Schedule', 'post-expirator');?></th>
								</tr>
								<?php
								$cron = _get_cron_array();
								foreach ( $cron as $key => $value ) {
									foreach ( $value as $eventkey => $eventvalue ) {
										print '<tr>';
										print '<td>'.date_i18n('r',$key).'</td>';
										print '<td>'.$eventkey.'</td>';
										$arrkey = array_keys($eventvalue);
										print '<td>';
										foreach ( $arrkey as $eventguid ) {
											print '<table><tr>';					
											if ( empty($eventvalue[$eventguid]['args']) ) {
												print '<td>No Arguments</td>';
											} else {
												print '<td>';
												$args = array();
												foreach ( $eventvalue[$eventguid]['args'] as $key => $value ) {
													$args[] = "$key => $value";
												}
												print implode("<br/>\n",$args);
												print '</td>';
											}
											if ( empty($eventvalue[$eventguid]['schedule']) ) {
												print '<td>'.__( 'Single Event', 'post-expirator').'</td>';
											} else {
												print '<td>'.$eventvalue[$eventguid]['schedule'].' ('.$eventvalue[$eventguid]['interval'].')</td>';
											}
											print '</tr></table>';
										}
										print '</td>';
										print '</tr>';
									}
								}
								?>
							</table>
						</td>
					</tr>
				</table>
			</form>
		<?php
		}

		function tab_viewdebug() {
			print '<p>' . __( 'Below is a dump of the debugging table, this should be useful for troubleshooting.', 'post-expirator') . '</p>';
			$debug = new postExpiratorDebug();
			$debug->getTable();
		}
		
		public function plugin_action_links( $links, $file ) {
			if ( $file == plugin_basename(__FILE__) ) {
				$links[] = '<a href="options-general.php?page=post-expirator">' . __( 'Settings') . '</a>';
			}
			return $links;
		}
	
} // END class Post_Expirator_Admin

$post_expirator_admin = Post_Expirator_Admin::get_instance();
