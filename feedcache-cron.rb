#!/usr/bin/env ruby

# Add the path to your feedcache directory here
FEEDCACHE_DIR = '/Users/cpjolicoeur/Sites/gspolitics/wp-content/plugins/feedcache' #'/path/to/your/wordpress/wp-content/plugins/feedcache'
# How many characters from each feed item do you want to display
CHAR_COUNT = 75
# Set to 'true' if you want to receive error emails from the CRON job
CRON_EMAILS = false
# Run as threaded
THREADED = true #false


#################################################################
#                                                               #
#  DO NOT EDIT BELOW THIS LINE                                  #
#                                                               #
#################################################################
$LOAD_PATH << File.expand_path(File.dirname(__FILE__))

require 'net/http'
require 'lib/feedparser'
require 'uri'
require 'yaml'
require 'tempfile'

# Read master config settings
MASTER_CONFIG = "#{FEEDCACHE_DIR}/master-config.txt"
cfg = File.open(MASTER_CONFIG, 'r') do |f|
  @params = f.gets.split('~');
end
# parse the parameters from the config file
@groups_num   = @params[0].strip.to_i
@display_num  = @params[1].strip
@title_pre    = @params[2].strip
@title_post   = @params[3].strip
@format_text  = @params[4].strip == 'true' ? true : false
@link_target  = @params[5].strip == 'true' ? '_blank' : '_self'

# Load config and cache file variables
CONFIG_FILES, CACHE_FILES = [], []
1.upto(@groups_num) do |i|
  CONFIG_FILES << "#{FEEDCACHE_DIR}/files/feedcache-config#{i}.txt"
  CACHE_FILES <<  "#{FEEDCACHE_DIR}/files/feedcache-cache#{i}.txt"
end

# RSS formatting function
def shorten_text(txt)
  if txt.size > CHAR_COUNT
    @text = "#{txt} ".slice(0,CHAR_COUNT)
    # need to break on the last space
    if @text.include?(' ') and @text.slice(@text.size-1, 1) != ' '
      @text.slice!(0, @text.size - (@text.reverse.index(' ') + 1))
      @text << '...'
    end
    return @text
  else
    return txt
  end
end

if THREADED
  
  # fork a thread for each config file here
  CONFIG_FILES.each do |config|
    pid = fork {
      # exec scripts here
      system("/usr/bin/env ruby feedcache-lite.rb -p #{config}")
    }
    Process.detach(pid)
  end

else

  begin # read the config file settings
    @all_feeds = {}
    CONFIG_FILES.each_with_index do |cfg_file, idx|
      config = File.open(cfg_file, 'r') do |f|
        @feeds = []
        while line = f.gets
          @feeds << line.strip
        end
      end
      @all_feeds[idx] = @feeds
    end
  rescue => e
    if CRON_EMAILS
      puts "Error reading configuration file"
      puts YAML.dump(e)
    end
  end  

  @all_feeds.each do |k,v|
    @tmp = Tempfile.new("feedcache#{k}")
    @processed = 0

    # parse the feeds here
    v.each do |feed|
      #puts "\nFEED -> #{feed}\n"
      @html_text = ''
      data = feed.split('|')
      feed_url, feed_title, feed_num, feed_format = data[0], data[1], data[2], data[3]
      begin
        source = Net::HTTP::get URI::parse(feed_url)
        fp = FeedParser::Feed::new(source)
          @html_text << @title_pre + (feed_title || fp.title) + @title_post
          @html_text << "<ul>"
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
            @html_text << output
          end # end fp.items.each
          @html_text << "</ul><br />\n"
          @tmp << @html_text
          @processed += 1
      rescue => e
        puts "Error processing feed - Group #{k.to_i + 1} - #{feed_url}"
        puts YAML.dump(e)
      end  
    end

    @tmp.close
    # if we had new feeds, move them to the cache file
    if @processed > 0
      @tmp.open
      @cache = File::open(CACHE_FILES[k], "w")
      @cache << @tmp.gets(nil)
      @cache.close
      @tmp.close(true) # remove the /tmp file
    end
  end #--> @all_feeds.each do |k,v|
  
end # end if THREADED