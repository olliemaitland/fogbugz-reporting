<?php
/**
 * Implements functionality shared by more than one command
 *
 * @author  Yuriy Akopov
 * @date    2013-05-07
 */
namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;

class ByngCommand extends Command
{
    /**
     * Returns application configuration object
     *
     * @return  \Fogbugz\Entities\Configuration
     * @throws  \Exception
     */
    public function getConfig()
    {
        // accessing stored configuration
        $app = $this->getApplication()->getSilexApplication();
        $config = $app['config'];

        if (!$config instanceof \Fogbugz\Entities\Configuration) {
            throw new \Exception('Configuration not found!');
        }

        return $config;
    }

    /**
     * @return string
     */
    public static function getAppFolder()
    {
        $appFolder = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        return $appFolder;
    }
}