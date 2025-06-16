<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;

/**
 * Behavior for logging changes to entities.
 */
class EntitiesLogBehavior extends Behavior
{
    use LocatorAwareTrait;

    /**
     * Handles actions to be performed after an entity is saved.
     *
     * @param \Cake\Event\EventInterface $event The event that triggered the method.
     * @param \Cake\Datasource\EntityInterface $entity The entity object that was saved.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        /** @var \Cake\EntitiesLogger\Model\Table\EntitiesLogsTable $EntitiesLogsTable */
        $EntitiesLogsTable = $this->fetchTable('EntitiesLogs');

        /** @var \Cake\Http\ServerRequest $Request */
        $Request = Router::getRequest();
        /** @var \App\Model\Entity\User $Identity */
        $Identity = $Request->getAttribute('identity');

        $EntitiesLog = $EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entity->id,
            'user_id' => $Identity->id,
            'type' => $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated,
        ]);

        $EntitiesLogsTable->saveOrFail($EntitiesLog);
    }
}