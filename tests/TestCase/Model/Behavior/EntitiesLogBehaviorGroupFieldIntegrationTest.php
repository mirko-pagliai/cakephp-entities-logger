<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Test\TestCase\Model\Behavior;

use App\Model\Entity\Article;
use App\Model\Entity\User;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior;
use Cake\EntitiesLogger\Model\Entity\EntitiesLog;
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

/**
 * EntitiesLogBehaviorGroupFieldIntegrationTest.
 *
 * These tests save and delete real entities through a table that loads the behavior with the `groupField` option. The
 * articles are grouped by the `category_id` field, so the logs are identified by the category and not by the article.
 * The article with ID 1 (without a category) has a log (`Created`) in the `entities_logs` table, which is not related
 * to any category.
 */
#[CoversClass(EntitiesLogBehavior::class)]
class EntitiesLogBehaviorGroupFieldIntegrationTest extends TestCase
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
        $request = (new ServerRequest())
            ->withEnv('REMOTE_ADDR', '192.168.0.100')
            ->withEnv('HTTP_USER_AGENT', 'MyBrowser/1.0')
            ->withAttribute('identity', new User(['id' => 1]));
        Router::setRequest($request);

        // The updates are not merged, unless a test says otherwise
        $this->Articles = $this->fetchTable(
            alias: 'GroupedArticles',
            options: ['table' => 'articles', 'entityClass' => Article::class],
        );
        $this->Articles->addBehavior(
            name: 'Cake/EntitiesLogger.EntitiesLog',
            options: ['groupField' => 'category_id', 'mergeInterval' => 0],
        );

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
     * Internal method to save an article at the given date and time.
     *
     * @param string $datetime The date and time at which the article is saved.
     * @param array<string, mixed> $data The data of the article.
     * @param int|null $id The ID of the article to edit. If `null`, a new article is created.
     * @return \App\Model\Entity\Article
     */
    private function saveArticle(string $datetime, array $data, ?int $id = null): Article
    {
        DateTime::setTestNow(new DateTime($datetime));

        $Article = $id ? $this->Articles->get($id) : $this->Articles->newEmptyEntity();

        /** @var \App\Model\Entity\Article $SavedArticle */
        $SavedArticle = $this->Articles->saveOrFail($this->Articles->patchEntity($Article, $data));

        return $SavedArticle;
    }

    /**
     * Internal method to get the logs of a category, from the oldest to the newest.
     *
     * @param int $categoryId The ID of the category.
     * @return array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>
     */
    private function getEntitiesLogs(int $categoryId): array
    {
        /** @var array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> $EntitiesLogs */
        $EntitiesLogs = $this->EntitiesLogs->find()
            ->where(['entity_class' => Article::class, 'entity_id' => $categoryId])
            ->orderBy(['datetime' => 'ASC', 'id' => 'ASC'])
            ->all()
            ->toArray();

        return $EntitiesLogs;
    }

    /**
     * Tests that a new entity is logged as an update of its group, never as a creation.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveNewEntity(): void
    {
        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);

        $EntitiesLogs = $this->getEntitiesLogs(10);
        $this->assertCount(1, $EntitiesLogs);
        $this->assertSame(EntitiesLogType::Updated, $EntitiesLogs[0]->type);
        $this->assertSame(1, $EntitiesLogs[0]->user_id);
        $this->assertSame('192.168.0.100', $EntitiesLogs[0]->ip);
        $this->assertSame('MyBrowser/1.0', $EntitiesLogs[0]->user_agent);

        // The article is not logged by its own ID
        $this->assertSame([], $this->getEntitiesLogs($Article->id));
    }

    /**
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveExistingEntity(): void
    {
        $expected = [EntitiesLogType::Updated, EntitiesLogType::Updated];

        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);
        $this->saveArticle('2026-01-01 10:10:00', ['title' => 'Edited article'], $Article->id);

        $EntitiesLogs = $this->getEntitiesLogs(10);
        $result = array_column(array: $EntitiesLogs, column_key: 'type');
        $this->assertSame($expected, $result);
    }

    /**
     * Tests that, when an entity is moved to another group, both groups are modified, so both are logged.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityMovedToAnotherGroup(): void
    {
        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);
        $this->saveArticle('2026-01-01 10:10:00', ['category_id' => 20], $Article->id);

        $expected = [EntitiesLogType::Updated, EntitiesLogType::Updated];
        $result = array_column(array: $this->getEntitiesLogs(10), column_key: 'type');
        $this->assertSame($expected, $result);

        $expected = [EntitiesLogType::Updated];
        $result = array_column(array: $this->getEntitiesLogs(20), column_key: 'type');
        $this->assertSame($expected, $result);

        $expected = ['2026-01-01 10:00:00', '2026-01-01 10:10:00'];
        $result = array_map(
            callback: fn(EntitiesLog $EntitiesLog): string => $EntitiesLog->datetime->toDateTimeString(),
            array: $this->getEntitiesLogs(10),
        );
        $this->assertSame($expected, $result);
    }

    /**
     * Tests that, when an entity that had no group is moved to a group, only that group is logged.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityMovedFromNoGroup(): void
    {
        $this->saveArticle('2026-01-01 10:00:00', ['category_id' => 10], 1);

        $this->assertCount(1, $this->getEntitiesLogs(10));

        // The only other log is the one of the fixture
        $this->assertSame(2, $this->EntitiesLogs->find()->count());
    }

    /**
     * Tests that an entity cannot be removed from its group, because the log needs the group.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityWithoutGroup(): void
    {
        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('`' . Article::class . '::$category_id` is null, expected non-null value.');
        $this->saveArticle('2026-01-01 10:10:00', ['category_id' => null], $Article->id);
    }

    /**
     * Tests that nothing is logged, even for the previous group, when there is no request.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterSave()
     */
    #[Test]
    public function testSaveEntityWithoutRequest(): void
    {
        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);

        Router::reload();
        $this->saveArticle('2026-01-01 10:10:00', ['category_id' => 20], $Article->id);

        $this->assertCount(1, $this->getEntitiesLogs(10));
        $this->assertSame([], $this->getEntitiesLogs(20));
    }

    /**
     * Tests that deleting an entity is logged as an update of its group, never as a deletion.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::afterDelete()
     */
    #[Test]
    public function testDeleteEntity(): void
    {
        $Article = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'New article', 'category_id' => 10]);
        $this->Articles->deleteOrFail($this->Articles->get($Article->id));

        $expected = [EntitiesLogType::Updated, EntitiesLogType::Updated];
        $result = array_column(array: $this->getEntitiesLogs(10), column_key: 'type');
        $this->assertSame($expected, $result);
    }

    /**
     * Tests that the updates of all the entities of the same group are merged, within the `mergeInterval` option, and
     * that the previous group is merged as well when an entity is moved.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::saveEntitiesLog()
     */
    #[Test]
    public function testUpdatesOfTheSameGroupAreMerged(): void
    {
        $this->Articles->behaviors()->get('EntitiesLog')->setConfig('mergeInterval', 300);

        $First = $this->saveArticle('2026-01-01 10:00:00', ['title' => 'First article', 'category_id' => 10]);
        $this->saveArticle('2026-01-01 10:01:00', ['title' => 'Second article', 'category_id' => 10]);

        $EntitiesLogs = $this->getEntitiesLogs(10);
        $this->assertCount(1, $EntitiesLogs);
        $this->assertSame('2026-01-01 10:01:00', $EntitiesLogs[0]->datetime->toDateTimeString());

        $this->saveArticle('2026-01-01 10:02:00', ['category_id' => 20], $First->id);

        $EntitiesLogs = $this->getEntitiesLogs(10);
        $this->assertCount(1, $EntitiesLogs);
        $this->assertSame('2026-01-01 10:02:00', $EntitiesLogs[0]->datetime->toDateTimeString());

        $EntitiesLogs = $this->getEntitiesLogs(20);
        $this->assertCount(1, $EntitiesLogs);
        $this->assertSame('2026-01-01 10:02:00', $EntitiesLogs[0]->datetime->toDateTimeString());
    }

    /**
     * Tests that the `EntitiesLogs` association finds the logs of the group of each entity, not the ones identified by
     * its primary key.
     *
     * @link \Cake\EntitiesLogger\Model\Behavior\EntitiesLogBehavior::__construct()
     */
    #[Test]
    public function testEntitiesLogsAssociation(): void
    {
        $this->saveArticle('2026-01-01 10:00:00', ['title' => 'First article', 'category_id' => 10]);
        $this->saveArticle('2026-01-01 10:10:00', ['title' => 'Second article', 'category_id' => 10]);
        $this->saveArticle('2026-01-01 10:20:00', ['title' => 'Third article', 'category_id' => 20]);

        /** @var array<\App\Model\Entity\Article> $Articles */
        $Articles = $this->Articles->find()
            ->contain(['EntitiesLogs'])
            ->where(['GroupedArticles.category_id IN' => [10, 20]])
            ->orderBy(['GroupedArticles.id' => 'ASC'])
            ->all()
            ->toArray();

        $expected = [10, 10, 20];
        $result = array_column(array: $Articles, column_key: 'category_id');
        $this->assertSame($expected, $result);

        $expected = [2, 2, 1];
        $result = array_map(callback: fn(Article $Article): int => count($Article->entities_logs), array: $Articles);
        $this->assertSame($expected, $result);
    }
}
