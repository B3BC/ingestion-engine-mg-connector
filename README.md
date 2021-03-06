# IngestionEngine Connector for Magento 2:

IngestionEngine Connector for Magento 2 is a module that allows you to export your catalog data and structure from IngestionEngine to Magento 2 through API calls. This version of the connector is designed to work with IngestionEngine. It could either work with Magento 2 Community Edition or Enterprise Edition.

### Features:

With IngestionEngine Connector for Magento 2, you can import products

### Commandline Usage:
`php bin/magento ingestionengine_connector:import --code=product`

### Installation
`composer require ingestion-engine/connector:dev-master`


`php bin/magento module:enable IngestionEngine_Connector`

`php bin/magento setup:db:status`

`php bin/magento setup:upgrade`

`php bin/magento cache:flush`


### Requirements:

* tcdent/php-restclient
* Magento >= 2.2 (CE & EE)
* Database encoding must be UTF-8

### About IngestionEngine
https://www.silksoftware.com/modules/product-data-ingestion-engine/
