<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Behavior;

use Authentication\IdentityInterface;
use Cake\Datasource\EntityInterface;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
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

    /**
     * Retrieves the identity of the currently authenticated user.
     *
     * @return \Authentication\IdentityInterface The identity object representing the authenticated user.
     */
    protected function getIdentity(): IdentityInterface
    {
        /** @var \Cake\Http\ServerRequest $Request */
        $Request = Router::getRequest();
        /** @var \Authentication\IdentityInterface $Identity */
        $Identity = $Request->getAttribute('identity');

        return $Identity;
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
        /** @var \Cake\EntitiesLogger\Model\Table\EntitiesLogsTable $EntitiesLogsTable */
        $EntitiesLogsTable = $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');

        $EntitiesLog = $EntitiesLogsTable->newEntity([
            'entity_class' => $entity::class,
            'entity_id' => $entity->id,
            'user_id' => $this->getIdentity()->id,
            'type' => $entity->isNew() ? EntitiesLogType::Created : EntitiesLogType::Updated,
            'datetime' => new DateTime(),
        ]);

        $EntitiesLogsTable->saveOrFail($EntitiesLog);
    }
}