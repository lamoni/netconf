<?php namespace Lamoni\NetConf\NetConfAuth;

use Net_SSH2;
use Crypt_RSA;

/**
 * Class NetConfAuthRSAFile
 * @package Lamoni\NetConf\NetConfAuth
 */
class NetConfAuthRSAFile extends NetConfAuthAbstract
{
    /**
     *Performs the authentication check for this auth type
     *
     * @param Net_SSH2 $ssh
     * @throws \Exception
     */
    public function login(Net_SSH2 &$ssh)
    {
        $this->validateAuthParams(
            $this->authParams,
            $acceptableParams = [
                'username' => 'is_string',
                'rsafile' => 'file_exists'
            ]
        );

        extract($this->authParams);

        $rsakey = new Crypt_RSA();

        $rsakey->loadKey(file_get_contents($rsafile));

        if (!$ssh->login($username, $rsakey)) {
            throw new \Exception(get_class().': Authentication failed');
        }
    }
}