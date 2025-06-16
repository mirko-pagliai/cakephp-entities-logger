<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Enum;

/**
 * Represents the types of log actions that can be associated with entities.
 */
enum EntitiesLogType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
