<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 09/02/13
 * Time: 15:06
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Entities;

/**
 * Configuration entity
 *
 * @author Ollie Maitland
 *
 */
class Configuration
{
    private $configuration = array ();

    public function __construct($app)
    {
        $this->db = $app['db'];
        $this->logger = $app['monolog'];
        $sql = "SELECT * FROM configuration";
        $result = $this->db->fetchAll($sql);
        foreach ($result as $row) {
            $this->configuration[$row['parameter']] = $row['value'];
        }
    }

    /**
     * Save the configuration values
     *
     * @param array $values
     */
    public function fromArray(array $values)
    {
        $this->configuration = $values;
    }

    /**
     * Save the configuration
     *
     */
    public function save()
    {
        foreach ($this->configuration as $k => $v) {
            if (!$v) $v = "";

            if (($this->db->update("configuration", array("value" => $v), array("parameter" => $k))) === 0) {
                $this->db->insert("configuration", array("parameter" => $k, "value" => $v));
                $this->logger->addInfo("Inserted new configuration records");
            } else {
                $this->logger->addInfo("Updated existing configuration records");
            }
        }
    }

    /**
     * Set a parameter
     *
     * @param $name
     * @param $value
     */
    public function set($name, $value)
    {
        $this->configuration[$name] = $value;
    }

    /**
     * Retrieve a single config parameter
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function get($name)
    {
        if (false == array_key_exists($name, $this->configuration)) {
            throw new \Exception;
        }

        return $this->configuration[$name];
    }

    /**
     * Return all the config parameters
     *
     * @return array
     */
    public function getAll()
    {
        return $this->configuration;
    }
}