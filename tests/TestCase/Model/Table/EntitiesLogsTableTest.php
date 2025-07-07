<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Table;

use App\Model\Table\UsersTable;
use Cake\Database\Type\EnumType;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\EntitiesLogger\Test\Fixture\EntitiesLogsFixture;
use Cake\ORM\Association\BelongsTo;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * EntitiesLogsTable.
 */
#[CoversClass(EntitiesLogsTable::class)]
class EntitiesLogsTableTest extends TestCase
{
    protected EntitiesLogsTable $EntitiesLogs;

    /**
     * @var array<string>
     */
    protected array $fixtures = [
        EntitiesLogsFixture::class,
    ];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->EntitiesLogs ??= $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');
    }

    #[Test]
    public function testInitialize(): void
    {
        $expectedEntitiesLogType = EnumType::from(EntitiesLogType::class);

        $this->assertSame($expectedEntitiesLogType, $this->EntitiesLogs->getSchema()->getColumnType('type'));

        $UsersBelongsTo = $this->EntitiesLogs->getAssociation('Users');
        $this->assertInstanceOf(BelongsTo::class, $UsersBelongsTo);
        $this->assertInstanceOf(UsersTable::class, $UsersBelongsTo->getTarget());
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
