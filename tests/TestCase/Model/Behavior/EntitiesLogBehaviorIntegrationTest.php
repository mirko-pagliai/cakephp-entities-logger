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
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

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
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        DateTime::setTestNow();
    }

    /**
     * Internal method to edit the article with ID 1 at the given date and time.
     *
     * @param string $datetime The date and time at which the article is edited.
     * @param string $title The new title of the article.
     * @return void
     */
    private function editArticle(string $datetime, string $title): void
    {
        DateTime::setTestNow(new DateTime($datetime));

        $Article = $this->Articles->get(1);
        $this->Articles->saveOrFail($this->Articles->patchEntity($Article, compact('title')));
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

    /**
     * Tests that the updates made within the default `mergeInterval` option (300 seconds, inclusive) are merged into a
     * single log, which has the date and time of the last update. The `Created` log is never merged.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    #[TestWith([2, '2026-01-01 10:04:59'])]
    #[TestWith([2, '2026-01-01 10:05:00'])]
    #[TestWith([3, '2026-01-01 10:05:01'])]
    public function testUpdateMergesCloseUpdates(int $expectedCount, string $secondUpdateAt): void
    {
        $this->editArticle('2026-01-01 10:00:00', 'First edit');
        $this->editArticle($secondUpdateAt, 'Second edit');

        $EntitiesLogs = $this->getEntitiesLogs($this->Articles->get(1));
        $this->assertCount($expectedCount, $EntitiesLogs);
        $this->assertSame(EntitiesLogType::Created, $EntitiesLogs[0]->type);
        $this->assertSame(EntitiesLogType::Updated, $EntitiesLogs[$expectedCount - 1]->type);
        $this->assertSame($secondUpdateAt, $EntitiesLogs[$expectedCount - 1]->datetime->format('Y-m-d H:i:s'));
    }

    /**
     * Tests that, when the updates are merged, the existing log is updated and no other log is written.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testUpdateMergesIntoTheSameLog(): void
    {
        $this->editArticle('2026-01-01 10:00:00', 'First edit');
        $FirstUpdatedLog = $this->getEntitiesLogs($this->Articles->get(1))[1];

        $this->editArticle('2026-01-01 10:01:00', 'Second edit');
        $EntitiesLogs = $this->getEntitiesLogs($this->Articles->get(1));

        $this->assertCount(2, $EntitiesLogs);
        $this->assertSame($FirstUpdatedLog->id, $EntitiesLogs[1]->id);
    }

    /**
     * Tests that the updates are not merged if the user, the IP address or the user agent are not the same.
     *
     * @param array<string, string> $environment Environment of the second request.
     * @param int $userId ID of the user of the second request.
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    #[TestWith([['REMOTE_ADDR' => '192.168.0.100', 'HTTP_USER_AGENT' => 'MyBrowser/1.0'], 2])]
    #[TestWith([['REMOTE_ADDR' => '192.168.0.101', 'HTTP_USER_AGENT' => 'MyBrowser/1.0'], 1])]
    #[TestWith([['REMOTE_ADDR' => '192.168.0.100', 'HTTP_USER_AGENT' => 'AnotherBrowser/2.0'], 1])]
    public function testUpdateDoesNotMergeWithDifferentUserIpOrUserAgent(array $environment, int $userId): void
    {
        $this->editArticle('2026-01-01 10:00:00', 'First edit');

        $request = new ServerRequest(compact('environment'));
        Router::setRequest($request->withAttribute('identity', new User(['id' => $userId])));
        $this->editArticle('2026-01-01 10:01:00', 'Second edit');

        $this->assertCount(3, $this->getEntitiesLogs($this->Articles->get(1)));
    }

    /**
     * Tests that only the last log is considered: if another user has updated the entity in the meantime, the update
     * is not merged with the previous one of the first user, so that the history is kept.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testUpdateDoesNotMergeIfTheLastLogIsOfAnotherUser(): void
    {
        $this->editArticle('2026-01-01 10:00:00', 'First edit');

        $request = new ServerRequest(['environment' => ['REMOTE_ADDR' => '192.168.0.100', 'HTTP_USER_AGENT' => 'MyBrowser/1.0']]);
        Router::setRequest($request->withAttribute('identity', new User(['id' => 2])));
        $this->editArticle('2026-01-01 10:01:00', 'Second edit');

        Router::setRequest($request->withAttribute('identity', new User(['id' => 1])));
        $this->editArticle('2026-01-01 10:02:00', 'Third edit');

        $EntitiesLogs = $this->getEntitiesLogs($this->Articles->get(1));
        $this->assertSame([1, 1, 2, 1], array_column($EntitiesLogs, 'user_id'));
    }

    /**
     * Tests that, with the `mergeInterval` option set to `0`, the updates are never merged.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testUpdateWithMergeIntervalDisabled(): void
    {
        $this->Articles->behaviors()->get('EntitiesLog')->setConfig('mergeInterval', 0);

        $this->editArticle('2026-01-01 10:00:00', 'First edit');
        $this->editArticle('2026-01-01 10:00:01', 'Second edit');

        $this->assertCount(3, $this->getEntitiesLogs($this->Articles->get(1)));
    }

    /**
     * Tests that the `Created` and `Deleted` logs are never merged, even within the `mergeInterval` option.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testCreatedAndDeletedLogsAreNeverMerged(): void
    {
        DateTime::setTestNow(new DateTime('2026-01-01 10:00:00'));

        $Article = $this->Articles->saveOrFail($this->Articles->newEntity(['title' => 'New article']));
        $this->Articles->saveOrFail($this->Articles->patchEntity($Article, ['title' => 'Edited article']));
        $this->Articles->deleteOrFail($Article);

        $EntitiesLogs = $this->getEntitiesLogs($Article);
        $this->assertSame(
            [EntitiesLogType::Created, EntitiesLogType::Updated, EntitiesLogType::Deleted],
            array_column($EntitiesLogs, 'type'),
        );
    }
}
