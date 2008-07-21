<?php
/*
Plugin Name: FeedCache
Plugin URI: http://www.craigjolicoeur.com/feedcache
Description: Caches RSS Feeds for display on WP site sidebar.  This prevents multiple HTTP requests with each page load since the feeds can be read from the cache file.
Author: Craig P Jolicoeur
Version: 1.0.5
Author URI: http://www.craigjolicoeur.com/
*/

//***************************************************
//
//         DO NOT EDIT BELOW THIS LINE                         
//
//***************************************************

// Pre-2.6 compatibility
if ( !defined('WP_CONTENT_URL') ) {
	define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}
if ( !defined('WP_CONTENT_DIR') ) {
	define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

// Constants
define("FEEDCACHE_PATH", WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__)));
define("FEEDCACHE_FILES_PATH", FEEDCACHE_PATH . '/');
define("DEFAULT_PRE_TAG", "<h3>");
define("DEFAULT_POST_TAG", "</h3>");
define("DEFAULT_FORMAT_TEXT", "true");
define("DEFAULT_GROUP_NUM", '4');
define("DEFAULT_DISPLAY_NUM", '5');
define("CHAR_COUNT", 75);
define("MAX_GROUPSIZE", 10);

// Include Spyc PHP YAML library
include_once(FEEDCACHE_PATH . '/lib/spyc/spyc.php');

// Wordpress hooks
add_action('admin_menu', 'feedcache');
register_activation_hook(__FILE__, 'feedcache_install');

// DB setup
$feedcache_db_version = "1.0";
function feedcache_install () {
	global $wpdb;
	global $feedcache_db_version;
	
	// Install DB table if not already installed
	$table_name = $wpdb->prefix . "feedcache_data";
	if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			group_id mediumint(9) NOT NULL,
			data text default NULL,
			updated_at datetime default NULL,
			UNIQUE KEY id (id),
			UNIQUE KEY group_id (group_id)
			);";
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			
			add_option("feedcache_db_version", $feedcache_db_version);
	}

	// Check if DB table upgrade is needed
	$installed_ver = get_option("feedcache_db_version");
	if ($installed_ver != $feedcache_db_version) {
		$sql = ""; // New DB schema here
		
		require_once(ABSPATH . 'wp-admin/inclues/upgrade.php');
		dbDelta($sql);
		
		update_option("feedcache_db_version", $feedcache_db_verion);
	}
	
	$new_groups = array();
	$new_options = array(
		'group_num' => DEFAULT_GROUP_NUM,
		'display_num' => DEFAULT_DISPLAY_NUM,
		'title_pre' => DEFAULT_PRE_TAG,
		'title_post' => DEFAULT_POST_TAG,
		'format_text' => DEFAULT_FORMAT_TEXT,
		'target_blank' => false
	);
	// if old options exist, update to new system
	foreach ( $new_options as $key => $value ) {
		if( $existing = get_option('feedcache_'.$key) ) {
			$new_options[$key] = $existing;
			delete_option('feedcache_'.$key);
		}
	}
	// if old groups exist, update to new system
	for ($i=1; $i<=99; $i++) {
		if( $existing = get_option("feedcache_rss_list$i") ) {
			$new_groups["group$i"] = $existing;
			delete_option("feedcache_rss_list$i");
		}
	}
	add_option('plugin_feedcache_groups', $new_groups);
	add_option('plugin_feedcache_options', $new_options);
	
}

// Functions
function feedcache_display_feeds($rss_group = '1', $fname = 'feedcache-cache') {
	global $wpdb;
	$feed_data = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "feedcache_data WHERE group_id = $rss_group");
	return $feed_data->data;
}

function fc_build_config_file($group_array, $fname = 'feedcache-config') {
		$fpath = FEEDCACHE_FILES_PATH . "$fname.yml";
		$yaml = Spyc::YAMLDump($group_array);

		// create the config file if it doesn't exist and make it writeable
		if (!file_exists($fpath)) {
			$tmp_handle = fopen($fpath, 'w') or die("can't create file");
			fclose($tmp_handle);
			chmod($fpath, 0666);
		}

	  if (is_writable($fpath)) {
	    if (!$handle = fopen($fpath, "w")) {
	      echo "Cannot open file ($fpath)";
	      exit;
	    }
	    if (fwrite($handle, "$yaml") === FALSE) {
	      echo "Cannot write to file ($fpath)";
	      exit;
	    }
	    fclose($handle);
	  } else {
	    echo "The config file ($fpath) is not writable";
	  }
}

function fc_build_master_config($group_num, $display_num, $title_pre, $title_post, $format_text, $target_blank, $fname = 'master-config') {
	global $wpdb;
	$fpath = FEEDCACHE_PATH . "/$fname" . ".txt";

  if (is_writable($fpath)) {
    if (!$handle = fopen($fpath, "w")) {
      echo "Cannot open file ($fpath)";
      exit;
    }
    if (fwrite($handle, "$group_num~$display_num~$title_pre~$title_post~$format_text~$target_blank~$wpdb->prefix~".DB_HOST."~".DB_NAME."~".DB_USER."~".DB_PASSWORD) === FALSE) {
      echo "Cannot write to file ($fpath)";
      exit;
    }
    fclose($handle);
  } else {
    echo "The master-config file ($fpath) is not writable";
  }
}

function feedcache() {
	if ( current_user_can('switch_themes') ) {
    add_submenu_page('plugins.php', 'FeedCache Options', 'FeedCache', 'switch_themes', __FILE__, 'feedcache_subpanel');
	}
}

function feedcache_admin_footer() {
	$plugin_data = get_plugin_data( __FILE__ );
	printf('%1$s plugin | Version %2$s | by %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']); 
}

function feedcache_subpanel() {
	
	add_action('in_admin_footer', 'feedcache_admin_footer');

	if ($_POST['stage'] == 'prep') {
		$options = get_option('plugin_feedcache_options');
		$options['group_num'] = $_POST['feedcache_group_num'];
		update_option('plugin_feedcache_options', $options);
	}
   
	$options = get_option('plugin_feedcache_options');
	$number_of_groups = $options['group_num'];

  if ($_POST['stage'] == 'process' ) {
		for ($i=1; $i<=$number_of_groups; $i++) {
			$group_array["group$i"] = $_POST["feedcache_rss_list$i"]; //explode("\n", $_POST["feedcache_rss_list$i"]);
		}
		update_option('plugin_feedcache_groups', $group_array);
		fc_build_config_file($group_array);

		// update other variables and master config
		$new_options = $_POST['feedcache'];
		$new_options['group_num'] = $number_of_groups;
		update_option('plugin_feedcache_options', $new_options);
		fc_build_master_config($new_options['group_num'], $new_options['display_num'], $new_options['title_pre'], $new_options['title_post'], $new_options['format_text'], $new_options['target_blank']);
  }

	$options = get_option('plugin_feedcache_options');
	$groups = get_option('plugin_feedcache_groups');
?>
    
   <div class="wrap">
       <h2 id="write-post">FeedCache&hellip;</h2>
       <p>Fill in the list of RSS feeds you want to process and how you want them formatted and displayed on your site.  If you notice any bugs or have suggestions please contact the developers at <a href="http://www.craigjolicoeur.com/feedcache" title="Craig Jolicoeurs's Home Page">FeedCache on craigjolicoeur.com</a>.</a></p>
       <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" name="prep_form">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">How many RSS Groups do you want?</th>
							<td>
								<label for="feedcache_group_num">
									<select name="feedcache_group_num" onchange="document.prep_form.submit();">
										<?php
											for($i=1; $i <= MAX_GROUPSIZE; $i++) {
												if($i == $number_of_groups) {
													echo "<option value='$i' selected>$i</option>";
												}
												else {
													echo "<option value='$i'>$i</option>";
												}
											}
										?>
									</select>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
         <input type="hidden" name="stage" value="prep" />
			</form><!-- end #prep_form -->
								
       <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		  	<div>
					<p>Enter the list of RSS Feeds [1 per line]</p>
				  <p>Following the RSS URL, there are up to three (3) additional options you can specify for each feed: 1. Feed title, 2.
				  Number of items to display, 3. Option to enable special formatting of feed text (boolean value - true/false)</p>
				  <p>If you wish to specify the third option, you MUST also set the first two options.  If you wish to set the
				  second option, you MUST also specify the first option</p>
				  <p>All options should be separated by the pipe ('|') character with no spaces before and after the pipe</p>
		      <p>(e.g. http://www.yourfeed.com/feed|Feed Title|4|false)</p>
				</div>
				<table class="form-table">
					<tbody>
						<?php
							for ($i=1; $i<=$number_of_groups; $i++) {
						?>
							<tr valign="top">
								<th scope="row">Group <?php echo $i; ?></th>
								<td>
									<textarea name="feedcache_rss_list<?php echo $i; ?>" rows="10" cols="60" style="width:98%;"><?php echo $groups["group$i"]; ?></textarea>
								</td>
							</tr>
						<?php
							}
						?>
						<tr valign="top">
							<th scope="row">Number of articles to display:</th>
							<td><input type="text" name="feedcache[display_num]" style="width:20px;" value="<?php echo $options['display_num']; ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Format feed text for capitalization: </th>
							<td>
								<label for"feedcache[format_text]">
									<select name="feedcache[format_text]">
								    <option value='true' <?php if ($options['format_text'] == 'true') { echo 'selected'; } ?> >True</option>
								    <option value='false' <?php if ($options['format_text'] == 'false') { echo 'selected'; } ?>										</select>
								</label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Open feed links in a new window:</th>
							<td>
								<label for"feedcache[target_blank]">
									<select name="feedcache[target_blank]">
								    <option value='true' <?php if ($options['target_blank'] == 'true') { echo 'selected'; } ?> >True</option>
								    <option value='false' <?php if ($options['target_blank'] == 'false') { echo 'selected'; } ?>										</select>
								</label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Feed title display pre/post tags:</th>
							<td>
                 <input type="text" name="feedcache[title_pre]" style="width:50px;" value=<?php echo '\''. $options['title_pre'] . '\''; ?> />&nbsp;/&nbsp;
                 <input type="text" name="feedcache[title_post]" style="width:50px;" value=<?php echo '\''. $options['title_post'] . '\''; ?> /> <em style="font-size:11px;">e.g &lt;h2&gt; / &lt;/h2&gt;</em>
							</td>
						</tr>
					</tbody>
				</table>

	      <div class="submit">
					<input type="submit" value="Update FeedCache Preferences &raquo;" name="Submit" />
				</div>
				<input type="hidden" name="stage" value="process" />
			</form><!-- end main form -->
				
			
			<div class="options" id="script-settings">
        <h3>CRON Script Settings</h3>
				<p> 
			    Here is the <b>feedcache directory path</b> to use in the CRON script:<br />
			    <?php echo FEEDCACHE_PATH . "<br />"; ?>
				</p>
			</div>
   </div><!-- end .wrap -->

<?php            
}
?>
