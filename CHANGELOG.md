# 1.x branch
## 1.0 branch
### 1.0.7
* `EntitiesLogBehavior` now reads the current request from `Router` each time a log is created, instead of keeping the
  one available when the behavior was built. The public `EntitiesLogBehavior::$request` property has been removed.
