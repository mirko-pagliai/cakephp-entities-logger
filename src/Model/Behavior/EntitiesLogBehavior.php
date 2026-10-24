<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\Validation\Validation;
use RuntimeException;
use UnexpectedValueException;

/**
 * Behavior for logging changes to entities.
 *
 * Inside your table's `initialize()` method:
 * ```
 * $this->addBehavior('Cake/EntitiesLogger.EntitiesLog', [
 *    'checkRules' => true, // optional, default true
 *    'mergeInterval' => 300, // optional, in seconds, default 300 (5 minutes), 0 disables the merge
 *    'groupField' => null, // optional, name of the field that groups the entities, default null
 * ]);
 * ```
 */
class EntitiesLogBehavior extends Behavior
{
    use LocatorAwareTrait;

    public EntitiesLogsTable|Table $EntitiesLogsTable;

    /**
     * @inheritDoc
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        if (empty($this->EntitiesLogsTable)) {
            $this->EntitiesLogsTable = $this->fetchTable(EntitiesLogsTable::class);
        }

        /**
         * Automatically sets a "has many" association to the table that loaded the behavior.
         */
        if (!$table->hasAssociation('EntitiesLogs')) {
            /** @phpstan-ignore cake.addAssociation.existClass */
            $Association = $table->hasMany('EntitiesLogs', [
                'targetTable' => $this->EntitiesLogsTable,
                'foreignKey' => 'entity_id',
                // The entity class is read when the query is built, because the table can change it after this point
                'conditions' => fn(QueryExpression $exp): QueryExpression => $exp->eq('entity_class', $table->getEntityClass()),
                'sort' => ['EntitiesLogs.datetime' => 'ASC'],
                'strategy' => 'select',
            ]);

            /** @var string|null $groupField */
            $groupField = $this->getConfig('groupField');

            // With the `groupField` option, the logs are those of the group, so they are found by that field
            if ($groupField !== null) {
                $Association->setBindingKey($groupField);
            }
        }
    }

    /**
     * Internal method to build a new `EntitiesLog` entity based on the provided entity and log type.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity instance to create a log for.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The log type associated with the entity.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog|null Returns an `EntitiesLog` entity or `null` if the
     * request is unavailable.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the entity or identity is missing required properties.
     * @throws \UnexpectedValueException If the entity is a generic `Cake\ORM\Entity`, the primary key of the table is
     * composite or the entity id (or the value of the `groupField` option) is not an integer.
     * @throws \RuntimeException If the request does not contain an identity attribute.
     */
    protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
    {
        $request = Router::getRequest();
        if (!$request) {
            return null;
        }

        // The generic entity class does not identify the table, so the logs of different tables would be mixed up
        if ($entity::class === Entity::class) {
            throw new UnexpectedValueException('Unable to log `' . $entity::class . '`: the table must have its own entity class.');
        }

        /** @var string|null $groupField */
        $groupField = $this->getConfig('groupField');

        // With the `groupField` option, the logs are identified by the value of that field instead of the primary key
        $idField = $groupField ?? $this->table()->getPrimaryKey();
        if (is_array($idField)) {
            throw new UnexpectedValueException('Unable to log `' . $entity::class . '`: a single-column primary key is required.');
        }

        // Checks for the entity's id
        $entityId = $entity->get($idField);
        if ($entityId === null) {
            throw new MissingPropertyException('`' . $entity::class . "::$$idField` is null, expected non-null value.");
        }

        if (!Validation::isInteger($entityId)) {
            $idLabel = $groupField === null ? 'the primary key' : "the `$groupField` field";

            throw new UnexpectedValueException('Unable to log `' . $entity::class . "`: $idLabel must be an integer.");
        }

        /**
         * Checks for the identity attribute.
         *
         * It is usually a decorator of the user entity (for example, `Authentication\Identity`) that exposes its
         * properties. The `EntityInterface` type only lets static analysis accept the access to `$id`.
         *
         * @var \Cake\Datasource\EntityInterface|null $Identity
         */
        $Identity = $request->getAttribute('identity');
        if (!$Identity) {
            throw new RuntimeException('Unable to retrieve identity. Request does not have an identity attribute.');
        }

        // Checks for the identity's id
        if (!isset($Identity->id)) {
            throw new MissingPropertyException('`' . $Identity::class . '::$id` is null, expected non-null value.');
        }

        /** @var \Cake\EntitiesLogger\Model\Entity\EntitiesLog $EntitiesLog */
        $EntitiesLog = $this->EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entityId,
            'user_id' => $Identity->id,
            'type' => $entitiesLogType,
            'datetime' => new DateTime(),
            'ip' => $request->clientIp(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ]);

        return $EntitiesLog;
    }

    /**
     * Internal method to save a log entry for the given entity based on the specified log type.
     *
     * An `Updated` log is not added if the last log of the entity is an `Updated` log made by the same user, from the
     * same IP address and with the same user agent, within the `mergeInterval` option (in seconds): the `datetime` of
     * that log is updated instead. `Created` and `Deleted` logs are never merged.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity for which the log is being created.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The type of log entry to be created.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog|null The saved log entity or `null` if the log could not be created.
     */
    protected function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
    {
        $EntitiesLog = $this->buildEntity($entity, $entitiesLogType);
        if (!$EntitiesLog) {
            return null;
        }

        /** @var string|int $mergeInterval */
        $mergeInterval = $this->getConfig('mergeInterval', 300);
        $mergeInterval = (int)$mergeInterval;
        if ($entitiesLogType === EntitiesLogType::Updated && $mergeInterval > 0) {
            /** @var \Cake\EntitiesLogger\Model\Entity\EntitiesLog|null $LastEntitiesLog */
            $LastEntitiesLog = $this->EntitiesLogsTable->find()
                ->where(['entity_class' => $EntitiesLog->entity_class, 'entity_id' => $EntitiesLog->entity_id])
                ->orderByDesc('id')
                ->first();

            // Only the last log is considered, so that the changes made by other users in the meantime are not lost
            if (
                $LastEntitiesLog &&
                $LastEntitiesLog->type === $EntitiesLog->type &&
                $LastEntitiesLog->user_id === $EntitiesLog->user_id &&
                $LastEntitiesLog->ip === $EntitiesLog->ip &&
                $LastEntitiesLog->user_agent === $EntitiesLog->user_agent &&
                $LastEntitiesLog->datetime >= $EntitiesLog->datetime->subSeconds($mergeInterval)
            ) {
                $LastEntitiesLog->datetime = $EntitiesLog->datetime;
                $EntitiesLog = $LastEntitiesLog;
            }
        }

        return $this->EntitiesLogsTable->saveOrFail($EntitiesLog, [
            'checkRules' => (bool)$this->getConfig('checkRules', true),
        ]);
    }

    /**
     * Handles the logic to be executed after an entity is saved.
     *
     * With the `groupField` option, the log is always an `Updated` one, because the group is what changes. If the entity
     * has been moved from one group to another, the previous group is modified too, so it gets its own log. The result
     * of the event is the log of the current group.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The event triggered after the save operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was saved.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        /** @var string|null $groupField */
        $groupField = $this->getConfig('groupField');
        $type = $entity->isNew() && $groupField === null ? EntitiesLogType::Created : EntitiesLogType::Updated;

        $event->setResult($this->saveEntitiesLog($entity, $type));

        if ($groupField === null || $entity->isNew()) {
            return;
        }

        /** @var string|int|null $previousGroup */
        $previousGroup = $entity->getOriginal($groupField);

        /** @var string|int $currentGroup */
        $currentGroup = $entity->get($groupField);
        if ($previousGroup !== null && (string)$previousGroup !== (string)$currentGroup) {
            // The entity is cloned, so that it has the previous group, without changing the saved one
            $PreviousGroupEntity = clone $entity;
            $PreviousGroupEntity->set($groupField, $previousGroup, ['setter' => false]);

            $this->saveEntitiesLog($PreviousGroupEntity, EntitiesLogType::Updated);
        }
    }

    /**
     * Handles the logic to be executed after an entity is deleted.
     *
     * With the `groupField` option, the log is an `Updated` one, because the group is what changes.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The event triggered after the delete operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was deleted.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity): void
    {
        $type = $this->getConfig('groupField') === null ? EntitiesLogType::Deleted : EntitiesLogType::Updated;

        $event->setResult($this->saveEntitiesLog($entity, $type));
    }
}
