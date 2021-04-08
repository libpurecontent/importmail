<?php


/*

Example use, run in a shell context:

$pearLocation = '/usr/share/php/';
require_once ('importMail.php');
$importMail = new importMail ($pearLocation);
list ($from, $subject, $date, $message, $attachments) = $importMail->main ($pearLocation);
*/


# Class to read mail coming from Exim; see https://evolt.org/incoming_mail_and_php
# Version 1.2.0
class importMail
{
	# Constructor
	function __construct ($pearLocation = false)
	{
		# Environment
		ini_set ('date.timezone', 'Europe/London');		// Required for PHP 5.3+
		
		# Add the PEAR location (and library path within this) to the include path
		if ($pearLocation) {
			$includePathComponents = array (ini_get ('include_path'), $pearLocation, $pearLocation . '/Mail/');
			ini_set ('include_path', implode (PATH_SEPARATOR, $includePathComponents));
		}
		
		# Load required PEAR libraries
		require_once ('mimeDecode.php');	// in $pearLocation
	}
	
	
	# Main function
	public function main ()
	{
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
		$email = get_object_vars ($structure);
		
		# Extract key headers
		$from = $email['headers']['from'];
		$subject = $email['headers']['subject'];
		
		# Convert the date to a UNIX timestamp
		$date = strtotime ($email['headers']['date']);
		
		# Determine if the message is multipart MIME or not
		$isMultipart = (isSet ($email['parts']));
		
		# For a standard text-only mail, get the message body
		if (!$isMultipart) {
			$message = $this->utf8Message ($email);
			$attachments = array ();
		} else {
			
			# For multipart messages, parse into parts and extract the message and any attachments
			list ($message, $attachments) = $this->parseMultipart ($email['parts']);
		}
		
		# Return the values
		return array ($from, $subject, $date, $message, $attachments);
	}
	
	
	# Function to parse multipart messages, with support for nested (recursive) message parts
	private function parseMultipart ($parts)
	{
		# Assume there are no attachments, to start with
		$attachments = array ();
		
		# Parse each part
		foreach ($parts as $index => $part) {
			$part = get_object_vars ($part);
			
			# If this part has sub-parts (which mail clients show as 1.1, 1.2, etc.), recurse
			if (isSet ($part['parts'])) {
				list ($message, $partAttachments) = $this->parseMultipart ($part['parts']);
				$attachments += $partAttachments;
				continue;	// Skip to next part
			}
			
			# Handle each content type differently
			$contentType = "{$part['ctype_primary']}/{$part['ctype_secondary']}";
			switch ($contentType) {
				
				# Plain text, which will be the default
				case 'text/plain':
					$message = $this->utf8Message ($part);
					break;
				
				# HTML
				case 'text/html':
					if (isSet ($message)) {continue 2;}	// Skip to next part if text/plain or a nested part has already found a message
					$message = $this->utf8Message ($part);
					$message = strip_tags ($message);	// Convert to plain text
					$message = self::numeric_entities ($message);
					$message = html_entity_decode ($message, ENT_COMPAT, 'UTF-8');
					break;
				
				# Binaries
				default:
					$filename = $part['d_parameters']['filename'];
					$binaryPayload = $part['body'];
					$attachments[$filename] = $binaryPayload;
					break;
			}
		}
		
		# Return the message and attachments
		return array ($message, $attachments);
	}
	
	
	# UTF-8 message conversion
	private function utf8Message ($container)
	{
		# Extract the string and charset (both messages and part are containers for the same structure)
		$string  = $container['body'];
		$charset = $container['ctype_parameters']['charset'];
		
		# Do the conversion
		$string = iconv ($charset, 'UTF-8//IGNORE', $string);
		
		# Return the contained string
		return $string;
	}
	
	
	# Decode numeric entities; from www.php.net/html-entity-decode#96324
	private function numeric_entities ($string)
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
