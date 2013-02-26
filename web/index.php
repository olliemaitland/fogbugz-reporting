<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 08/02/13
 * Time: 16:25
 * To change this template use File | Settings | File Templates.
 */

// web/index.php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Fogbugz\Application();

$app['debug'] = true;

require __DIR__.'/../resources/config/prod.php';
require __DIR__.'/../src/app.php';

require __DIR__.'/../src/controllers.php';


$app->run();