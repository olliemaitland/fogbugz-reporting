<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 17:42
 * To change this template use File | Settings | File Templates.
 */

class FogbugzClientTest extends PHPUnit_Framework_TestCase
{

    protected function initialise()
    {

        require_once __DIR__.'/../vendor/autoload.php';

        $app = new Fogbugz\Application();

        $app['debug'] = true;

        require __DIR__.'/../resources/config/prod.php';
        require __DIR__.'/../src/app.php';

        return $app;
    }

    public function testGetWorklogs()
    {

        $app = $this->initialise();

        $config = \Fogbugz\Entities\Configuration($app);

        $client = new \Fogbugz\Api\FogbugzClient($config);

        $client->getWorklogs();
    }
}