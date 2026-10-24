<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ModifiedIpField39CharsForEntitiesLogsTable extends BaseMigration
{

    public function up(): void
    {
        $this->table('entities_logs')
            ->changeColumn('ip', 'string', ['limit' => 39, 'null' => true, 'default' => null, 'collation' => 'utf8mb4_general_ci'])
            ->update();
    }

    public function down(): void
    {
        $this->table('entities_logs')
            ->changeColumn('ip', 'string', ['limit' => 15, 'null' => true, 'default' => null, 'collation' => 'utf8mb4_general_ci'])
            ->update();
    }
}
