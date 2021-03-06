<?php
/**
 * Implements importRows method of Google Fusion Tables API
 *
 * As per version 0.6.2 importRows functionality for CSV upload is not supported in vendor API properly
 *
 * This way around implementation has been borrowed from:
 * https://code.google.com/p/google-api-php-client/issues/detail?id=192#c7
 * http://stackoverflow.com/a/14648321
 *
 * @author  Sanya
 * @author  Yuriy Akopov
 * @date    2013-05-12
 */

namespace Fogbugz\Api;

class GoogleFusionTablesUploadService extends \Google_Service
{
    // import parameter names
    const
        PARAM_STARTLINE = 'startLine',
        PARAM_STRICT    = 'isStrict',
        PARAM_ENCODING  = 'encoding',
        PARAM_DELIMITER = 'delimiter',
        PARAM_ENDLINE   = 'endLine'
    ;

    // import parameter values
    const
        PARAM_ENCODING_UTF8         = 'UTF-8',
        PARAM_ENCODING_AUTODETECT   = 'auto-detect'
    ;

    /**
     * @var GoogleTableUploadServiceResource
     */
    public $import;

    public function __construct(\Google_Client $client) {
        $this->servicePath = 'upload/fusiontables/v1/';
        $this->version = 'v1';
        $this->serviceName = 'fusiontables';

        $client->addService($this->serviceName, $this->version);
        $this->import = new GoogleTableUploadServiceResource($this, $this->serviceName, 'table', json_decode(
            '{
                "methods":{
                   "import":{
                      "scopes":[
                         "https://www.googleapis.com/auth/fusiontables"
                      ],
                      "path":"tables/{tableId}/import",
                      "id":"fusiontables.table.import",
                      "parameters":{
                         "tableId":{
                            "required":true,
                            "type":"string",
                            "location":"path"
                         },
                         "encoding":{
                            "required":false,
                            "type":"string",
                            "location":"path"
                         },
                         "delimiter":{
                            "required":false,
                            "type":"string",
                            "location":"path"
                         },
                         "isStrict":{
                            "required":false,
                            "type":"boolean",
                            "location":"path"
                         },
                         "startLine":{
                            "required":false,
                            "type":"string",
                            "location":"path"
                         },
                         "endLine":{
                            "required":false,
                            "type":"string",
                            "location":"path"
                         }
                      },
                      "response":{
                         "$ref":"TableImportResponse"
                      },
                      "httpMethod":"POST",
                      "requestContentTypeOverride":"application/octet-stream"
                   }
                }
            }', true)
        );
    }
}