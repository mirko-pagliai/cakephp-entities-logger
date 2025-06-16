<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\Fixture;

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
                'entity_class' => 'Lorem ipsum dolor sit amet',
                'entity_id' => 1,
                'user_id' => 1,
                'type' => 'Lorem ipsum dolor sit amet',
                'datetime' => '2025-06-16 20:10:24',
            ],
        ];

        parent::init();
    }
}
