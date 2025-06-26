<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddedUserAgentFieldToEntitiesLogsTable extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-up-method
     * @return void
     */
    public function up(): void
    {

        $this->table('entities_logs')
            ->addColumn('user_agent', 'text', [
                'after' => 'ip',
                'collation' => 'utf8mb4_general_ci',
                'default' => null,
                'length' => null,
                'null' => true,
            ])
            ->update();
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

        $this->table('entities_logs')
            ->removeColumn('user_agent')
            ->update();
    }
}
