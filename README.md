# mycurator
WordPress Plugin MyCurator code repository

This code repository includes the MyCurator WordPress plugin code as well as the code for the Cloud Process (cloud-process subdirectory).  The tgtinfo-admin directory is a simple admin plugin that you load on your WordPress site to add API keys for users (Add Payment to add a user, Admin for changing user details) as well as a few other tools.  

The cloud process is a standalone php program that is called from the MyCurator plugin through PHP Curl - you will need to set the URL in the mycruator/Mycurator_local_proc.php in the mct_ai_callcloud subroutine by replacing the 'YourURL'.  The cloud process runs as a php program that is initiated by an Apache server at a specific website by placing the index.php file in the root directory of that server.

The cloud process receives a URL and uses the Diffbot service to grab the web page text. It needs a Diffbot API key to work (enter in the mycurator_cloud_functions.php program).  The cloud service also does keyword checking on the returned page as well as running it through a simple Bayesian algorithm to decide whether it passes the preferences of the client through the thumbs up/down mechanism.  It then returns the page with appropriate success or error codes.
