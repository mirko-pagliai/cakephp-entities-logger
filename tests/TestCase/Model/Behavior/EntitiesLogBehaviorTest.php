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

        $Identity = new User(['id' => 1]);

        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', $Identity);
        Router::setRequest($Request);
    }

    #[Test]
    public function testConstructForHasManyAssociation(): void
    {
        $Table = new Table();
        $Table->setEntityClass(Article::class);

        $this->assertFalse($Table->hasAssociation('EntitiesLogs'));

        new EntitiesLogBehavior($Table);

        $this->assertTrue($Table->hasAssociation('EntitiesLogs'));

        $association = $Table->getAssociation('EntitiesLogs');
        $this->assertInstanceOf(HasMany::class, $association);
        $this->assertInstanceOf(EntitiesLogsTable::class, $association->getTarget());
        $this->assertSame(['entity_class' => Article::class], $association->getConditions());
        $this->assertSame(['EntitiesLogs.datetime' => 'ASC'], $association->getSort());
    }

    #[Test]
    public function testGetRequest(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getRequest(): ServerRequest
            {
                return parent::getRequest();
            }
        };

        $this->assertSame(Router::getRequest(), $Behavior->getRequest());
    }

    #[Test]
    public function testGetRequestNotInstanceOfServerRequest(): void
    {
        Router::reload();

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getRequest(): ServerRequest
            {
                return parent::getRequest();
            }
        };

        $this->expectExceptionMessage('Request is not an instance of Cake\Http\ServerRequest.');
        $Behavior->getRequest();
    }

    #[Test]
    public function testGetIdentityId(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $result = $Behavior->getIdentityId();
        $this->assertSame(1, $result);
    }

    #[Test]
    #[TestWith(['Unable to retrieve identity. Request does not have an identity attribute.', null])]
    #[TestWith(['`App\Model\Entity\User::$id` is null, expected non-null value.', new User()])]
    #[TestWith(['`App\Model\Entity\User::$id` is null, expected non-null value.', new User(['id' => null])])]
    public function testGetIdentityIdWithoutValidIdentity(string $expectedExceptionMessage, ?User $Identity): void
    {
        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', $Identity);
        Router::setRequest($Request);

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $this->expectExceptionMessage($expectedExceptionMessage);
        $Behavior->getIdentityId();
    }

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

            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $result = $Behavior->buildEntity(new Article(['id' => 3]), EntitiesLogType::Created);

        $this->assertSame($expectedKeys, array_keys($result->toArray()));
        $this->assertSame(Article::class, $result->entity_class);
        $this->assertSame(3, $result->entity_id);
        $this->assertSame(2, $result->user_id);
        $this->assertSame(EntitiesLogType::Created, $result->type);
        $this->assertInstanceOf(DateTime::class, $result->datetime);
        $this->assertLessThanOrEqual(1, $result->datetime->diffInSeconds());
    }

    #[Test]
    public function testBuildEntityWithNoEntityId(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
            {
                return parent::buildEntity($entity, $entitiesLogType);
            }
        };

        $this->expectExceptionMessage('`' . Entity::class . '::$id` is null, expected non-null value.');
        $Behavior->buildEntity(new Entity(), EntitiesLogType::Created);
    }

    #[Test]
    public function testSaveEntitiesLog(): void
    {
        $Behavior = new class (new Table(), ['checkRules' => false]) extends EntitiesLogBehavior {
            protected function buildEntity(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
            {
                return new EntitiesLog();
            }

            public function saveEntitiesLog(EntityInterface $entity, EntitiesLogType $entitiesLogType): EntitiesLog
            {
                return parent::saveEntitiesLog($entity, $entitiesLogType);
            }
        };

        /** @var \Cake\EntitiesLogger\Model\Table\EntitiesLogsTable&\Mockery\MockInterface $EntitiesLogsTable */
        $EntitiesLogsTable = Mockery::mock(EntitiesLogsTable::class);
        $EntitiesLogsTable->shouldReceive('saveOrFail')
            ->once()
            ->with(Mockery::type(EntitiesLog::class), ['checkRules' => false])
            ->andReturn(new EntitiesLog());
        $Behavior->EntitiesLogsTable = $EntitiesLogsTable;

        $Behavior->saveEntitiesLog(new Article(['id' => 3]), EntitiesLogType::Created);
    }

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

        $result = $Table->dispatchEvent('Model.afterSave', [$Entity]);
        $this->assertInstanceOf(EntitiesLog::class, $result->getResult());
    }

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

        $result = $Table->dispatchEvent('Model.afterDelete', [$Entity]);
        $this->assertInstanceOf(EntitiesLog::class, $result->getResult());
    }
}
