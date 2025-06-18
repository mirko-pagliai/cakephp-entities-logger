<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use Cake\Datasource\EntityInterface;
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
    public function testGetIdentity(): void
    {
        $Identity = new Entity(['id' => 1]);

        $Request = new ServerRequest();
        $Request = $Request->withAttribute('identity', $Identity);
        Router::setRequest($Request);

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentity(): EntityInterface
            {
                return parent::getIdentity();
            }
        };

        $result = $Behavior->getIdentity();
        $this->assertSame($Identity, $result);
    }

    #[Test]
    public function testGetIdentityWithNoRequest(): void
    {
        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentity(): EntityInterface
            {
                return parent::getIdentity();
            }
        };

        $this->expectExceptionMessage('Unable to retrieve identity. Request is not an instance of Cake\Http\ServerRequest.');
        $Behavior->getIdentity();
    }

    #[Test]
    public function testGetIdentityWithNoIdentity(): void
    {
        Router::setRequest(new ServerRequest());

        $Behavior = new class (new Table()) extends EntitiesLogBehavior {
            public function getIdentity(): EntityInterface
            {
                return parent::getIdentity();
            }
        };

        $this->expectExceptionMessage('Unable to retrieve identity. Request does not have an identity attribute.');
        $Behavior->getIdentity();
    }
}