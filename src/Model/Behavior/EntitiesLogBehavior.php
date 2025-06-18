<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use ArrayObject;
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

    /**
     * @inheritDoc
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        $this->EntitiesLogsTable = $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');
    }

    /**
     * Retrieves the identity ID from the current request.
     *
     * @return int The ID of the current identity.
     * @throws \RuntimeException If the request does not have an identity attribute.
     * @throws \Cake\Datasource\Exception\MissingPropertyException If the identity attribute does not have an `id` property.
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
            throw new MissingPropertyException('`' . $Identity::class . '::$id` is null, expected non-null value.');
        }

        return $Identity->id;
    }

    /**
     * Builds a new log entity based on the provided entity and log type.
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
        ]);
    }

    /**
     * Handles the logic to be executed after an entity is saved.
     *
     * @param \Cake\Event\EventInterface $event The event triggered after the save operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was saved.
     * @param \ArrayObject $options Additional options used for the save operation.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entitiesLogType = $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated;
        $EntitiesLog = $this->buildEntity(entity: $entity, entitiesLogType: $entitiesLogType);

        $result = $this->EntitiesLogsTable->saveOrFail($EntitiesLog, ['checkRules' => $options['checkRules'] ?? true]);

        $event->setResult($result);
    }

    /**
     * Handles the logic to be executed after an entity is deleted.
     *
     * @param \Cake\Event\EventInterface $event The event triggered after the delete operation.
     * @param \Cake\Datasource\EntityInterface $entity The entity instance that was deleted.
     * @param \ArrayObject $options Additional options used for the delete operation.
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entitiesLogType = EntitiesLogType::Deleted;
        $EntitiesLog = $this->buildEntity(entity: $entity, entitiesLogType: $entitiesLogType);

        $result = $this->EntitiesLogsTable->saveOrFail($EntitiesLog, ['checkRules' => $options['checkRules'] ?? true]);

        $event->setResult($result);
    }
}
