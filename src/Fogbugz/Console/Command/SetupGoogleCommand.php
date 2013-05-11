<?php
/**
 * Sets up Google Service Account credentials for the app to be able to work with Google services
 *
 * @author  Yuriy Akopov
 * @date    2013-05-07
 */

namespace Fogbugz\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupGoogleCommand extends ByngCommand
{
    const
        // parameters set by user by calling this command
        GOOGLE_ACCOUNT_CLIENT_ID        = 'client-id',          // service account client ID
        GOOGLE_ACCOUNT_NAME             = 'account-name',       // service account name
        GOOGLE_ACCOUNT_KEY              = 'private-key',        // service account private key filename
        GOOGLE_ACCOUNT_SECRET           = 'key-secret',         // private key secret
        // parameters set later by app itself
        GOOGLE_TOKEN                    = 'google-oauth-token'  // local settings key for storing an authentication token
    ;

    // default path for Google private key(s)
    const DEFAULT_KEY_FOLDER    = 'resources/google';
    // default secret for Google private key(s)
    const DEFAULT_SECRET        = 'notasecret';

    protected function configure()
    {
        $this
            ->setName('setup:google')
            ->addArgument(self::GOOGLE_ACCOUNT_CLIENT_ID, InputArgument::REQUIRED, 'Google API service account client ID')
            ->addArgument(self::GOOGLE_ACCOUNT_NAME, InputArgument::REQUIRED, 'Google API service account account name')
            ->addArgument(self::GOOGLE_ACCOUNT_KEY, InputArgument::REQUIRED, 'Google API service account private key path/filename')
            // is optional, but cannot be empty - if omitted, will be replaced with the default value
            ->addArgument(self::GOOGLE_ACCOUNT_SECRET, InputArgument::OPTIONAL, 'Google API service account private key secret')
        ;
    }

    /**
     * Returns inbound command line parameters checking their validity and using default values where needed
     *
     * @param InputInterface $input
     *
     * @return array
     * @throws \Exception
     */
    protected function getArguments(InputInterface $input)
    {
        $args = parent::getArguments($input);

        if (strlen($args[self::GOOGLE_ACCOUNT_SECRET]) === 0) {
            // default secret for generate Google service account private keys
            $args[self::GOOGLE_ACCOUNT_SECRET] = self::DEFAULT_SECRET;
        }

        if (!file_exists($args[self::GOOGLE_ACCOUNT_KEY])) {
            // key cannot be found, try default location...
            $args[self::GOOGLE_ACCOUNT_KEY] = self::getAppFolder() . '/' . self::DEFAULT_KEY_FOLDER . '/' . $args[self::GOOGLE_ACCOUNT_KEY];
            // ...and try again
            if (!file_exists($args[self::GOOGLE_ACCOUNT_KEY])) {
                throw new \Exception('Private key cannot be found');
            }
        }

        return $args;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // save all the arguments
        $args = $this->getArguments($input);

        $configuration = $this->getConfig();
        $configuration->fromArray($args);
        $configuration->save();

        $output->writeln('<info>Google credentials configuration saved</info>');
    }
}