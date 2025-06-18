<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior;
use Cake\Http\ServerRequest;
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
}
