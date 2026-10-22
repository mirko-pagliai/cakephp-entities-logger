<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use App\Model\Entity\User;
use Cake\Datasource\EntityInterface;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\EntitiesLogger\Model\Table\EntitiesLogsTable;
use Cake\EntitiesLogger\Test\Fixture\ArticlesFixture;
use Cake\EntitiesLogger\Test\Fixture\EntitiesLogsFixture;
use Cake\EntitiesLogger\Test\Fixture\UsersFixture;
use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * EntitiesLogBehaviorIntegrationTest.
 *
 * Unlike `EntitiesLogBehaviorTest`, these tests save and delete real entities through a table that loads the behavior.
 * The `articles` table has the article with ID 1, which already has a log (`Created`) in the `entities_logs` table.
 */
#[CoversClass(EntitiesLogBehavior::class)]
class EntitiesLogBehaviorIntegrationTest extends TestCase
{
    protected Table $Articles;

    protected EntitiesLogsTable $EntitiesLogs;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        ArticlesFixture::class,
        EntitiesLogsFixture::class,
        UsersFixture::class,
    ];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadPlugins(['Cake/EntitiesLogger']);

        // Resets the router state and sets a request with the identity of the user with ID 1
        Router::reload();
        $request = new ServerRequest(['environment' => ['REMOTE_ADDR' => '192.168.0.100', 'HTTP_USER_AGENT' => 'MyBrowser/1.0']]);
        Router::setRequest($request->withAttribute('identity', new User(['id' => 1])));

        $this->Articles = $this->fetchTable('Articles');
        $this->EntitiesLogs = $this->fetchTable(EntitiesLogsTable::class);
    }

    /**
     * Internal method to get the logs of an entity, from the oldest to the newest.
     *
     * @param \Cake\Datasource\EntityInterface $Entity The entity.
     * @return array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>
     */
    private function getEntitiesLogs(EntityInterface $Entity): array
    {
        /** @var array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> $EntitiesLogs */
        $EntitiesLogs = $this->EntitiesLogs->find()
            ->where(['entity_class' => $Entity::class, 'entity_id' => $Entity->get('id')])
            ->orderBy(['datetime' => 'ASC', 'id' => 'ASC'])
            ->all()
            ->toArray();

        return $EntitiesLogs;
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveNewEntity(): void
    {
        $Article = $this->Articles->saveOrFail($this->Articles->newEntity(['title' => 'New article']));

        $EntitiesLogs = $this->getEntitiesLogs($Article);
        $this->assertCount(1, $EntitiesLogs);
        $this->assertSame(EntitiesLogType::Created, $EntitiesLogs[0]->type);
        $this->assertSame(1, $EntitiesLogs[0]->user_id);
        $this->assertSame('192.168.0.100', $EntitiesLogs[0]->ip);
        $this->assertSame('MyBrowser/1.0', $EntitiesLogs[0]->user_agent);
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveExistingEntity(): void
    {
        $Article = $this->Articles->get(1);
        $this->Articles->saveOrFail($this->Articles->patchEntity($Article, ['title' => 'Edited article']));

        $EntitiesLogs = $this->getEntitiesLogs($Article);
        $this->assertCount(2, $EntitiesLogs);
        $this->assertSame(EntitiesLogType::Created, $EntitiesLogs[0]->type);
        $this->assertSame(EntitiesLogType::Updated, $EntitiesLogs[1]->type);
        $this->assertSame(1, $EntitiesLogs[1]->user_id);
    }

    /**
     * Tests that saving an entity that has not been modified does not create a log, because CakePHP does not even
     * dispatch the events.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityWithoutChanges(): void
    {
        $Article = $this->Articles->get(1);
        $this->Articles->saveOrFail($Article);

        $this->assertCount(1, $this->getEntitiesLogs($Article));
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterDelete()
     */
    #[Test]
    public function testDeleteEntity(): void
    {
        $Article = $this->Articles->get(1);
        $this->assertTrue($this->Articles->delete($Article));

        $EntitiesLogs = $this->getEntitiesLogs($Article);
        $this->assertCount(2, $EntitiesLogs);
        $this->assertSame(EntitiesLogType::Deleted, $EntitiesLogs[1]->type);
        $this->assertSame(1, $EntitiesLogs[1]->user_id);
    }

    /**
     * Tests that, when the logs use the same connection of the entity, the log is rolled back with the entity.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testLogIsRolledBackWithTheEntity(): void
    {
        $Article = $this->Articles->newEntity(['title' => 'Rolled back article']);

        // Returning `false` rolls the transaction back
        $this->Articles->getConnection()->transactional(function () use ($Article): bool {
            $this->Articles->saveOrFail($Article);

            return false;
        });

        $this->assertFalse($this->Articles->exists(['title' => 'Rolled back article']));
        $this->assertSame([], $this->getEntitiesLogs($Article));
    }

    /**
     * Tests that nothing is logged, and the entity is saved as usual, when there is no request (for example, in a
     * shell command or in a cronjob).
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityWithoutRequest(): void
    {
        Router::reload();

        $Article = $this->Articles->saveOrFail($this->Articles->newEntity(['title' => 'Article without request']));

        $this->assertTrue($this->Articles->exists(['title' => 'Article without request']));
        $this->assertSame([], $this->getEntitiesLogs($Article));
    }

    /**
     * Tests that the logs of an entity can be read through the `EntitiesLogs` association, oldest first.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testEntitiesLogsAssociation(): void
    {
        $Article = $this->Articles->get(1);
        $this->Articles->saveOrFail($this->Articles->patchEntity($Article, ['title' => 'Edited article']));

        /** @var \App\Model\Entity\Article $Article */
        $Article = $this->Articles->find()->contain(['EntitiesLogs'])->where(['Articles.id' => 1])->firstOrFail();

        $this->assertCount(2, $Article->entities_logs);
        $this->assertSame(EntitiesLogType::Created, $Article->entities_logs[0]->type);
        $this->assertSame(EntitiesLogType::Updated, $Article->entities_logs[1]->type);
    }
}
