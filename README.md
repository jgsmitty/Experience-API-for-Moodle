Experience-API-for-Moodle
=========================

Experience API for Moodle as a local plugin

Installation / Configuration Settings:

1. Create a folder in Moodle under the ./local folder named tcapi.
2. Copy the contents of this package into the ./local/tcapi folder in Moodle.
2. Return to Moodle and allow the plugin to install. This will create new tables and turn on specific permissions to the authenticated user role.
3. In order for the TCAPI to work, you will still need to enable web services withing Moodle and enable the REST web service protocol.
4. Go to the Site Administration -> Plugins -> Web Services -> Overview. You will see the steps to allow external system to control Moodle.
5. Verify at Step 1 that web services are enabled. If not, click on the link to Enable web services.
6. Verify at Step 2 that the rest protocol for web services is enabled. If not, click on the link to Enable protocols.
7. An .htaccess file is provided for the purpose of exposing specific authorization headers to PHP. If you have access on your server to the apache_request_headers function, you should not need this file. 
8. If you plan to access the LRS externally using Basic Auth methods, you'll need to enable the web service Auth plugin as well.

You Moodle installation should now be set to receive TCAPI statements.

The endpoint for TCAPI is http://moodlesite/local/tcapi/endpoint.php.

If you're using the modified SCORM module for TIN CAN packages, you should not have to modify any settings beyond the steps above.
=======
