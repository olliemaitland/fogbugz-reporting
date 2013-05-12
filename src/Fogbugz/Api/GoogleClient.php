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

    protected static $columns = array(
        self::TABLE_COL_PROJECT,
        self::TABLE_COL_DAY,
        self::TABLE_COL_HOURS,
        self::TABLE_COL_PERSON
    );

    /**
     * @var null|\Google_Client
     */
    protected $client = null;

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

    /**
     * @return \Google_Client|null
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return \Google_FusiontablesService|null
     */
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

        $this->client = $client;
        $this->fusionTables = new \Google_FusiontablesService($client);
    }

    /**
     * Executes a given array of insert queries
     *
     * @param   array   $queries
     *
     * @return  int
     * @throws  \Exception
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
     * @throws  \Exception
     */
    protected function getInsertStatement($tableId, array $csvRow)
    {
        if (count($csvRow) !== count(self::$columns)) {
            throw new \Exception('Wrong number of fields in the record');
        }

        // @todo: $tableId should be sanitised as well

        // escape values to be inserted
        // table ID is not escape as it is supposed to be obtained in a safe way
        foreach ($csvRow as $key => $value) {
            $csvRow[$key] = addslashes($value);
        }

        $sql =
            'INSERT INTO ' . $tableId . ' (' .
            implode(',', self::$columns) .
            ") VALUES ('" .
            implode("','", $csvRow) .
            "')"
        ;

        return $sql;
    }

    /**
     * Inserts data from the specified CSV into a Fusion table
     *
     * @param   string      $csvPath
     * @param   string      $tableId
     *
     * @return  int
     * @throws  \Exception
     */
    public function csvToTable($csvPath, $tableId)
    {
        // using custom implementation of importRows method as it doesn't work properly in vendor Google API package

        // @todo: possible memory limit error here large files
        // @todo: also importRows supports up to 100Mb files only, so paginating here would be nice
        $csvContent = file_get_contents($csvPath);
        if ($csvContent === false) {
            throw new \Exception('Failed to open CSV file to import');
        }

        $uploadService = new GoogleFusiontablesUploadService($this->client);
        $uploadServiceResource = $uploadService->import;
        $response = $uploadServiceResource->import($tableId, $csvContent);

        $rowCount = $response['numRowsReceived'];

        return $rowCount;
    }
}