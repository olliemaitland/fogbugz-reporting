<?php
/**
 * Wrapper for importRows Google Fusion Tables API method implemented by GoogleFusionTablesUploadService
 *
 * As per version 0.6.2 importRows functionality for CSV upload is not supported in vendor API properly
 *
 * This way around implementation has been borrowed from:
 * https://code.google.com/p/google-api-php-client/issues/detail?id=192#c7
 * http://stackoverflow.com/a/14648321
 *
 * @author
 * @author  Yuriy Akopov
 * @date    2013-05-12
 */

namespace Fogbugz\Api;

class GoogleTableUploadServiceResource extends \Google_ServiceResource
{
    const MAX_IMPORT_SIZE = 104857600; // 100 Mb API limitation

    /**
     * Import data into a table. (table.import)
     *
     * @param   string  $tableId
     * @param   string  $csv
     * @param   array   $optParams  Optional parameters. See vendor importRows method for description
     *
     * @return  GoogleTableImportResult
     */
    public function import($tableId, $csv, $optParams = array()) {
        $params = array('tableId' => $tableId,'postBody' => $csv);
        $params = array_merge($params, $optParams);
        $data = $this->__call('import', array($params));
        if ($this->useObjects()) {
            return new GoogleTableImportResult($data);
        } else {
            return $data;
        }
    }

    // we need to redeclare a bunch of fields used in __call method we need to override because
    // they're private in the parent vendor class
    // hence a lot of code duplication - sorry, poor parent API architecture :(

    // Valid query parameters that work, but don't appear in discovery.
    private $stackParameters = array(
        'alt' => array('type' => 'string', 'location' => 'query'),
        'boundary' => array('type' => 'string', 'location' => 'query'),
        'fields' => array('type' => 'string', 'location' => 'query'),
        'trace' => array('type' => 'string', 'location' => 'query'),
        'userIp' => array('type' => 'string', 'location' => 'query'),
        'userip' => array('type' => 'string', 'location' => 'query'),
        'quotaUser' => array('type' => 'string', 'location' => 'query'),
        'file' => array('type' => 'complex', 'location' => 'body'),
        'data' => array('type' => 'string', 'location' => 'body'),
        'mimeType' => array('type' => 'string', 'location' => 'header'),
        'uploadType' => array('type' => 'string', 'location' => 'query'),
        'mediaUpload' => array('type' => 'complex', 'location' => 'query'),
    );

    /** @var Google_Service $service */
    private $service;

    /** @var string $serviceName */
    private $serviceName;

    /** @var string $resourceName */
    private $resourceName;

    /** @var array $methods */
    private $methods;

    /**
     * Constructor overridden to initialised re-declared private fields
     *
     * @param $service
     * @param $serviceName
     * @param $resourceName
     * @param $resource
     */
    public function __construct($service, $serviceName, $resourceName, $resource) {
        $this->service = $service;
        $this->serviceName = $serviceName;
        $this->resourceName = $resourceName;
        $this->methods = isset($resource['methods']) ? $resource['methods'] : array($resourceName => $resource);
    }

    /**
     * Overridden because of a single line change we need to support application/octet-stream data in requests
     *
     * @param   string  $name
     * @param   array   $arguments
     *
     * @return array|\Google_HttpRequest
     * @throws \Google_Exception
     */
    public function __call($name, $arguments) {
        if (! isset($this->methods[$name])) {
            throw new \Google_Exception("Unknown function: {$this->serviceName}->{$this->resourceName}->{$name}()");
        }
        $method = $this->methods[$name];
        $parameters = $arguments[0];

        // postBody is a special case since it's not defined in the discovery document as parameter, but we abuse the param entry for storing it
        $postBody = null;
        if (isset($parameters['postBody'])) {
            if (is_object($parameters['postBody'])) {
                $this->stripNull($parameters['postBody']);
            }

            // Some APIs require the postBody to be set under the data key.
            if (is_array($parameters['postBody']) && 'latitude' == $this->serviceName) {
                if (!isset($parameters['postBody']['data'])) {
                    $rawBody = $parameters['postBody'];
                    unset($parameters['postBody']);
                    $parameters['postBody']['data'] = $rawBody;
                }
            }

            $postBody = is_array($parameters['postBody']) || is_object($parameters['postBody'])
                ? json_encode($parameters['postBody'])
                : $parameters['postBody'];
            unset($parameters['postBody']);

            if (isset($parameters['optParams'])) {
                $optParams = $parameters['optParams'];
                unset($parameters['optParams']);
                $parameters = array_merge($parameters, $optParams);
            }
        }

        if (!isset($method['parameters'])) {
            $method['parameters'] = array();
        }

        $method['parameters'] = array_merge($method['parameters'], $this->stackParameters);
        foreach ($parameters as $key => $val) {
            if ($key != 'postBody' && ! isset($method['parameters'][$key])) {
                throw new \Google_Exception("($name) unknown parameter: '$key'");
            }
        }
        if (isset($method['parameters'])) {
            foreach ($method['parameters'] as $paramName => $paramSpec) {
                if (isset($paramSpec['required']) && $paramSpec['required'] && ! isset($parameters[$paramName])) {
                    throw new \Google_Exception("($name) missing required param: '$paramName'");
                }
                if (isset($parameters[$paramName])) {
                    $value = $parameters[$paramName];
                    $parameters[$paramName] = $paramSpec;
                    $parameters[$paramName]['value'] = $value;
                    unset($parameters[$paramName]['required']);
                } else {
                    unset($parameters[$paramName]);
                }
            }
        }

        // Discovery v1.0 puts the canonical method id under the 'id' field.
        if (! isset($method['id'])) {
            $method['id'] = $method['rpcMethod'];
        }

        // Discovery v1.0 puts the canonical path under the 'path' field.
        if (! isset($method['path'])) {
            $method['path'] = $method['restPath'];
        }

        $servicePath = $this->service->servicePath;

        // Process Media Request

        //////////////////////////////////////
        // the only line overridden from the original method
        // $contentType = false;
        $contentType = isset($method['requestContentTypeOverride']) ? $method['requestContentTypeOverride'] : false;
        //////////////////////////////////////

        if (isset($method['mediaUpload'])) {
            $media = \Google_MediaFileUpload::process($postBody, $parameters);
            if ($media) {
                $contentType = isset($media['content-type']) ? $media['content-type']: null;
                $postBody = isset($media['postBody']) ? $media['postBody'] : null;
                $servicePath = $method['mediaUpload']['protocols']['simple']['path'];
                $method['path'] = '';
            }
        }

        $url = \Google_REST::createRequestUri($servicePath, $method['path'], $parameters);
        $httpRequest = new \Google_HttpRequest($url, $method['httpMethod'], null, $postBody);
        if ($postBody) {
            $contentTypeHeader = array();
            if (isset($contentType) && $contentType) {
                $contentTypeHeader['content-type'] = $contentType;
            } else {
                $contentTypeHeader['content-type'] = 'application/json; charset=UTF-8';
                $contentTypeHeader['content-length'] = \Google_Utils::getStrLen($postBody);
            }
            $httpRequest->setRequestHeaders($contentTypeHeader);
        }

        $httpRequest = \Google_Client::$auth->sign($httpRequest);
        if (\Google_Client::$useBatch) {
            return $httpRequest;
        }

        // Terminate immediately if this is a resumable request.
        if (isset($parameters['uploadType']['value'])
            && \Google_MediaFileUpload::UPLOAD_RESUMABLE_TYPE == $parameters['uploadType']['value']) {
            $contentTypeHeader = array();
            if (isset($contentType) && $contentType) {
                $contentTypeHeader['content-type'] = $contentType;
            }
            $httpRequest->setRequestHeaders($contentTypeHeader);
            if ($postBody) {
                $httpRequest->setPostBody($postBody);
            }
            return $httpRequest;
        }

        return \Google_REST::execute($httpRequest);
    }
}