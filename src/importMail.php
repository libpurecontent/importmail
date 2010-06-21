<?php


/*

Example use, run in a shell context:

$pearLocation = '/usr/local/lib/php/';
$includePathWithPear = "/path/to/libraries/:{$pearLocation}/:{$pearLocation}/Mail/";	// Make sure the Mail/ location is also included
ini_set ('include_path', $includePathWithPear);
require_once ('importMail.php');
list ($subject, $body, $date) = importMail::main ($includePathWithPear);

*/


# Class to read mail coming from Exim; see http://www.evolt.org/article/Incoming_Mail_and_PHP/18/27914/
# Version 1.0.1
class importMail
{
	# Constructor
	function __construct ()
	{
		// Do nothing
	}
	
	
	# Main function
	public function main ($includePathWithPear)
	{
		# Environment
		ini_set ('date.timezone', 'Europe/London');	// Required for PHP 5.3+
		ini_set ('include_path', $includePathWithPear);
		
		# Load required PEAR libraries
		require_once ('mimeDecode.php');	// in path/to/pear/Mail/
		
		# Read from stdin
		$fd = fopen ('php://stdin', 'r');
		$email = '';
		while (!feof ($fd)) {
		    $email .= fread ($fd, 1024);
		}
		fclose ($fd);
		
		# Decode the message
		$params['include_bodies'] = true;
		$params['decode_bodies']  = true;
		$params['decode_headers'] = true;
		$decoder = new Mail_mimeDecode ($email);
		$structure = $decoder->decode ($params);
		
		# Convert from object to array format
		$message = get_object_vars ($structure);
		
		# Extract key headers
		$dateOriginal = $message['headers']['date'];
		$subject = $message['headers']['subject'];
		
		# Get the message body
		if (isSet ($message['body'])) {
			$messageBody = $message['body'];
		} else {
			foreach ($message['parts'] as $index => $part) {
				$part = get_object_vars ($part);
				if (substr_count ($part['headers']['content-type'], 'text/plain')) {
					$messageBody = $part['body'];
					break;
				} else if (substr_count ($part['headers']['content-type'], 'text/html')) {
					
					# Convert HTML to plain text
					preg_match ('/charset="?([^\s]+)"?/i', $part['headers']['content-type'], $contentTypeMatches);
					$contentType = $contentTypeMatches[1];
					$messageBody = $part['body'];
					$messageBody = iconv ($contentType, 'UTF-8', $messageBody);	// Convert to UTF-8
					$messageBody = strip_tags ($messageBody);	// Convert to plain text
					$messageBody = self::numeric_entities ($messageBody);
					$messageBody = html_entity_decode ($messageBody, ENT_COMPAT, 'UTF-8');
					break;
				}
				
				# Format not known
				return false;
			}
		}
		
		# Convert the date to a unix timestamp
		$date = strtotime ($dateOriginal);
		
		# Return the values
		return array ($subject, $messageBody, $date);
	}
	
	
	# Decode numeric entities; from www.php.net/html-entity-decode#96324
	function numeric_entities ($string)
	{
		$mapping_hex = array();
		$mapping_dec = array();
		
		$translations = get_html_translation_table (HTML_ENTITIES, ENT_QUOTES);
		foreach ($translations as $char => $entity){
			$mapping_hex[html_entity_decode($entity,ENT_QUOTES,"UTF-8")] = '&#x' . strtoupper(dechex(ord(html_entity_decode($entity,ENT_QUOTES)))) . ';';
			$mapping_dec[html_entity_decode($entity,ENT_QUOTES,"UTF-8")] = '&#' . ord(html_entity_decode($entity,ENT_QUOTES)) . ';';
		}
		$string = str_replace(array_values($mapping_hex),array_keys($mapping_hex) , $string);
		$string = str_replace(array_values($mapping_dec),array_keys($mapping_dec) , $string);
		return $string;
	}
	
}