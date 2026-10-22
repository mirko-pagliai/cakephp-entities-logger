<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddedEntityIndexToEntitiesLogsTable extends BaseMigration
{
    /**
     * Adds the index used to find the logs of an entity.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('entities_logs')
            ->addIndex(['entity_class', 'entity_id'], ['name' => 'entities_logs_entity_idx'])
            ->update();
    }

    /**
     * Removes the index used to find the logs of an entity.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('entities_logs')
            ->removeIndexByName('entities_logs_entity_idx')
            ->update();
    }
}

