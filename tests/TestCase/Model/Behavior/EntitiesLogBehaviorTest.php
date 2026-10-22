<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use App\Model\Entity\Article;
use App\Model\Entity\User;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use UnexpectedValueException;

/**
 * EntitiesLogBehaviorTest
 */
#[CoversClass(EntitiesLogBehavior::class)]
class EntitiesLogBehaviorTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Resets the router state, including the current request
        Router::reload();
    }

    /**
     * Internal method to get a table with a schema, so that its primary key can be read without querying the database.
     *
     * @param array<string, string> $columns Column names and types.
     * @param array<string> $primaryKey Columns of the primary key.
     * @return \Cake\ORM\Table
     */
    private function getTableWithSchema(array $columns = ['id' => 'integer'], array $primaryKey = ['id']): Table
    {
        $schema = array_map(callback: fn($type): array => compact('type'), array: $columns);
        $schema['_constraints'] = ['primary' => ['type' => 'primary', 'columns' => $primaryKey]];

        return new Table(['table' => 'articles'] + compact('schema'));
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testConstruct(): void
    {
        $Table = new Table();
        $Table->setEntityClass(Article::class);

        $Behavior = new EntitiesLogBehavior($Table);
        $this->assertSame(EntitiesLogsTable::class, $Behavior->EntitiesLogsTable->getRegistryAlias());

        $Association = $Table->getAssociation('EntitiesLogs');
        $this->assertInstanceOf(HasMany::class, $Association);
        $this->assertInstanceOf(EntitiesLogsTable::class, $Association->getTarget());
        $this->assertSame(['EntitiesLogs.datetime' => 'ASC'], $Association->getSort());
    }

    /**
     * Test for the `__construct()` method.
     *
     * Tests that the conditions of the `EntitiesLogs` association use the entity class that the table has when the
     * query is built, not the one it had when the behavior was built.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testConstructEntityClassCondition(): void
    {
        $Table = new Table();
        $Table->setEntityClass(Article::class);

        new EntitiesLogBehavior($Table);

        $Query = $Table->getAssociation('EntitiesLogs')->find();
        $Query->sql();
        $this->assertContains(Article::class, array_column($Query->getValueBinder()->bindings(), 'value'));

        $Table->setEntityClass(User::class);

        $Query = $Table->getAssociation('EntitiesLogs')->find();
        $Query->sql();
        $values = array_column($Query->getValueBinder()->bindings(), 'value');
        $this->assertContains(User::class, $values);
        $this->assertNotContains(Article::class, $values);
    }

    /**
     * Tests for `__construct` when the table already has the `EntitiesLogs` association.
     *
     * Unlike the previous test, in this case the table instance to which the behavior is attached already has the
     * `EntitiesLogs` association, so this is not modified.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testConstructTableAlreadyHasTheAssociation(): void
    {
        $NewAssociatedTable = new Table();

        $Table = new Table();
        /** @phpstan-ignore cake.addAssociation.existClass */
        $Table->hasMany('EntitiesLogs', ['targetTable' => $NewAssociatedTable]);

        new EntitiesLogBehavior($Table);

        $this->assertSame($NewAssociatedTable, $Table->getAssociation('EntitiesLogs')->getTarget());
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testConstructBehaviorAlreadyHasTheEntitiesLogsTableProperty(): void
    {
        $Behavior = new EntitiesLogBehavior(new Table());
        $Behavior->EntitiesLogsTable = new Table(['alias' => 'MyEntitiesLogsTable']);

        $this->assertSame('MyEntitiesLogsTable', $Behavior->EntitiesLogsTable->getRegistryAlias());
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntity(): void
    {
        $expectedKeys = [
            'entity_class',
            'entity_id',
            'user_id',
            'type',
            'datetime',
            'ip',
            'user_agent',
        ];

        $Behavior = new class ($this->getTableWithSchema()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $request = new ServerRequest();
        $request = $request->withAttribute('identity', new User(['id' => 5]));
        Router::setRequest($request);

        $result = $Behavior->buildEntity(new Article(['id' => 3]), EntitiesLogType::Created);

        $this->assertInstanceOf(EntitiesLog::class, $result);
        $this->assertSame($expectedKeys, array_keys($result->toArray()));
        $this->assertSame(Article::class, $result->entity_class);
        $this->assertSame(3, $result->entity_id);
        $this->assertSame(5, $result->user_id);
        $this->assertSame(EntitiesLogType::Created, $result->type);
        $this->assertInstanceOf(DateTime::class, $result->datetime);
        $this->assertLessThanOrEqual(1, $result->datetime->diffInSeconds());
    }

    /**
     * Tests that `buildEntity()` reads the request when it is called, not when the behavior is built.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntityUsesTheCurrentRequest(): void
    {
        Router::setRequest((new ServerRequest())->withAttribute('identity', new User(['id' => 1])));

        $Behavior = new class ($this->getTableWithSchema()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        // The request is replaced after the behavior has been built
        $request = new ServerRequest(['environment' => ['REMOTE_ADDR' => '192.168.0.200']]);
        Router::setRequest($request->withAttribute('identity', new User(['id' => 7])));

        $result = $Behavior->buildEntity(new Article(['id' => 3]), EntitiesLogType::Created);

        $this->assertInstanceOf(EntitiesLog::class, $result);
        $this->assertSame(7, $result->user_id);
        $this->assertSame('192.168.0.200', $result->ip);
    }

    /**
     * Tests for the `buildEntity()` method with different primary keys.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    #[TestWith(['id', 3])]
    #[TestWith(['id', '7'])]
    #[TestWith(['article_id', 3])]
    public function testBuildEntityWithPrimaryKey(string $primaryKey, int|string $entityId): void
    {
        $Behavior = new class ($this->getTableWithSchema([$primaryKey => 'integer'], [$primaryKey])) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $request = new ServerRequest();
        $request = $request->withAttribute('identity', new User(['id' => 5]));
        Router::setRequest($request);

        $result = $Behavior->buildEntity(new Entity([$primaryKey => $entityId]), EntitiesLogType::Created);

        $this->assertInstanceOf(EntitiesLog::class, $result);
        $this->assertSame((int)$entityId, $result->entity_id);
    }

    /**
     * Tests for the `buildEntity()` method when the entity does not have the primary key.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    #[TestWith(['id'])]
    #[TestWith(['article_id'])]
    public function testBuildEntityWithNoEntityId(string $primaryKey): void
    {
        $Behavior = new class ($this->getTableWithSchema([$primaryKey => 'integer'], [$primaryKey])) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('`' . Entity::class . '::$' . $primaryKey . '` is null, expected non-null value.');
        $Behavior->buildEntity(new Entity(), EntitiesLogType::Created);
    }

    /**
     * Tests for the `buildEntity()` method when the primary key is composite.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntityWithCompositePrimaryKey(): void
    {
        $Table = $this->getTableWithSchema(['article_id' => 'integer', 'user_id' => 'integer'], ['article_id', 'user_id']);
        $Behavior = new class ($Table) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unable to log `' . Entity::class . '`: a single-column primary key is required.');
        $Behavior->buildEntity(new Entity(['article_id' => 3, 'user_id' => 5]), EntitiesLogType::Created);
    }

    /**
     * Tests for the `buildEntity()` method when the entity id is not an integer (for example, an UUID).
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    #[TestWith(['550e8400-e29b-41d4-a716-446655440000'])]
    #[TestWith(['abc'])]
    #[TestWith([3.5])]
    public function testBuildEntityWithNonIntegerEntityId(string|float $entityId): void
    {
        $Behavior = new class ($this->getTableWithSchema(['id' => 'string'])) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unable to log `' . Entity::class . '`: the primary key must be an integer.');
        $Behavior->buildEntity(new Entity(['id' => $entityId]), EntitiesLogType::Created);
    }

    /**
     * Tests for the `buildEntity()` method when the request is `null`.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntityWithNullRequest(): void
    {
        $Behavior = new class ($this->getTableWithSchema()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $result = $Behavior->buildEntity(new Article(['id' => 3]), EntitiesLogType::Created);
        $this->assertNull($result);
    }

    /**
     * Tests for the `buildEntity()` method when the identity is not valid.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    #[TestWith(['Unable to retrieve identity. Request does not have an identity attribute.', null])]
    #[TestWith(['`App\Model\Entity\User::$id` is null, expected non-null value.', new User()])]
    #[TestWith(['`App\Model\Entity\User::$id` is null, expected non-null value.', new User(['id' => null])])]
    public function testBuildEntityWithInvalidIdentity(string $expectedExceptionMessage, ?User $Identity): void
    {
        $Behavior = new class ($this->getTableWithSchema()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $request = new ServerRequest();
        $request = $request->withAttribute('identity', $Identity);
        Router::setRequest($request);

        $this->expectExceptionMessage($expectedExceptionMessage);
        $Behavior->buildEntity(new Article(['id' => 3]), EntitiesLogType::Created);
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testSaveEntitiesLog(): void
    {
        $Behavior = new class (new Table(), ['checkRules' => false]) extends EntitiesLogBehavior {
            protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
            {
                return new EntitiesLog();
            }

            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };

        /** @var \Cake\EntitiesLogger\Model\Table\EntitiesLogsTable&\Mockery\MockInterface $EntitiesLogsTable */
        $EntitiesLogsTable = Mockery::mock(EntitiesLogsTable::class);
        $EntitiesLogsTable
            ->shouldReceive('saveOrFail')
            ->once()
            ->with(Mockery::type(EntitiesLog::class), ['checkRules' => false])
            ->andReturn(new EntitiesLog());

        $Behavior->EntitiesLogsTable = $EntitiesLogsTable;

        $Behavior->saveEntitiesLog(new Article(['id' => 3]), EntitiesLogType::Created);
    }

    /**
     * Tests for the `saveEntitiesLog()` method, with Ipv4 and Ipv6 addresses.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    #[TestWith(['192.168.1.100'])]
    #[TestWith(['2001:0db8:85a3:0000:0000:8a2e:0370:7334'])]
    public function testSaveEntitiesLogIpv4AndIpv6(string $ipAddress): void
    {
        $Behavior = new class ($this->getTableWithSchema(), ['checkRules' => false]) extends EntitiesLogBehavior {
            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };

        $request = new ServerRequest(['environment' => ['REMOTE_ADDR' => $ipAddress]]);
        $request = $request->withAttribute('identity', new User(['id' => 1]));
        Router::setRequest($request);

        $result = $Behavior->saveEntitiesLog(new Article(['id' => 4]), EntitiesLogType::Created);
        $this->assertInstanceOf(EntitiesLog::class, $result);
        $this->assertSame($ipAddress, $result->ip);
    }

    /**
     * Tests for the `saveEntitiesLog()` method when the request is `null`.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testSaveEntitiesLogWithNullRequest(): void
    {
        $Behavior = new class ($this->getTableWithSchema()) extends EntitiesLogBehavior {
            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };

        $result = $Behavior->saveEntitiesLog(new Article(['id' => 3]), EntitiesLogType::Created);
        $this->assertNull($result);
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    #[TestWith([EntitiesLogType::Created, true])]
    #[TestWith([EntitiesLogType::Updated, false])]
    public function testAfterSave(EntitiesLogType $expectedEntitiesLogType, bool $entityIsNew): void
    {
        $Entity = new Article(['id' => 3]);
        $Entity->setNew($entityIsNew);

        $Table = new Table();

        /** @var \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior&\Mockery\MockInterface $Behavior */
        $Behavior = Mockery::mock(EntitiesLogBehavior::class . '[saveEntitiesLog]', [$Table]);
        $Behavior->shouldAllowMockingProtectedMethods();
        $Behavior
            ->shouldReceive('saveEntitiesLog')
            ->with($Entity, $expectedEntitiesLogType)
            ->once()
            ->andReturn(new EntitiesLog());

        $request = new ServerRequest();
        $request = $request->withAttribute('identity', new User(['id' => 5]));
        Router::setRequest($request);

        $Table->behaviors()->set('EntitiesLog', $Behavior);

        $event = $Table->dispatchEvent('Model.afterSave', [$Entity]);
        $this->assertInstanceOf(EntitiesLog::class, $event->getResult());
    }

    /**
     * Tests for the `afterSave()` method when the request is `null`.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testAfterSaveWithNullRequest(): void
    {
        $Table = new Table();

        $Behavior = new EntitiesLogBehavior($Table);

        $Table->behaviors()->set('EntitiesLog', $Behavior);
        $event = $Table->dispatchEvent('Model.afterSave', [new Article(['id' => 3])]);
        $this->assertNull($event->getResult());
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterDelete()
     */
    #[Test]
    public function testAfterDelete(): void
    {
        $Entity = new Article(['id' => 3]);

        $Table = new Table();

        /** @var \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior&\Mockery\MockInterface $Behavior */
        $Behavior = Mockery::mock(EntitiesLogBehavior::class . '[saveEntitiesLog]', [$Table]);
        $Behavior->shouldAllowMockingProtectedMethods();
        $Behavior
            ->shouldReceive('saveEntitiesLog')
            ->with($Entity, EntitiesLogType::Deleted)
            ->once()
            ->andReturn(new EntitiesLog());

        $request = new ServerRequest();
        $request = $request->withAttribute('identity', new User(['id' => 5]));
        Router::setRequest($request);

        $Table->behaviors()->set('EntitiesLog', $Behavior);

        $event = $Table->dispatchEvent('Model.afterDelete', [$Entity]);
        $this->assertInstanceOf(EntitiesLog::class, $event->getResult());
    }

    /**
     * Tests for the `afterDelete()` method when the request is `null`.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterDelete()
     */
    #[Test]
    public function testAfterDeleteWithNullRequest(): void
    {
        $Table = new Table();

        $Behavior = new EntitiesLogBehavior($Table);

        $Table->behaviors()->set('EntitiesLog', $Behavior);
        $event = $Table->dispatchEvent('Model.afterDelete', [new Article(['id' => 3])]);
        $this->assertNull($event->getResult());
    }
}
