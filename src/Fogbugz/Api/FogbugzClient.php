<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 16:29
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Api;

use Fogbugz\Console\Command\SetupFogbugzCommand;
use Guzzle\Http\Client as BaseClient;

class FogbugzClient extends BaseClient
{
    /**
     * @var \Fogbugz\Entities\Configuration|null
     */
    protected $config = null;

    public function __construct(\Fogbugz\Entities\Configuration $config)
    {
        $this->config = $config;
        $baseUrl = $this->config->get(SetupFogbugzCommand::FOGBUGZ_URL);


        $params = array ();
        parent::__construct($baseUrl, $params);

        try {
            $token = $this->config->get(SetupFogbugzCommand::FOGBUGZ_TOKEN);

        } catch (\Exception $e) {
            $token = $this->generateToken();
            $this->config->set(SetupFogbugzCommand::FOGBUGZ_TOKEN, $token);
        }

        $this->token = $token;
    }

    protected function runCommand($cmd, $parameters)
    {
        $parameters['cmd'] = $cmd;

        if ($this->token) {
            // add to the request
            $parameters['token'] = $this->token;
        }

        $query = http_build_query($parameters);
        $request = $this->get(sprintf("api.asp?%s", $query));

        // var_dump(sprintf("api.asp?%s", $query));

        $response = $request->send();

//        var_dump($response->getBody(true));

        // needs the LIBXML NOCDATA
        $xml = simplexml_load_string($response->getBody(true), null, LIBXML_NOCDATA);

        return $xml;
    }

    protected function generateToken()
    {
        $options = array (
            "email"     => $this->config->get(SetupFogbugzCommand::FOGBUGZ_EMAIL),
            "password"  => $this->config->get(SetupFogbugzCommand::FOGBUGZ_PASSWORD)
        );

        $xml = $this->runCommand("logon", $options);

        return (string) $xml->token;
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     *
     * @return \Fogbugz\Entities\Interval[]
     */
    public function getWorklogs(\DateTime $start, \DateTime $end = null)
    {

        $options = array (
            "ixPerson" => 1,
            "dtStart"   => $start->format("Y-m-d")." 00:00:00",
            "dtEnd"     => $end->format("Y-m-d")." 23:59:59"
        );

        $xml = $this->runCommand("listIntervals", $options);

        $intervals = array ();
        $intervalTags = $xml->intervals[0];

        foreach ($intervalTags as $intervalTag) {

            $interval = new \Fogbugz\Entities\Interval();
            $interval->case     = (string) $intervalTag->ixBug;
            $interval->start    = (string) $intervalTag->dtStart;
            $interval->end      = (string) $intervalTag->dtEnd;
            $interval->person   = (string) $intervalTag->ixPerson;

            $intervals[] = $interval;
        }

        return $intervals;
    }

    private $caseProjects = array ();

    public function getProjectFromCase($case)
    {
        if (array_key_exists($case, $this->caseProjects)) {
            return $this->caseProjects[$case];
        }

        $options = array (
            "q"   => $case,
            "cols" => "sProject"
        );

        $xml = $this->runCommand("search", $options);

        $project = (string) ($xml->cases->case->sProject);

        $this->caseProjects[$case] = $project;

        return $project;
    }

    public $people = array ();

    public function getPersonFromCase($personId)
    {
        if (array_key_exists($personId, $this->people)) {
            return $this->people[$personId];
        }

        $options = array (
            "ixPerson"   => $personId
        );

        $xml = $this->runCommand("viewPerson", $options);

        $name = (string) $xml->person->sFullName;

        $this->people[$personId] = $name;

        return $name;
    }

}