# easy-installer

## usage

1. call __get_feng_office_installations.php__ with following cgi-parameters:
   - h ... hostname, e.g.: h=dev.ilia.ch
   - p ... port, e.g.: p=118 
   - s ... password, e.g.: s=pwd
   - u ... username, e.g.: u=user
   - **returns: [ptt] ... paths to test = all valid www-roots for Apache and NGinx on the specified host in JSON format**

2. call __get_feng_office_installations.php__ with following cgi-parameters:
   - h, p, s, u ... cf above
   - ptt ... path to test as string 
   - **returns: [html-buttons] ... html for buttons for each Feng Office installation found below [ppt]
   
   	