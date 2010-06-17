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
# Version 1.0.0
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
				}
				
				# text/plain not found
				return false;
			}
		}
		
		# Convert the date to a unix timestamp
		$date = strtotime ($dateOriginal);
		
		# Return the values
		return array ($subject, $messageBody, $date);
	}
}
