<?php
/**
 * Provides HTML-related methods
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * {@link http://flourishlib.com/docs/UTF-8} for more information.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fHTML
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fHTML
{
	/**
	 * Checks a string of HTML for block level elements
	 * 
	 * @param  string $content  The HTML content to check
	 * @return boolean  If the content contains a block level tag
	 */
	static public function checkForBlockLevelHTML($content)
	{
		static $inline_tags = '<a><abbr><acronym><b><big><br><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		return strip_tags($content, $inline_tags) != $content;
	}
	
	
	/**
	 * Prints text, turning newlines into breaks as long as there aren't any block-level html tags
	 * 
	 * @param  string $content  The content to display
	 * @return void
	 */
	static public function convertNewlines($content)
	{
		static $inline_tags_minus_br = '<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		return (strip_tags($content, $inline_tags_minus_br) != $content) ? $content : nl2br($content);
	}
	
	
	/**
	 * Takes a block of text and converts all URLs into HTML links
	 * 
	 * @param  string  $content           The content to parse for links
	 * @param  integer $link_text_length  If non-zero, all link text will be truncated to this many characters
	 * @return string  The content with all URLs converted to HTML link
	 */
	static public function createLinks($content, $link_text_length=0)
	{
		// Determine what replacement to perform
		if ($link_text_length) {
			// We don't need UTF-8 strlen here becuase email addresses and URLs can't contain UTF-8 characters
			$replacement = '((strlen("\1") > ' . $link_text_length . ') ? substr("\1", 0, ' . $link_text_length . ') . "..." : "\1")';
		} else {
			$replacement = '"\1"';
		}
		
		
		// Handle fully qualified urls with protocol
		$full_url_regex       = '#\b([a-z]{3,}://[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])\b#ie';
		$full_url_replacement = '"<a href=\"\1\">" . ' . $replacement . ' . "</a>"';
		
		// Handle domains names that start with www
		$www_url_regex       = '#\b(www\.([a-z0-9\-]+\.)+[a-z]{2,}(?:/[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])?)\b#ie';
		$www_url_replacement = '"<a href=\"http://\1\">" . ' . $replacement . ' . "</a>"';
		
		// Handle email addresses
		$email_regex       = '#\b([a-z0-9\\.+\'_\\-]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,})\b#ie';
		$email_replacement = '"<a href=\"mailto:\1\">" . ' . $replacement . ' . "</a>"';
		
		$searches = array(
			$full_url_regex => $full_url_replacement,
			$www_url_regex  => $www_url_replacement,
			$email_regex    => $email_replacement
		);
		
		
		// Loop through and do each kind of replacement, by doing a pass for each replacement, we prevent nested links
		foreach ($searches as $regex => $replacement) {
			
			// Find all a tags
			$reg_exp = "#<\s*a(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*>.*?<\s*/\s*a\s*>#";
			preg_match_all($reg_exp, $content, $a_tag_matches, PREG_SET_ORDER);
			
			// Find all text
			$text_matches = preg_split($reg_exp, $content);
			
			// For each chunk of text, convert all URLs to links
			foreach($text_matches as $key => $text) {
				$text = preg_replace($regex, $replacement, $text);
				$text_matches[$key] = str_replace("\\'", "'", $text);
			}
			
			// Merge the text and a tags back together
			for ($i = 0; $i < sizeof($a_tag_matches); $i++) {
				$text_matches[$i] .= $a_tag_matches[$i][0];
			}
			
			$content = implode($text_matches);
		}
		
		return $content;
	}
	
	
	/**
	 * Converts all html entities to normal characters, using UTF-8
	 * 
	 * @param  string $content  The content to decode
	 * @return string  The decoded content
	 */
	static public function decode($content)
	{
		return html_entity_decode($content, ENT_COMPAT, 'UTF-8');
	}
	
	
	/**
	 * Converts all special characters to entites, using UTF-8.
	 * 
	 * @param  string $content  The content to encode
	 * @return string  The encoded content
	 */
	static public function encode($content)
	{
		return htmlentities($content, ENT_COMPAT, 'UTF-8');
	}
	
	
	/**
	 * Prepares content for display in UTF-8 encoded HTML - allows HTML tags
	 * 
	 * @param  string $content  The content to prepare
	 * @return string  The encoded html
	 */
	static public function prepare($content)
	{
		// Find all html tags, entities and comments
		$reg_exp = "/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<\!--.*?-->/";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);
		
		// Find all text
		$text_matches = preg_split($reg_exp, $content);
		
		// For each chunk of text, make sure it is converted to entities
		foreach($text_matches as $key => $value) {
			$text_matches[$key] = htmlspecialchars($value);
		}
		
		// Merge the text and html back together
		for ($i = 0; $i < sizeof($html_matches); $i++) {
			$text_matches[$i] .= $html_matches[$i][0];
		}
		
		return implode($text_matches);
	}
	
	
	/**
	 * Sets the proper Content-Type header for a UTF-8 HTML (or pseudo-XHTML) page
	 * 
	 * @return void
	 */
	static public function sendHeader()
	{
		header('Content-Type: text/html; charset=utf-8');
	}
	
	
	/**
	 * Prints a paragraph (or div if the content has block-level html) with the contents and the class specified - will not print if no content
	 * 
	 * @param  string $content    The content to display
	 * @param  string $css_class  The css class to apply
	 * @return boolean  If the content was shown
	 */
	static public function show($content, $css_class='')
	{
		if (!fCore::stringlike($content)) {
			return FALSE;
		}
		
		$class = ($css_class) ? ' class="' . $css_class . '"' : '';
		if (self::checkForBlockLevelHTML($content)) {
			echo '<div' . $class . '>' . self::prepare($content) . '</div>';
		} else {
			echo '<p' . $class . '>' . self::prepare($content) . '</p>';
		}
		
		return TRUE;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fHTML
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */