<?php
declare(strict_types=1);

use Cake\Datasource\ConnectionManager;
use Migrations\TestSuite\Migrator;

define('ROOT', dirname(__DIR__) . DS);
const CORE_PATH = ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS;
define('TMP', sys_get_temp_dir() . DS . 'cakephp-entities-logger' . DS);

// phpcs:disable
@mkdir(TMP, 0777, true);
// phpcs:enable

require dirname(__DIR__) . '/vendor/autoload.php';
require_once CORE_PATH . 'config' . DS . 'bootstrap.php';

ConnectionManager::setConfig('test', ['url' => 'sqlite:///' . TMP . 'test.sq3']);

$migrator = new Migrator();
$migrator->run();
