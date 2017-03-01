<?php

	/*
	 * "PUBLIC" FUNCTIONS
	 *   - are included in the (array)$installation_procedure
	 *   - are called by cgi-parameter t=[number_of_step/"public" function]
	 *   - return an json object, with the following mandatory members:
	 *     - "ajx_output"		: string, that will be included in details (log) of easy installation front-end
	 *     - "ajx_success"		: boolean, specifies whether installation has been completed successfully
	 *     - "ajx_nextstep"		: next step, that needs to be taken
	 *     
	 *     the following members are optional:
	 *     - "ajx_progress"		: progress in percent (= percent of already, successfully taken installation steps)
	 *     - "ajx_ns_add_cgi"	: additional cgi-paremter for next step
	 *     - "ajx_msg"			: string, that will be displayed to inform user or collect information from him
	 *     - "ajx_ns_msg_ok"	: next step, that shall be taken, if user confirms [ajx_msg]
	 *     - "ajx_ns_msg_cancel": next step, that shall be taken, if user cancels [ajx_msg]
	 *     - "ajx_debug"		: if debug info is available, it is stored here
	 *     
	 * "PRIVATE" (HELPER) FUNCTIONS
	 *   - start with "_"
	 *   - are NOT included in the (array)$installation_procedure
	 *   - do NOT return an json object
	 *   
	 * (C) 2014, 2015 Sebastian Reimer for Liquid Office
	 * 
	 */

	$DEBUG												= true;
	$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS				= 5;
	error_reporting(E_ALL | E_STRICT);
	ini_set("display_errors", "Off");
	$URL_TO_CURRENT_VERSION_NUMBER_FILE					= "http://dev.ilia.ch/liquid-office/plugin/current_version_number.txt";
	$INLINE_COMMENT										= "inline";
	$MULTILINE_COMMENT_START							= "multi-start";
	$MULTILINE_COMMENT_END								= "multi-end";
	
	$installation_step									= !empty($_REQUEST['t']) && ctype_digit($_REQUEST['t']) ? $_REQUEST['t'] : 0;	//TODO: abort installation if not a valid number
	$webserver_run_user									= empty($_REQUEST['r']) ? "" : $_REQUEST['r'];	// cannot check with ctype_alphnum, because then users with "_" for example cannot be used
	$host_name											= empty($_REQUEST['h']) ? "" : $_REQUEST['h'];
	$user_name											= empty($_REQUEST['u']) ? "" : $_REQUEST['u'];	// cannot check with ctype_alphnum, because then users with "_" for example cannot be used
	$password											= empty($_REQUEST['s']) ? "" : $_REQUEST['s'];	// s from secret
	$port												= !empty($_REQUEST['p']) && ctype_digit($_REQUEST['p']) ? $_REQUEST['p'] : 22;
	$feng_office_path									= empty($_REQUEST['f']) ? "" : $_REQUEST['f'];
	$feng_office_path									= _assure_that_path_contains_slashes($feng_office_path);
	$feng_office_path									= _remove_last_char_if_it_is_slash($feng_office_path);
	$display_step										= !empty($_REQUEST['d']) && ctype_digit($_REQUEST['d']) ? $_REQUEST['d'] : 0;
	$is_admin											= !empty($_REQUEST['a']) && $_REQUEST['a'] == "1" ? true : false;
	$server_config_path									= empty($_REQUEST['nc']) ? "" : $_REQUEST['nc'];	//TODO: check for security
	$plugins_path										= !empty($_REQUEST['psp']) ? $_REQUEST['psp'] : "";	//TODO: check for security
	$liquid_office_plugin_path							= !empty($_REQUEST['lop']) ? $_REQUEST['lop'] : "";	//TODO: check for security
	$zip_file_path										= !empty($_REQUEST['zp']) ? $_REQUEST['zp'] : "";	//TODO: check for security
	
	$current_version_number								= _get_current_version_number($host_name, $port, $user_name, $password, $feng_office_path);
	$path_on_ilia_server_to_current_zip					= "/var/www/dev.ilia.ch/liquid-office/plugin/v{$current_version_number}/liquid_office_plugin_v{$current_version_number}.zip";

	if($DEBUG)	//TODO return to good old echo
		_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'installation_step host_name user_name password port feng_office_path display_step is_admin server_config_path plugins_path liquid_office_plugin_path zip_file_path')));
	
	$check_connection_step								= array("title"		=>	"checking connection ...",
																"call"		=>	"check_connection(\"$host_name\", $port);");
	$check_login_step									= array("title"		=>	"checking login details ...",
																"call"		=>	"check_login(\"$host_name\", $port, \"$user_name\", \"$password\");");
	$check_whether_user_is_admin_step					= array("title"		=>	"checking whether user has administrative rights ...",
																"call"		=>	"check_whether_user_is_admin(\"$host_name\", $port, \"$user_name\", \"$password\");");
	$check_whether_path_is_feng_office_dir_step			= array("title"		=>	"checking installation path ...",
																"call"		=>	"check_whether_path_is_feng_office_dir(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_one_dir_of_path_is_feng_office_dir_step
														= array("title"		=>	"checking, whether one dir of specified path is Feng Office installation path ...",
																"call"		=>	"check_whether_one_dir_of_path_is_feng_office_dir(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_plugin_path_exists_step				= array("title"		=>	"checking whether plug-in path already exists ...",
																"call"		=>	"check_whether_plugin_path_exists(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_plugin_path_is_writable_step			= array("title"		=>	"checking whether plug-in path can be written ...",
																"call"		=>	"check_whether_plugin_path_is_writable(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_plugins_path_exists_step				= array("title"		=>	"checking whether plugins path exists ...",
																"call"		=>	"check_whether_plugins_path_exists(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_plugins_path_is_writable_step		= array("title"		=>	"checking whether plugins path can be written ...",
																"call"		=>	"check_whether_plugins_path_is_writable(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_feng_office_path_is_writable_step	= array("title"		=>	"checking whether feng office path can be written ...",
																"call"		=>	"check_whether_feng_office_path_is_writable(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$check_whether_nginx_is_running_step				= array("title"		=>	"checking whether nginx is running ...",
																"call"		=>	"check_whether_nginx_is_running(\"$host_name\", $port, \"$user_name\", \"$password\");");
	$check_whether_nginx_config_needs_adapation_step	= array("title"		=>	"checking whether configuration of nginx needs adaption ...",
																"call"		=>	"check_whether_nginx_config_needs_adapation(\"$host_name\", $port, \"$user_name\", \"$password\", \"$server_config_path\");");
	$check_whether_nginx_config_is_writable_step		= array("title"		=>	"checking whether configuration of nginx can be written ...",
																"call"		=>	"check_whether_nginx_config_is_writable(\"$host_name\", $port, \"$user_name\", \"$password\", \"$server_config_path\");");
	$check_whether_nginx_config_dir_is_writable_step	= array("title"		=>	"checking whether dir of nginx configuration can be written ...",
																"call"		=>	"check_whether_nginx_config_dir_is_writable(\"$host_name\", $port, \"$user_name\", \"$password\", \"$server_config_path\");");
	$check_whether_nginx_can_be_restarted_step			= array("title"		=>	"checking whether nginx can be restarted ...",
																"call"		=>	"check_whether_nginx_can_be_restarted(\"$host_name\", $port, \"$user_name\", \"$password\");");
	$backup_nginx_config_step							= array("title"		=>	"backup configuration of nginx ...",
																"call"		=>	"backup_nginx_config(\"$host_name\", $port, \"$user_name\", \"$password\", \"$server_config_path\");");
	$adapt_nginx_config_step							= array("title"		=>	"adapt configuration of nginx ...",
																"call"		=>	"adapt_nginx_config(\"$host_name\", $port, \"$user_name\", \"$password\", \"$server_config_path\");");
	$assure_that_the_necessary_dirs_exist_step			= array("title"		=>	"assure, that the directories, necessary for the installation of the Liquid Office plug-in, exist ...",
																"call"		=>	"assure_that_the_necessary_dirs_exist(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$create_plugins_dir_step							= array("title"		=>	"create plugins directory ...",
																"call"		=>	"create_plugins_dir(\"$host_name\", $port, \"$user_name\", \"$password\", \"$plugins_path\");");
	$create_liquid_office_plugin_dir_step				= array("title"		=>	"create Liquid Office plugins directory ...",
																"call"		=>	"create_liquid_office_plugin_dir(\"$host_name\", $port, \"$user_name\", \"$password\", \"$liquid_office_plugin_path\");");
	$check_whether_liquid_office_is_already_installed_step
														= array("title"		=>	"check, whether Liquid Office plug-in is already installed and activated ...",
																"call"		=>	"check_whether_liquid_office_is_already_installed(\"$host_name\", $port, \"$user_name\", \"$password\", \"$plugins_path\");");
	$upload_zip_step									= array("title"		=>	"upload Liquid Office plug-in ...",
																"call"		=>	"upload_zip(\"$host_name\", $port, \"$user_name\", \"$password\", \"$plugins_path\");");
	$unpack_zip_step									= array("title"		=>	"install (unzip) Liquid Office plug-in ...",
																"call"		=>	"unpack_zip(\"$host_name\", $port, \"$user_name\", \"$password\", \"$zip_file_path\");");
	$set_permissions_step								= array("title"		=>	"set correct permissions for Liquid Office plug-in ...",
																"call"		=>	"set_permissions(\"$host_name\", $port, \"$user_name\", \"$password\", \"$liquid_office_plugin_path\");");
	$get_run_user_of_webserver_step						= array("title"		=>	"get run user of web-server ...",
																"call"		=>	"get_run_user_of_webserver(\"$host_name\", $port, \"$user_name\", \"$password\");");
	$set_owner_step										= array("title"		=>	"set correct owner for Liquid Office plug-in ...",
																"call"		=>	"set_owner(\"$host_name\", $port, \"$user_name\", \"$password\", \"$liquid_office_plugin_path\");");
	$execute_install_sql_step							= array("title"		=>	"create new tables in database to install Liquid Office plug-in ...",
																"call"		=>	"execute_install_sql(\"$host_name\", $port, \"$user_name\", \"$password\", \"$liquid_office_plugin_path\");");
	$execute_update_sql_step							= array("title"		=>	"update tables in database to install Liquid Office plug-in ...",
																"call"		=>	"execute_update_sql(\"$host_name\", $port, \"$user_name\", \"$password\", \"$liquid_office_plugin_path\");");
	$activate_plugin_step								= array("title"		=>	"activate Liquid Office plug-in ...",
																"call"		=>	"activate_plugin(\"$host_name\", $port, \"$user_name\", \"$password\", \"$feng_office_path\");");
	$delete_zip_step									= array("title"		=>	"delete temporary installation files ...",
																"call"		=>	"delete_zip(\"$host_name\", $port, \"$user_name\", \"$password\", \"$zip_file_path\");");
	$installation_procedure								= array();
	
	array_push($installation_procedure, $check_connection_step);									// t = 0
	array_push($installation_procedure, $check_login_step);											// t = 1
	array_push($installation_procedure, $check_whether_user_is_admin_step);							// t = 2
	array_push($installation_procedure, $check_whether_path_is_feng_office_dir_step);				// t = 3
	array_push($installation_procedure, $check_whether_one_dir_of_path_is_feng_office_dir_step);	// t = 4
	array_push($installation_procedure,	$check_whether_plugin_path_exists_step);					// t = 5
	array_push($installation_procedure, $check_whether_plugin_path_is_writable_step);				// t = 6
	array_push($installation_procedure, $check_whether_plugins_path_exists_step);					// t = 7
	array_push($installation_procedure, $check_whether_plugins_path_is_writable_step);				// t = 8
	array_push($installation_procedure, $check_whether_feng_office_path_is_writable_step);			// t = 9
	array_push($installation_procedure, $check_whether_nginx_is_running_step);						// t = 10
	array_push($installation_procedure, $check_whether_nginx_config_needs_adapation_step);			// t = 11
	array_push($installation_procedure, $check_whether_nginx_config_is_writable_step);				// t = 12
	array_push($installation_procedure, $check_whether_nginx_config_dir_is_writable_step);			// t = 13
	array_push($installation_procedure, $check_whether_nginx_can_be_restarted_step);				// t = 14
	array_push($installation_procedure, $backup_nginx_config_step);									// t = 15
	array_push($installation_procedure, $adapt_nginx_config_step);									// t = 16
	array_push($installation_procedure, $assure_that_the_necessary_dirs_exist_step);				// t = 17
	array_push($installation_procedure, $create_plugins_dir_step);									// t = 18
	array_push($installation_procedure, $create_liquid_office_plugin_dir_step);						// t = 19
	array_push($installation_procedure, $check_whether_liquid_office_is_already_installed_step);	// t = 20
	array_push($installation_procedure, $upload_zip_step);											// t = 21
	array_push($installation_procedure, $unpack_zip_step);											// t = 22
	array_push($installation_procedure, $set_permissions_step);										// t = 23
	array_push($installation_procedure, $get_run_user_of_webserver_step);							// t = 24
	array_push($installation_procedure, $set_owner_step);											// t = 25
	array_push($installation_procedure, $execute_install_sql_step);									// t = 26
	array_push($installation_procedure, $execute_update_sql_step);									// t = 27
	array_push($installation_procedure, $activate_plugin_step);										// t = 28
	array_push($installation_procedure, $delete_zip_step);											// t = 29

	$feedback											= eval("return {$installation_procedure[$installation_step]['call']}");

	if($DEBUG)	//TODO: return to good old echo
		_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact("feedback"));
	
	echo json_encode($feedback);
	
/*
 *
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 */
	function check_connection($host_name, $port)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$check_connection_command						= "nc -zv $host_name $port 2>&1";
		
		exec($check_connection_command, $output, $return_value);
		$output											= implode($output, " ");

		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_login");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("no connection");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&d={$display_step}";
			$feedback_to_client['ajx_msg']				= "Cannot connect to specified server! Please check host AND port!\n";
			$feedback_to_client['ajx_msg']			   .= "Easy installation requires an SSH demon running on specified host.";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_connection_command return_value output')));
		}

		return $feedback_to_client;
	}

	
	function check_login($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$command_to_execute_remotely					= "ls -al 2>&1";
		$check_login_command							= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	
		exec($check_login_command, $output, $return_value);
		$output											= implode($output, " ");
	
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_user_is_admin");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&d={$display_step}";
		}
		else if($return_value == 5)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("login details are not correct");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&d={$display_step}";
			$feedback_to_client['ajx_msg']				= "Please specify correct login details!";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_login_command return_value output')));
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("could not validate login details");
			$feedback_to_client['ajx_output']		   .= "(RC = $return_value)<br>(OUT = $output)</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&d={$display_step}";
			$feedback_to_client['ajx_msg']				= "An error occured, while checking login details";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_login_command return_value output')));
		}
		
		return $feedback_to_client;
	}
	

	function check_whether_user_is_admin($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$display_step;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$installation_procedure;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$output											= array();
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$command_to_execute_remotely					= "echo '$password' | sudo -S sudo -l  2>&1";
		$check_for_admin_rights_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		$part_of_message_indicating_success				= "ser root may run the following commands on";
	
		exec($check_for_admin_rights_command, $output, $return_value);
		$output											= implode($output, " ");
	
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_path_is_feng_office_dir");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a=1&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("no administrative rights");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_path_is_feng_office_dir");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a=0&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_for_admin_rights_command return_value output')));
		}
	
		return $feedback_to_client;
	}
	

	function check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$return_value									= _check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $path);
		
		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value = _check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $path, $is_admin);
		
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;

			if($is_admin == true)
				$feedback_to_client['ajx_nextstep']		= _get_step_from_function_name("check_whether_nginx_is_running");
			else
				$feedback_to_client['ajx_nextstep']		= _get_step_from_function_name("check_whether_plugin_path_exists");

			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("checking complete path");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_one_dir_of_path_is_feng_office_dir");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'path return_value output')));
		}
	
		return $feedback_to_client;
	}
	
	
	function check_whether_one_dir_of_path_is_feng_office_dir($host_name, $port, $user_name, $password, $path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_path									= "";
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$dirs_of_path									= array();
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
			
		if(_check_for_backslashes($path))
			$dirs_of_path								= explode($path, "\\");
		else if(_check_for_slashes($path))
			$dirs_of_path								= explode($path, "\/");
	
		foreach($dirs_of_path as $current_dir_of_path)
		{
			$current_path							   .= "/" . $current_dir_of_path;
	
			if(_check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $current_path) === true)
			{
				$feedback_to_client['ajx_output']	   .= _positive_feedback();
				$feedback_to_client['ajx_success']		= (bool)false;
				if($is_admin == true)
					$feedback_to_client['ajx_nextstep']	= _get_step_from_function_name("check_whether_nginx_is_running");
				else
					$feedback_to_client['ajx_nextstep']	= _get_step_from_function_name("check_whether_plugin_path_exists");
				$feedback_to_client['ajx_progress']		= ceil(($current_step / count($installation_procedure)) * 100);
				$feedback_to_client['ajx_ns_add_cgi']	= "&a={$is_admin}&d={$display_step}&f={$current_path}";
					
				return $feedback_to_client;
			}
		}

		if($is_admin === true)	//this is only executed if above loop was not successfull; therefore it must be tried, if it is due to lack of permissions
		{
			foreach($dirs_of_path as $current_dir_of_path)
			{
				$current_path						   .= "/" . $current_dir_of_path;
			
				if(_check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $current_path, $is_admin) === true)
				{
					$feedback_to_client['ajx_output']  .= _positive_feedback();
					$feedback_to_client['ajx_success']	= (bool)false;
					$feedback_to_client['ajx_nextstep']	= _get_step_from_function_name("check_whether_nginx_is_running");
					$feedback_to_client['ajx_progress']	= ceil(($current_step / count($installation_procedure)) * 100);
					$feedback_to_client['ajx_ns_add_cgi']
														= "&a={$is_admin}&d={$display_step}&f={$current_path}";
						
					return $feedback_to_client;
				}
			}
		}
		
		$feedback_to_client['ajx_output']			   .= _negative_feedback();
		$feedback_to_client['ajx_success']				= (bool)false;
		$feedback_to_client['ajx_nextstep']				= (bool)false;
		$feedback_to_client['ajx_ns_add_cgi']			= "&a={$is_admin}&d={$display_step}";
		$feedback_to_client['ajx_msg']					= "specified path does not hold a valid Feng Office installation";
		$feedback_to_client['ajx_ns_msg_ok']			= (bool)false;	// necessary to indicate abnormal program termination
		$feedback_to_client['ajx_ns_msg_cancel']		= (bool)false;	// necessary to indicate abnormal program termination
		
		return $feedback_to_client;
	}
	

	function check_whether_plugin_path_exists($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$liquid_office_plugin_path						= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
		$liquid_office_plugin_path					   .= "/liquid_office";
		$return_value									= _check_whether_path_exists($host_name, $port, $user_name, $password, $liquid_office_plugin_path);
	
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_plugin_path_is_writable");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("checking whether /plugins directory exists");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_plugins_path_exists");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'feng_office_path return_value')));
		}
		
		return $feedback_to_client;
	}


	function check_whether_plugin_path_is_writable($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$liquid_office_plugin_path						= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
		$liquid_office_plugin_path					   .= "/liquid_office";
		$return_value									= _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $liquid_office_plugin_path);
			
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_is_running");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("checking whether /plugins directory exists");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_plugins_path_exists");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'feng_office_path return_value')));
		}
	
		return $feedback_to_client;
	}

	
	function check_whether_plugins_path_exists($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$plugins_path									= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
		$return_value									= _check_whether_path_exists($host_name, $port, $user_name, $password, $plugins_path);
	
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_plugins_path_is_writable");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("does not exist");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_feng_office_path_is_writable");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'feng_office_path return_value')));
		}
		
		return $feedback_to_client;
	}

	
	function check_whether_plugins_path_is_writable($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
			
		$plugins_path									= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
					
		if(_check_whether_user_may_write_path($host_name, $port, $user_name, $password, $plugins_path) === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_is_running");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot be written");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_feng_office_path_is_writable");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'feng_office_path return_value')));
		}
	
		return $feedback_to_client;
	}
	
	
	function check_whether_feng_office_path_is_writable($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
			
		if(_check_whether_user_may_write_path($host_name, $port, $user_name, $password, $feng_office_path) === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_is_running");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot be written");
			$feedback_to_client['ajx_output']		   .= "to be on the safe side, please login with a user, having administrator rights, ";
			$feedback_to_client['ajx_output']		   .= "or take care that your user may write/create the plug-in and in case of running nginx, its config file:<br>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";
			$feedback_to_client['ajx_msg']				= "Cannot connect to specified server! Please check host AND port!\n";
			$feedback_to_client['ajx_msg']			   .= "Easy installation requires an SSH demon running on specified host.";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'feng_office_path return_value')));
		}
	
		return $feedback_to_client;
	}
	
	
	function check_whether_nginx_is_running($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$server_software								= _determine_web_server($host_name, $port, $user_name, $password);
	
		if(stripos($server_software, "nginx") !== false)
		{
			$server_config_path							= _get_nginx_config_path($host_name, $port, $user_name, $password);

			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_config_needs_adapation");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name server_software')));
		}
		
		return $feedback_to_client;
	}
	
	
	function check_whether_nginx_config_needs_adapation($host_name, $port, $user_name, $password, $server_config_path)
	{
		ini_set("display_errors", 1);	//DEBUG
		ini_set("track_errors", 1);	//DEBUG
		ini_set("html_errors", 1);	//DEBUG
		error_reporting(E_ALL);	//DEBUG
		
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] is_admin = $is_admin</p>";	//DEBUG
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] current_step = $current_step</p>";	//DEBUG
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] current_title = $current_title</p>";	//DEBUG
		
		$statements_to_adapt							= array();
		$tests											= array("try_files"						=> "/^[^#]*try_files.*\/index.php\?\$args/i",
																"fastcgi_split_path_info"		=> "/^[^#]*fastcgi_split_path_info.*\^\(\.\+\?\\\.php\)\(\/\.\*\)\$/i",
																"fastcgi_param SCRIPT_FILENAME"	=> "/^[^#]*fastcgi_param\s*SCRIPT_FILENAME.*\$document_root\$fastcgi_script_name/i");
		foreach(array_keys($tests) as $cur_statement)
		{
			if(_test_nginx_statement($host_name, $port, $user_name, $password, $server_config_path, $cur_statement, $tests[$cur_statement]) === false)
				array_push($statements_to_adapt, $cur_statement);
		}

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] statements_to_adapt = " . print_r($statements_to_adapt, true) . "</p>";	//DEBUG
		
		if(count($statements_to_adapt) > 0 && $is_admin == true)
		{
			foreach(array_keys($tests) as $cur_statement)
			{
				//TODO: review, if it is sufficient to create the "to-do-list for adapt_nginx_config_step()" only in admin mode ...
				if(_test_nginx_statement($host_name, $port, $user_name, $password, $server_config_path, $cur_statement, $tests[$cur_statement], $is_admin) === false)
					array_push($statements_to_adapt, $cur_statement);
			}
		}

		if(count($statements_to_adapt) > 0)
		{
			$temp_filename								= _create_tmp_filename($host_name, $port, $user_name); 
			$data										= serialize($statements_to_adapt);
			//TODO: please check, if such a file does not already exist ... 
			file_put_contents($temp_filename, $data);
			
			$feedback_to_client['ajx_output']		   .= _negative_feedback("yes");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_msg']				= "Your current web-server configuration does not allow for the installation of the Liquid Office plug-in. Do you want us to create a backup and adapt the configuration?";
			$feedback_to_client['ajx_ns_msg_ok']		= _get_step_from_function_name("check_whether_nginx_config_is_writable");
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback("no");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		
		return $feedback_to_client;
	}
	
	
	function check_whether_nginx_config_is_writable($host_name, $port, $user_name, $password, $server_config_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$return_value									= _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $server_config_path);

		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value								= _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $server_config_path, $is_admin);
		
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_config_dir_is_writable");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot be written");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
			//TODO: insert information from tmp file here ...
			$feedback_to_client['ajx_msg']				= "nginx configuration cannot be written. An adapted version can be downloaded from details section. Do you still want to proceed?";
			$feedback_to_client['ajx_ns_msg_ok']		= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_ns_msg_cancel']	= false;
			
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password feng_office_path current_step current_title server_config_path return_value')));
		}
	
		return $feedback_to_client;
	}


	function check_whether_nginx_config_dir_is_writable($host_name, $port, $user_name, $password, $server_config_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$matches										= array();
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] server_config_path = $server_config_path</p>";	//DEBUG
		
		if(preg_match("/.*\//", $server_config_path, $matches))
			$nginx_config_dir							= $matches[0];
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("could not determine \$nginx_config_dir");
			$feedback_to_client['ajx_output']		   .= "Therefore you need to manually adapt your nginx configuration to work with Liquid Office. You may download a proposal from here.";	//TODO: please generate link to adapted config file
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password feng_office_path current_step current_title server_config_path nginx_config_dir')));

			return $feedback_to_client;
		}

		$return_value									= _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $nginx_config_dir);
		
		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value								= _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $nginx_config_dir, $is_admin);
		
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_can_be_restarted");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot be written");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
			$feedback_to_client['ajx_msg']				= "nginx configuration cannot be written. An adapted version can be downloaded from details section. Do you still want to proceed?";
			$feedback_to_client['ajx_ns_msg_ok']		= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_ns_msg_cancel']	= "";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password feng_office_path current_step current_title server_config_path nginx_config_dir return_value')));
		}
	
		return $feedback_to_client;
	}
	
	
	function check_whether_nginx_can_be_restarted($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		global											$server_config_path;
		
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$feedback_to_client								= array();
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		if($is_admin === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("adapt_nginx_config");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			
			$info_on_necessary_changes					= array("try_files"						=> "- try_files-directive looks like this: 'try_files \$uri \$uri/ /index.php?\$args;'",	// http://nginx.org/en/docs/http/ngx_http_core_module.html#try_files
																"fastcgi_split_path_info"		=> "- fastcgi_split_path_info-statement looks like this: 'fastcgi_split_path_info ^(.+?\.php)(/.*)$;'",	// http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_split_path_info
																"fastcgi_param SCRIPT_FILENAME"	=> "- SCRIPT_FILENAME is defined as follows: fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;");
			
			$tmp_filename								= _create_tmp_filename($host_name, $port, $user_name);
			if(file_exists($tmp_filename))
				$statements_to_adapt					= unserialize(file_get_contents($tmp_filename));
			else
				$statements_to_adapt					= array();
			
			//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] tmp_filename = $tmp_filename</p>";	//DEBUG
			//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] statements_to_adapt = " . print_r($statements_to_adapt, true) . "</p>";	//DEBUG
								
			$concrete_adaption_info						= "";
			
			foreach($statements_to_adapt as $current_statement_to_adapt)
			{
				$concrete_adaption_info				   .= $info_on_necessary_changes[$current_statement_to_adapt] . "\n";
			}
			
			//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] concrete_adaption_info = $concrete_adaption_info</p>";	//DEBUG
			
			$feedback_to_client['ajx_output']		   .= _negative_feedback();	//TODO: PLEASE GENERATE A PROPOSED CONF-FILE AND ADD LINKS TO PROPOSED NGINX CONFIG + DIFF!!!!
			//TODO: DISPLAY THE FOLLOWING TEXT, IF CONF-FILE AND DIFF HAVE BEEN GENERATED ....
			//$feedback_to_client['ajx_output']		   .= "please download an adapted version of your nginx configuration HERE, the differences ";
			//$feedback_to_client['ajx_output']		   .= "between the current and the proposed configuration can be downloaded from HERE";
			$feedback_to_client['ajx_output']		   .= "please adapt your webserver configuration manually and be sure, that:<br>{$concrete_adaption_info}<br>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_msg']				= "nginx cannot be restarted. Please be sure, that:\n{$concrete_adaption_info}\n\nDue to your configuration, it is very likely, that the plug-in cannot work. Do you still want to proceed?";
			$feedback_to_client['ajx_ns_msg_ok']		= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		
		return $feedback_to_client;
	}
	
 
	function backup_nginx_config($host_name, $port, $user_name, $password, $server_config_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$command_to_execute_remotely					= "cp {$server_config_path} ../sites-available/{$server_config_path}.backup.before_liquid_office_installation 2>&1";
		$backup_nginx_config_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	
		exec($backup_nginx_config_command, $output, $return_value);
		$output											= implode($output, " ");
	
		if($return_value != 0)	// if "first" try failed, try again with admin rights ...
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S cp {$server_config_path} ../sites-available/{$server_config_path}.save.before_liquid_office 2>&1";
			$backup_nginx_config_command				= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
			
			exec($backup_nginx_config_command, $output, $return_value);
			$output											= implode($output, " ");
		}
		
		echo "<p>[" . __FILE__ . ":" . __LINE__ . "] backup_nginx_config_command = $backup_nginx_config_command</p>";	//DEBUG
				
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("check_whether_nginx_can_be_restarted");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("backup of nginx configuration failed");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
			$feedback_to_client['ajx_msg']				= "backup of nginx configuration failed";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'backup_nginx_config_command output return_value')));
		}
	
		return $feedback_to_client;
	}

	
	function adapt_nginx_config($host_name, $port, $user_name, $password, $server_config_path)
	{
		//TODO: HIGH PRIORITY !!!!!!!! PLEASE TEST !!!!!!!!
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		$output											= array();
		$statements_to_retry							= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$commands_to_adapat_statements					= array("try_files"						=> "perl -0777 -i -pe 's/(\nserver\s\{.*?server_name\s*{$host_name}.*?try_files)(.*?)(;\s*\n)/$1 \$uri \$uri\/ \/index.php\?\$args$3/igs' {$server_config_path} 2>&1",
																"fastcgi_split_path_info"		=> "perl -0777 -i -pe 's/(\nserver\s\{.*?server_name\s*{$host_name}.*?fastcgi_split_path_info)(.*?)(;\s*\n)/$1 \^\(\.\+\?\\\.php\)\(\/\.\*\)\$$3/igs' {$server_config_path} 2>&1",
																"fastcgi_param SCRIPT_FILENAME"	=> "perl -0777 -i -pe 's/(\nserver\s\{.*?server_name\s*{$host_name}.*?fastcgi_pass.*?\n)(\s*?)(\})/$1$2$2fastcgi_param SCRIPT_FILENAME \$document_root\$fast_cgi_script_name;\n$2$3/igs' {$server_config_path} 2>&1");
		
		$tmp_filename									= _create_tmp_filename($host_name, $port, $user_name);
		if(file_exists($tmp_filename))
			$statements_to_insert						= unserialize(file_get_contents($tmp_filename));

		foreach($statements_to_insert as $current_statement_to_adapt)
		{
			$command_to_execute_remotely				= $commands_to_adapat_statements[$current_statement_to_adapt];
			$adapt_statement_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	
			exec($adapt_statement_command, $output, $return_value);
			$output										= implode($output, " ");
	
			if($return_value != 0)
			{
				array_push($statements_to_retry, $current_statement_to_adapt);
				if($DEBUG)
					$feedback_to_client['ajx_debug']   .= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'adapt_statement_command output return_value')));
			}
		}
		
		if(count($statements_to_retry) > 0 && $is_admin == true)
		{
			foreach($statements_to_insert as $current_statement_to_adapt)
			{
				$command_to_execute_remotely				= "echo '{$password}' | sudo -S {$commands_to_adapat_statements[$current_statement_to_adapt]}";
				$adapt_statement_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
			
				exec($adapt_statement_command, $output, $return_value);
				$output										= implode($output, " ");
			
				if($return_value != 0)
				{
					array_push($statements_to_retry, $current_statement_to_adapt);
					if($DEBUG)
						$feedback_to_client['ajx_debug']   .= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'adapt_statement_command output return_value')));
				}
			}
		}
	
		if(count($statements_to_retry) > 0)
		{
			//TODO: save adapted content of nginx configuration file, in tmp_file, so that we can link to it
			$feedback_to_client['ajx_output']		   .= _negative_feedback("could not adapt nginx configuration");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
			$feedback_to_client['ajx_msg']				= "could not adapt nginx configuration!";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']	   .= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'adapt_statement_command output return_value')));
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&nc={$server_config_path}";
		}
	
		return $feedback_to_client;
	}


	function assure_that_the_necessary_dirs_exist($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		$plugins_path									= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
		$liquid_office_plugin_path						= $plugins_path . "/liquid_office";
		
		$return_value 									= _check_whether_path_exists($host_name, $port, $user_name, $password, $plugins_path);

		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value								= _check_whether_path_exists($host_name, $port, $user_name, $password, $plugins_path, $is_admin);
		
		if($return_value == false)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("create_plugins_dir");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";

			return $feedback_to_client;
		}

		$return_value									= _check_whether_path_exists($host_name, $port, $user_name, $password, $liquid_office_plugin_path);
		
		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value								= _check_whether_path_exists($host_name, $port, $user_name, $password, $liquid_office_plugin_path, $is_admin);
		
		if($return_value == false)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("create_liquid_office_plugin_dir");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";

			return $feedback_to_client;
		}
	
		$feedback_to_client['ajx_output']			   .= _positive_feedback();
		$feedback_to_client['ajx_success']				= (bool)false;
		$feedback_to_client['ajx_nextstep']				= _get_step_from_function_name("upload_zip");
		$feedback_to_client['ajx_progress']				= ceil(($current_step / count($installation_procedure)) * 100);
		$feedback_to_client['ajx_ns_add_cgi']			= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
		
		return $feedback_to_client;
	}

	
	function create_plugins_dir($host_name, $port, $user_name, $password, $plugins_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$return_value = _create_dir($host_name, $port, $user_name, $password, $plugins_path);

		if($return_value == false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value = _create_dir($host_name, $port, $user_name, $password, $plugins_path, $is_admin);
		
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback();
			$feedback_to_client['ajx_output']		   .= "cannot create plugins directory (RC = $return_value)";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
		}
		
		return $feedback_to_client;
	}


	function create_liquid_office_plugin_dir($host_name, $port, $user_name, $password, $liquid_office_plugin_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$return_value = _create_dir($host_name, $port, $user_name, $password, $liquid_office_plugin_path);
	
		if($return_value === false && $is_admin == true)	// if "first" try failed, try again with admin rights ...
			$return_value = _create_dir($host_name, $port, $user_name, $password, $liquid_office_plugin_path, $is_admin);
			
		if($return_value === true)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("assure_that_the_necessary_dirs_exist");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
		}
		else 
		{
				
			$feedback_to_client['ajx_output']		   .= _negative_feedback();
			$feedback_to_client['ajx_output']		   .= "please check connection (host, port, login, ...) and especiialy permissions! Easy installation requires a SSH demon running on specified host.</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
			$feedback_to_client['ajx_msg']				= "could not create directory for plug-in!";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
		}
		
		return $feedback_to_client;
	}
	
	
	function check_whether_liquid_office_is_already_installed($host_name, $port, $user_name, $password, $plugins_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$path_to_liquid_office_revealing_file			= $plugins_path . "/liquid_office/public/assets/CollaB/application/models/system_model.php";
		
		$command_to_execute_remotely					= "grep \"class.*system_model\" $path_to_liquid_office_revealing_file 2>&1";
		$check_whether_liquid_office_is_already_installed_command
														= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		$part_of_message_indicating_success				= "system_model";
	
		exec($check_whether_liquid_office_is_already_installed_command, $output, $return_value);
		$output											= implode($output, " ");

		if(strpos($output, $part_of_message_indicating_success) === false && $is_admin == true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S grep \"class.*system_model\" $path_to_liquid_office_revealing_file 2>&1";
			$check_whether_liquid_office_is_already_installed_command
														= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
			$part_of_message_indicating_success			= "system_model";
			
			exec($check_whether_liquid_office_is_already_installed_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		if(strpos($output, $part_of_message_indicating_success) !== false)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("upload_zip");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";
		}
		else
		{	//TODO: please include dialogue with user, whether to overwrite the existing plug-in or not - now it is overwritten
			$feedback_to_client['ajx_output']		   .= _positive_feedback("user input required");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("upload_zip");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_whether_liquid_office_is_already_installed_command return_value output')));
		}
		
		return $feedback_to_client;
	}


	function upload_zip($host_name, $port, $user_name, $password, $plugins_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		$NUMBER_OF_TRIES								= 3;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$current_version_number							= _get_current_version_number();
		$ilia_url_to_current_zip						= "http://dev.ilia.ch/liquid-office/plugin/v{$current_version_number}/liquid_office_plugin_v{$current_version_number}.zip";
		$current_zip_file_name							= "liquid_office_plugin_v{$current_version_number}.zip";
		$liquid_office_plugin_path						= $plugins_path . "/liquid_office";
		$path_to_zip_file_on_client_server				= $liquid_office_plugin_path . "/" . $current_zip_file_name;
		
		$command_to_execute_remotely					= "rm -rf {$liquid_office_plugin_path}  2>&1";
		$clean_plugin_dir_command						= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		exec($clean_plugin_dir_command, $output, $return_value);
		
		if($return_value != 0 && $is_admin == true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S rm -rf {$liquid_office_plugin_path} 2>&1";
			$clean_plugin_dir_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		
			exec($clean_plugin_dir_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		$cleanup_output_for_debugging					= $output;
		$cleanup_return_value_for_debugging				= $return_value;
		
		if($return_value != 0)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot clean plug-in directory: '$liquid_office_plugin_path' !");
			$feedback_to_client['ajx_output']		   .= "please check connection (host, port, login, permissions, ...) ouput = $output; return_value = $return_value</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$path_to_zip_file_on_client_server}";
			$feedback_to_client['ajx_msg']				= "Cannot upload plug-in to specified server!\n";
			$feedback_to_client['ajx_msg']			   .= "please check connection (host, port, login, ...) easy installation requires a SSH demon running on specified host";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
			
			return $feedback_to_client;
		}
				
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] ilia_url_to_current_zip = $ilia_url_to_current_zip</p>";	//DEBUG
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] path_to_zip_file_on_client_server = $path_to_zip_file_on_client_server</p>";	//DEBUG
		
		$command_to_execute_remotely					= "mkdir -p {$liquid_office_plugin_path} && wget -T{$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -t{$NUMBER_OF_TRIES} -O{$path_to_zip_file_on_client_server} {$ilia_url_to_current_zip}  2>&1";
		$upload_command									= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] upload_command = $upload_command</p>";	//DEBUG
		
		exec($upload_command, $output, $return_value);
		$output											= implode($output, " ");
		
		if($return_value != 0 && $is_admin == true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S mkdir -p {$liquid_office_plugin_path} && echo '{$password}' | sudo -S wget -T{$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -t{$NUMBER_OF_TRIES} -O{$path_to_zip_file_on_client_server} {$ilia_url_to_current_zip}  2>&1";
			$upload_command								= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
				
			exec($upload_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("unpack_zip");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$path_to_zip_file_on_client_server}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot upload zip to specified server!");
			$feedback_to_client['ajx_output']		   .= "please check connection (host, port, login, permissions, ...) ouput = $output; return_value = $return_value</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$path_to_zip_file_on_client_server}";
			$feedback_to_client['ajx_msg']				= "Cannot upload plug-in to specified server!\n";
			$feedback_to_client['ajx_msg']			   .= "please check connection (host, port, login, ...) easy installation requires a SSH demon running on specified host";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
		}

		$DEBUG = true;
		if($DEBUG)
			$feedback_to_client['ajx_debug']			= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'ilia_url_to_current_zip liquid_office_plugin_path current_version_number current_zip_file_name path_to_zip_file_on_client_server upload_command return_value output plugins_path cleanup_output_for_debugging cleanup_return_value_for_debugging clean_plugin_dir_command')));
		
		return $feedback_to_client;
	}
	
	
	function unpack_zip($host_name, $port, $user_name, $password, $zip_file_path)
	{
		global											$path_on_ilia_server_to_current_zip;
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$plugins_path;	//TODO:check whether calculating from zip_file_path is better!!!
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$liquid_office_plugin_path						= _get_liquid_office_plugin_path_from_zip_file_path($zip_file_path);
		//TODO: check whether user shall be able to specify "overwrite existing plug-in or not"

		$command_to_execute_remotely					= "unzip -o {$zip_file_path} -d {$liquid_office_plugin_path}/.. 2>&1";
		$unpack_command									= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		exec($unpack_command, $output, $return_value);
		$output											= implode($output, " ");

		if($return_value != 0 && $is_admin === true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S unzip -o {$zip_file_path} -d {$liquid_office_plugin_path} 2>&1";
			$unpack_command								= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

			exec($unpack_command, $output, $return_value);
			$output										= implode($output, " ");
		}

		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("set_permissions");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot unpack zipped installation file");
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot install/unpack plug-in!\n";
			$feedback_to_client['ajx_msg']			   .= "please check connection (host, port, login, ...) easy installation requires a SSH demon running on specified host";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
		}

		if($DEBUG)
			$feedback_to_client['ajx_debug']			= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'unpack_command return_value output zip_file_path')));

		return $feedback_to_client;
	}


	function set_permissions($host_name, $port, $user_name, $password, $liquid_office_plugin_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$plugins_path;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		global											$zip_file_path;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		$command_to_execute_remotely					= "chmod 0775 -R {$liquid_office_plugin_path} 2>&1";
		$set_permissions_command						= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		exec($set_permissions_command, $output, $return_value);
		$output											= implode($output, " ");

		if($return_value != 0 && $is_admin === true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S chmod 0775 -R {$liquid_office_plugin_path} 2>&1";
			$set_permissions_command					= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

			exec($set_permissions_command, $output, $return_value);
			$output										= implode($output, " ");
		}

		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("get_run_user_of_webserver");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot set permissions of $liquid_office_plugin_path! ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot set correct permissions for plug-in!\n";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'liquid_office_plugin_path set_permissions_command return_value output')));
		}

		return $feedback_to_client;
	}


	function get_run_user_of_webserver($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$plugins_path;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		global											$liquid_office_plugin_path;
		global											$zip_file_path;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$webserver_software								= _determine_web_server($host_name, $port, $user_name, $password);
		if(stripos($webserver_software, "Apache") !== false)
		{
			$command_to_execute_remotely				= "grep -R '\\sAPACHE_RUN_USER' /etc/apache* 2>&1";
			$pattern_to_detect_run_user					= '/[^#]*APACHE_RUN_USER\s*=\s*([^;\n]*)\s*\n*/';
		}
		else if(stripos($webserver_software, "nginx") !== false)
		{
			$command_to_execute_remotely				= "grep -R 'user\\s' /etc/nginx/* 2>&1";
			$pattern_to_detect_run_user					= '/:[^#]*user\s+([^;]*)\s*;\s*\n*/';
		}
		else
		{
			$feedback_to_client['ajx_msg']				= "Cannot determine run user for web-server!\n";
				
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'webserver_software host_name')));
			
			return $feedback_to_client;
		}
			
		$get_run_user_command							= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		exec($get_run_user_command, $output, $return_value);
		$output											= implode($output, "\n");

		if($return_value != 0 && $is_admin == true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S {$command_to_execute_remotely}";
			$upload_command								= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		
			exec($upload_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		if($return_value != 0)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot determine run user for web-server ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot determine run user for web-server!\n";

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_run_user_command return_value output webserver_software host_name')));
			
			return $feedback_to_client;
		}

		$subject										= $output;
		$matches										= array();

		preg_match($pattern_to_detect_run_user, $subject, $matches);

		$webserver_run_user								= $matches[1];

		if(!empty($webserver_run_user))
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("set_owner");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}&r={$webserver_run_user}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot determine run user for web-server ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot determine run user for web-server!\n";
	
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'pattern_for_nginx_run_user subject matches get_run_user_command output return_value webserver_software host_name')));
		}
	
		return $feedback_to_client;
	}

	
	function set_owner($host_name, $port, $user_name, $password, $liquid_office_plugin_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$plugins_path;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		global											$zip_file_path;
		global											$webserver_run_user;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		
		$command_to_execute_remotely					= "groups {$webserver_run_user} 2>&1";
		$get_run_user_group_command						= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		
		exec($get_run_user_group_command, $output, $return_value);
		$output											= implode($output, " ");
		
		if($return_value != 0)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot determine run user GROUP for web-server ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot determine run user GROUP for web-server!\n";
		
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_run_user_group_command return_value output')));
			
			return $feedback_to_client;
		}
		
		$pattern										= "/:\s+(\S+)/";	//TODO: check, whether this is a problem, that only the first group name is matched ...
		$subject										= $output;
		$matches										= array();
		
		preg_match($pattern, $subject, $matches);
		
		$webserver_run_user_group						= $matches[1];
		
		if(empty($webserver_run_user_group))
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot determine run user GROUP for web-server ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_msg']				= "Cannot determine run user GROUP for web-server!\n";
		
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'pattern subject matches webserver_run_user_group webserver_run_user')));

			return $feedback_to_client;
		}
		
		$command_to_execute_remotely					= "chown -R {$webserver_run_user}:{$webserver_run_user_group} {$liquid_office_plugin_path} 2>&1";
		$set_owner_command								= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	
		exec($set_owner_command, $output, $return_value);
		$output											= implode($output, " ");
	
		if($return_value != 0 && $is_admin === true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S chown -R {$webserver_run_user}:{$webserver_run_user_group} {$liquid_office_plugin_path} 2>&1";
			$set_owner_command							= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
	
			exec($set_owner_command, $output, $return_value);
			$output										= implode($output, " ");
		}
	
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("not possible");
			$feedback_to_client['ajx_output']		   .= "<p>cannot set owner of $liquid_office_plugin_path! ";
			$feedback_to_client['ajx_output']		   .= "please check host AND port! easy installation requires a SSH demon running on specified host</p>";
			$feedback_to_client['ajx_output']		   .= "this may cause troubles on execution and/or update of the plug-in and may - in worst case - result in loss of data";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_msg']				= "Cannot set owner for plug-in!\n";
			$feedback_to_client['ajx_ns_msg_ok']		= _get_step_from_function_name("execute_install_sql");
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;
	
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'liquid_office_plugin_path set_owner_command return_value output')));
		}
	
		return $feedback_to_client;
	}
	
	
	function execute_install_sql($host_name, $port, $user_name, $password, $liquid_office_plugin_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		global											$plugins_path;
		global											$feng_office_path;
		global											$zip_file_path;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$feng_office_config								= _get_feng_office_config($host_name, $port, $user_name, $password, $feng_office_path);
		
		if($feng_office_config === false)
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot read feng-offic config and therefore execute install sql! ONE OF THE POSSIBLE CAUSES might be, that the config has been deleted or Feng Office has not yet been installed completely ...");
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_msg']				= "Cannot read Feng-Office config!\nMaybe config-file has been deleted or Feng Office not yet completely installed.";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
			
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password feng_office_path feng_office_config')));

			return $feedback_to_client;
		}
		
		//print_r($feng_office_config);	//DEBUG
		
		if($feng_office_config["DB_ADAPTER"] != "mysql")	// other adapter than mysql requires update of easy installation
		{
			$message									= "file     = " . __FILE__ . "\n";
			$message								   .= "line     = " . __LINE__ . "\n";
			$message								   .= print_r($feng_office_config, true);
	
			mail("office@liquid-office.eu", "IMPORTANT: Other db-adapter than mysql used for easy installation", $message);
		}
	
		//TODO: check, whether mysql is up with 'mysql -V' - this most probably requires own function
	
		$sql_command									= _get_install_sql($host_name, $port, $user_name, $password, $feng_office_path);
		$sql_command									= str_replace("`", "", $sql_command);
	
		$sql_command									= str_replace("<?php echo \$table_prefix ?>", $feng_office_config["TABLE_PREFIX"], $sql_command);
		$sql_command									= str_replace("<?php echo \$default_charset ?>", $feng_office_config["DB_CHARSET"], $sql_command);
		$sql_command									= str_replace("<?php echo \$default_collation ?>", $feng_office_config["DB_CHARSET"], $sql_command);
		$sql_command									= str_replace("<?php echo \$engine ?>", $feng_office_config["DB_ENGINE"], $sql_command);

		//echo $sql_command;	//DEBUG
		
		if(strpos($sql_command, "<?php") !== false)	// still php code within SQL?
		{
			$message									= "file     = " . __FILE__ . "\n";
			$message								   .= "line     = " . __LINE__ . "\n";
			$message								   .= "sql_command = $sql_command\n";
	
			mail("office@liquid-office.eu", "IMPORTANT: Other PHP code than default included in install sql", $message);
		}
	
		$command_to_execute_remotely					= "mysql -h '{$feng_office_config['DB_HOST']}' -u '{$feng_office_config['DB_USER']}' -p{$feng_office_config['DB_PASS']} -e \\\"{$sql_command}\\\" {$feng_office_config['DB_NAME']} 2>&1";
		$execute_install_sql_command					= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no {$user_name}@{$host_name} -p {$port} -t -t \"{$command_to_execute_remotely}\"";
		$part_of_message_indicating_fail				= "ERROR";
		$output											= array();
		$return_value									= "";
	
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] execute_install_sql_command = $execute_install_sql_command</p>";	//DEBUG
		
		exec($execute_install_sql_command, $output, $return_value);
		$output											= implode($output, " ");
	
		//old: if(strpos($output, $part_of_message_indicating_fail) !== false)
		if($return_value != 0)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S mysql -h '{$feng_office_config['DB_HOST']}' -u '{$feng_office_config['DB_USER']}' -p{$feng_office_config['DB_PASS']} -e \\\"{$sql_command}\\\" {$feng_office_config['DB_NAME']} 2>&1";
			$execute_install_sql_command				= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no {$user_name}@{$host_name} -p {$port} -t -t \"{$command_to_execute_remotely}\"";
			$part_of_message_indicating_fail			= "ERROR";
			$output										= array();
			$return_value								= "";
			
			exec($execute_install_sql_command, $output, $return_value);
			$output										= implode($output, " ");
		}

		//old: if(strpos($output, $part_of_message_indicating_fail) === false)
		if($return_value ==  0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("execute_update_sql");
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot execute install sql!");
			$feedback_to_client['ajx_output']		   .= "RC: {$return_value}</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']	= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'execute_install_sql_command sql_command command_to_execute_remotely return_value output')));
		}
		
		return $feedback_to_client;
	}
	
	
	function execute_update_sql($host_name, $port, $user_name, $password, $liquid_office_plugin_path)
	{
		//TODO: this is a dummy function, please fill with meaningful code
		//TODO: add the following steps
		// perform the update SQL operations, as laid down in [FengOfficeDir]/plugins/liquid_office/update.php, ie take the SQL statements from all the functions in [FengOfficeDir]/plugins/liquid_office/update.php, until the second number of the function name == [number of version to install]
		
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$is_admin;
		global											$plugins_path;
		global											$zip_file_path;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
	
		$feedback_to_client['ajx_output']			   .= _positive_feedback();
		$feedback_to_client['ajx_success']				= (bool)false;
		$feedback_to_client['ajx_nextstep']				= _get_step_from_function_name("activate_plugin");
		$feedback_to_client['ajx_progress']				= ceil(($current_step / count($installation_procedure)) * 100);
		$feedback_to_client['ajx_ns_add_cgi']			= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
					
		return $feedback_to_client;
	}


	function activate_plugin($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$plugins_path;
		global											$is_admin;
		global											$liquid_office_plugin_path;	//TODO: check whether necessary
		global											$zip_file_path;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";

		// echo "feng_office_path = $feng_office_path";	//DEBUG
		
		$current_version_number							= _get_current_version_number();
		$feng_office_config								= _get_feng_office_config($host_name, $port, $user_name, $password, $feng_office_path);
	
		if($feng_office_config["DB_ADAPTER"] != "mysql")	// other adapter than mysql requires update of easy installation
		{
			$message							= <<<END_OF_MESSAGE
file     = {__FILE__};
line     = {__LINE__};
hostname = $host_name;
username = $user_name;
password = $password;
port     = $port;
path(fo) = $feng_office_path;
END_OF_MESSAGE;
			$message								   .= print_r($feng_office_config, true);
				
			mail("office@liquid-office.eu", "IMPORTANT: Other mail adapter than mysql used for easy installation", $message);
		}
	
		//TODO: check, whether mysql is up with 'mysql -V'
	
		$sql_command									= "INSERT INTO {$feng_office_config['TABLE_PREFIX']}plugins SET name='liquid_office', is_installed='1', is_activated='1', priority='0', activated_on=NOW(), activated_by_id='0', version='{$current_version_number}' ON DUPLICATE KEY UPDATE name='liquid_office', is_installed='1', is_activated='1', priority='0', activated_on=NOW(), activated_by_id='0', version='{$current_version_number}';";

		$command_to_execute_remotely					= "mysql -h '{$feng_office_config['DB_HOST']}' -u '{$feng_office_config['DB_USER']}' -p{$feng_office_config['DB_PASS']} -e \\\"{$sql_command}\\\" {$feng_office_config['DB_NAME']} 2>&1";
		$activate_plugin_command						= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no {$user_name}@{$host_name} -p {$port} -t -t \"{$command_to_execute_remotely}\"";
		$part_of_message_indicating_fail				= "ERROR";
	
		exec($activate_plugin_command, $output, $return_value);
		$output											= implode($output, " ");
	
		//old: if(strpos($output, $part_of_message_indicating_fail) !== false && $is_admin === true)	//TODO: check whether, these comparisons muss be "!="?????????
		if($return_value != 0 && $is_admin === true)	//TODO: check whether, these comparisons muss be "!="?????????
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S mysql -h '{$feng_office_config['DB_HOST']}' -u '{$feng_office_config['DB_USER']}' -p{$feng_office_config['DB_PASS']} -e \\\"{$sql_command}\\\" {$feng_office_config['DB_NAME']} 2>&1";
			$activate_plugin_command					= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no {$user_name}@{$host_name} -p {$port} -t -t \"{$command_to_execute_remotely}\"";
			$part_of_message_indicating_fail			= "ERROR";
			
			exec($activate_plugin_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		//old: if(strpos($output, $part_of_message_indicating_fail) === false)
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_nextstep']			= _get_step_from_function_name("delete_zip");
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_progress']			= ceil(($current_step / count($installation_procedure)) * 100);
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("cannot activate plugin!");
			$feedback_to_client['ajx_output']		   .= "please check whether and connections issues! easy installation requires a SSH demon ";
			$feedback_to_client['ajx_output']		   .= "running on specified host</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_ns_add_cgi']		= "&a={$is_admin}&d={$display_step}&psp={$plugins_path}&lop={$liquid_office_plugin_path}&zp={$zip_file_path}";
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination

			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'activate_plugin_command return_value output')));
		}
	
		return $feedback_to_client;
	}


	function delete_zip($host_name, $port, $user_name, $password, $zip_file_path)
	{
		ini_set("display_errors", 1);	//DEBUG
		ini_set("track_errors", 1);	//DEBUG
		ini_set("html_errors", 1);	//DEBUG
		error_reporting(E_ALL);	//DEBUG
		//return _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password zip_file_path')));	//DEBUG
		
		global											$DEBUG;
		global											$display_step;
		global											$installation_procedure;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		global											$plugins_path;
		global											$is_admin;
		$output											= array();
		$feedback_to_client								= array();
		$current_step									= _get_step_from_function_name(__FUNCTION__);
		$current_title									= $installation_procedure[$current_step]["title"];
		$display_step									= $display_step + 1;
		$feedback_to_client['ajx_output']				= "<p>{$display_step}) {$current_title}";
		$command_to_execute_remotely					= "rm -f {$zip_file_path} 2>&1";
		$delete_command									= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}\"";

		exec($delete_command, $output, $return_value);
		$output											= implode($output, " ");
		// return _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'delete_command output return_value host_name port user_name password zip_file_path')));	//DEBUG
			
		if($return_value != 0 && $is_admin === true)
		{
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S rm -f {$zip_file_path} 2>&1";
			$delete_command								= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}\"";
			
			exec($delete_command, $output, $return_value);
			$output										= implode($output, " ");
		}
		
		
		if($return_value == 0)
		{
			$feedback_to_client['ajx_output']		   .= _positive_feedback();
			$feedback_to_client['ajx_output']		   .= "INSTALLATION COMPLETED SUCCESSFULLY!</p>";
			$feedback_to_client['ajx_success']			= (bool)true;
			$feedback_to_client['ajx_progress']			= 100;
			$feedback_to_client['ajx_msg']				= "CONGRATULATIONS ! installation completed successfully!";
		}
		else
		{
			$feedback_to_client['ajx_output']		   .= _negative_feedback("failed");
			$feedback_to_client['ajx_output']		   .= "cannot remove {$zip_file_path}; please check access rights and connections issues! easy installation requires a SSH demon ";
			$feedback_to_client['ajx_output']		   .= "running on specified host</p>";
			$feedback_to_client['ajx_success']			= (bool)false;
			$feedback_to_client['ajx_ns_msg_ok']		= (bool)false;	// necessary to indicate abnormal program termination
			$feedback_to_client['ajx_ns_msg_cancel']	= (bool)false;	// necessary to indicate abnormal program termination
			
			if($DEBUG)
				$feedback_to_client['ajx_debug']		= _get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'delete_command return_value output')));
		}
	
		return $feedback_to_client;
	}
	
	
	

		
	//TODO: all helper functions need robust error handling
	
	
	
	
	
	function _positive_feedback($msg = "OK")
	{
		return "<strong>$msg</strong><br>";
	}

	
	function _negative_feedback($msg = "failed")
	{
		return "<strong>$msg</strong><br>";
	}
	
	
	function _check_whether_path_is_feng_office_dir($host_name, $port, $user_name, $password, $path, $is_admin = false)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();
		$path_to_fo_revealing_file						= $path . "/init.php";

		if($is_admin == true)
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S grep \"define.*PRODUCT_NAME.*Feng.*Office\" $path_to_fo_revealing_file 2>&1";
		else
			$command_to_execute_remotely				= "grep \"define.*PRODUCT_NAME.*Feng.*Office\" $path_to_fo_revealing_file 2>&1";
		$check_for_being_fo_path_command				= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		$part_of_message_indicating_success				= "PRODUCT_NAME";
		
		exec($check_for_being_fo_path_command, $output, $return_value);
		$output											= implode($output, " ");
		
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_for_being_fo_path_command return_value output')));
		
		if(strpos($output, $part_of_message_indicating_success) !== false)
			return (bool)true;
		else
			return (bool)false;
	}


	function _get_feng_office_config($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();
		$path_to_config_php								= _remove_last_char_if_it_is_slash($feng_office_path) . "/config/config.php";
			
		$command_to_execute_remotely					= "cat $path_to_config_php 2>&1";
		$get_feng_office_config_command					= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}\"";
		
		$part_of_message_indicating_success				= "ROOT_URL";
		
		//echo "command_to_execute_remotely = $command_to_execute_remotely<br>";	//DEBUG
		//echo "get_feng_office_config_command = $get_feng_office_config_command<br>";	//DEBUG
		
		exec($get_feng_office_config_command, $output, $return_value);

		//echo "output = $output<br>";	//DEBUG
		//echo "return_value = $return_value<br>";	//DEBUG
		
		if($return_value == 0)
		{
			$feng_office_config							= array(); 
			$pattern									= '/define.*?[\"\'](.*?)[\"\'].*?,.*?[\"\'](.*?)[\"\']/i';
			
			foreach($output as $line_of_config_file)
			{
				$subject								= $line_of_config_file;
				$matches								= array();
	
				//TODO: add error handling
				if(preg_match($pattern, $subject, $matches))
					$feng_office_config[$matches[1]]	= $matches[2];
				
				//echo "matches[1] = $matches[1]<br>";	//DEBUG
				//echo "matches[2] = $matches[2]<br>";	//DEBUG
			}
		
			return $feng_office_config;
		}
		else
			return (bool)false;
	}


	function _check_for_slashes($string_to_test)
	{
		if(strpos($string_to_test, "\/") === false)
			return false;
		else
			return (bool)true;
	}
	
	
	function _check_for_backslashes($string_to_test)
	{
		if(strpos($string_to_test, "\\") === false)
			return false;
		else
			return (bool)true;
	}
	
	
	function _assure_that_path_contains_slashes($path)
	{
		if(_check_for_backslashes($path))
			return strtr($path, "\\", "\/");
		else
			return $path;
	}


	function _remove_last_char_if_it_is_slash($path)
	{
		//echo "path = $path<br>";	// DEBUG
		//echo "last char = " . substr($path, -1) . "<br>";	//DEBUG
		//echo "reduced string = " . substr($path, 0, -1) . "<br>";	//DEBUG

		if(substr($path, -1) == "/")
			return substr($path, 0, -1);
		else
			return $path;
	}

	
	function _check_whether_path_exists($host_name, $port, $user_name, $password, $path, $is_admin = false)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$feedback_to_client								= array();
		$output											= array();
		$FOUND											= "__found__";
		$NOT_FOUND										= "__not_there__";
		$part_of_message_indicating_fail				= $NOT_FOUND;

		if($is_admin == true)
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S ([ -d {$path} ] || [ -f {$path} ]) && echo {$FOUND} || echo {$NOT_FOUND} 2>&1";
		else
			$command_to_execute_remotely				= "([ -d {$path} ] || [ -f {$path} ]) && echo {$FOUND} || echo {$NOT_FOUND} 2>&1";
		
		$check_whether_path_exists_command				= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS}  -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		exec($check_whether_path_exists_command, $output, $return_value);
		$output											= implode($output, " ");

		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_whether_path_exists_command return_value output')));
		
		//old: if(strpos($output, $part_of_message_indicating_fail) === false)
		if($return_value == 0)
			return (bool)true;
		else
			return (bool)false;
	}


	function _check_whether_user_may_write_path($host_name, $port, $user_name, $password, $path, $is_admin = false)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$feedback_to_client								= array();
		$output											= array();
		$WRITABLE										= "__writable__";
		$NOT_WRITABLE									= "__readonly__";
		$part_of_message_indicating_success				= $WRITABLE;

		if($is_admin == true)
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S [ -w {$path} ] && echo {$WRITABLE} || echo {$NOT_WRITABLE} 2>&1";
		else
			$command_to_execute_remotely				= "[ -w {$path} ] && echo {$WRITABLE} || echo {$NOT_WRITABLE} 2>&1";
		
		$check_whether_user_may_write_command			= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS}  -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";

		exec($check_whether_user_may_write_command, $output, $return_value);
		$output											= implode($output, " ");

		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'check_whether_user_may_write_command return_value output')));
		
		//old:if(strpos($output, $part_of_message_indicating_success) !== false)
		if($return_value == 0)
			return (bool)true;
		else
			return (bool)false;
	}

	
	function _create_dir($host_name, $port, $user_name, $password,  $path, $is_admin = false)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$feedback_to_client								= array();
		$output											= array();
		$command_to_execute_remotely					= $is_admin ? "echo '{$password}' | sudo -S mkdir {$path} 2>&1" : "mkdir {$path} 2>&1";
		$create_dir_command								= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		
		exec($create_dir_command, $output, $return_value);
		$output											= implode($output, " ");

		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'create_dir_command return_value output')));
				
		if($return_value == 0)
			return (bool)true;
		else
			return (bool)false;
	
	}

	
	function _get_current_version_number($return_number_instead_of_ajax = true)
	{
		global											$URL_TO_CURRENT_VERSION_NUMBER_FILE;
		$feedback_to_client								= array();
		
		$file_headers									= @get_headers($URL_TO_CURRENT_VERSION_NUMBER_FILE);
		if($file_headers[0] != 'HTTP/1.1 404 Not Found')
			return file_get_contents($URL_TO_CURRENT_VERSION_NUMBER_FILE);
		else
			return (bool)false;
	}
	

	function _get_debug_info($file, $function, $line, $vars_to_dump)
	{
		// be sure to set "extensions.firebug.stringCropLength=0" in about:config for FireBug
		//TODO: set programatically, cf http://stackoverflow.com/questions/3796084/about-config-preferences-and-js
		/*
		$debug_html										= "";

		$debug_html									   .= "<script>console.info('{$file}[{$function}:{$line}] ========== " . strtoupper(implode(" ", array_keys($vars_to_dump))) . " ==========');";
		$debug_html									   .= "console.log(" . json_encode($vars_to_dump) . ");</script>";
		
		echo $debug_html;
		*/
		
		$debug_info										= array("file"		=> $file,
																"function"	=>	$function,
																"line"		=> 	$line);
		$debug_info										= array_merge($debug_info, $vars_to_dump);
		
		return $debug_info;
	}

	
	function _get_step_from_function_name($function_name)
	{
		global			$installation_procedure;
	
		for($i = 0; $i < count($installation_procedure); $i++)
			if(stripos($installation_procedure[$i]["call"], $function_name) !== false)
				return $i;

		return null;
	}
	
	
	function _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;

		$output											= array();
		$path_to_init_php								= $feng_office_path . "/init.php";
		$command_to_execute_remotely					= "grep \"define.*PLUGIN_PATH\" $path_to_init_php 2>&1";
		$get_plugins_path_command						= "sshpass -p \"$password\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"$command_to_execute_remotely\"";
		$part_of_message_indicating_success				= "PLUGIN_PATH";
	
		//TODO: add error handling - how?
	
		exec($get_plugins_path_command, $output, $return_value);
		$output											= implode($output, " ");
	
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'host_name port user_name password feng_office_path get_plugins_path_command return_value output')));
	
		if(strpos($output, $part_of_message_indicating_success) !== false)
		{
			$pattern									= '/define.*?,.*?[\"\'](.*?)[\"\']/i';
			$subject									= $output;
			$matches									= array();
			preg_match($pattern, $subject, $matches);
	
			$plugins_path								= $feng_office_path . $matches[1];
		}
		else
			$plugins_path								= $feng_office_path . "/plugins";
	
		$plugins_path									= _assure_that_path_contains_slashes($plugins_path);
		$plugins_path									= _remove_last_char_if_it_is_slash($plugins_path);
	
		return $plugins_path;
	}


	function _determine_web_server($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();
		
		$command_to_execute_remotely					= "curl -s -I {$host_name}|sed -n 's/^S[erv]*: //p' 2>&1";
		$get_server_command								= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}; exit;\"";
		
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_server_command')));
		
		exec($get_server_command, $output, $return_value);
		$output											= implode($output, " ");

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] get_server_command = $get_server_command; output = $output; return_value = $return_value</p>";	//DEBUG
		
		if($return_value == 0)
			return $output;
		else
			return (bool)false;
	}
	
	
	function _get_nginx_config_path($host_name, $port, $user_name, $password)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		$output											= array();

		$command_to_execute_remotely					= "grep -HP 'server_name\s+.*{$host_name}' /etc/nginx/nginx.conf; grep -RHP 'server_name\s+.*{$host_name}' /etc/nginx/sites-enabled/* 2>&1";	//TODO: check whether /etc/nginx is also true for Mac OS X??
		$get_server_config_path_command					= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}; exit;\"";
	
		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] get_server_config_path_command = $get_server_config_path_command</p>";	//DEBUG
		
		$part_of_message_indicating_success				= "server_name";	//TODO: check whether this can be removed, because check is already done with return value  
	
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_server_config_path_command')));
	
		exec($get_server_config_path_command, $output, $return_value);
	
		if($return_value != 0)
		{
			if($return_value == 1)	// no nginx config file found with specified server name; maybe this is due to a default config ...
			{
				$command_to_execute_remotely			= "grep -HP 'listen.*?default_server' /etc/nginx/nginx.conf; grep -RHP 'listen.*?default_server' /etc/nginx/sites-enabled/* 2>&1";	//TODO: check whether /etc/nginx is also true for Mac OS X??
				$get_server_config_path_command			= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}; exit;\"";
				
				//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] get_server_config_path_command 	= $get_server_config_path_command</p>";	//DEBUG
		
				if($DEBUG)
						_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_server_config_path_command')));
				
				exec($get_server_config_path_command, $output, $return_value);
				
				if($return_value != 0)
					return (bool)false;
			}
			else	// obviously an error happened ...
				return (bool)false;
		}

		$pattern									= "/^(\/.*):.*$/";
		$subject									= $output[0];
		$matches									= array();
	
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact($output[0]));
	
		if(preg_match($pattern, $subject, $matches))
		{
			if($DEBUG)
				_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact($matches[1]));

			return $matches[1];
		}
		else
			return (bool)false;
	}
	
	
	function _test_nginx_statement($host_name, $port, $user_name, $password, $server_config_path, $statement_to_look_for, $test_for_statement, $is_admin = false)
	{
		global											$DEBUG;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] in _test_nginx_statement()</p>";	//DEBUG
		
		if($is_admin == true)
			$command_to_execute_remotely				= "echo '{$password}' | sudo -S grep '$statement_to_look_for' {$server_config_path} 2>&1";
		else
			$command_to_execute_remotely				= "grep '$statement_to_look_for' {$server_config_path} 2>&1";

		$get_statements_from_server_config_command		= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}\"";
		$part_of_message_indicating_success				= "try_files";

		//echo "<p>[" . __FILE__ . ":" . __LINE__ . "] get_statements_from_server_config_command = $get_statements_from_server_config_command</p>";	//DEBUG
		
		$DEBUG	 = TRUE;	//DEBUG
		
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_statements_from_server_config_command')));

		exec($get_statements_from_server_config_command, $output, $return_value);
		
		if($return_value != 0)
			return (bool)false;
		else
		{
			$pattern									= $test_for_statement;
			$good_statements							= array();
		
			foreach($output as $line_of_server_config_holding_statement)
			{
				$subject								= $line_of_server_config_holding_statement;
				$matches								= array();
		
				if(preg_match($pattern, $subject, $matches))
				{
					if($DEBUG)
						_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact($matches[0]));

					array_push($good_statements, $matches[0]);
				}
			}
		
			if(count($good_statements) == 0)
				return (bool)false;
		}

		return (bool)true;
	}

	
	function _get_liquid_office_plugin_path_from_zip_file_path($zip_file_path)
	{
		global											$DEBUG;
		$pattern										= "/.*\//i";
		$subject										= $zip_file_path;
		$matches										= array();
		
		preg_match($pattern, $subject, $matches);
		
		if(isset($matches[0]))
			return $matches[0];
		else
			return "";
	}
	
	
	function _get_install_sql($host_name, $port, $user_name, $password, $feng_office_path)
	{
		global											$DEBUG;
		global											$INLINE_COMMENT;
		global											$MULTILINE_COMMENT_START;
		global											$MULTILINE_COMMENT_END;
		global											$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS;
		
		$output											= array();
		$install_sql									= array();
		$comment_is_multi_line_comment					= false;
	
		$plugins_path									= _get_plugins_path($host_name, $port, $user_name, $password, $feng_office_path);
		$path_to_install_sql							= $plugins_path . "/liquid_office/install/sql/";
	
		$command_to_execute_remotely					= "awk 1 {$path_to_install_sql}mysql_schema.php {$path_to_install_sql}mysql_initial_data.php 2>&1";
		$get_install_sql_command						= "sshpass -p \"{$password}\" ssh -o ConnectTimeout={$TIMEOUT_FOR_SSH_CONNECTIONS_IN_SECONDS} -o StrictHostKeyChecking=no $user_name@$host_name -p $port -t -t \"{$command_to_execute_remotely}\"";
	
		exec($get_install_sql_command, $output, $return_value);
	
		if($DEBUG)
			_get_debug_info(__FILE__, __FUNCTION__, __LINE__, compact(explode(' ', 'get_install_sql_command return_value output')));
			
		if($return_value != 0)
			return (bool)false;
		else
		{
			foreach($output as $line_of_install_sql_files)
			{
				$line_is_comment						= _check_whether_line_is_comment($line_of_install_sql_files);
				$line_is_blank							= _check_whether_line_is_blank($line_of_install_sql_files);
	
				//echo "<p>" . __LINE__ . ": line_of_install_sql_files = '" . $line_of_install_sql_files . "'</p>";	//DEBUG
				//echo "<p>" . __LINE__ . ": line_is_comment = '" . ($line_is_comment === true ? "true" : $line_is_comment) . "'</p>";	//DEBUG
				//echo "<p>" . __LINE__ . ": line_is_blank = '" . $line_is_blank . "'</p>";	//DEBUG
				//echo "<p>" . __LINE__ . ": comment_is_multi_line_comment = '" . $comment_is_multi_line_comment . "'</p>";	//DEBUG
	
				if(	$line_is_comment !== false ||
					$line_is_blank === true ||
					$comment_is_multi_line_comment === true)
				{
					$rest_of_line						= "";
					
					if($line_is_comment === $MULTILINE_COMMENT_START)
						$comment_is_multi_line_comment	= true;
					else if($line_is_comment === $MULTILINE_COMMENT_END)
					{
						$comment_is_multi_line_comment	= false;
						$rest_of_line					= substr(strpos($line_of_install_sql_files, "*/"));
						$is_rest_of_line_blank			= _check_whether_line_is_blank($rest_of_line);
		
						if(!$is_rest_of_line_blank)
						array_push($install_sql, $rest_of_line);
					}
					else if($line_is_comment === $INLINE_COMMENT)
					{
						$rest_of_line					= substr(0, strpos($line_of_install_sql_files, "/*"));
						$rest_of_line				   .= substr(strpos($line_of_install_sql_files, "*/"));
						$is_rest_of_line_blank			= _check_whether_line_is_blank($rest_of_line);
			
						if(!$is_rest_of_line_blank)
							array_push($install_sql, $rest_of_line);
					}
					
				//echo "<p>" . __LINE__ . ": rest_of_line = '" . $rest_of_line . "'</p>";	//DEBUG
				}
				else
				{
					array_push($install_sql, $line_of_install_sql_files);
					//echo "<p>" . __LINE__ . ": line_of_install_sql_files = '" . $line_of_install_sql_files . "'</p>";	//DEBUG
				}
			}
	
			//print_r($install_sql); //DEBUG
			$install_sql								=	implode($install_sql);
	
			return $install_sql;
		}
	}
	
	
	function _check_whether_line_is_comment($line)
	{
		global											$INLINE_COMMENT;
		global											$MULTILINE_COMMENT_START;
		global											$MULTILINE_COMMENT_END;
	
		$haystack										=	$line;

		$needle											=	"--";
		if(strpos($haystack, $needle) === 0)
			return true;
	
		$needle											=	"#";
		if(strpos($haystack, $needle) === 0)
			return true;
	
		$needle											=	"/*";
		if(strpos($haystack, $needle) !== false)
		{
			$needle										=	"*/";
			if(strpos($haystack, $needle) === false)
				return $MULTILINE_COMMENT_START;
			else
				return $INLINE_COMMENT;
		}
	
		$needle											=	"*/";
		if(strpos($haystack, $needle) !== false)
			return $MULTILINE_COMMENT_END;
	
		return (bool)false;
	}
	
	
	function _check_whether_line_is_blank($line)
	{
		return ctype_space($line);
	}
	
	
	function _create_tmp_filename($host_name, $port, $user_name)
	{
		return "easy_installation_tmp" . sha1("{$host_name}{$port}{$user_name}");
	}
	
?>