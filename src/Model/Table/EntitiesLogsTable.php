<?php
declare(strict_types=1);

namespace Cake\EntitiesLogger\Model\Table;

use Cake\Database\Type\EnumType;
use Cake\EntitiesLogger\Model\Enum\EntitiesLogType;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EntitiesLogs Model
 *
 * @property \Cake\EntitiesLogger\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog newEmptyEntity()
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog newEntity(array $data, array $options = [])
 * @method array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> newEntities(array $data, array $options = [])
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Cake\EntitiesLogger\Model\Entity\EntitiesLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|\Cake\Datasource\ResultSetInterface<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|\Cake\Datasource\ResultSetInterface<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|\Cake\Datasource\ResultSetInterface<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Cake\EntitiesLogger\Model\Entity\EntitiesLog>|\Cake\Datasource\ResultSetInterface<\Cake\EntitiesLogger\Model\Entity\EntitiesLog> deleteManyOrFail(iterable $entities, array $options = [])
 */
class EntitiesLogsTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('entities_logs');
        $this->setDisplayField('entity_class');
        $this->setPrimaryKey('id');

        $this->getSchema()->setColumnType('type', EnumType::from(EntitiesLogType::class));

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
            'className' => 'Cake/EntitiesLogger.Users',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('entity_class')
            ->maxLength('entity_class', 255)
            ->requirePresence('entity_class', 'create')
            ->notEmptyString('entity_class');

        $validator
            ->integer('entity_id')
            ->requirePresence('entity_id', 'create')
            ->notEmptyString('entity_id');

        $validator
            ->integer('user_id')
            ->notEmptyString('user_id');

        $validator
            ->enum('type', EntitiesLogType::class)
            ->requirePresence('type', 'create')
            ->notEmptyString('type');

        $validator
            ->dateTime('datetime')
            ->requirePresence('datetime', 'create')
            ->notEmptyDateTime('datetime');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }
}
