=== FeedCache ===
Contributors: cpjolicoeur
Donate link: http://www.craigjolicoeur.com/feedcache
Tags: RSS, ATOM, feed, cache, Ruby, CRON
Requires at least: 2.3
Tested up to: 2.6.*
Stable tag: 1.0.6.1

Caches RSS Feeds for display on your WP sidebar. 

== Requirements ==

* Ruby
* Rubygems with the following gems available: active_record, feed_tools
	(If you are hosted with Dreamhost you have these gems already.  If you are hosted elsewhere you will need to check.  If you run a VPS or dedicated machine like me, then just install them via `sudo gem install`)
* CRON access

== Description ==

FeedCache will retrieve, cache and store locally a list of RSS feeds that you can then display on your WP site. This prevents multiple HTTP requests with each page load since the feeds can be read from the cache file.

== Installation ==

1. Upload the feedcache directory to your wordpress wp-content/plugins directory. Make sure the feedcache directory is writeable by the web server (rw-rw-rw 666).

2. Change the file permissions on the master-config.txt file to rw-rw-rw- (666)

3. Activate the FeedCache plugin through your wordpress plugin menu

4. Setup FeedCache options under the Options -> FeedCache Options menu
	(a) Choose the number of feed groups you want (1 - 99)
  (b) Add your list of RSS feeds 1 per line and set the other options.  You can have up to 4 different groupings of RSS feeds to cache.
      If you wish to manually override the name of the feed, place a pipe character "|" after the RSS URL and then type the name you want to use (e.g http://www.craigjolicoeur.com|Craig P Jolicoeur)
      If you wish to manually override the number of feed items to display, place a pipe character "|" after the feed title and enter the number to display
      If you wish to manually override the feed text formatting, place a pipe character "|" after the feed display number and put either of a true/false boolean value
  (c) Take note of the "CRON Script Settings" section at the bottom - you will need this info

5. Edit the feedcache-cron.rb file
  (a) Copy the "feedcache directory" path from the CRON Script Settings into the "FEEDCACHE_DIR = ''" variable
  (b) Set the number of characters from each RSS feed you want to display

6. Add the feedcache-cron.rb ruby script to your servers CRON job (if you need help with CRON please refer
   to the following URL: http://www.unixgeeks.org/security/newbie/unix/cron-1.html)
  (e.g. 30 * * * * /usr/bin/ruby /path/to/your/wordpress/install/wp-content/plugins/feedcache/feedcache-cron.rb)

7. Add the following code to your wordpress theme where you want the RSS feed listing to be displayed

	<?php if (function_exists('feedcache_display_feeds')) { echo feedcache_display_feeds(); } ?>  
	
	for the default (group 1) listing or you can specify a group (in this case, group 2) 

	<?php if (function_exists('feedcache_display_feeds')) { echo feedcache_display_feeds(2); } ?>  
	
	If you don't specify a group number in the function call, then Group 1 will be used.  If you want to specify the specific
	group number to display use "feedcache_display_feeds(_GROUP_NUMBER_)" where _GROUP_NUMBER_ is the number you want to display.

8. Sit back and enjoy the plugin



== Upgrading ==

**** IMPORTANT ************************************************************************
	If you are upgrading from a version of FeedCache prior to v0.9.8, please
	deactivate and delete your existing feedcache plugin directory and follow the new
	installation steps.
***************************************************************************************

1. Deactivate the previous version of FeedCache through your Wordpress plugins menu

2. Upload the new feedcache-cron.rb, feedcache.php and complete lib/ directory files to your existing feedcache directory.  You do not need to overwrite your master-config.txt file.

3. Enter the correct FEEDCACHE_DIR variable in the feedcache-cron.rb script

4. Go to Options -> FeedCache Options and update your FeedCache settings


== Frequently Asked Questions ==

= Why would I need this plugin? =

If you are using your WP installation to display other website's RSS feeds, this will save HTTP requests and improve page load times for your users.  By using a CRON job to fetch and format the feeds, the user will not have to wait during page load for the feed to be updated.

= Will FeedCache work with Atom feeds? = 

Yes!  FeedCache will work with both RSS and ATOM feeds.

= Can I receive error emails from the CRON process? =

Yes, just set CRON_EMAILS = true in the feedcache-cron.rb script.  Error emails are turned off by default.

= Can I have more than 10 RSS Groups = 

Yes, but you'll need to manually edit the feedcache.php file.  Find the following line:

  define("MAX_GROUPSIZE", 10);

and change the number 10 to whatever number you need.  (Just don't go overboard, :) )  Take note that when you upgrade the
FeedCache plugin in the future, you'll need to update this number every time.


== Screenshots ==

1. FeedCache Options page


