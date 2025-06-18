<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use App\Model\Entity\Article;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * EntitiesLogBehaviorTest
 */
#[CoversClass(EntitiesLogBehavior::class)]
class EntitiesLogBehaviorTest extends TestCase
{
    #[Test]
    public function testGetIdentityId(): void
    {
        $Identity = new Entity(['id' => 1]);

        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', $Identity);
        Router::setRequest($Request);

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $result = $Behavior->getIdentityId();
        $this->assertSame($Identity->id, $result);
    }

    #[Test]
    public function testGetIdentityIdWithNoRequest(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $this->expectExceptionMessage('Unable to retrieve identity. Request is not an instance of Cake\Http\ServerRequest.');
        $Behavior->getIdentityId();
    }

    #[Test]
    public function testGetIdentityIdWithNoIdentity(): void
    {
        Router::setRequest(new ServerRequest());

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $this->expectExceptionMessage('Unable to retrieve identity. Request does not have an identity attribute.');
        $Behavior->getIdentityId();
    }

    #[Test]
    public function testGetIdentityIdWithNoIdProperty(): void
    {
        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', new Entity());
        Router::setRequest($Request);

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentityId(): int
            {
                return parent::getIdentityId();
            }
        };

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('`' . Entity::class . '::$id` is null, expected non-null value.');
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
}
