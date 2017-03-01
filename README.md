# easy-installer

## usage [get_feng_office_installations.php](https://github.com/LiquidOffice/easy-installer/blob/master/get_feng_office_installations.php)

= script to get the paths of Feng Office installations on the specified server 

1. get www-roots with following cgi-parameters:
   - h ... hostname, e.g.: h=dev.ilia.ch
   - p ... port, e.g.: p=118 
   - s ... password, e.g.: s=pwd
   - u ... username, e.g.: u=user
   **returns: [ptt] ... paths to test = all valid www-roots for Apache and NGinx on the specified host in JSON format**

2. get paths to Feng Office installations below specified path to test (ptt) with following cgi-parameters:
   - h, p, s, u ... cf above
   - j ............ if set, returned paths to Feng Office installations will be in JSON format, e.g.: j=1
   - ptt .......... path to test as string, e.g: ptt=/var/www
   - **returns: [html-buttons/JSON] ... html for buttons/JSON for each Feng Office installation found below [ppt]**
   
## usage [easy_installation.php](https://github.com/LiquidOffice/easy-installer/blob/master/easy_installation.php)

= script to perform the installation

1..x perform installation step by step:
   - a .... whether user is ADMIN (0=false, 1=true), e.g.: a=1
   - d .... step to DISPLAY, e.g.: d=2 
   - f .... FENG office path, e.g.: f=/var/www/fo30
   - h .... hostname, e.g.: h=dev.ilia.ch
   - lop .. LIQUID OFFICE PLUGIN path, e.g.: lop=/var/www/fo30/plugins/liquid_office
   - nc ... server CONFIG path, e.g.: nc=/etc/apache2
   - p .... port, e.g.: p=118 
   - psp .. plugins path, e.g.: lop=/var/www/fo30/plugins   
   - r .... web-server RUN user, e.g.: r=www
   - s .... password, e.g.: s=pwd
   - t .... installation STEP, e.g.: t=0 (first step)
   - u .... username, e.g.: u=user
   - zp ... zip file path
   
   **returns:  an json object, with the following mandatory members:**
	 - "ajx_output"		: string, that will be included in details (log) of easy installation front-end
	 - "ajx_success"		: boolean, specifies whether installation has been completed successfully
	 - "ajx_nextstep"		: next step, that needs to be taken

	 **the following members are optional:**
	 - "ajx_progress"		: progress in percent (= percent of already, successfully taken installation steps)
	 - "ajx_ns_add_cgi"	: additional cgi-paremter for next step
	 - "ajx_msg"			: string, that will be displayed to inform user or collect information from him
	 - "ajx_ns_msg_ok"	: next step, that shall be taken, if user confirms [ajx_msg]
	 - "ajx_ns_msg_cancel": next step, that shall be taken, if user cancels [ajx_msg]
	 - "ajx_debug"		: if debug info is available, it is stored here
