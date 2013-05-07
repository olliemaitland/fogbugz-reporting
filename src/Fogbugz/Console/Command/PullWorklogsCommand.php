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

class PullWorklogsCommand extends ByngCommand
{
    const
        DATE_START  = 'start-date',
        DATE_END    = 'end-date',
        CSV_FOLDER  = 'csv-folder'
    ;

    const DEFAULT_DATE_FORMAT = 'Y-m-d';

    const DEFAULT_FOLDER = 'resources/csvs';

    protected function configure()
    {
        $this
            ->setName('pull:worklogs')
            ->addArgument(self::DATE_START, InputArgument::REQUIRED, 'Worklog start date')
            ->addArgument(self::DATE_END, InputArgument::OPTIONAL, 'Worklog end date - current date if omitted')
            ->addArgument(self::CSV_FOLDER, InputArgument::OPTIONAL, 'Folder to output CSV to - system temp folder if omitted')
        ;
    }

    /**
     * Returns inbound command line parameters checking their validity and using default values where needed
     *
     * @param   InputInterface  $input
     *
     * @return  array
     * @throws  \Exception
     */
    protected function getArguments(InputInterface $input)
    {
        $args = parent::getArguments($input);

        if (strlen($args[self::CSV_FOLDER]) === 0) {
            $args[self::CSV_FOLDER] = self::getAppFolder() . '/' . self::DEFAULT_FOLDER;
        }

        if (!is_dir($args[self::CSV_FOLDER]) or !is_writable($args[self::CSV_FOLDER])) {
            throw new \Exception('Unable to access folder to store CSV files in');
        }

        return $args;
    }

    /**
     * Builds and returns filename to CSV to store export results in
     *
     * @param   \DateTime   $start
     * @param   \DateTime   $end
     *
     * @return  string
     */
    protected function getCsvFilename(\DateTime $start, \DateTime $end)
    {
        $filename = sprintf('fogbugz-hours_%s_%s.csv', $start->format(self::DEFAULT_DATE_FORMAT), $end->format(self::DEFAULT_DATE_FORMAT));
        return $filename;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $this->getArguments($input);

        $config = $this->getConfig();
        $client = new \Fogbugz\Api\Client($config);

        // get worklogs
        $start  = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $args[self::DATE_START]);

        if ($args[self::DATE_END]) {
            $end = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $args[self::DATE_END]);
        } else {
            $end = new \DateTime();
        }

        $intervals = $client->getWorklogs($start, $end);    // @todo: this is dangerous as big interval would hit memory limit
        $csvPath = $args[self::CSV_FOLDER] . '/' . $this->getCsvFilename($start, $end);

        $i = 0;
        $fp = fopen($csvPath, 'w');
        foreach ($intervals as $interval) {
            // get project for each case
            $project = $client->getProjectFromCase($interval->case);
            $person  = $client->getPersonFromCase($interval->person);

            fputcsv($fp, array (
                $project,
                date(self::DEFAULT_DATE_FORMAT, strtotime($interval->start)),
                $interval->getDurationHours(),
                $person
            ));

            $i++;
        }

        fclose($fp);

        $output->writeln(sprintf('<info>Found %s cases</info>', $i));
    }
}