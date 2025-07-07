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
                'email' => 'myfakemail@example.com',
                'first_name' => 'Mirko',
                'last_name' => 'Pagliai',
            ],
        ];

        parent::init();
    }
}
