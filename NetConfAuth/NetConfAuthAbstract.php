<?php namespace Lamoni\NetConf\NetConfAuth;

use Net_SSH2;

/**
 * Class NetConfAuthAbstract
 * @package Lamoni\NetConf\NetConfAuth
 */
abstract class NetConfAuthAbstract
{
    /**
     * Holds the auth parameters as described by our extended class
     */
    protected $authParams;

    /**
     * Builds our NetConfAuth* instance
     *
     * @param $authParams
     */
    public function __construct($authParams) {

        $this->authParams = $authParams;
    }

    /**
     * All classes extending NetConfAuthAbstract require the specification of logging in
     *
     * @param Net_SSH2 $ssh
     * @return mixed
     */
    abstract public function login(Net_SSH2 &$ssh);

    /**
     * All children will need this to validate the passed inputs against our defined inputs
     *
     * @param array $authParams
     * @param array $acceptableParams
     * @throws \Exception
     */
    public function validateAuthParams(array $authParams, array $acceptableParams)
    {
        foreach ($authParams as $paramName=>$paramValue) {

            if (!isset($acceptableParams[$paramName])) {
                throw new \Exception(get_class().": Unacceptable authParam: {$paramName}");
            }

            // Try resolving to class method first, then try resolving to global function
            if (method_exists($this, $acceptableParams[$paramName])) {
                $validationPassed = $this->$acceptableParams[$paramName]($paramValue);
            }
            elseif (function_exists($acceptableParams[$paramName])) {
                $validationPassed = $acceptableParams[$paramName]($paramValue);
            }
            else {
                throw new \Exception(get_class().": authParam validator not found: {$paramName}");
            }

            if (!$validationPassed) {
                throw new \Exception(get_class().": Failed authParam validation: {$paramName}");
            }
        }
    }
}