<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:39
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupFogbugzCommand extends ByngCommand
{
    const
        FOGBUGZ_URL         = 'fogbugz-url',
        FOGBUGZ_EMAIL       = 'fogbugz-email',
        FOGBUGZ_PASSWORD    = 'fogbugz-password',
        FOGBUGZ_TOKEN       = 'fogbugz-token'
    ;

    protected function configure()
    {
        $this
            ->setName('setup:fogbugz')
            ->addArgument(self::FOGBUGZ_URL, InputArgument::REQUIRED, 'Fogbugz API end point URL')
            ->addArgument(self::FOGBUGZ_EMAIL, InputArgument::REQUIRED, 'Fogbugz API email')
            ->addArgument(self::FOGBUGZ_PASSWORD, InputArgument::REQUIRED, 'Fogbugz API password');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $this->getArguments($input);
        $configuration = $this->getConfig();

        // save all the arguments
        $configuration->fromArray($args);
        $configuration->save();

        $output->writeln('<info>FogBugz credentials configuration saved</info>');
    }
}