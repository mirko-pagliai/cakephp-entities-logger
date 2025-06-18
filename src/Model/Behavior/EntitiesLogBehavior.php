<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\Routing\Router;

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
     * Retrieves the identity of the currently authenticated user.
     *
     * @return \Cake\Datasource\EntityInterface The identity object representing the authenticated user.
     */
    protected function getIdentity(): EntityInterface
    {
        /** @var \Cake\Http\ServerRequest $Request */
        $Request = Router::getRequest();
        /** @var \Cake\Datasource\EntityInterface $Identity */
        $Identity = $Request->getAttribute('identity');

        return $Identity;
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
            'user_id' => $this->getIdentity()->get('id'),
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
        $EntitiesLog = $this->buildEntity(entity: $entity, entitiesLogType: EntitiesLogType::Deleted);

        $this->EntitiesLogsTable->saveOrFail($EntitiesLog);
    }
}
