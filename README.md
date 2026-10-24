# cakephp-entities-logger

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![CI](https://github.com/mirko-pagliai/cakephp-entities-logger/actions/workflows/ci.yml/badge.svg)](https://github.com/mirko-pagliai/cakephp-entities-logger/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/mirko-pagliai/cakephp-entities-logger/graph/badge.svg?token=9yi4NCAADl)](https://codecov.io/gh/mirko-pagliai/cakephp-entities-logger)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/79152e0bd07a47c58daecd04aa6bc225)](https://app.codacy.com/gh/mirko-pagliai/cakephp-entities-logger/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![CodeFactor](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-entities-logger/badge)](https://www.codefactor.io/repository/github/mirko-pagliai/cakephp-entities-logger)

Logs who created, updated or deleted your CakePHP entities, and when.

  * [Requirements](#requirements)
  * [Installation](#installation)
  * [Create the table](#create-the-table)
  * [Add the behavior](#add-the-behavior)
  * [Reading the logs](#reading-the-logs)
  * [What is logged](#what-is-logged)
  * [Options](#options)
  * [Merging close updates](#merging-close-updates)
  * [Grouping entities](#grouping-entities)
  * [Requests and identity](#requests-and-identity)
  * [Supported primary keys](#supported-primary-keys)
  * [Entity class](#entity-class)
  * [IP address](#ip-address)
  * [Logs on a different connection](#logs-on-a-different-connection)
  * [Customizing](#customizing)
  * [Versioning](#versioning)


Add a behavior to the tables you want, and the plugin writes a log every time an entity of those tables is created,
updated or deleted. Each log stores:

* the entity (its class and its id);
* the type of the change: `created`, `updated` or `deleted`;
* the user who made the change, the date and time, the IP address and the user agent.

For example, after a few changes the `entities_logs` table contains:

| id | entity_class               | entity_id | user_id | type    | datetime            | ip           |
|----|----------------------------|-----------|---------|---------|---------------------|--------------|
| 1  | `App\Model\Entity\Article` | 1         | 1       | created | 2026-10-20 09:30:00 | 192.168.0.10 |
| 2  | `App\Model\Entity\Article` | 2         | 1       | created | 2026-10-20 09:42:00 | 192.168.0.10 |
| 3  | `App\Model\Entity\Article` | 1         | 2       | updated | 2026-10-20 15:10:00 | 192.168.0.20 |
| 4  | `App\Model\Entity\Article` | 2         | 1       | deleted | 2026-10-21 08:05:00 | 192.168.0.10 |

The plugin tells you **who** changed an entity and **when**, but it does not store **what** has changed: the values of
the fields are not saved.

## Requirements

* PHP 8.2 or later;
* CakePHP 5.1 or later;
* the `cakephp/migrations` plugin, to create the table with the migrations of this plugin;
* a `Users` table in your application: each log belongs to the user who made the change, and by default the user must
  exist when the log is saved (see the `checkRules` option);
* an authenticated user: the current request must have an `identity` attribute (for example, the one set by the
  [CakePHP Authentication plugin](https://github.com/cakephp/authentication)) with an integer `id`. See
  "Requests and identity".

## Installation
You can install the plugin via composer:
```bash
composer require --prefer-dist mirko-pagliai/cakephp-entities-logger
```

Then you have to load the plugin. For more information on how to load the plugin,
please refer to the [CakePHP documentation](https://book.cakephp.org/5/en/plugins.html#loading-a-plugin).

Simply, you can execute the shell command to enable the plugin:
```bash
bin/cake plugin load Cake/EntitiesLogger
```
This would update your application's bootstrap method.

## Create the table
Now you need to create the table that the plugin will use to keep changes to the entities you want.

The best way is using migrations (if your application doesn't have it yet, `composer require cakephp/migrations`):
```bash
bin/cake migrations migrate -p Cake/EntitiesLogger
```

Instead, if you want to verify that the plugin migrations have been applied correctly:
```bash
bin/cake migrations status -p Cake/EntitiesLogger
```

## Add the behavior

Add the `Cake/EntitiesLogger.EntitiesLog` behavior to the tables you want.

Inside the `initialize()` method of your tables:
```php
namespace App\Model\Table;

use Cake\ORM\Table;

class ArticlesTable extends Table
{
    public function initialize(array $config): void
    {
        // ...
        
        $this->addBehavior('Cake/EntitiesLogger.EntitiesLog');
        
        // ...
    }
}
```

## Reading the logs

The behavior adds an `EntitiesLogs` association to the table, so the logs of an entity can be read like any other
association, from the oldest to the newest:
```php
$Article = $this->Articles->find()
    ->contain(['EntitiesLogs' => ['Users']])
    ->where(['Articles.id' => $id])
    ->firstOrFail();

foreach ($Article->entities_logs as $EntitiesLog) {
    // `type` is a `Cake\EntitiesLogger\Model\Enum\EntitiesLogType` (`Created`, `Updated` or `Deleted`)
    echo $EntitiesLog->type->name . ' by user ' . $EntitiesLog->user_id . ' on ' . $EntitiesLog->datetime;
}
```
Containing `Users` is optional: it also loads the user of each log (`$EntitiesLog->user`).

A deleted entity no longer exists, so it cannot be loaded this way. To read its logs, query `EntitiesLogsTable`
directly, by `entity_class` and `entity_id`.

## What is logged

Each log is a row of the `entities_logs` table:

| Column         | Description                                                                         |
|----------------|-------------------------------------------------------------------------------------|
| `entity_class` | The entity class (with its namespace) of the table that loaded the behavior         |
| `entity_id`    | The primary key of the entity (an integer)                                          |
| `user_id`      | The user who made the change                                                        |
| `type`         | `created`, `updated` or `deleted`                                                   |
| `datetime`     | When the change was made                                                            |
| `ip`           | The IP address of the request (IPv4 or IPv6)                                        |
| `user_agent`   | The user agent of the request                                                       |

The `entity_class` and `entity_id` columns have an index, used to find the logs of an entity.

The logs are written by the `afterSave()` and `afterDelete()` callbacks, so only when entities are saved or deleted
through their table (`save()`, `saveMany()`, `delete()`, and so on). For this reason:

* an entity that is saved without any change is not logged, because CakePHP does not even dispatch the events;
* the operations that don't go through entities, such as `updateAll()` and `deleteAll()`, are not logged;
* when there is no request (for example, in a shell command or in a cronjob), nothing is logged.

Some things to keep in mind:

* every change adds a row to the table (and, for updates, the plugin reads the last log of the entity to merge close
  updates, see below);
* the plugin never deletes old logs: how long to keep them is up to your application;
* the IP address and the user agent may be personal data, depending on where you operate (for example, under the GDPR).

## Options

The behavior accepts the following options:
```php
$this->addBehavior('Cake/EntitiesLogger.EntitiesLog', [
    'checkRules' => true,
    'mergeInterval' => 300,
    'groupField' => null,
]);
```

* `checkRules` (default `true`): whether the rules of `EntitiesLogsTable` are checked when the log is saved (for
  example, that the user exists). With `false`, the rules are not checked;
* `mergeInterval` (default `300`): the interval, in seconds, within which close updates are merged into a single log.
  With `0`, the updates are not merged and every change is logged. See below;
* `groupField` (default `null`): the name of the field that groups the entities, to log the changes of the group instead
  of those of each single entity. See below.

## Merging close updates

A user who changes the same entity several times in a short time (for example, it is saved, a mistake is noticed and it is
saved again) would leave a log for each change. For this reason, when an entity is updated, a new log is not added if
the **last** log of that entity:

* is an `Updated` log (the `Created` and `Deleted` logs are never merged);
* was made by the same user, from the same IP address and with the same user agent;
* is not older than the `mergeInterval` option (300 seconds, 5 minutes, by default).

In this case the `datetime` of that log is updated, so the log always shows the time of the last change. Nothing else is
written or deleted.

Some things to keep in mind:

* only the last log is considered: if another user has changed the entity in the meantime, a new log is added, so that
  the history is kept (the changes of A, B and then A again are three logs);
* the interval is counted from the last log, not from the first change. If the same user keeps changing the entity and
  never leaves more than the interval between two changes, only one log is kept;
* the logs that already exist are not modified.

## Grouping entities

Some entities make sense only as part of a group. For example, the lines of an order are always handled together, and
what you want to log is that the order has been changed, no matter which line.

With the `groupField` option you set the name of the field that identifies the group (here, `order_id`):
```php
$this->addBehavior('Cake/EntitiesLogger.EntitiesLog', ['groupField' => 'order_id']);
```

With this option:

* the `entity_id` column of the log is the value of that field, **not** the primary key. The `entity_class` column is
  still the entity class of the table. So the logs of a group are those with the entity class of the table and the
  `entity_id` equal to the value of the field (for example, the id of the order);
* every change is logged as `Updated`. Creating, editing or deleting an entity is always a change of the group, so with
  this option the `Created` and `Deleted` logs are **never** written. The log tells that the group has been changed, by
  whom and when, but not which entity of the group has been changed, nor how;
* if an entity is moved from one group to another (the value of the field is changed), both groups have been changed, so
  an `Updated` log is written for each of them. This works for entities that have been read from the database and then
  modified;
* the field must have a value, and it must be an integer, as for the primary key. Otherwise a `MissingPropertyException`
  or an `UnexpectedValueException` is thrown when the entity is saved or deleted. For this reason an entity cannot be
  removed from its group (the field cannot be set to `null`);
* the primary key of the table is not used, so it can also be composite;
* close updates are merged as described above, and the group is what counts: the changes made by the same user to any
  entity of the same group, within the interval, are a single log;
* the `EntitiesLogs` association added by the behavior finds the logs by the field instead of the primary key. So all
  the entities of a group have the same logs, those of the group.

If the table already has logs, don't add this option: they identify the entities by their primary key, so they would be
mixed up with those of the groups.

## Requests and identity

The log stores the user who made the change, so it is created only when there is a request:

* when there is no current request (for example, in a shell command or in a cronjob), nothing is logged, and the entity
  is saved or deleted as usual;
* when there is a request, but it has no `identity` attribute (for example, the user is not authenticated), or the
  identity has no `id`, an exception is thrown when the entity is saved or deleted and the changes are rolled back, so
  that a change without an author is never saved silently.

## Supported primary keys

The `entity_id` column of the `entities_logs` table is an integer. For this reason, the tables that load the behavior
must have a single-column primary key (it doesn't have to be named `id`) whose value is an integer.

If the primary key is composite or its value is not an integer (for example, a UUID), the log cannot be saved and an
`UnexpectedValueException` is thrown when the entity is saved or deleted.

The same applies to the identity stored in the request (the user), whose `id` must be an integer.

## Entity class

The logs of an entity are identified by the `entity_class` and `entity_id` columns, and the `EntitiesLogs` association
added by the behavior finds the logs whose `entity_class` is exactly the entity class of the table. For this reason,
the logs of entities of other classes (even subclasses) are not found.

The entity class must identify the table: each table that loads the behavior must have its own entity class. Tables
that share the same entity class would also share the logs (the same `entity_id` of two tables would be mixed up), so
the generic `Cake\ORM\Entity` class, which CakePHP uses when a table has no entity class of its own, is not accepted:
an `UnexpectedValueException` is thrown when the entity is saved or deleted.

## IP address

The IP address is taken from `ServerRequest::clientIp()`. If the application is behind a proxy or a load balancer, the
request must trust it (`ServerRequest::$trustProxy`), otherwise the address of the proxy is logged instead of the one of
the client.

## Logs on a different connection

The log is saved in the `afterSave()` and `afterDelete()` callbacks of the table that loaded the behavior, so inside the
transaction of the entity being saved or deleted.

If you store the logs on a different connection (for example, with your own `EntitiesLogsTable` that overrides
`defaultConnectionName()`), the two cannot be rolled back together: if the transaction is rolled back after the log has
been written (for example, when the save is part of a larger transaction), the log is kept.

In this case, the plugin migrations must be run on that connection too (here `logs`), otherwise they would try to modify
the `entities_logs` table of the default connection, which doesn't exist:
```bash
bin/cake migrations migrate -p Cake/EntitiesLogger -c logs
```

The `afterSaveCommit()` and `afterDeleteCommit()` callbacks are not used, because CakePHP does not dispatch those
events when the save is part of a larger transaction, and the log would be lost.

## Customizing

To use your own table for the logs (for example, to add a finder, or to store the logs on a different connection),
extend `Cake\EntitiesLogger\Model\Table\EntitiesLogsTable`, and extend the behavior so that it uses your table:
```php
namespace App\Model\Behavior;

use App\Model\Table\EntitiesLogsTable;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior as BaseEntitiesLogBehavior;
use Cake\ORM\Table;

class EntitiesLogBehavior extends BaseEntitiesLogBehavior
{
    /**
     * @inheritDoc
     */
    public function __construct(Table $table, array $config = [])
    {
        $this->EntitiesLogsTable = $this->fetchTable(EntitiesLogsTable::class);

        parent::__construct($table, $config);
    }
}
```
Then add your own behavior to the tables, instead of the one of the plugin:
```php
$this->addBehavior('EntitiesLog');
```

The table must be set before calling the parent constructor, because the constructor uses it as the target of the
`EntitiesLogs` association. The protected methods `buildEntity()` and `saveEntitiesLog()` can be overridden too.

## Versioning
For transparency and insight into our release cycle and to maintain backward compatibility,
this package will be maintained under the [Semantic Versioning guidelines](http://semver.org).
