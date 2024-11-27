# mycurator
WordPress Plugin MyCurator code repository

This code repository includes the MyCurator WordPress plugin code as well as the code for the Cloud Service.  The cloud service is called by the plugin through php Curl.  
The cloud service uses the Diffbot service to grab web page text and needs a Diffbot API key to work.  The cloud service also does keyword checking on the returned page as well as running it through a simple Bayesian algorithm to decide whether it passes the preferences of the client through the thumbs up/down mechanism.  It then returns the page with appropriate success or error codes.
