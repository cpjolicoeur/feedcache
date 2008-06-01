# TODO: update this to use the new config file setup and memory management code

$LOAD_PATH << File.expand_path(File.dirname(__FILE__))

require 'optparse'
require 'net/http'
require 'lib/feedparser'
require 'uri'
require 'yaml'

# Default options
options = {
  :display_num => 5,
  :title_pre => "<h3>",
  :title_post => "</h3>",
  :format_text => 'true',
  :link_target => '_blank',
  :char_count => 75, 
  :group => 1,
  :cron_emails => false
}
OptionParser.new do |opts|
  opts.banner = "Usage: feedcache-lite.rb [options]"

  opts.on("-v", "--[no-]verbose", "Run verbosely") do |v|
    options[:verbose] = v
  end
  
  opts.on("-e", "--cron-emails", "Send CRON emails") do |e|
    options[:cron_emails] = e
  end
  
  opts.on("-p PATH", "--path PATH", "Path of config file") do |p|
    options[:path] = p
  end
  
  opts.on("-g GROUP", "--group GROUP", "Group number to process") do |g|
    options[:group] = g.to_i
  end
  
  opts.on("-n NUM", "--num NUM", "Number of entries to show") do |n|
    options[:display_num] = n.to_i
  end
  
  opts.on("-r PRE", "--pre PRE", "Title pre text") do |r|
    options[:title_pre] = r
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
    options[:char_count] = c.to_i
  end
end.parse!

raise "You must specify at least the -g group number option" if options[:group].nil?

CONFIG_FILE = "#{options[:path]}"
CACHE_FILE = CONFIG_FILE.gsub(/config/, 'cache')

# RSS formatting function
def shorten_text(txt, char_count)
  if txt.size > char_count
    text = "#{txt} ".slice(0,char_count)
    # need to break on the last space
    if text.include?(' ') and text.slice(text.size-1, 1) != ' '
      text.slice!(0, text.size - (text.reverse.index(' ') + 1))
      text << '...'
    end
    return @text
  else
    return txt
  end
end

begin # read the config file settings
  feeds = []
  yaml_config = YAML.load_file(CONFIG_FILE)
  yaml_config["group#{options[:group]}"].each { |x| feeds << x.strip if (!x.nil? && !x.strip.blank?) }
  feeds = nil
rescue => e
  if options[:cron_emails]
    puts "Error reading configuration file"
    puts e.inspect
    puts e.backtrace
  end
end  

tmp = ''
@processed = 0

# parse the feeds here
feeds.each do |feed|
  html_text = ''
  data = feed.split('|')
  feed_url, feed_title, feed_num, feed_format = data[0], data[1], data[2], data[3]
  begin
    source = Net::HTTP::get URI::parse(feed_url)
    fp = FeedParser::Feed::new(source)
      html_text << options[:title_pre] + (feed_title || fp.title) + options[:title_post]
      html_text << "<ul>"
      fp.items.each_with_index do |item, idx|
        break if feed_num ? feed_num.to_i == idx.to_i : options[:display_num].to_i == idx.to_i
        output = ''
        output << "<li><a href='#{item.link}' target='#{options[:link_target]}'>"
        if feed_format && feed_format == 'true'
          txt = "#{item.title.downcase.gsub(/^[a-z]|\s+[a-z]/) {|a| a.upcase}}"
          output << shorten_text(txt, options[:char_count])
        elsif options[:format_text] == 'true'
          txt = "#{item.title.downcase.gsub(/^[a-z]|\s+[a-z]/) {|a| a.upcase}}"
          output << shorten_text(txt, options[:char_count])
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
    puts "Error processing feed - #{feed_url}"
    puts e.inspect
    puts e.backtrace
  end  
end

# if we had new feeds, move them to the cache file
if @processed > 0
  cache = File::open(CACHE_FILE, "w")
  cache << tmp
  cache.close
end
