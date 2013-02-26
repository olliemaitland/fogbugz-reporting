<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 14:38
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PullWorklogsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('pull:worklogs')
            ->addArgument('start-date', InputArgument::REQUIRED, 'Worklog start date')
            ->addArgument('end-date', InputArgument::REQUIRED, 'Worklog end date');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //
        $app = $this->getApplication()->getSilexApplication();
        $client = new \Fogbugz\Api\Client($app['config']);

        // get worklogs
        $start  = \DateTime::createFromFormat("Y-m-d", $input->getArgument("start-date"));

        if ($input->getArgument("end-date")) {
            $end    = \DateTime::createFromFormat("Y-m-d", $input->getArgument("end-date"));
        } else {
            $end    = new \DateTime();
        }

        $intervals = $client->getWorklogs($start, $end);

        $i = 0;
        $file = sprintf("/var/www/fogbugz-hours_%s_%s.csv", $start->format("Y-m-d"), $end->format("Y-m-d"));
        $fp = fopen($file, 'w');
        foreach ($intervals as $interval) {

            // get project for each case
            $project = $client->getProjectFromCase($interval->case);
            $person  = $client->getPersonFromCase($interval->person);

            fputcsv($fp, array (
                $project,
                date("Y-m-d", strtotime($interval->start)),
                $interval->getDurationHours(),
                $person
            ));

            $i++;
        }

        fclose($fp);

        $output->writeln(sprintf('<info>Found %s cases</info>', $i));
    }
}