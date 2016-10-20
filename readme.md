# PHP-SecurityServicesScraper

PHP-SecurityServicesScraper is a data collection and metrics reporting application aimed at solving data centralization and transparency issues that occur from having multiple, indepedent security tools deployed through an environment. 

Data collection is either performed by a web crawler or an API client, depending on what the security tool has to offer. The crawlers authenticate and format requests to their tool to collect and dump various sets of data to file. 

>Note: the $url values in the crawlers should be updated to go after data that is relevant to you.

Then data processors tidy up and store the data dumps into a MySQL database. 

The frontend is written in [AngularJS](https://angularjs.org) to supplement the future implementation of reporting and searching capabilities.

