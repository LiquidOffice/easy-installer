# easy-installer

## usage get_feng_office_installations.php

1. 1st call with following cgi-parameters:
   - h ... hostname, e.g.: h=dev.ilia.ch
   - p ... port, e.g.: p=118 
   - s ... password, e.g.: s=pwd
   - u ... username, e.g.: u=user
   - **returns: [ptt] ... paths to test = all valid www-roots for Apache and NGinx on the specified host in JSON format**

2. 2nd call with following cgi-parameters:
   - h, p, s, u ... cf above
   - j ............ if set, returned paths to Feng Office installations will be in JSON format
   - ptt .......... path to test as string
   - **returns: [html-buttons/JSON] ... html for buttons/JSON for each Feng Office installation found below [ppt]
   
   	