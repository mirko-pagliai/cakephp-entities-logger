# 1.x branch
## 1.1 branch
### 1.1.0
* added the `mergeInterval` option to `EntitiesLogBehavior`, in seconds. If the last log of an entity is an `Updated`
  log made by the same user, from the same IP address and with the same user agent, within this interval, a new log is
  not added and the `datetime` of that log is updated instead, so that changes made one after the other leave a single
  log. The default is `300` (5 minutes): to log every change, as in previous versions, set it to `0`. `Created` and
  `Deleted` logs are never merged;
* the `README.md` file now documents the `checkRules` and `mergeInterval` options.

## 1.0 branch
### 1.0.7
* `EntitiesLogBehavior` now reads the current request from `Router` each time a log is created, instead of keeping the
  one available when the behavior was built. The public `EntitiesLogBehavior::$request` property has been removed;
* `EntitiesLogBehavior` now takes the id of the logged entity from the primary key of the table, so primary keys not
  named `id` are supported. An `UnexpectedValueException` is thrown if the primary key is composite or if its value is
  not an integer;
* the `EntitiesLogs` association added by `EntitiesLogBehavior` now filters the logs by the entity class that the table
  has when the query is built, instead of the one it had when the behavior was added;
* added the `entities_logs_entity_idx` index on the `entity_class` and `entity_id` columns of the `entities_logs` table,
  used to find the logs of an entity. You need to run the plugin migrations to apply it;
* the `user_id` field of `EntitiesLogsTable` is now required on creation, like the other fields, so a missing value
  gives a validation error instead of a database error;
* `EntitiesLogBehavior` now throws an `UnexpectedValueException` if the entity being saved or deleted is a generic
  `Cake\ORM\Entity`, so a table must have its own entity class: otherwise the logs of tables with the same entity class
  would be mixed up.
* the `README.md` file has been enriched and expanded, with new sections on requests and identity, supported primary
  keys, entity class, IP address and logs on a different connection.
