<?php
/**
 * Encapsulates operations that the application needs from the variety of Google API (Fusion Tables)
 *
 * @author  Yuriy Akopov
 * @date    2013-05-11
 */

namespace Fogbugz\Api;

class GoogleClient
{
    // name of the application in Google stats etc.
    const GOOGLE_APP_NAME = 'Byng FogBugz Reporting Tool';

    // table columns
    const
        TABLE_COL_PROJECT   = 'Project',
        TABLE_COL_DAY       = 'Day',
        TABLE_COL_HOURS     = 'Hours',
        TABLE_COL_PERSON    = 'Person'
    ;

    /**
     * Fusion Tables API client
     *
     * @var \Google_FusiontablesService|null
     */
    protected $fusionTables = null;

    /**
     * Actual OAuth token used to authenticate with Google API
     *
     * @var string|null
     */
    protected $oauthToken = null;

    public function getFusionTables()
    {
        return $this->fusionTables;
    }

    /**
     * @return null|string
     */
    public function getOauthToken()
    {
        return $this->oauthToken;
    }

    /**
     * Authenticates with Google and initialises the service object
     *
     * @param   string|null $token          null if no token from the previous sessions
     * @param   string      $clientId
     * @param   string      $accountName
     * @param   string      $privateKey
     * @param   string      $keySecret
     */
    public function __construct($token, $clientId, $accountName, $privateKey, $keySecret)
    {
        $client = new \Google_Client();
        $client->setClientId($clientId);
        $client->setApplicationName(self::GOOGLE_APP_NAME);

        $client->setAssertionCredentials(new \Google_AssertionCredentials(
            $accountName,
            array('https://www.googleapis.com/auth/fusiontables'),
            $privateKey,
            $keySecret
        ));

        $updateToken = true;
        if (!is_null($token)) {
            $client->setAccessToken($token);
            $updateToken = $client->getAuth()->isAccessTokenExpired();
        }

        if ($updateToken) {
            $client->getAuth()->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
        }

        // remember new token so caller can update its storage
        $this->oauthToken = $token;

        // connect to Fusion Tables service
        $this->fusionTables = new \Google_FusiontablesService($client);
    }

    /**
     * Executes a given array of insert queries
     *
     * @param   array   $queries
     *
     * @return  int
     */
    protected function runInsertQueries(array $queries)
    {
        $sqlStr = implode(';', $queries);
        $insertResult = $this->getFusionTables()->query->sql($sqlStr);

        sleep(1);   // API rate is nasty

        if (count($insertResult['rows']) !== count($queries)) {
            throw new \Exception('Failed to insert rows into Fusion Table');
        }

        return count($queries);

    }

    /**
     * Returns INSERT statement to add the CSV row given into the specified Fusion table
     *
     * @param   string  $tableId
     * @param   array   $csvRow
     *
     * @return  string
     */
    protected function getInsertStatement($tableId, array $csvRow) {
        // escape values inserted
        // table ID is not escape as it is supposed to be obtained in a safe way
        foreach ($csvRow as $key => $value) {
            $csvRow[$key] = addslashes($value);
        }

        $sql =
            'INSERT INTO ' . $tableId . ' (' . implode(',', array(
                self::TABLE_COL_PROJECT,
                self::TABLE_COL_DAY,
                self::TABLE_COL_HOURS,
                self::TABLE_COL_PERSON
            )) .
                ") VALUES ('" . implode("','", $csvRow) . "')"
        ;

        return $sql;
    }

    /**
     * Inserts data from the specified CSV into a Fusion table
     *
     * @param   resource    $csvFile
     * @param   string      $tableId
     *
     * @return  int
     */
    public function csvToTable($csvFile, $tableId)
    {
        // there is apparently no way to import File via PHP API, method appears incomplete...
        // $importResult = $service->table->importRows(self::TABLE_ID, array('isStrict'  => true));
        //
        // ...so uploading via SQL then

        $rowCount = 0;
        $sql = array();
        $queriesAtOnce = 5;

        while (($record = fgetcsv($csvFile)) !== false) {
            $sql[] = $this->getInsertStatement($tableId, $record);

            if (count($sql) >= $queriesAtOnce) {
                $rowCount += $this->runInsertQueries($sql);
                $sql = array();
            }
        };

        if (!empty($sql)) {
            $rowCount += $this->runInsertQueries($sql);
        }

        return $rowCount;
    }
}