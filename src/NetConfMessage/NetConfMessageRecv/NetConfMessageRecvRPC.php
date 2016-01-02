<?php namespace Lamoni\NetConf\NetConfMessage\NetConfMessageRecv;

/**
 * Class NetConfMessageRecvRPC
 * @package Lamoni\NetConf\NetConfMessage\NetConfMessageRecv
 */
class NetConfMessageRecvRPC extends NetConfMessageRecvAbstract
{

    /**
     * Send with every RPC call
     *
     * @var int
     */
    protected $messageID=0;

    /**
     * If rpc-error exists in output, save it to instance.
     *
     * @var array
     */
    protected $rpcError=[];


    /**
     * Build our NetConfMessageRPC instance
     *
     * @param $response
     */
    public function __construct($response)
    {

        parent::__construct($response);

        $this->setMessageID();

        $this->setRPCError();


    }

    /**
     * Sets $messageID
     */
    public function setMessageID()
    {

        if (isset($this->getResponse()->attributes()->{'message-id'}))
        {

            $this->messageID = (string)$this->getResponse()->attributes()->{'message-id'};

        }

    }

    /**
     * Returns $messageID
     *
     * @return int
     */
    public function getMessageID()
    {

        return $this->messageID;

    }

    /**
     * Returns <rpc-reply> data
     *
     * @return bool|SimpleXMLElement
     */
    public function getRPCReply()
    {
        if (!$this->isRPCReplyOK()) {

            return false;

        }

        return $this->getResponse();

    }

    /**
     * If response contains either an <ok/> or doesn't have any errors, consider it "OK"
     *
     * @return bool
     */
    public function isRPCReplyOK()
    {
        /*
         * This needs to stay due to the fact an <ok> and a <rpc-error> can actually co-exist, but
         * only if the rpc-error's error-severity is a "warning" level.
         */
        if (isset($this->getResponse()->{'ok'})) {

            return true;

        }

        /*
         * Added this because some server implementations of NETCONF are returning an empty array
         * for rpc-error despite there being no error...
         */
        if ($this->doesRPCReplyHaveError() ) {

            return false;

        }

        return true;

    }

    /**
     * Checks to see if response has <rpc-error>
     *
     * @return bool
     */
    public function doesRPCReplyHaveError()
    {
        if ($this->getNumberOfRPCErrors() !== 0) {

            return true;

        }

        return false;

    }

    /**
     * Returns the number of RPC errors.
     */

    public function getNumberOfRPCErrors()
    {
        return count($this->rpcError);
    }

    /**
     * Sets $rpcError
     */
    public function setRPCError()
    {
        if (isset($this->getResponse()->{'rpc-error'})) {


            $this->rpcError = $this->getResponse()->{'rpc-error'};
        }

    }

    /**
     * Returns <rpc-error> data, if existent
     *
     * @return array|bool
     */
    public function getRPCError()
    {

        if ($this->getNumberOfRPCErrors() === 0) {

            return false;

        }

        return $this->rpcError;

    }

    /**
     * Returns the error type of the <rpc-error> response
     *
     * @return bool|string
     */
    public function getRPCErrorType()
    {

        if (!isset($this->getRPCError()->{'error-type'})) {

            return false;

        }

        return (string)$this->getRPCError()->{'error-type'};

    }

    /**
     * Returns the <rpc-error> tag data
     *
     * @return bool|string
     */
    public function getRPCErrorTag()
    {

        if (!isset($this->getRPCError()->{'error-tag'})) {

            return false;

        }

        return (string)$this->getRPCError()->{'error-tag'};

    }

    /**
     * Returns <rpc-error>'s error-severity
     *
     * @return bool|string
     */
    public function getRPCErrorSeverity()
    {

        if (!isset($this->getRPCError()->{'error-severity'})) {

            return false;

        }

        return (string)$this->getRPCError()->{'error-severity'};

    }

    /**
     * Gets the error-app-tag from the response
     *
     * @return bool|string
     */
    public function getRPCErrorAppTag()
    {

        if (!isset($this->getRPCError()->{'error-app-tag'})) {

            return false;

        }

        return (string)$this->getRPCError()->{'error-app-tag'};

    }

    /**
     * Gets the error-path node
     *
     * @return bool|string
     */
    public function getRPCErrorPath()
    {

        if (!isset($this->getRPCError()->{'error-path'})) {

            return false;

        }

        return (string)$this->getRPCError()->{'error-path'};

    }

    /**
     * Returns error-info
     *
     * @return bool
     */
    public function getRPCErrorInfo()
    {

        if (!isset($this->getRPCError()->{'error-info'})) {

            return false;

        }

        return $this->getRPCError()->{'error-info'};

    }

}