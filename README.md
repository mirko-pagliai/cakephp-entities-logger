# cakephp-entities-logger

![screenshot_phpmyadmin.png](docs/screenshot_phpmyadmin.png)


## Installation
You can install the plugin via composer:
```bash
composer require --prefer-dist mirko-pagliai/cakephp-entities-logger
```

## Load the plugin
```php
$this->addPlugin('Cake/EntitiesLogger');
```

## Create the table
Now you need to create the table that the plugin will use. The best way is using migrations:
```bash
bin/cake migrations migrate -p Cake/EntitiesLogger
```

Alternatively (not recommended) you can run the `sql` code directly:
```sql
CREATE TABLE IF NOT EXISTS `entities_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_class` varchar(255) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `datetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```