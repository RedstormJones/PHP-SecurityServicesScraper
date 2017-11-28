# PHP-SecurityServicesScraper

PHP-SecurityServicesScraper is a proof of concept project aimed at solving data collection, centralization and correlation issues that can occur from deploying numerous, indepedent security tools throughout an environment.

This application uses [the Laravel framework](https://laravel.com/). Data collection is performed through scheduled [artisan commands](https://laravel.com/docs/5.5/artisan). Each command is basically either a web crawler or an API client, depending on whether or not the target application offers any services for accessing its data.

The commands authenticate to their target application and then query (or scrape) for data. When data collection and normalization is complete the commands ship their data to a box running an instance of [Kafka](https://kafka.apache.org/). Each command sends data to Kafka as it's own [Kafka topic](https://kafka.apache.org/documentation/#intro_topics). At this time Kafka doesn't do anything except pass the data along to another box running an instance of [Nifi](https://nifi.apache.org/). There, a fleet of Nifi processors act as Kafka consumers where each processor consumes data from a particular Kafka topic. Each consuming processor then streams the data to another processor to be indexed into an Elasticsearch cluster.
