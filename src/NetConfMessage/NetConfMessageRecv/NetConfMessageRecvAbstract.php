<?php namespace Lamoni\NetConf\NetConfMessage\NetConfMessageRecv;

/**
 * Class NetConfMessageRecvAbstract
 * @package Lamoni\NetConf\NetConfMessage\NetConfMessageRecv
 */
abstract class NetConfMessageRecvAbstract
{

    /**
     * Holds the SimpleXMLElement'd response from the server
     *
     * @var SimpleXMLElement
     */
    protected $response;

    /**
     * Build our NetConfMessageRecv* instance
     *
     * @param $response
     */
    public function __construct($response)
    {

        $this->setResponse($response);

    }

    /**
     * Sets $response
     *
     * @param $response
     */
    public function setResponse($response)
    {

        $this->response = simplexml_load_string($response);

    }

    /**
     * Returns $response
     *
     * @return SimpleXMLElement
     */
    public function getResponse()
    {

        return $this->response;

    }

    /**
     * Magic method for handling as a string
     *
     * @return mixed
     */
    public function __toString()
    {

        return (string)$this->getResponse()->asXML();

    }

}