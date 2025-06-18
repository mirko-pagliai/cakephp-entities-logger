<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Cake\Datasource\EntityInterface;
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

    protected EntitiesLogsTable $EntitiesLogsTable;

    /**
     * @inheritDoc
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        $this->EntitiesLogsTable = $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');
    }

    /**
     * Retrieves the identity id from the current request.
     *
     * @return int The id of the identity associated with the current request.
     * @throws \RuntimeException If the request does not have an identity attribute.
     * @throws \RuntimeException If the identity object does not have a valid id.
     * @throws \RuntimeException If the request is not an instance of \Cake\Http\ServerRequest.
     */
    protected function getIdentityId(): int
    {
        $Request = Router::getRequest();
        if (!$Request instanceof ServerRequest) {
            throw new RuntimeException('Unable to retrieve identity. Request is not an instance of Cake\Http\ServerRequest.');
        }

        /** @var \Cake\Datasource\EntityInterface|null $Identity */
        $Identity = $Request->getAttribute('identity');
        if (!$Identity) {
            throw new RuntimeException('Unable to retrieve identity. Request does not have an identity attribute.');
        }

        if (!isset($Identity->id)) {
            throw new RuntimeException('`' . $Identity::class . '::$id` is null, expected non-null value.');
        }

        return $Identity->id;
    }

    /**
     * Constructs a new EntitiesLog entity based on the provided parameters.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity object related to the log entry.
     * @param \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $entitiesLogType The type of log to be created.
     * @return \Cake\EntitiesLogger\Model\Entity\EntitiesLog The newly created EntitiesLog entity.
     */
    protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
    {
        return $this->EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entity->get('id'),
            'user_id' => $this->getIdentityId(),
            'type' => $entitiesLogType,
            'datetime' => new DateTime(),
        ]);
    }

    /**
     * Handles actions to be performed after an entity is saved.
     *
     * @param \Cake\Event\EventInterface $event The event that triggered the method.
     * @param \Cake\Datasource\EntityInterface $entity The entity object that was saved.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        $entitiesLogType = $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated;
        $EntitiesLog = $this->buildEntity(entity: $entity, entitiesLogType: $entitiesLogType);

        $this->EntitiesLogsTable->saveOrFail($EntitiesLog);
    }

    /**
     * Handles actions to be performed after an entity is deleted.
     *
     * @param \Cake\Event\EventInterface $event The event that triggered the method.
     * @param \Cake\Datasource\EntityInterface $entity The entity object that was deleted.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity): void
    {
        $entitiesLogType = EntitiesLogType::Deleted;
        $EntitiesLog = $this->buildEntity(entity: $entity, entitiesLogType: $entitiesLogType);

        $this->EntitiesLogsTable->saveOrFail($EntitiesLog);
    }
}
