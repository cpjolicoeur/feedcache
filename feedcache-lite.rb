$LOAD_PATH << File.expand_path(File.dirname(__FILE__))

CRON_EMAILS = false

require 'optparse'
require 'net/http'
require 'lib/feedparser'
require 'uri'
require 'yaml'
require 'tempfile'

# Default options
options = {
  :display_num => 5,
  :title_pre => "<h3>",
  :title_post => "</h3>",
  :format_text => 'true',
  :link_target => '_blank',
  :char_count => 75
}
OptionParser.new do |opts|
  opts.banner = "Usage: feedcache-lite.rb [options]"

  opts.on("-v", "--[no-]verbose", "Run verbosely") do |v|
    options[:verbose] = v
  end
  
  opts.on("-p PATH", "--path PATH", "File path to process") do |p|
    options[:path] = p
  end
  
  opts.on("-n NUM", "--num NUM", "Number of entries to show") do |n|
    options[:display_num] = n
  end
  
  opts.on("-r PRE", "--pre PRE", "Title pre text") do |p|
    options[:title_pre] = p
  end
  
  opts.on("-s POST", "--post POST", "Title post text") do |s|
    options[:title_post] = s
  end
  
  opts.on("-f FORMAT", "--format FORMAT", ['true', 'false'], "Format text [true|false]") do |f|
    options[:format_text] = f
  end
  
  opts.on("-l LINK", "--link LINK", "HTML link target") do |l|
    options[:link_target] = l
  end
  
  opts.on("-c COUNT", "--count COUNT", "Character count") do |c|
    options[:char_count] = c
  end
end.parse!

CONFIG_FILE = "#{options[:path]}"
CACHE_FILE = CONFIG_FILE.gsub(/config/, 'cache')

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

begin # read the config file settings
  @feeds = []
  config = File.open(CONFIG_FILE, 'r') do |f|
    while line = f.gets
      @feeds << line.strip
    end
  end
rescue => e
  if CRON_EMAILS
    puts "Error reading configuration file"
    puts YAML.dump(e)
  end
end  

@tmp = Tempfile.new("feedcache#{Time.now.to_i}")
@processed = 0

# parse the feeds here
@feeds.each do |feed|
  #puts "\nFEED -> #{feed}\n"
  @html_text = ''
  data = feed.split('|')
  feed_url, feed_title, feed_num, feed_format = data[0], data[1], data[2], data[3]
  begin
    source = Net::HTTP::get URI::parse(feed_url)
    fp = FeedParser::Feed::new(source)
      @html_text << options[:title_pre] + (feed_title || fp.title) + options[:title_post]
      @html_text << "<ul>"
      fp.items.each_with_index do |item, idx|
        break if feed_num ? feed_num.to_i == idx.to_i : options[:display_num].to_i == idx.to_i
        output = ''
        output << "<li><a href='#{item.link}' target='#{options[:link_target]}'>"
        if feed_format && feed_format == 'true'
          txt = "#{item.title.downcase.gsub(/^[a-z]|\s+[a-z]/) {|a| a.upcase}}"
          output << shorten_text(txt)
        elsif options[:format_text] == 'true'
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
    puts "Error processing feed - #{feed_url}"
    puts YAML.dump(e)
  end  
end

@tmp.close
# if we had new feeds, move them to the cache file
if @processed > 0
  @tmp.open
  @cache = File::open(CACHE_FILES, "w")
  @cache << @tmp.gets(nil)
  @cache.close
  @tmp.close(true) # remove the /tmp file
end
