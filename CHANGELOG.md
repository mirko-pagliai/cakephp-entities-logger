# 1.x branch
## 1.0 branch
### 1.0.7
* `EntitiesLogBehavior` now reads the current request from `Router` each time a log is created, instead of keeping the
  one available when the behavior was built. The public `EntitiesLogBehavior::$request` property has been removed;
* `EntitiesLogBehavior` now takes the id of the logged entity from the primary key of the table, so primary keys not
  named `id` are supported. An `UnexpectedValueException` is thrown if the primary key is composite or if its value is
  not an integer;
* the `EntitiesLogs` association added by `EntitiesLogBehavior` now filters the logs by the entity class that the table
  has when the query is built, instead of the one it had when the behavior was added.
