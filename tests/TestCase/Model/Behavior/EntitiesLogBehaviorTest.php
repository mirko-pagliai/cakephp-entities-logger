<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use App\Model\Entity\Article;
use App\Model\Entity\User;
use Cake\Datasource\EntityInterface;
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

        $Request = new ServerRequest();
        $Request = $Request
            ->withAttribute('identity', new User(['id' => 5]))
            ->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36')
            ->withEnv('REMOTE_ADDR', '127.0.0.1');

        Router::setRequest($Request);
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
        $this->assertSame(['entity_class' => Article::class], $Association->getConditions());
        $this->assertSame(['EntitiesLogs.datetime' => 'ASC'], $Association->getSort());
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
        $this->assertSame(Router::getRequest(), $Behavior->request);
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

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            protected function getIdentityId(): int
            {
                return 2;
            }

            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

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
     * Tests for the `buildEntity()` method when the identity is not valid.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntityWithNoEntityId(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $this->expectExceptionMessage('`' . Entity::class . '::$id` is null, expected non-null value.');
        $Behavior->buildEntity(new Entity(), EntitiesLogType::Created);
    }

    /**
     * Tests for the `buildEntity()` method when the request is `null`.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::buildEntity()
     */
    #[Test]
    public function testBuildEntityWithNullRequest(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };
        $Behavior->request = null;

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
    public function testBuildEntityWithoutValidIdentity(string $expectedExceptionMessage, ?User $Identity): void
    {
        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', $Identity);
        Router::setRequest($Request);

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

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
        $Request = new ServerRequest();
        $Request = $Request
            ->withAttribute('identity', new User(['id' => 1]))
            ->withEnv('REMOTE_ADDR', $ipAddress);

        Router::setRequest($Request);

        $Behavior = new class (new Table(), ['checkRules' => false]) extends EntitiesLogBehavior {
            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };

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
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): ?EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };
        $Behavior->request = null;

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
        $Behavior->request = null;

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
        $Behavior->request = null;

        $Table->behaviors()->set('EntitiesLog', $Behavior);
        $event = $Table->dispatchEvent('Model.afterDelete', [new Article(['id' => 3])]);
        $this->assertNull($event->getResult());
    }
}
