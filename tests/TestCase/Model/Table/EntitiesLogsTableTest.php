<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Table;

use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * EntitiesLogsTable.
 */
#[CoversClass(EntitiesLogsTable::class)]
class EntitiesLogsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Cake\EntitiesLogger\Model\Table\EntitiesLogsTable
     */
    protected $EntitiesLogs;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'plugin.Cake/EntitiesLogger.EntitiesLogs',
        'plugin.Cake/EntitiesLogger.Users',
    ];

    /**
     * @inheritDoc
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->getTableLocator()->exists('EntitiesLogs') ? [] : ['className' => EntitiesLogsTable::class];
        $this->EntitiesLogs = $this->getTableLocator()->get('EntitiesLogs', $config);
    }

    #[Test]
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    #[Test]
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
