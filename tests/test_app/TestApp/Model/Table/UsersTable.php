<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class UsersTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('last_name');
        $this->setPrimaryKey('id');
    }
}
