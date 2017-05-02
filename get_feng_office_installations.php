<?php

//error_reporting(E_ALL);	//DEBUG
//ini_set("display_errors", true);	//DEBUG
header("Access-Control-Allow-Origin: *");
	
$host_name									= $_REQUEST['h'];
$user_name									= !empty($_REQUEST['u']) && ctype_alnum($_REQUEST['u']) ? $_REQUEST['u'] : "";	// check for security reasons
$password									= empty($_REQUEST['s']) ? "" : $_REQUEST['s'];	// s from secret
$port										= !empty($_REQUEST['p']) && ctype_digit($_REQUEST['p']) ? $_REQUEST['p'] : "";
$path_to_test								= empty($_REQUEST['ptt']) ? "" : $_REQUEST['ptt'];
$return_json								= empty($_REQUEST['j']) ? false : true;

if(stripos($host_name, "secure.ilia.ch") !== false ||
   stripos($host_name, "www.ilia.ch") !== false ||
   stripos($host_name, "www.liquid-office") !== false)
{
	error_log("[" . __FILE__ . ":" . __LINE__ . "]: \$hostname = $hostname and MUST NOT INCLUDE internal servers", 0);
	die("<p>please install directly</p>");
}
	
$test_result								= test_connection($host_name, $port);

if($test_result !== true) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "]: \$test_result should be true, but is $test_result => could not connect to host - aborting", 0);
	die("<p>could not connect to host</p>");
}

if(empty($user_name)) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "]: \$user_name should not be empty, BUT IS EMPTY => could not connect to host - aborting", 0);
	die("<p>could not connect to host</p>");
}
	
if(empty($password)) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "]: \$password should not be empty, BUT IS EMPTY => could not connect to host - aborting", 0);
	die("<p>could not connect to host</p>");
}


// ----------------------------------------------------------------------------------------------------------
// THIS WORKS
// ----------------------------------------------------------------------------------------------------------
// grep -sRP "^[^#]*(root|DocumentRoot)\s" /etc/nginx/sites-enabled /etc/apache*/sites-enabled | sed -r 's/.*oot (.*)/\1/i' | tr -d ";" | xargs -l -I{} find -O3 {} -name init.php | xargs grep -l "define('PRODUCT_NAME', 'Feng"
// ----------------------------------------------------------------------------------------------------------
//TODO: improve with removing dupclicate lines after tr-section with something like ... uniq ...

// grep -sRP "^[^#]*(root|DocumentRoot)\s" /etc/nginx/sites-enabled /etc/apache*/sites-enabled
//													... finds all www directories in server config for NginX + Apache
//														-s tells grep to be SILENT and especially suppress error messages
//														-R tells grep to RECURSE sub-directories
//														-P tells grep to use PERL-LIKE syntax for regex
// ... sed -r 's/.*oot (.*)/\1/i'					... extracts the actual www directories
//														-r tells sed to use extend REGULAR expressions
//														/i tells sed to be case-INSENSITIVE		  
// ... tr -d ";"									... removes ";" if any
//														-d tells tr to DELETE the characters of the following set
// ... xargs -l -I{} find -O3 {} -name init.php		...	finds all files in these www directories, called "init.php"
//														-l tells xargs to use continuous input
//														-I tells xargs which pattern to use for inserting stdin
//														-O3 tells find to use best optimization
//														-name tells find the filename of the files to be found
// ... xargs grep -l "define('PRODUCT_NAME', 'Feng"	...	finds all files including the string "define('PRODUCT_NAME', 'Feng"
//														-l tells grep to list the complete file name

# takes very long
// TODO: there is a parameter for parallel execution with xargs ...
//$command_to_execute_remotely				= "find -O3 /var/www -name init.php 2>&1 | xargs grep -l \\\"define('PRODUCT_NAME', 'Feng\\\"";
//$command_to_execute_remotely				= "find -O3 /var/www -name init.php 2>/dev/null | xargs grep -l \\\"define('PRODUCT_NAME', 'Feng\\\"";
//$command_to_execute_remotely				= "find -O3 /var/www -name init.php 2>/dev/null";

if($path_to_test === "") {
	$unique_destinations					= get_www_roots();
	echo json_encode($unique_destinations);
} else {
	$return_value							= check_whether_ptt_is_fo_path($path_to_test);
	echo $return_value;
}

$return_value_messages						= array("1" =>	"invalid command line argument",
													"2" =>	"conflicting arguments given",
													"3" =>	"general runtime error",
													"4" =>	"unrecognized response from ssh (parse error)",
													"5"	=>	"invalid/incorrect password",
													"6"	=>	"host public key is unknown. sshpass exits without confirming the new key",
													"255" => "undefined error");

function get_www_roots() {
	global									$password, $user_name, $host_name, $port, $return_value_messages;
	//$command_to_execute_remotely			= "grep -sRP \\\"^[^#]*(root|DocumentRoot)\s\\\" /etc/nginx/sites-enabled /etc/apache*/sites-enabled | sed -r 's/.*oot (.*)/\\1/i' | tr -d ';'";	// old version; does not find Apache Aliases ...
	//$command_to_execute_remotely			= "grep -isRP \\\"^[^#]*(root|DocumentRoot|alias)\s\\\" /etc/nginx/sites-enabled /etc/apache*/sites-enabled | sed -r 's/(.*oot|Alias\s*\S*\s*) (.*)/\2/i' | tr -d ';'";	// old version; does not find Nginx aliases ...
	$command_to_execute_remotely			= "grep -isRP \\\"^[^#]*(root|DocumentRoot|alias)\s\\\" /etc/nginx/sites-enabled /etc/apache*/sites-enabled | sed -r 's/(.*oot|.*\\\/apache.*Alias\\\s*\\\S*\\\s*|.*\\\/nginx\\\/.*alias\\\s*) (.*)/\\2/i' | tr -d ';'";
	$run_command							= "sshpass -p '$password' ssh -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	$output									= array();
	
	exec($run_command, $output, $return_value);
	//echo "run_command = " . $run_command . "; return_value = " . $return_value;	//DEBUG

	if($return_value != 0) {
		$debug								= "command = '$run_command'\n";
		$debug							   .= "return value = '$return_value'\n";
		if(gettype($output) == "array")
			$debug						   .= "output (array) = '" . print_r($output, true) . "'\n";
		else
			$debug						   .= "output = '$output'\n";

		echo $debug;	//DEBUG
	
		die("<div class='easy_installation_form_fo_inst_path'>no feng installation found\nplease specify installation directory manually!\n({$return_value}:{$return_value_messages[$return_value]})</div>");
	}

	$unique_destinations					= array_keys(array_flip($output));
	
	//echo "<p>return value = $return_value</p>";	//DEBUG
	//echo "<p>output = $output</p>";	//DEBUG
	//echo "<pre>";	//DEBUG
	//print_r($output);	//DEBUG
	//print_r($unique_destinations);	//DEBUG
	//echo "</pre>";	//DEBUG
	
	$copy_of_unique_destinations			= $unique_destinations;
	$indexes_to_delete	= array();
	
	for($i = 0; $i < count($unique_destinations); $i++) {
		for($j = 0; $j < count($copy_of_unique_destinations); $j++) {
			//print "unique_destinations[$i] ('{$unique_destinations[$i]}') vs copy_of_unique_destinations[$j] ('{$copy_of_unique_destinations[$j]}') => ";	//DEBUG
			if(($j != $i) && (stripos($copy_of_unique_destinations[$j], $unique_destinations[$i]) !== false)) {
				array_push($indexes_to_delete, $j);
				//print "DELETE";	//DEBUG
			}
			//print "<br>";	//DEBUG
		}
	}
	
	for($i = 0; $i < count($indexes_to_delete); $i++)
		unset($copy_of_unique_destinations[$indexes_to_delete[$i]]);
	
	$unique_destinations = array_values($copy_of_unique_destinations);
	return $unique_destinations;
}


function check_whether_ptt_is_fo_path($path_to_test) {
	global									$password, $user_name, $host_name, $port, $return_value_messages, $return_json;
	$command_to_execute_remotely			= "find -O3 {$path_to_test} -name init.php 2>/dev/null | xargs grep -l \\\"define('PRODUCT_NAME', 'Feng\\\"";
	$run_command							= "sshpass -p '$password' ssh -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	$output									= array();
	$feng_office_paths						= array();
	
	exec($run_command, $output, $return_value);
	//echo "run_command = " . $run_command . "; output = " . $output . "; return_value = " . $return_value;	//DEBUG
	//echo "<pre>";	//DEBUG
	//print_r($output);	//DEBUG
	//echo "</pre>";	//DEBUG
	
	if($return_value != 0) {
		$debug								= "command = '$run_command'\n";
		$debug							   .= "return value = '$return_value'\n";
		if(gettype($output) == "array")
			$debug						   .= "output (array) = '" . print_r($output, true) . "'\n";
		else
			$debug						   .= "output = '$output'\n";
		
		//echo $debug;	//DEBUG
		
		exit;
	}
	
	$return_value							= "";
	$pattern								= '/(.*)\/init\.php/';
	foreach($output as $feng_office_path) {
		preg_match($pattern, $feng_office_path, $match);
		//echo "pattern = " . $pattern . "; feng_office_path = " . $feng_office_path . "; match = " . $match;	//DEBUG
		
		//print "<p>Path of feng installation " . $i++ . " = $match</p>";	//DEBUG
		if(isset($match[1])) {
			$feng_office_path				= $match[1];
			if(!$return_json) { 
				$feng_office_path_html		= preg_replace('/(.*\/)(.*)/', "$1<span style=\"font-weight:bold;\" fo-path=\"{$feng_office_path}\">$2</span>", $feng_office_path);
				$return_value			   .= "<div class=\"easy_installation_form_fo_inst_path\" fo-path=\"{$feng_office_path}\">{$feng_office_path_html}</div>";
			} else {
				array_push($feng_office_paths, $feng_office_path);
			}
		}
	}

	if(!$return_json) {
		return $return_value;
	} else {
		return json_encode($feng_office_paths);
	}
}




// taken over from easy_installation.php
function test_connection($host_name, $port) {
	$time_out_in_seconds						= 4;
	
	$test_connection_command					= "nc -zvw $time_out_in_seconds $host_name $port 2>&1";
	$output										= array();
	$part_of_message_indicating_success			= "succeeded";

	exec($test_connection_command, $output, $return_value);
	$output										= implode($output, " ");

	if(strpos($output, $part_of_message_indicating_success) !== false)
		return true;
	else {
		$debug									= "command = '$test_connection_command'\n";
		$debug							       .= "return value = '$return_value'\n";
		$debug							       .= "output = '$output'\n";

		return $debug;		
	}
}

?>
