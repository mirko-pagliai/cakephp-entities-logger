<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\Event\EventInterface;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\Routing\Router;
use RuntimeException;

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

    protected ?ServerRequest $request = null;

    /**
     * @inheritDoc
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        $this->request = Router::getRequest();

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
                'conditions' => ['entity_class' => $table->getEntityClass()],
                'sort' => ['EntitiesLogs.datetime' => 'ASC'],
            ]);
        }
    }

    /**
     * Internal method to retrieve the identity ID from the current request's identity attribute.
     *
     * @return int|null The identity ID if available, or `null` if the request is not set.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the identity object does not have a valid ID.
     * @throws \RuntimeException If the identity attribute is not present in the request.
     */
    protected function getIdentityId(): ?int
    {
        if (!$this->request) {
            return null;
        }

        /** @var \Cake\Datasource\EntityInterface|null $Identity */
        $Identity = $this->request->getAttribute('identity');
        if (!$Identity) {
            throw new RuntimeException('Unable to retrieve identity. Request does not have an identity attribute.');
        }

        if (!isset($Identity->id)) {
            throw new MissingPropertyException('`' . $Identity::class . '::$id` is null, expected non-null value.');
        }

        return $Identity->id;
    }

    /**
     * Internal method to build a new `EntitiesLog` instance based on the provided entity and log type.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity instance to log.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The type of log to be created.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog|null The created EntitiesLog instance or `null` if no
     * request is available.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the entity's id is `null`.
     */
    protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
    {
        if (!$this->request) {
            return null;
        }

        if (!isset($entity->id)) {
            throw new MissingPropertyException('`' . $entity::class . '::$id` is null, expected non-null value.');
        }

        /** @var \Cake\EntitiesLogger\Model\Entity\EntitiesLog $EntitiesLog */
        $EntitiesLog = $this->EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entity->id,
            'user_id' => $this->getIdentityId(),
            'type' => $entitiesLogType,
            'datetime' => new DateTime(),
            'ip' => $this->request->clientIp(),
            'user_agent' => $this->request->getHeaderLine('User-Agent'),
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
     * @param \Cake\Event\EventInterface $event The event triggered after the save operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was saved.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        if (!$this->request) {
            return;
        }

        $type = $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated;

        $event->setResult($this->saveEntitiesLog($entity, $type));
    }

    /**
     * Handles the logic to be executed after an entity is deleted.
     *
     * @param \Cake\Event\EventInterface $event The event triggered after the delete operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was deleted.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity): void
    {
        if (!$this->request) {
            return;
        }

        $event->setResult($this->saveEntitiesLog($entity, EntitiesLogType::Deleted));
    }
}
