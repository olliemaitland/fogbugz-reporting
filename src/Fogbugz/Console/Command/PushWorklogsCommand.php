<?php
/**
 * Implements command that pushes data from a selected CSV pulled from Fogbugz to Google Fusion table
 *
 * @author  Yuriy Akopov
 * @date    2013-05-01
 */
namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Fogbugz\Api;

class PushWorklogsCommand extends ByngCommand
{
    // path to to default certificate from http://curl.haxx.se/docs/caextract.html to run HTTPS requests without warnings
    const CURL_CERT_PATH = 'resources/cacert.pem';

    // command parameters
    const
        CSV_PATH    = 'csv-path',   // path to CSV file to be uploaded
        TABLE_ID    = 'table-id'    // Google Fusion Table ID
    ;

    /**
     * Holds path to CURL certification originally defined in php.ini to restore it back if needed
     *
     * @var null|string
     */
    protected static $oldCertPath = null;

    protected function configure()
    {
        $this
            ->setName('push:worklogs')
            ->addArgument(self::CSV_PATH, InputArgument::REQUIRED, 'Path to CSV generated with pull:worklogs command to be uploaded')
            ->addArgument(self::TABLE_ID, InputArgument::REQUIRED, 'Google Fusion table ID to load data into')
        ;
    }

    /**
     * Checks settings storage for Google OAuth credentials and returns them as an array or throws an exception otherwise
     *
     * @return  array
     * @throws  \Exception
     */
    public function getCredentials()
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

        // reading private key content
        $privateKey = file_get_contents($oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_KEY]);
        if ($privateKey === false) {
            throw new \Exception('Private key cannot be found');
        }
        $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_KEY] = $privateKey;

        // retrieving token - unlike other parameters about, it's okay to not have it stored
        try {
            $token = $config->get(SetupGoogleCommand::GOOGLE_TOKEN);
        } catch (\Exception $e) {
            $token = null;
        }
        $oauth[SetupGoogleCommand::GOOGLE_TOKEN] = $token;

        return $oauth;
    }

    /**
     * Authenticates with a previously saved token or first-time authentication information
     * Returns ready to use Fusion Table API client
     *
     * @return  Api\GoogleClient
     * @throws  \Exception
     */
    protected function getGoogleClient()
    {
        $oauth = $this->getCredentials();

        $client = new Api\GoogleClient(
            $oauth[SetupGoogleCommand::GOOGLE_TOKEN],
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_CLIENT_ID],
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_NAME],
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_KEY],
            $oauth[SetupGoogleCommand::GOOGLE_ACCOUNT_SECRET]
        );

        $newToken = $client->getOauthToken();
        if ($newToken !== $oauth[SetupGoogleCommand::GOOGLE_TOKEN]) {
            // store new token in app configuration
            $config = $this->getConfig();
            $config->set(SetupGoogleCommand::GOOGLE_TOKEN, $newToken);
            $config->save();
        }

        return $client;
    }

    /**
     * @param InputInterface $input
     *
     * @return  array
     * @throws  \Exception
     */
    protected function getArguments(InputInterface $input)
    {
        $args = parent::getArguments($input);

        if (!file_exists($args[self::CSV_PATH])) {
            // look in the default folder...
            $args[self::CSV_PATH] = PullWorklogsCommand::DEFAULT_FOLDER . '/' . $args[self::CSV_PATH];
            // ... and try again
            if (!file_exists($args[self::CSV_PATH])) {
                throw new \Exception('CSV file to be uploaded cannot be found');
            }
        }

        // check table ID to prevent "SQL" injection
        // sample id 1X4PBLYxV_msgowgDMMWpO_SULzbeJ5mjKGy2Ccw
        if (!preg_match('/^[0-9a-zA-Z_]+$/', $args[self::TABLE_ID])) {
            throw new \Exception('Fusion table ID seems to be invalid');
        }

        return $args;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $this->getArguments($input);
        $csvHandle = fopen($args[self::CSV_PATH], 'r'); // not checking result here as getArguments() is safe

        $client = $this->getGoogleClient();
        $rowCount = $client->csvToTable($csvHandle, $args[self::TABLE_ID]);
        $output->writeln(sprintf('<info>%s records pushed to Fusion Tables</info>', $rowCount));
    }
}