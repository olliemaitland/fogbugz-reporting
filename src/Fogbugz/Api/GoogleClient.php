<?php
/**
 * Encapsulates operations that the application needs from the variety of Google API (Fusion Tables)
 *
 * @author  Yuriy Akopov
 * @date    2013-05-11
 */

namespace Fogbugz\Api;

use Fogbugz\Console\Command\PushWorklogsCommand;

class GoogleClient
{
    // name of the application in Google stats etc.
    const GOOGLE_APP_NAME = 'Byng FogBugz Reporting Tool';

    // Google API constants for KIND properties of created objects
    const
        KIND_TABLE  = 'fusiontables#table',
        KIND_COLUMN = 'fusiontables#column'
    ;

    // supported column tables
    // strangely no constants in vendor API
    const
        COL_TYPE_TEXT   = 'TEXT',
        COL_TYPE_NUMBER = 'NUMBER',
        COL_TYPE_DATE   = 'DATETIME'
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
        self::TABLE_COL_PROJECT => self::COL_TYPE_TEXT,
        // self::TABLE_COL_DAY     => self::COL_TYPE_DATE,
        // self::TABLE_COL_HOURS   => self::COL_TYPE_NUMBER,
        // self::TABLE_COL_PERSON  => self::COL_TYPE_TEXT
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

        // escape values to be inserted
        // table ID is not escape as it is supposed to be obtained in a safe way
        foreach ($csvRow as $key => $value) {
            $csvRow[$key] = addslashes($value);
        }

        // @todo: tableId is not sanitised because it doesn't come from file input, but maybe it should be?

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

        foreach (self::$columns as $colName => $type) {
            $column = new \Google_Column();
            $column->setName($colName);
            $column->setType($type);
            // $column->setKind(self::KIND_COLUMN);

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Creates a new Fusion Table to import data in and returns its ID
     *
     * @param   string  $name
     *
     * @return  array
     */
    public function createTable($name)
    {
        $columns = self::getDefaultColumns();

        $table = new \Google_Table();
        $table->setName($name);
        $table->setColumns($columns);
        // $table->setKind(self::KIND_TABLE);
        $table->setDescription('Created by ' . self::GOOGLE_APP_NAME .' on ' . date('Y-m-d H:i:s'));
        $table->setIsExportable(true);

        $fusionTables = new \Google_FusiontablesService($this->client);

        $result = $fusionTables->table->insert($table);

        // $table = $fusionTables->table->get($name);

        return $result;
    }

    /**
     * Returns true if we're close enough to allowed memory limit (80% of it used)
     *
     * @return  int
     */
    protected static function isCloseToMemoryLimit()
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
        $memoryOccupied = memory_get_usage(true);

        if (($memoryOccupied / $memoryLimit) >= 0.8) {
            return true;
        }

        return false;
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
            if (
                ((strlen($csvContent) + strlen($csvRow)) >= GoogleTableUploadServiceResource::MAX_IMPORT_SIZE) or
                self::isCloseToMemoryLimit()
            ) {
                // if we add current line on top, it'll be too much, so let's send what we have accumulated
                $response = $uploadServiceResource->import($tableId, $csvContent, $importParams);
                $rowCount += $response[GoogleTableImportResult::RESULT_NUMROWS];

                unset($csvContent); // couldn't harm (http://php.net/manual/en/features.gc.php)
                $csvContent = '';
            }

            $csvContent .= $csvRow;
            unset($csvRow);
        }

        if (strlen($csvContent)) {
            // upload the last remaining chunk
            $response = $uploadServiceResource->import($tableId, $csvContent, $importParams);
            $rowCount += $response[GoogleTableImportResult::RESULT_NUMROWS];
        }

        if (!$hasHeaders and ($rowCount > 0)) {
            $rowCount--;
        }

        return $rowCount;
    }
}