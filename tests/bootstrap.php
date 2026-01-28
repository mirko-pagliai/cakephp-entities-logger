<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Migrations\TestSuite\Migrator;

define('ROOT', dirname(__DIR__) . DS);
const CORE_PATH = ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS;
const APP_DIR = 'test_app' . DS;
const TESTS = ROOT . DS . 'tests' . DS;
define('TMP', sys_get_temp_dir() . DS . 'cakephp-entities-logger' . DS);

// phpcs:disable
@mkdir(TMP, 0777, true);
// phpcs:enable

require dirname(__DIR__) . '/vendor/autoload.php';
require_once CORE_PATH . 'config' . DS . 'bootstrap.php';

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => APP_DIR,
    'fullBaseUrl' => 'http://localhost',
]);

Cache::setConfig([
    '_cake_translations_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
    ],
]);

ConnectionManager::setConfig('test', ['url' => 'sqlite:///' . TMP . 'test.sq3']);

$loader = new SchemaLoader();
/** @link tests/schema.php */
$loader->loadInternalFile(TESTS . 'schema.php');

$migrator = new Migrator();
$migrator->run(['skip' => ['users']]);
