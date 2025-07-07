<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'users_group_id' => 1,
                'email' => 'myfakemail@example.com',
                'status' => 'active',
                'first_name' => 'Mirko',
                'last_name' => 'Pagliai',
                'created' => '2023-06-21 15:32:00',
                'modified' => '2023-06-21 15:32:00',
            ],
        ];

        parent::init();
    }
}
