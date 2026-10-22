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
            $table->hasMany('EntitiesLogs', [
                'targetTable' => $this->EntitiesLogsTable,
                'foreignKey' => 'entity_id',
                // The entity class is read when the query is built, because the table can change it after this point
                'conditions' => fn (QueryExpression $exp): QueryExpression => $exp->eq('entity_class', $table->getEntityClass()),
                'sort' => ['EntitiesLogs.datetime' => 'ASC'],
                'strategy' => 'select',
            ]);
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
     * @throws \UnexpectedValueException If the primary key of the table is composite or the entity id is not an integer.
     * @throws \RuntimeException If the request does not contain an identity attribute.
     */
    protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
    {
        $primaryKey = $this->table()->getPrimaryKey();
        if (is_array($primaryKey)) {
            throw new UnexpectedValueException('Unable to log `' . $entity::class . '`: a single-column primary key is required.');
        }

        // Checks for the entity's id
        $entityId = $entity->get($primaryKey);
        if ($entityId === null) {
            throw new MissingPropertyException('`' . $entity::class . '::$' . $primaryKey . '` is null, expected non-null value.');
        }

        if (!Validation::isInteger($entityId)) {
            throw new UnexpectedValueException('Unable to log `' . $entity::class . '`: the primary key must be an integer.');
        }

        $request = Router::getRequest();
        if (!$request) {
            return null;
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

        return $this->EntitiesLogsTable->saveOrFail($EntitiesLog, [
            'checkRules' => (bool)$this->getConfig('checkRules', true),
        ]);
    }

    /**
     * Handles the logic to be executed after an entity is saved.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The event triggered after the save operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was saved.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        if (!Router::getRequest()) {
            return;
        }

        $type = $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated;

        $event->setResult($this->saveEntitiesLog($entity, $type));
    }

    /**
     * Handles the logic to be executed after an entity is deleted.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The event triggered after the delete operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was deleted.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity): void
    {
        if (!Router::getRequest()) {
            return;
        }

        $event->setResult($this->saveEntitiesLog($entity, EntitiesLogType::Deleted));
    }
}
