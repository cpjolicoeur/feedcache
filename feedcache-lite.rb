require 'optparse'

# Default options
options = {
  :display_num => 5,
  :title_pre => "<h3>",
  :title_post => "</h3>",
  :format_text => true,
  :link_target => '_blank'
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
end.parse!

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

