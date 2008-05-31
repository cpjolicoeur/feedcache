<?php
/*
Plugin Name: FeedCache
Plugin URI: http://www.craigjolicoeur.com/feedcache
Description: Caches RSS Feeds for display on WP site sidebar.  This prevents multiple HTTP requests with each page load since the feeds can be read from the cache file.
Author: Craig P Jolicoeur
Version: 0.9.9
Author URI: http://www.craigjolicoeur.com/
*/

// Constants
define("MAX_GROUPSIZE", 10);
define("FEEDCACHE_PATH", ABSPATH . "wp-content/plugins/feedcache");
define("FEEDCACHE_FILES_PATH", FEEDCACHE_PATH . '/' . "files/");
define("DEFAULT_PRE_TAG", "<h3>");
define("DEFAULT_POST_TAG", "</h3>");
define("DEFAULT_FORMAT_TEXT", "true");
define("DEFAULT_GROUP_NUM", '4');
define("DEFAULT_DISPLAY_NUM", '5');
define("CHAR_COUNT", 75);
define("MAGPIE_CACHE_DIR", FEEDCACHE_PATH . '/cache'); // set cache path
define("MAGPIE_CACHE_ON", 1); // turn on RSS caching
define('MAGPIE_CACHE_AGE', 60*60); // one hour

//***************************************************
//
//         DO NOT EDIT BELOW THIS LINE                         
//
//***************************************************

// Include RSS functions
include_once(ABSPATH . WPINC . '/rss.php');
// Include Spyc PHP YAML library
include_once(FEEDCACHE_PATH . '/lib/spyc/spyc.php');

// Wordpress hooks
add_action('admin_menu', 'feedcache');

// Functions
function feedcache_display_feeds($rss_group = '1', $fname = 'feedcache-config') {
	list($groups_num, $display_num, $title_pre, $title_post, $format_text, $link_target) = load_master_config();
	$format_text = ($format_text == 'true') ? TRUE : FALSE;
	$link_target = ($link_target == 'true') ? '_blank' : '_self';
	
	$fname = FEEDCACHE_FILES_PATH . trim($fname) . "$rss_group.txt";
	$output = "";
	if ($file_handle = fopen($fname, "r")) {
		while (!feof($file_handle)) {
			$line = trim(fgets($file_handle));
			if (empty($line)) continue; // if there is a blank line in the config file just continue
			$feed_info = explode('|', $line);
			$feed = fetch_rss($feed_info[0]);
			if (!is_array($feed->items)) break; // not sure why $feed->items is not always an array, but check for it here
			$items = array_slice($feed->items, 0, (isset($feed_info[2])) ? intval($feed_info[2]) : intval($display_num));
			$output .= $title_pre . (isset($feed_info[1]) ? $feed_info[1] : $feed->channel['title'] ) . $title_post;
			$output .= "<ul>";
			if (empty($items)) {
				$output .= "<li>No items</li>";
			} else {
				foreach ($items as $item) {
					//*******************************
					//* still need to check if feed
					//* title needs to be formatted
					//*******************************
					$output .= "<li><a href='" . $item['link'] . "' title='" . $item['title'] . "'>" . $item['title'] . "</a></li>";
				}
			}
			$output .= "</ul>";
		}
		fclose($file_handle);
		echo $output;
	} else {
		$output .= "Cannot find FeedCache feed group config file!";
		echo $output;
	}
}

function shorten_text($string='', $chars=CHAR_COUNT, $elli='...') {
	list($new_string, $elli)= explode("\n", wordwrap($string, $chars, "\n", false));
	return  ( $elli ) ? $new_string.'...' : $new_string;
}

function load_master_config() {
	$fname = FEEDCACHE_PATH . "/master-config.txt";
	if (file_exists($fname)) {
		$file_content = trim(file_get_contents($fname));
		return explode('~', $file_content);
	} else {
		echo "FeedCache Master Config file not found!";
		exit;
	}
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
	$fpath = FEEDCACHE_PATH . "/$fname" . ".txt";

  if (is_writable($fpath)) {
    if (!$handle = fopen($fpath, "w")) {
      echo "Cannot open file ($fpath)";
      exit;
    }
    if (fwrite($handle, "$group_num~$display_num~$title_pre~$title_post~$format_text~$target_blank") === FALSE) {
      echo "Cannot write to file ($fpath)";
      exit;
    }
    fclose($handle);
  } else {
    echo "The master-config file ($fpath) is not writable";
  }
}

function feedcache() {
    global $wpdb;
    if (function_exists('add_submenu_page'))
        add_submenu_page('plugins.php', __('FeedCache Options'), __('FeedCache Options'), 1, __FILE__, 'feedcache_subpanel');
}

function feedcache_subpanel() {
		for ($i=1; $i<=MAX_GROUPSIZE; $i++) {
			add_option("feedcache_rss_list$i", '');
		}
    add_option('feedcache_group_num', DEFAULT_GROUP_NUM);
    add_option('feedcache_display_num', DEFAULT_DISPLAY_NUM);
    add_option('feedcache_title_pre', DEFAULT_PRE_TAG);
    add_option('feedcache_title_post', DEFAULT_POST_TAG);
    add_option('feedcache_format_text', DEFAULT_FORMAT_TEXT);
		add_option('feedcache_target_blank', false);

		if ($_POST['stage'] == 'prep') {
			update_option('feedcache_group_num', $_POST['feedcache_group_num']);
		}
    
		$number_of_groups = get_option('feedcache_group_num');

    if ($_POST['stage'] == 'process' ) {
			// $group_array = [];

			for ($i=1; $i<$number_of_groups; $i++) {
				update_option("feedcache_rss_list$i", $_POST["feedcache_rss_list$i"]);
				$group_array["group$i"] = explode("\n", $_POST["feedcache_rss_list$i"]);
			}
			fc_build_config_file($group_array);

			// update other variables and master config
			update_option('feedcache_display_num', $_POST['feedcache_display_num']);
			update_option('feedcache_title_pre', $_POST['feedcache_title_pre']);
			update_option('feedcache_title_post', $_POST['feedcache_title_post']);
			update_option('feedcache_format_text', $_POST['feedcache_format_text']);
			update_option('feedcache_target_blank', $_POST['feedcache_target_blank']);
			fc_build_master_config($number_of_groups, $_POST['feedcache_display_num'], $_POST['feedcache_title_pre'], $_POST['feedcache_title_post'], $_POST['feedcache_format_text'], $_POST['feedcache_target_blank']);
    }
?>
    
    <div class="wrap">
        <h2 id="write-post">FeedCache&hellip;</h2>
        <p>Fill in the list of RSS feeds you want to process and how you want them formatted and displayed on your site.  If you notice any bugs or have suggestions please contact the developers at <a href="http://www.craigjolicoeur.com/feedcache" title="Craig Jolicoeurs's Home Page">FeedCache on craigjolicoeur.com</a>.</a></p>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=feedcache/feedcache.php" name="prep_form">
            <input type="hidden" name="stage" value="prep" />
            <fieldset class="options">
                <legend>FeedCache Options</legend>

								<div>
									How many RSS Groups do you want?
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
								</div>
						</fieldset>
				</form>
									
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=feedcache/feedcache.php">
            <input type="hidden" name="stage" value="process" />
            <fieldset class="options">
                <div>
		  <p>Enter the list of RSS Feeds [1 per line]</p>
		  <p>Following the RSS URL, there are up to three (3) additional options you can specify for each feed: 1. Feed title, 2.
		  Number of items to display, 3. Option to enable special formatting of feed text (boolean value - true/false)</p>
		  <p>If you wish to specify the third option, you MUST also set the first two options.  If you wish to set the
		  second option, you MUST also specify the first option</p>
		  <p>All options should be separated by the pipe ('|') character with no spaces before and after the pipe</p>
          <p>(e.g. http://www.yourfeed.com/feed|Feed Title|4|false)</p>

					<?php
						for($i=1; $i<=$number_of_groups; $i++) {
					?>
						<h3>Group <?php echo $i; ?></h3>
					  <textarea name="feedcache_rss_list<?php echo $i; ?>" rows="10" style="width:90%;"><?php echo get_option("feedcache_rss_list$i"); ?></textarea>
					<?php
						}
					?>
				
		</div>
		<div>
		  <p>
		    <label for='feedcache_display_num'>Number of articles to display: </label>
		    <input type="text" name="feedcache_display_num" style="width: 20px;" value=<?php echo '\'' . get_option('feedcache_display_num') . '\''; ?> />
		  </p>
			<p>
			  <label for='feedcache_format_text'>Format feed text for proper capitalization: </label>
			  <select name="feedcache_format_text">
			    <option value='true' <?php if (get_option('feedcache_format_text') == 'true') { echo 'selected'; } ?> >True</option>
			    <option value='false' <?php if (get_option('feedcache_format_text') == 'false') { echo 'selected'; } ?> >False</option>
			  </select>
		  </p>
			<p>
			  <label for='feedcache_target_blank'>Open feed links in a new window: </label>
			  <select name="feedcache_target_blank">
			    <option value='true' <?php if (get_option('feedcache_target_blank') == 'true') { echo 'selected'; } ?> >True</option>
			    <option value='false' <?php if (get_option('feedcache_target_blank') == 'false') { echo 'selected'; } ?> >False</option>
			  </select>
		  </p>
		  <p>
		    <label for="feedcache_title_pre_post">Feed Title Display Pre/Post Tags: </label>
                    <input type="text" name="feedcache_title_pre" style="width:50px;" value=<?php echo '\''. get_option('feedcache_title_pre') . '\''; ?> />&nbsp;/&nbsp;
                    <input type="text" name="feedcache_title_post" style="width:50px;" value=<?php echo '\''. get_option('feedcache_title_post') . '\''; ?> /> <em style="font-size:11px;">e.g &lt;h2&gt; / &lt;/h2&gt;</em>
		  </p>
		</div>
	    </fieldset>

      <p class="submit"><input type="submit" value="Update FeedCache Preferences &raquo;" name="Submit" /></p>

	    <fieldset id="script-settings" class="options">
	        <legend>CRON Script Settings</legend>
		<p> 
		    Here is the <b>feedcache directory path</b> to use in the CRON script:<br />
		    <?php echo FEEDCACHE_PATH . "<br />"; ?>
		</p>
            </fieldset>
        </form>
    </div>

<?php            
}
?>
