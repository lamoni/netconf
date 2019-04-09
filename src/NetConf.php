<?php namespace Lamoni\NetConf;

use Lamoni\NetConf\NetConfAuth\NetConfAuthAbstract;
use Lamoni\NetConf\NetConfMessage\NetConfMessageRecv\NetConfMessageRecvHello;
use Lamoni\NetConf\NetConfMessage\NetConfMessageRecv\NetConfMessageRecvRPC;
use Net_SSH2;
use SimpleXMLElement;

/**
 * Class NetConf
 * @package Lamoni\NetConf
 * @author Lamoni Finlayson
 */
class NetConf
{

    /**
     * SSH2 interface
     *
     * @var Net_SSH2
     */
    protected $ssh;

    /**
     * NETCONF Session ID sent by server during Hello Exchange
     * @var int
     */
    protected $sessionID;

    /**
     * Our capabilities (auto-includes urn:ietf:params:netconf:base:1.0)
     * @var array
     */
    protected $myCapabilities;

    /**
     * Their capabilities (informational only... no logic that uses these, yet...)
     *
     * @var array
     */
    protected $theirCapabilities;

    /**
     * "message-id" for <rpc> requests.  Auto-increments whenever sendRPC() is called
     *
     * @var int
     */
    protected $messageID;

    /**
     * Tracks all XML sent through sendRaw()
     *
     * @var array
     */
    protected $sendHistory;

    /**
     * @param $hostname
     * @param NetConfAuthAbstract $netconfAuth
     * @param array $options
     */
    public function __construct($hostname,
                                NetConfAuthAbstract $netconfAuth,
                                $options=[])
    {

        /**
         * Defaults
         * - I declare the variables and then compact() them to appease the IDE Gods so they'll smite
         *    the red squiggly lines under variables created by extract().. I hate that I'll
         *    still have to add to the compact() call if I ever want to use a new variable...
         *    But flexibility in options is too sexy.
         */
        $myCapabilities = [];

        /**
         * Default: 830 (NETCONF default)
         */
        $port = 830;

        /**
         * Default: 120 seconds
         */
        $timeout = 120;


        /**
         * Options
         *  - Merge our options, if any, with the defaults, and then extract()
         */

        $options = array_merge(
            compact('myCapabilities', 'port', 'timeout'),
            $options
        );

        extract($options, EXTR_IF_EXISTS);


        /**
         * Initialization / Settings
         * - Define our default capabilities here
         */

        $this->myCapabilities = array_merge(
            ["urn:ietf:params:netconf:base:1.0"],
            $myCapabilities
        );

        $this->sessionID = 0;

        $this->messageID = 0;

        $this->sendHistory = array();

        /**
         * SSH2 Creation
         * - Consider decoupling this, despite its futility because PHP's SSH2 extension
         *   doesn't support custom subsystems (which we need for NETCONF)
         */

        $this->ssh = new Net_SSH2($hostname, $port);

        $this->ssh->setWindowSize(-1, -1);

        $this->ssh->setTimeout($timeout);

        $netconfAuth->login($this->ssh);

        $this->ssh->startSubsystem("netconf");


        /**
         * Hello Exchange
         * - Receive their hello, parse it for capabilities, and then send our hello+capabilities
         */

        $this->exchangeHellos();
    }

    /**
     * Hello Exchange
     */
    public function exchangeHellos()
    {

        $theirHello = new NetConfMessageRecvHello($this->readReply("</hello>"));

        $this->theirCapabilities = $theirHello->getTheirCapabilities();

        $this->sessionID = $theirHello->getSessionID();

        $this->sendHello();

    }

    /**
     * Handles the actual building and sending of XML to the server
     *
     * @param $data
     * @param $rootNode
     * @param $endOfMessageDelimiter
     * @param array $attributes
     * @param bool $waitForReply
     * @return bool|mixed
     */
    public function sendRaw($data, $rootNode, $endOfMessageDelimiter, $attributes=array(), $waitForReply=true)
    {

        $data = str_replace('<?xml version="1.0"?>', '', (string)$data);

        $data = new SimpleXMLElement("<{$rootNode}>$data</{$rootNode}>");

        foreach ($attributes as $attribute_name=>$attribute_value) {

            $data->addAttribute($attribute_name, $attribute_value);

        }

        $data = str_replace('<?xml version="1.0"?>', '', $data->asXML());

        $this->sendHistory[] = $data;

        $this->ssh->write($data."]]>]]>\n");

        if (!$waitForReply) {

            return true;

        }

        return $this->readReply($endOfMessageDelimiter);
    }

    /**
     * Builds the RPC calls (wraps with <rpc> and increases the message ID counter
     *
     * @param $rpc
     * @return NetConfMessageRecvRPC
     */
    public function sendRPC($rpc)
    {

        $this->messageID++;

        return new NetConfMessageRecvRPC(
            $this->sendRaw(
                $rpc, "rpc", "</rpc-reply>",
                ["message-id"=>$this->messageID]
            )
        );

    }

    /**
     * Sends our Hello message
     */
    public function sendHello() {

        $helloXML = new SimpleXMLElement("<capabilities> </capabilities>");

        foreach ($this->myCapabilities as $capability) {

            $helloXML->addChild("capability", $capability);

        }

        $this->sendRaw(
            $helloXML->asXML(),
            "hello",
            null,
            [],
            false
        );

    }

    /**
     * Returns the array containing all XML sent to the server
     *
     * @return array
     */
    public function getSendHistory()
    {

        return $this->sendHistory;

    }

    /**
     * Return the server's capabilities
     *
     * @return array
     */
    public function getTheirCapabilities()
    {

        return $this->theirCapabilities;

    }

    /**
     * After write()'ing input to the server, this handles the waiting and returning of that data
     *
     * @param $endOfMessageDelimiter
     * @return mixed
     */
    public function readReply($endOfMessageDelimiter)
    {

        return str_replace(
            "{$endOfMessageDelimiter}\n]]>]]>",
            "{$endOfMessageDelimiter}",
            $this->ssh->read("{$endOfMessageDelimiter}\n]]>]]>")
        );

    }

    /**
     * A wrapper for <get-config>
     *
     * @param array $filterPaths
     * @param string $dataStore
     * @return NetConfMessageRecvRPC
     */
    public function getConfig(array $filterPaths=[], $filterType = "", $dataStore="running")
    {

        $filterRoot = new SimpleXMLElement("<get-config><source><{$dataStore}/></source></get-config>");

        if (count($filterPaths)) {

            $addFilter = $filterRoot->addChild('filter');

            if ($filterType !== "") {
                $addFilter->addAttribute('type', $filterType);
            }


            foreach ($filterPaths as $filterPath => $specificElements) {

                $levelSplit = explode("/", trim($filterPath, "/"));

                $lastLevel = end($levelSplit);

                $deepestNode = $addFilter;

                foreach ($levelSplit as $level) {

                    if ($level !== $lastLevel) {

                        $deepestNode = $deepestNode->addChild("{$level}");

                    }
                    else {
                        foreach ($specificElements as $specificElement) {

                            $deepestNode = $deepestNode->addChild("{$lastLevel}");

                            foreach ($specificElement as $elementName => $elementValue) {

                                $deepestNode->addChild("{$elementName}", "{$elementValue}");

                            }

                            $deepestNode = $deepestNode->xpath("..")[0];

                        }
                    }
                }
            }
        }

        return $this->sendRPC($filterRoot->asXML());

    }


    /**
     * A wrapper for <edit-config>
     *
     * @param $configString
     * @param $datastore
     * @param array $customParams
     * @param bool $lockConfig
     * @return NetConfMessageRecvRPC
     */
    public function editConfig($configString, $datastore, array $customParams=[], $lockConfig=true)
    {
        if ($lockConfig) {

            $lockConfigCheck = $this->lockConfig($datastore);

            if (!$lockConfigCheck->isRPCReplyOK()) {

                return $lockConfigCheck;

            }

        }

        $editConfig = new SimpleXMLElement(
            "<edit-config>".
                "<config>{$configString}</config>".
            "</edit-config>"
        );

        foreach ($customParams as $paramName=>$paramValue) {

            $editConfig->addChild($paramName, $paramValue);

        }

        return $this->sendRPC($editConfig->asXML());
    }

    /**
     * Handles copying a datastore to a different datastore
     *
     * @param $source - Pass in dataStore name, or prefix like "url:http://test.com/config" to generate the <url> node
     * @param $target - Pass in dataStore name, or prefix like "url:http://test.com/config" to generate the <url> node
     */
    public function copyConfig($source, $target)
    {

        if (substr($source, 0, 4) === "url:") {

            $source = "<url>".substr($source, 4)."</url>";

        }
        else {

            $source = "<{$source}/>";

        }

        if (substr($target, 0, 4) === "url:") {

            $target = "<url>".substr($target, 4)."</url>";

        }
        else {

            $target = "<{$target}/>";

        }

        $copyConfig = new SimpleXMLElement(
            "<copy-config>".
                "<source>{$source}</source>".
                "<target>{$target}</target>".
            "</copy-config>"
        );

        return $this->sendRPC($copyConfig->asXML());

    }

    /**
     * A wrapper for deleting config (<delete-config>)
     *
     * @param $target - Pass in dataStore name, or prefix like "url:http://test.com/config" to generate the <url> node
     */
    public function deleteConfig($target)
    {

        if (substr($target, 0, 4) === "url:") {

            $target = "<url>".substr($target, 4)."</url>";

        }
        else {

            $target = "<{$target}/>";

        }

        $deleteConfig = new SimpleXMLElement(
            "<delete-config>".
                "<target>{$target}</target>".
            "</delete-config>"
        );


        return $this->sendRPC($deleteConfig->asXML());

    }

    /**
     * Handles commiting to the server (<commit>)
     *
     * @param string $dataStore
     * @param bool $unlockConfig
     * @param bool $requiresConfirm
     * @param int $confirmTimeout
     * @param string $persistID
     * @return NetConfMessageRecvRPC|SimpleXMLElement
     */
    public function commit($dataStore='candidate', $unlockConfig=true,
                           $requiresConfirm=false, $confirmTimeout=600, $persistID="")
    {
        $commit = new SimpleXMLElement("<commit/>");

        if ($requiresConfirm) {

            $commit->addChild("confirmed", "");

            $commit->addChild("confirm-timeout", "{$confirmTimeout}");

            if ($persistID !== "") {
                $commit->addChild("persist", "{$persistID}");
            }
        }

        if ($persistID !== "" && !$requiresConfirm) {
            $commit->addChild("persist-id", "{$persistID}");
        }


        $commit = $this->sendRPC($commit->asXML());

        if (!$commit->isRPCReplyOK()) {

            return $commit;

        }

        if ($unlockConfig) {

            $unlockConfigCheck = $this->unlockConfig($dataStore);

            if (!$commit->isRPCReplyOK()) {

                return $unlockConfigCheck;

            }
        }

        return $commit;
    }

    /**
     * Cancels the current commit
     *
     * @return NetConfMessageRecvRPC
     */
    public function cancelCommit() {

        return $this->sendRPC("<cancel-commit/>");

    }

    /**
     * Locks the config (e.g. edit exclusive on Junos)
     *
     * @param $dataStore
     * @return NetConfMessageRecvRPC
     */
    public function lockConfig($dataStore)
    {

        return $this->sendRPC("<lock><target><{$dataStore}/></target></lock>");

    }

    /**
     * Unlocks the config, explicitly.
     *
     * @param $dataStore
     * @return NetConfMessageRecvRPC
     */
    public function unlockConfig($dataStore)
    {

        return $this->sendRPC("<unlock><target><{$dataStore}/></target></unlock>");

    }

    /**
     * Sets $sessionID
     *
     * @param $sessionID
     */
    public function setSessionID($sessionID)
    {

        $this->sessionID = $sessionID;

    }

    /**
     * Returns $sessionID
     *
     * @return int
     */
    public function getSessionID()
    {

        return $this->sessionID;

    }

    /**
     * Forcefully kills the given NETCONF session
     *
     * @param $sessionID
     * @return NetConfMessageRecvRPC
     */
    public function killSession($sessionID)
    {

        return $this->sendRPC("<kill-session><session-id>{$sessionID}</session-id></kill-session>");

    }

    /**
     * Closes the current session
     *
     * @return NetConfMessageRecvRPC
     */
    public function closeSession()
    {

        return $this->sendRPC("<close-session/>");

    }

    /**
     * Builds the custom methods for certain actions (allows easy extendability)
     *
     * @param array $_params
     * @param array $getDefinedVars
     * @return bool
     */
    public function buildMethodOptions(array &$_params, array $getDefinedVars)
    {
        return array_walk(
            array_merge(
                array_filter(
                    array_keys($getDefinedVars), function($key) {
                        return $key[0] !== "_";
                    }
                ),
                $_params
            ),
            function(&$value) {
                $value = str_replace("_", "-", $value);
            }
        );
    }
}
