<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Article Entity.
 *
 * @property int $id
 * @property string $title
 * @property int|null $category_id
 * @property array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> $entities_logs
 */
class Article extends Entity
{
}
