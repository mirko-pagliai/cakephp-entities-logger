<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CakeEntitiesLoggerInitial extends BaseMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function up(): void
    {
        $this->table('entities_logs')
            ->addColumn('entity_class', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('entity_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('datetime', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-down-method
     * @return void
     */
    public function down(): void
    {
        $this->table('entities_logs')->drop()->save();
    }
}
