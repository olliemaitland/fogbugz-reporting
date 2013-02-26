<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:16
 * To change this template use File | Settings | File Templates.
 */


$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register(new Silex\Provider\SwiftmailerServiceProvider());
$app->register(new Fogbugz\Services\ConfigServiceProvider());

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../resources/logs/development.log',
));

return $app;