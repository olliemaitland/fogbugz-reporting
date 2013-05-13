<?php
/**
 * Implements result structure returned by importRows method of Google Fusion Tables API
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

class GoogleTableImportResult extends \Google_Model
{
    // when 'useObjects' is off in global API settings and simple arrays are returned
    // these constants can be used to access their keys
    const
        RESULT_NUMROWS = 'numRowsReceived'
    ;

    protected $kind;

    /**
     * @var int
     */
    protected $numRowsReceived;

    public function getKind() {
        return $this->kind;
    }

    public function setKind($kind) {
        $this->kind = $kind;
    }

    public function getNumRowsReceived() {
        return $this->numRowsReceived;
    }

    public function setNumRowsReceived($numRowsReceived) {
        $this->numRowsReceived = $numRowsReceived;
    }
}