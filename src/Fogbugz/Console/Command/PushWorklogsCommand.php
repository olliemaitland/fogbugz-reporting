<?php
/**
 * Implements command that pushes data from a selected CSV pulled from Fogbugz to Google Fusion table
 *
 * @author  Yuriy Akopov
 * @date    2013-05-01
 */
namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushWorklogsCommand extends ByngCommand
{
    // name of the application in Google stats etc.
    const GOOGLE_APP_NAME = 'Byng FogBugz Reporting Tool';

    const TABLE_ID = '1lQiu9hX7Ki4UZf0uA1X3ZBTyvHsWfBRWQ0UPJAk';

    protected function configure()
    {
        $this
            ->setName('push:worklogs')
        ;
    }

    /**
     * Checks settings storage for Google OAuth credentials and returns them as an array or throws an exception otherwise
     *
     * @return  array
     * @throws  \Exception
     */
    public function getGoogleOauthCredentials()
    {
        $settings = array(
            SetupGoogleCommand::GOOGLE_ACCOUNT_CLIENT_ID,
            SetupGoogleCommand::GOOGLE_ACCOUNT_NAME,
            SetupGoogleCommand::GOOGLE_ACCOUNT_KEY,
            SetupGoogleCommand::GOOGLE_ACCOUNT_SECRET
        );

        $config = $this->getConfig();
        $oauth  = array();

        foreach ($settings as $param) {
            try {
                $oauth[$param] = $config->get($param);

            } catch (\Exception $e) {
                throw new \Exception('Unable to read Google credentials, please initialise with setup:google command first');
            }
        }

        return $oauth;
    }

    /**
     * Authenticates with a previously saved token or first-time authentication information
     * Returns ready to use Fusion Table API client
     *
     * @return \Google_FusiontablesService
     */
    protected function getFusionTablesService()
    {
        // require_once $appFolder . '/vendor/google-api-php-client/src/Google_Client.php';
        // require_once $appFolder . '/vendor/google-api-php-client/src/contrib/Google_FusiontablesService.php';

        $oauth = $this->getGoogleOauthCredentials();

        $client = new \Google_Client();
        $client->setClientId($oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_CLIENT_ID]);
        $client->setApplicationName(self::GOOGLE_APP_NAME);

        $client->setAssertionCredentials(new \Google_AssertionCredentials(
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_NAME],
            array('https://www.googleapis.com/auth/fusiontables'),
            file_get_contents($oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_KEY]),
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_SECRET]
        ));

        // accessing stored configuration
        $config = $this->getConfig();

        $updateToken = false;
        try {
            // attempting to retrieve an existing token
            $token = $config->get(SetupGoogleCommand::GOOGLE_TOKEN_KEY);
            $client->setAccessToken($token);

        } catch (\Exception $e) {
            // failed to read token
            $updateToken = true;
        }

        // refresh OAuth token if no token, invalid or expired one
        if ($updateToken or $client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            // store new token in app configuration
            $config->set(SetupGoogleCommand::GOOGLE_TOKEN_KEY, $token);
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
        exit;

        // there is apparently no way to import File via PHP API, method appears incomplete...
        // $importResult = $service->table->importRows(self::TABLE_ID, array('isStrict'  => true));
        //
        // ...so uploading via SQL then
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