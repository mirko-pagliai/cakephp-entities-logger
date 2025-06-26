<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Entity;

use Cake\ORM\Entity;

/**
 * EntitiesLog Entity
 *
 * @property int $id
 * @property string $entity_class
 * @property int $entity_id
 * @property int $user_id
 * @property \Cake\EntitiesLogger\Model\Enum\EntitiesLogType $type
 * @property \Cake\I18n\DateTime $datetime
 * @property string|null $ip
 * @property string|null $user_agent
 *
 * @property \App\Model\Entity\User $user
 */
class EntitiesLog extends Entity
{
    /**
     * @inheritDoc
     */
    protected array $_accessible = [
        'entity_class' => true,
        'entity_id' => true,
        'user_id' => true,
        'type' => true,
        'datetime' => true,
        'ip' => true,
        'user_agent' => true,
        'user' => true,
    ];
}
