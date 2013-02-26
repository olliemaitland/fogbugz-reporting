<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:16
 * To change this template use File | Settings | File Templates.
 */


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/', function() {
    return ('Lighthouse to Fogbugz integration.');
});

$app->get('/api/{name}', function ($name) use ($app) {
    switch ($app->escape($name)) {
        case "pull":
            return 'Hello '.$app->escape($name);
            break;
        default:
            return 'Nothing';
    }
    return 'Nothing';
});

return $app;