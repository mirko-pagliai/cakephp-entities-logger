<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Table;

use App\Model\Table\UsersTable;
use Cake\Database\Type\EnumType;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\EntitiesLogger\Test\Fixture\EntitiesLogsFixture;
use Cake\EntitiesLogger\Test\Fixture\UsersFixture;
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
        UsersFixture::class,
    ];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->EntitiesLogs = $this->fetchTable('Cake/EntitiesLogger.EntitiesLogs');
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
    public function testInitializeAlreadyHasUsersAssociation(): void
    {
        $AnotherUsersTable = new class extends UsersTable {
        };

        //Removes the default `Users` association and sets a new one
        $this->EntitiesLogs->associations()->remove('Users');
        $this->EntitiesLogs->belongsTo('Users', ['targetTable' => $AnotherUsersTable]);

        //By re-initializing the table, the `Users` association remains the one already set
        $this->EntitiesLogs->initialize([]);
        $this->assertSame($AnotherUsersTable, $this->EntitiesLogs->Users->getTarget());
    }

    #[Test]
    public function testValidationDefault(): void
    {
        $EntitiesLog = $this->EntitiesLogs->newEntity([
            'entity_class' => 'App\Model\Entity\Article',
            'entity_id' => 1,
            'user_id' => 1,
            'type' => EntitiesLogType::Created,
            'datetime' => '2025-06-16 20:22:06',
            'ip' => '192.168.1.100',
            'user_agent' => '',
        ]);
        $this->assertEmpty($EntitiesLog->getErrors());
    }

    #[Test]
    public function testValidationDefaultWithErrors(): void
    {
        $expected = [
            'entity_class' => [
                'maxLength' => 'The provided value must be at most `255` characters long',
            ],
            'entity_id' => [
                'integer' => 'The provided value must be an integer',
            ],
            'user_id' => [
                'integer' => 'The provided value must be an integer',
            ],
            'type' => [
                'enum' => 'The provided value must be one of `created`, `updated`, `deleted`',
            ],
            'datetime' => [
                'dateTime' => 'The provided value must be a date and time of one of these formats: `ymd`',
            ],
            'ip' => [
                'ipv4' => 'The provided value must be an IPv4 address',
            ],
        ];
        $EntitiesLog = $this->EntitiesLogs->newEntity([
            'entity_class' => str_repeat('a', 256),
            'entity_id' => 'notInteger',
            'user_id' => 'notInteger',
            'type' => 'notValidType',
            'datetime' => '2025-06-16',
            'ip' => 'notIpv4Address',
            'user_agent' => '',
        ]);
        $this->assertSame($expected, $EntitiesLog->getErrors());
    }

    #[Test]
    public function testBuildRules(): void
    {
        $EntitiesLog = $this->EntitiesLogs->newEntity([
            'entity_class' => 'App\Model\Entity\Article',
            'entity_id' => 1,
            'user_id' => 1,
            'type' => EntitiesLogType::Created,
            'datetime' => '2025-06-16 20:22:06',
        ]);
        $this->assertTrue($this->EntitiesLogs->checkRules($EntitiesLog));
    }

    #[Test]
    public function testBuildRulesWithErrors(): void
    {
        $expected = [
            'user_id' => [
                '_existsIn' => 'This value does not exist',
            ],
        ];
        $EntitiesLog = $this->EntitiesLogs->newEntity([
            'entity_class' => 'App\Model\Entity\Article',
            'entity_id' => 1,
            'user_id' => 11,
            'type' => EntitiesLogType::Created,
            'datetime' => '2025-06-16 20:22:06',
        ]);
        $this->assertFalse($this->EntitiesLogs->checkRules($EntitiesLog));
        $this->assertSame($expected, $EntitiesLog->getErrors());
    }
}
