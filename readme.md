# PHP-SecurityServicesScraper

[![Build Status](https://travis-ci.org/laravel/framework.svg)](https://travis-ci.org/laravel/framework)
[![Total Downloads](https://poser.pugx.org/laravel/framework/d/total.svg)](https://packagist.org/packages/laravel/framework)
[![Latest Stable Version](https://poser.pugx.org/laravel/framework/v/stable.svg)](https://packagist.org/packages/laravel/framework)
[![Latest Unstable Version](https://poser.pugx.org/laravel/framework/v/unstable.svg)](https://packagist.org/packages/laravel/framework)
[![License](https://poser.pugx.org/laravel/framework/license.svg)](https://packagist.org/packages/laravel/framework)

PHP-SecurityServicesScraper is a data collection and metrics reporting application aimed at solving data centralization and transparency issues that occur from having multiple, indepedent security tools deployed through an environment. 

Data collection is either performed by a web crawler or an API client, depending on what the security tool has to offer. The crawlers authenticate and format requests to their tool to collect and dump various sets of data to file. Note: the $url values in the crawlers should be updated to go after data that is relevant to you.
Then data processors tidy up and store the data dumps into a MySQL database. 

The frontend is written in [AngularJS](https://angularjs.org) to supplement the implementation of reporting and searching capabilities.

