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

    // keys for column properties
    const
        COL_KIND = 'kind',
        COL_TYPE = 'type'
    ;

    // supported column tables
    // strangely no constants in vendor API
    const
        COL_TYPE_TEXT   = 'Text',
        COL_TYPE_NUMBER = 'Number',
        COL_TYPE_DATE   = 'Date/Time'
    ;

    // default table columns
    const
        TABLE_COL_PROJECT   = 'Project',
        TABLE_COL_DAY       = 'Day',
        TABLE_COL_HOURS     = 'Hours',
        TABLE_COL_PERSON    = 'Person'
    ;

    // default table structure
    protected static $columns = array(
        self::TABLE_COL_PROJECT => array(self::COL_TYPE => self::COL_TYPE_TEXT),
        self::TABLE_COL_DAY     => array(self::COL_TYPE => self::COL_TYPE_DATE),
        self::TABLE_COL_HOURS   => array(self::COL_TYPE => self::COL_TYPE_NUMBER),
        self::TABLE_COL_PERSON  => array(self::COL_TYPE => self::COL_TYPE_TEXT)
    );

    /**
     * @var null|\Google_Client
     */
    protected $client = null;

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
     * Returns a set of default columns (e.g. to create a new)
     *
     * @return  \Google_Column[]
     */
    protected static function getDefaultColumns()
    {
        $columns = array();

        foreach (self::$columns as $colName => $colProp) {
            $column = new \Google_Column();
            $column->setName($colName);

            $column->setType($colProp[self::COL_TYPE]);
            if ($colProp[self::COL_KIND]) {
                $column->setKind(self::COL_KIND);
            }

            $columns[] = $columns;
        }

        return $columns;
    }

    /**
     * Creates a new Fusion Table to import data in and returns its ID
     */
    public function createTable()
    {
        $columns = self::getDefaultColumns();
        print_r($columns);
        $table = new \Google_Table();
        $table->setColumns($columns);

        $fusionTables = new \Google_FusiontablesService($this->client);
        $insertResult = $fusionTables->table->insert($table);
    }

    /**
     * Returns the size of memory block it is safe to allocate
     *
     * @return  int
     */
    protected static function getRemainingMemorySize()
    {
        // requesting php.ini value
        $memoryLimit = ini_get('memory_limit');

        // extracting it into bytes
        $memoryLimit = trim($memoryLimit);
        $units =  strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        switch ($units) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }

        // decreasing it by already allocated size
        $memoryLimit -= memory_get_usage(true);

        // safety coefficient as we don't really want to occupy every remaining byte
        $memoryLimit = (int) floor($memoryLimit * 0.8);

        return $memoryLimit;
    }

    /**
     * Inserts data from the specified CSV into a Fusion table
     * Uses custom implementation of importRows method as it doesn't work properly in vendor Google API package
     *
     * @param   resource    $csvHandle
     * @param   string      $tableId
     * @param   bool        $hasHeaders
     *
     * @return  int
     * @throws  \Exception
     */
    public function csvToTable($csvHandle, $tableId, $hasHeaders = false)
    {
        // accessing the import service we will be callin the the process
        $uploadService = new GoogleFusiontablesUploadService($this->client);
        $uploadServiceResource = $uploadService->import;
        // default upload parameters for that service
        $importParams = array(
            GoogleFusionTablesUploadService::PARAM_ENCODING => GoogleFusionTablesUploadService::PARAM_ENCODING_AUTODETECT
        );

        // rewinding to the beginning of the imported CSV
        fseek($csvHandle, 0);

        $csvContent = '';
        if (!$hasHeaders) {
            // first line would be discarded by Fusion Tables so we need to start with a non-meaningful line
            // can't be just an empty line (even isStrict=false) seem still require the right number of headers
            $csvContent .= implode(',', array_keys(self::$columns)) . PHP_EOL;
        }

        // processing the CSV in maybe an unnecessarily sophisticated way, but that should allow to handling long CSVs
        $rowCount = 0;
        // reading CSV line by line
        while (($csvRow = fgets($csvHandle)) !== false) {
            // this calculation on every iteration might be expensive
            $limit = min(GoogleTableUploadServiceResource::MAX_IMPORT_SIZE, self::getRemainingMemorySize());

            if (strlen($csvRow) + strlen($csvContent) >= $limit) {  // that should handle Unicode strings as well, shouldn't it?
                // if we add current line on top, it'll be too much, so let's send what we have accumulated
                $response = $uploadServiceResource->import($tableId, $csvContent, $importParams);
                $rowCount += $response['numRowsReceived'];

                unset($csvContent); // couldn't harm (http://php.net/manual/en/features.gc.php)
                $csvContent = '';
            }

            $csvContent .= $csvRow;
            unset($csvRow);
        }

        if (strlen($csvContent)) {
            // upload the last remaining chunk
            $response = $uploadServiceResource->import($tableId, $csvContent, $importParams);
            $rowCount += $response['numRowsReceived'];
        }

        return $rowCount;
    }
}