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
    protected function configure()
    {
        $this
            ->setName('setup:fogbugz')
            ->addArgument('client-id', InputArgument::REQUIRED, 'Fogbugz API end point URL')
            ->addArgument('fogbugz-email', InputArgument::REQUIRED, 'Fogbugz API email')
            ->addArgument('fogbugz-password', InputArgument::REQUIRED, 'Fogbugz API password');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // save all the arguments
        $args = $input->getArguments();
        unset($args['command']);

        $configuration = $this->getConfig();
        /* @var \Fogbugz\Entities\Configuration $configuration */
        $configuration->fromArray($args);
        $configuration->save();

        $output->writeln('<info>Configuration saved</info>');
    }
}