<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\Fixture;

use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\TestSuite\Fixture\TestFixture;

/**
 * EntitiesLogsFixture
 */
class EntitiesLogsFixture extends TestFixture
{
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'entity_class' => 'App\Model\Entity\Article',
                'entity_id' => 1,
                'user_id' => 1,
                'type' => EntitiesLogType::Created->value,
                'datetime' => '2025-06-16 20:22:06',
                'ip' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            ],
        ];

        parent::init();
    }
}
