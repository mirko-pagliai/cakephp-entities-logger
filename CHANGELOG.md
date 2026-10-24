# 1.x branch
## 1.1 branch
### 1.1.0
* added the `mergeInterval` option to `EntitiesLogBehavior` (in seconds, default `300`): an `Updated` log is no longer
  added if the last log of the entity is an `Updated` log made by the same user, from the same IP address and with the
  same user agent within this interval, and the `datetime` of that log is updated instead. Set it to `0` to log every
  change, as in previous versions.
* added the `groupField` option to `EntitiesLogBehavior`, the name of the field that groups the entities, to log the
  changes of the group instead of those of each single entity. With this option the logs are identified by the value of
  that field, instead of the primary key (which can also be composite), and every change is logged as `Updated`, also
  when an entity is created or deleted. If an entity is moved to another group, the previous group is logged too. The
  `EntitiesLogs` association finds the logs by that field.

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
