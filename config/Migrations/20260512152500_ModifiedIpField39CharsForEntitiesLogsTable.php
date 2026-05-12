<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ModifiedIpField39CharsForEntitiesLogsTable extends BaseMigration
{

    public function up(): void
    {
        $this->table('entities_logs')
            ->changeColumn('ip', 'string', ['limit' => 39])
            ->update();
    }

    public function down(): void
    {
        $this->table('entities_logs')
            ->changeColumn('ip', 'string', ['limit' => 15])
            ->update();
    }
}
