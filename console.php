<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new Fogbugz\Application();

$app['debug'] = true;

require __DIR__.'/resources/config/prod.php';
require __DIR__.'/src/app.php';

use Knp\Provider\ConsoleServiceProvider;

$app->register(new ConsoleServiceProvider(), array(
    'console.name'              => 'Fogbugz Reporting',
    'console.version'           => '1.0.0',
    'console.project_directory' => __DIR__.'/..'
));


$application = $app['console'];
$application->add(new Fogbugz\Console\Command\SetupFogbugzCommand());
$application->add(new Fogbugz\Console\Command\PullWorklogsCommand());
$application->add(new Fogbugz\Console\Command\PushWorklogsCommand());
$application->run();

$app->run();