<?php
require_once('markdown.php');
define( 'MARKDOWNEXTRAEXTENDED_VERSION',  "0.3" );

function MarkdownExtended($text, $default_claases = array()){
  $parser = new MarkdownExtraExtended_Parser($default_claases);
  return $parser->transform($text);
}

class MarkdownExtraExtended_Parser extends MarkdownExtra_Parser {
	# Tags that are always treated as block tags:
	var $block_tags_re = 'figure|figcaption|p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|form|fieldset|iframe|hr|legend';
	var $default_classes;
		
	function MarkdownExtraExtended_Parser($default_classes = array()) {
	    $default_classes = $default_classes;
		
		$this->block_gamut += array(
			"doFencedFigures" => 7,
			"doTodoLists" => 35
		);
		
		parent::MarkdownExtra_Parser();
	}
	
	function transform($text) {	
		$text = parent::transform($text);				
		return $text;		
	}
	
	//function doHardBreaks($text) {
		//# Do hard breaks:
		//# EXTENDED: changed to allow breaks without two spaces and just one new line
		//# original code [> return preg_replace_callback('/ {2,}\n/', <]
		//return preg_replace_callback('/ *\n/', 
			//array(&$this, '_doHardBreaks_callback'), $text);
	//}


	function doBlockQuotes($text) {
		$text = preg_replace_callback('/
			(?>^[ ]*>[ ]?
				(?:\((.+?)\))?
				[ ]*(.+\n(?:.+\n)*)
			)+	
			/xm',
			array(&$this, '_doBlockQuotes_callback'), $text);

		return $text;
	}
	
	function _doBlockQuotes_callback($matches) {
		$cite = $matches[1];
		$bq = '> ' . $matches[2];
		# trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->runBlockGamut($bq);		# recurse

		$bq = preg_replace('/^/m', "  ", $bq);
		# These leading spaces cause problem with <pre> content, 
		# so we need to fix that:
		$bq = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx', 
			array(&$this, '_doBlockQuotes_callback2'), $bq);
		
		$res = "<blockquote";
		$res .= empty($cite) ? ">" : " cite=\"$cite\">";
		$res .= "\n$bq\n</blockquote>";
		return "\n". $this->hashBlock($res)."\n\n";
	}

	function doFencedCodeBlocks($text) {
		$less_than_tab = $this->tab_width;
		
		$text = preg_replace_callback('{
				(?:\n|\A)
				# 1: Opening marker
				(
					~{3,}|`{3,} # Marker: three tilde or more.
				)
				
				[ ]?(\w+)?(?:,[ ]?(\d+))?[ ]* \n # Whitespace and newline following marker.
				
				# 3: Content
				(
					(?>
						(?!\1 [ ]* \n)	# Not a closing marker.
						.*\n+
					)+
				)
				
				# Closing marker.
				\1 [ ]* \n
			}xm',
			array(&$this, '_doFencedCodeBlocks_callback'), $text);

		return $text;
	}
	
	function _doFencedCodeBlocks_callback($matches) {
		$codeblock = $matches[4];
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
		$codeblock = preg_replace_callback('/^\n+/',
			array(&$this, '_doFencedCodeBlocks_newlines'), $codeblock);
		//$codeblock = "<pre><code>$codeblock</code></pre>";
		//$cb = "<pre><code";
		$cb = empty($matches[3]) ? "<pre><code" : "<pre class=\"linenums:$matches[3]\"><code"; 
		$cb .= empty($matches[2]) ? ">" : " class=\"language-$matches[2]\">";
		$cb .= "$codeblock</code></pre>";
		return "\n\n".$this->hashBlock($cb)."\n\n";
	}

	function doFencedFigures($text){
		$text = preg_replace_callback('{
			(?:\n|\A)
			# 1: Opening marker
			(
				={3,} # Marker: equal sign.
			)
			
			[ ]?(?:\[([^\]]+)\])?[ ]* \n # Whitespace and newline following marker.
			
			# 3: Content
			(
				(?>
					(?!\1 [ ]?(?:\[([^\]]+)\])?[ ]* \n)	# Not a closing marker.
					.*\n+
				)+
			)
			
			# Closing marker.
			\1 [ ]?(?:\[([^\]]+)\])?[ ]* \n
		}xm', array(&$this, '_doFencedFigures_callback'), $text);		
		
		return $text;	
	}
	
	function _doFencedFigures_callback($matches) {
		# get figcaption
		$topcaption = empty($matches[2]) ? null : $this->runBlockGamut($matches[2]);
		$bottomcaption = empty($matches[4]) ? null : $this->runBlockGamut($matches[4]);
		$figure = $matches[3];
		$figure = $this->runBlockGamut($figure); # recurse

		$figure = preg_replace('/^/m', "  ", $figure);
		# These leading spaces cause problem with <pre> content, 
		# so we need to fix that - reuse blockqoute code to handle this:
		$figure = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx', 
			array(&$this, '_doBlockQuotes_callback2'), $figure);
		
		$res = "<figure>";
		if(!empty($topcaption)){
			$res .= "\n<figcaption>$topcaption</figcaption>";
		}
		$res .= "\n$figure\n";
		if(!empty($bottomcaption) && empty($topcaption)){
			$res .= "<figcaption>$bottomcaption</figcaption>";
		}
		$res .= "</figure>";		
		return "\n". $this->hashBlock($res)."\n\n";
	}

	function doTodoLists($text) {
	#
	# Form HTML todo lists
	#
		$less_than_tab = $this->tab_width - 1;

		# Re-usable patterns to match todo list
		$marker_re  = '\[[\*\s]\]';

		# Re-usable pattern to match any entire todo list:
		$whole_list_re = '
			(					# $1 = whole list
			  (					# $2
				([ ]{0,'.$less_than_tab.'})	# $3 = number of spaces
				('.$marker_re.')		# $4 = first list item marker
				[ ]+
			  )
			  (?s:.+?)
			  (					# $5
				  \z
				|
				  \n{2,}
				  (?=\S)
			  )
			)
		';

		$text = preg_replace_callback('{
				^
				'.$whole_list_re.'
			}mx',
			array(&$this, '_doTodoLists_callback'), $text);

		return $text;
	}
	function _doTodoLists_callback($matches) {
		$list = $matches[1];
		
		$list .= "\n";
		$result = $this->processTodoListItems($list, $marker_re);
		
		$result = $this->hashBlock("<br/><div class=\"todo\">\n" . $result . "</div><br/>");
		return "\n". $result ."\n\n";
	}
	function processTodoListItems($list_str) {
		# Re-usable pattern to match todo list items
		$marker_re  = '\[[\*\s]\]';

		# trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		$list_str = preg_replace_callback('{
			(\n)?				# leading line = $1
			(^[ ]*)				# leading whitespace = $2
			('.$marker_re.'			# list marker and space = $3
				(?:[ ]+|(?=\n))	# space only required if item is not empty
			)
			((?s:.*?))			# list item text   = $4
			(?:(\n+(?=\n))|\n)		# tailing blank line = $5
			(?= \n* (\z | \2 ('.$marker_re.') (?:[ ]+|(?=\n))))
			}xm',
			array(&$this, '_processTodoListItems_callback'), $list_str);
		return $list_str;
	}
	function _processTodoListItems_callback($matches) {
		static $item_id;

		$item_id = (!isset($item_id)?1:$item_id+1);
		$item = $matches[4];
		$leading_line =& $matches[1];
		$leading_space =& $matches[2];
		$marker_space = $matches[3];
		$tailing_blank_line =& $matches[5];

		if ($leading_line || $tailing_blank_line || 
			preg_match('/\n{2,}/', $item))
		{
			# Replace marker with the appropriate whitespace indentation
			$item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
			$item = $this->runBlockGamut($this->outdent($item)."\n");
		}

		if (preg_match('/\[\*\]/', $marker_space))
			return '<div>  ☑︎  ' . $item . "</div>\n";
		else
			return '<div>  ☐  ' . $item . "</div>\n";
	}
}
?>
