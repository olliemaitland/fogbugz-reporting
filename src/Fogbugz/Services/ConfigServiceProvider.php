<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:52
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Services;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Fogbugz\Entities;

class ConfigServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['config'] = $app->share(function () use ($app) {
            return new \Fogbugz\Entities\Configuration($app);
        });
    }

    public function boot(Application $app)
    {
        // check the presence of the database here
        $sm = $app['db']->getSchemaManager();
        $doesExist = $sm->tablesExist("configuration");
        if (false == $doesExist) {
            // create it
            $schema = new \Doctrine\DBAL\Schema\Schema();
            $myTable = $schema->createTable("configuration");
            $myTable->addColumn("parameter", "string", array("length" => 32));
            $myTable->addColumn("value", "string", array("length" => 32));
            $myTable->setPrimaryKey(array("parameter"));

            $platform = $app['db']->getDatabasePlatform();

            // get the queries
            $queries = $schema->toSql($platform);

            foreach($queries as $query) {
                $app['monolog']->addInfo(sprintf ("Running: %s ", $query));
                $app['db']->query($query);
            }

            $app['monolog']->addInfo("Created configuration table");
        }
    }
}