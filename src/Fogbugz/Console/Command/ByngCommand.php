<?php
/**
 * Implements functionality shared by more than one command
 *
 * @author  Yuriy Akopov
 * @date    2013-05-07
 */
namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class ByngCommand extends Command
{
    /**
     * Returns application configuration object
     *
     * @return  \Fogbugz\Entities\Configuration
     * @throws  \Exception
     */
    protected function getConfig()
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
     * Returns absolute path to application deployment folder
     *
     * @return string
     */
    protected static function getAppFolder()
    {
        $appFolder = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        return $appFolder;
    }

    /**
     * Returns somewhat cleansed command line arguments
     *
     * @param   InputInterface  $input
     *
     * @return  array
     */
    protected function getArguments(InputInterface $input)
    {
        $args = $input->getArguments();
        unset($args['command']);

        return $args;
    }
}