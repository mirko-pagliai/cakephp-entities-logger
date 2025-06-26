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
 * $this->addBehavior('Cake/EntitiesLogger.EntitiesLog');
 * ```
 */
class EntitiesLogBehavior extends Behavior
{
    use LocatorAwareTrait;

    public EntitiesLogsTable $EntitiesLogsTable;

    protected ServerRequest $request;

    /**
     * Constructor method for initializing the object.
     *
     * @param \Cake\ORM\Table $table The table instance the behavior is attached to.
     * @param array $config The configuration settings for the behavior.
     * @return void
     * @throws \RuntimeException If the request is not an instance of \Cake\Http\ServerRequest.
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        $this->EntitiesLogsTable = $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');

        $Request = Router::getRequest();
        if (!$Request instanceof ServerRequest) {
            throw new RuntimeException('Request is not an instance of Cake\Http\ServerRequest.');
        }
        $this->request = $Request;
    }

    /**
     * Internal method to get the ID of the identity entity associated with the current request.
     *
     * @return int The identity ID.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the identity entity does not have a non-null ID property.
     * @throws \RuntimeException If the identity attribute is not present in the request.
     */
    protected function getIdentityId(): int
    {
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
     * Internal method to build a new log entity based on the provided entity and log type.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity object for which the log is being created.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The type of log to be created for the entity.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog The newly created log entity.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the entity's `id` is not set.
     */
    protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
    {
        if (!isset($entity->id)) {
            throw new MissingPropertyException('`' . $entity::class . '::$id` is null, expected non-null value.');
        }

        return $this->EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entity->id,
            'user_id' => $this->getIdentityId(),
            'type' => $entitiesLogType,
            'datetime' => new DateTime(),
            'ip' => $this->request->clientIp(),
            'user_agent' => $this->request->getHeaderLine('User-Agent'),
        ]);
    }

    /**
     * Internal method to save a log entry for the given entity and log type.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity associated with the log entry.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The type of log entry to be created.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog The saved log entry.
     */
    protected function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
    {
        $entity = $this->buildEntity($entity, $entitiesLogType);

        return $this->EntitiesLogsTable->saveOrFail($entity, ['checkRules' => false]);
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
        $entitiesLogType = $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated;

        $result = $this->saveEntitiesLog(entity: $entity, entitiesLogType: $entitiesLogType);

        $event->setResult($result);
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
        $entitiesLogType = EntitiesLogType::Deleted;

        $result = $this->saveEntitiesLog(entity: $entity, entitiesLogType: $entitiesLogType);

        $event->setResult($result);
    }
}
