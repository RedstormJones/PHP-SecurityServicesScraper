# PHP-SecurityServicesScraper

PHP-SecurityServicesScraper is a data collection and reporting application aimed at solving data centralization and correlation issues that occur from having numerous indepedent security tools deployed throughout an environment. 

Data collection is either performed by a web crawler or an API client depending on what the resource has to offer for accessing its data. The crawlers authenticate and send requests to their resource to collect and dump various sets of data to file. 

>Note: In the crawlers, the hard coded resource end points stored in $url should be modified as necessary to request data that is relevant to you.

After being dumped to file data processors format and store the data dumps into a MySQL database. 
