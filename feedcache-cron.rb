#!/usr/bin/env ruby

# Add the path to your feedcache directory here
FEEDCACHE_DIR = '/path/to/your/wordpress/wp-content/plugins/feedcache'
# How many characters from each feed item do you want to display
CHAR_COUNT = 75
# Set to 'true' if you want to receive error emails from the CRON job
CRON_EMAILS = false

#################################################################
#                                                               #
#  DO NOT EDIT BELOW THIS LINE                                  #
#                                                               #
#################################################################
$LOAD_PATH << File.expand_path(File.dirname(__FILE__))

require 'rubygems'
require 'active_record'
require 'feed_tools'
require 'yaml'

# Read master config settings
MASTER_CONFIG = "#{FEEDCACHE_DIR}/master-config.txt"
params = []
cfg = File.open(MASTER_CONFIG, 'r') do |f|
  params = f.gets.split('~');
end
# parse the parameters from the config file
@groups_num   = params[0].strip.to_i
@display_num  = params[1].strip
@title_pre    = params[2].strip
@title_post   = params[3].strip
@format_text  = params[4].strip == 'true' ? true : false
@link_target  = params[5].strip == 'true' ? '_blank' : '_self'
WPDB_PREFIX   = params[6].strip
@wpdb_host    = params[7].strip
@wpdb_name    = params[8].strip
@wpdb_user    = params[9].strip
@wpdb_pass    = params[10].strip

ActiveRecord::Base.establish_connection(
  :adapter  => 'mysql',
  :host     => @wpdb_host,
  :username => @wpdb_user,
  :password => @wpdb_pass,
  :database => @wpdb_name
)

class WPFeed < ActiveRecord::Base
  set_table_name "#{WPDB_PREFIX}feedcache_data"
end

# Load config and cache file variables
CONFIG_FILE = "#{FEEDCACHE_DIR}/feedcache-config.yml"

# RSS formatting function
def shorten_text(txt)
  if txt.size > CHAR_COUNT
    text = "#{txt} ".slice(0,CHAR_COUNT)
    # need to break on the last space
    if text.include?(' ') and text.slice(text.size-1, 1) != ' '
      text.slice!(0, text.size - (text.reverse.index(' ') + 1))
      text << '...'
    end
    return text
  else
    return txt
  end
end

begin # read the config file settings
  @all_feeds = {}
  yaml_config = YAML.load_file(CONFIG_FILE)
  1.upto(@groups_num) do |num|
    feeds = []
    next if yaml_config["group#{num}"].nil?
    yaml_config["group#{num}"].each {|x| feeds << x.strip if (!x.nil? && !x.strip.blank?) }
    @all_feeds[num] = feeds
    feeds = nil
  end
rescue => e
  if CRON_EMAILS
    puts "Error reading YAML configuration file"
    puts e.inspect
    puts e.backtrace
  end
end  

@all_feeds.each do |k,v|
  tmp = ''
  @processed = 0

  # parse the feeds here
  v.each do |feed|
    html_text = ''
    data = feed.split('|')
    feed_url, feed_title, feed_num, feed_format = data[0], data[1], data[2], data[3]
    begin
      fp = FeedTools::Feed.open(feed_url)
      html_text << @title_pre + (feed_title || fp.title || '') + @title_post
      html_text << "<ul>"
      fp.items.each_with_index do |item, idx|
        break if feed_num ? feed_num.to_i == idx.to_i : @display_num.to_i == idx.to_i
        output = ''
        output << "<li><a href='#{item.link}' target='#{@link_target}'>"
        if feed_format && feed_format == 'true'
          txt = "#{item.title.downcase.gsub(/^[a-z]|\s+[a-z]/) {|a| a.upcase}}"
          output << shorten_text(txt)
        elsif @format_text == true
          txt = "#{item.title.downcase.gsub(/^[a-z]|\s+[a-z]/) {|a| a.upcase}}"
          output << shorten_text(txt)
        else
          output << "#{item.title}"
        end
        output << "</a></li>\n"
        html_text << output
      end # end fp.items.each
      html_text << "</ul><br />\n"
      tmp << html_text
      @processed += 1
    rescue => e
      if CRON_EMAILS
        puts "Error processing feed - Group #{k.to_i + 1} - #{feed_url}"
        puts e.inspect
        puts e.backtrace
      end
    end  
  end

  # if we had new feeds, move them to the cache file
  if @processed > 0
    wp_data = WPFeed.find_or_initialize_by_group_id(k)
    wp_data.update_attributes(:data => tmp, :updated_at => Time.now)
  end
end #--> @all_feeds.each do |k,v|
