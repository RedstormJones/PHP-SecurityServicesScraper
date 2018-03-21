# PHP-SecurityServicesScraper

PHP-SecurityServicesScraper is a proof of concept project aimed at solving data collection, centralization and correlation issues that can occur from deploying numerous, indepedent security tools throughout an environment.

This application uses [the Laravel framework](https://laravel.com/). Data collection is performed through scheduled [artisan commands](https://laravel.com/docs/5.5/artisan). Each command is basically either a web crawler or an API client, depending on whether or not the target application offers any services for accessing its data.

The commands authenticate to their target application and query for data. When data collection and normalization is complete the commands ship their data to an instance of [Kafka](https://kafka.apache.org/). Each command sends data to one or more [Kafka topics](https://kafka.apache.org/documentation/#intro_topics), unique to that particular data. Kafka, acting as an event queue, serves the data to a [Logstash](https://www.elastic.co/guide/en/logstash/6.2/index.html) consumer ([logstash-input-kafka](https://www.elastic.co/guide/en/logstash/current/plugins-inputs-kafka.html)) which then upserts the data to an [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html) cluster.
