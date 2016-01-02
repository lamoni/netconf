<?php namespace Lamoni\NetConf\NetConfMessage\NetConfMessageRecv;

/**
 * Class NetConfMessageRecvHello
 * @package Lamoni\NetConf\NetConfMessage\NetConfMessageRecv
 */
class NetConfMessageRecvHello extends NetConfMessageRecvAbstract
{

    /**
     * What NECONF capabilities is the server... capable of
     *
     * @var array
     */
    protected $theirCapabilities = [];

    /**
     * Assuming the server is following protocol, they should reply with a session ID
     *
     * @var int
     */
    protected $sessionID = 0;

    /**
     * Build our NetConfMessageRecvHello instance
     *
     * @param $response
     */
    public function __construct($response)
    {
        parent::__construct($response);

        $this->setSessionID();

        $this->setTheirCapabilities();

    }

    /**
     * Sets the $sessionID
     */
    public function setSessionID()
    {

        if (isset($this->getResponse()->{'session-id'})) {

            $this->sessionID = (string)$this->getResponse()->{'session-id'};

        }

    }

    /**
     * Gets the $sessionID
     *
     * @return int
     */
    public function getSessionID()
    {

        return $this->sessionID;

    }

    /**
     * Sets $theirCapabilities based on the Hello Exchange
     */
    public function setTheirCapabilities()
    {
        if (isset($this->getResponse()->capabilities->capability)) {

            $this->theirCapabilities = (array)$this->getResponse()->capabilities->capability;
        }

    }

    /**
     * Returns $theirCapabilities
     *
     * @return array
     */
    public function getTheirCapabilities()
    {

        return $this->theirCapabilities;

    }

}