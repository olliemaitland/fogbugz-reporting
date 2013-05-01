<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Yuriy Akopov
 * Date: 01/05/13
 * Time: 18:41
 * To change this template use File | Settings | File Templates.
 */
namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushWorklogsCommand extends Command
{
    const GOOGLE_CLIENT_TOKEN_KEY = 'googleClientToken';

    const TABLE_ID = '1lQiu9hX7Ki4UZf0uA1X3ZBTyvHsWfBRWQ0UPJAk';

    protected function configure()
    {
        $this
            ->setName('push:worklogs')
        //    ->addArgument('start-date', InputArgument::REQUIRED, 'Worklog start date')
        //    ->addArgument('end-date', InputArgument::REQUIRED, 'Worklog end date')
        ;
    }

    /**
     * Authenticates with a previously saved token or first-time authentication information
     * Returns ready to use Fusion Table API client
     *
     * @return \Google_FusiontablesService
     */
    protected function getFusionTablesService()
    {
        // @todo: should be included via composer's autoloader
        $appFolder = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once $appFolder . '/vendor/google-api-php-client/src/Google_Client.php';
        require_once $appFolder . '/vendor/google-api-php-client/src/contrib/Google_FusiontablesService.php';

        $client = new \Google_Client();

        // @todo: should be defined in SQLlite config or prod.php
        $oauth = array(
            'app_name'      => 'Byng FogBugz Reporting',
            'client_id'     => '560734462816-ln6javu29l7b2uhtrcsj2fr9e4l4v985.apps.googleusercontent.com',
            'account_name'  => '560734462816-ln6javu29l7b2uhtrcsj2fr9e4l4v985@developer.gserviceaccount.com',
            'private_key'   => $appFolder . '/resources/google/oauth-privatekey.p12',
            'key_secret'    => 'notasecret'
        );

        $client->setClientId($oauth['client_id']);
        $client->setApplicationName($oauth['app_name']);
        $client->setAssertionCredentials(new \Google_AssertionCredentials(
            $oauth['account_name'],
            array('https://www.googleapis.com/auth/fusiontables'),
            file_get_contents($oauth['private_key']),
            $oauth['key_secret']
        ));

        // accessing stored configuration
        $app = $this->getApplication()->getSilexApplication();
        /* @var \Fogbugz\Entities\Configuration $config */
        $config = $app['config'];

        // attempting to retrieve an existing token
        $updateToken = false;
        try {
            $token = $config->get(self::GOOGLE_CLIENT_TOKEN_KEY);
            $client->setAccessToken($token);

        } catch (\Exception $e) {
            // failed to read token
            $updateToken = true;
        }

        // refresh OAuth token if no token, invalid or expired one
        if ($updateToken or $client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            $config->set(self::GOOGLE_CLIENT_TOKEN_KEY, $token);
            $config->save();
        }

        // connect to Fusion Tables service
        $service = new \Google_FusiontablesService($client);
        return $service;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $service = $this->getFusionTablesService();

        // @todo: need to come as a parameter or be piped with pull:workflogs command
        $csvPath = '/var/www/fogbugz-hours_2013-01-01_2013-01-31.csv';
        $csvFile = fopen($csvPath, 'r');

        // there is apparently no way to import File via PHP API, method appears incomplete...
        // $importResult = $service->table->importRows(self::TABLE_ID, array('isStrict'  => true));
        //
        // ...so uploading via SQL then
        function runQueries(\Google_FusiontablesService $service, array $queries) {
            $sqlStr = implode(';', $queries);
            $insertResult = $service->query->sql($sqlStr);
            sleep(1);   // API rate is nasty

            if (count($insertResult['rows']) !== count($queries)) {
                throw \Exception('Failed to insert rows into Fusion Table');
            }

            return count($queries);
        };

        $rowCount = 0;
        $sql = array();
        while (($record = fgetcsv($csvFile)) !== false) {
            $sql[] = 'INSERT INTO ' . self::TABLE_ID . ' (Project, Date, Hours, Person) VALUES (' .
                // can't find dedicated Fusion Table escaping in the API
                "'" . addslashes($record[0]) . "'," .
                "'" . addslashes($record[1]) . "'," .
                "'" . addslashes($record[2]) . "'," .
                "'" . addslashes($record[3]) . "'" .
                ')'
            ;

            if (count($sql) >= 5) {
                $rowCount += runQueries($service, $sql);
                $sql = array();
            }
        };

        if (!empty($sql)) {
            $rowCount += runQueries($service, $sql);
        }

        $output->writeln(sprintf('<info>%s records pushed to Fusion Tables</info>', $rowCount));
    }
}