<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:12
 * To change this template use File | Settings | File Templates.
 */

// Cache
$app['cache.path'] = __DIR__ . '/../cache';

$app['db.options'] = array(
    'driver'   => 'pdo_sqlite',
    'path'     => sys_get_temp_dir().'/fogbugz-reporting.db',
);

$app['swiftmailer.options'] = array(
    'host' => 'mail.smtp.com',
    'port' => '25',
    'username' => '',
    'password' => '',
    'encryption' => null,
    'auth_mode' => null
);