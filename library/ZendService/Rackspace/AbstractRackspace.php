<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Rackspace;

use Zend\Http\Client as HttpClient;
use ZendService\Rackspace\Exception;

abstract class AbstractRackspace
{
    const VERSION                = 'v2.0';
    const AUTH_URL               = 'https://identity.api.rackspacecloud.com';
    const API_FORMAT             = 'json';
    const USER_AGENT             = 'ZendService\Rackspace';
    const STORAGE_URL            = "X-Storage-Url";
    const AUTHTOKEN              = "X-Auth-Token";
    const AUTHUSER_HEADER        = "X-Auth-User";
    const AUTHKEY_HEADER         = "X-Auth-Key";
    const AUTHUSER_HEADER_LEGACY = "X-Storage-User";
    const AUTHKEY_HEADER_LEGACY  = "X-Storage-Pass";
    const AUTHTOKEN_LEGACY       = "X-Storage-Token";
    const CDNM_URL               = "X-CDN-Management-Url";
    const MANAGEMENT_URL         = "X-Server-Management-Url";

    /**
     * Rackspace Key
     *
     * @var string
     */
    protected $key;

    /**
     * Rackspace account name
     *
     * @var string
     */
    protected $user;

    /**
     * Token of authentication
     *
     * @var string
     */
    protected $token;

    /**
     * Authentication URL
     *
     * @var string
     */
    protected $authUrl;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var boolean
     */
    protected $useServiceNet = false;

    /**
     * Error Msg
     *
     * @var string
     */
    protected $errorMsg;

    /**
     * HTTP error code
     *
     * @var string
     */
    protected $errorCode;

    /**
     * Storage URL
     *
     * @var string
     */
    protected $storageUrl;

    /**
     * CDN URL
     *
     * @var string
     */
    protected $cdnUrl;

    /**
     * Server management URL
     *
     * @var string
     */
    protected $managementUrl;

    /**
     * Constructor
     *
     * You must pass the account and the Rackspace authentication key.
     * Optional: the authentication url (default is US)
     *
     * @param string $user
     * @param string $key
     * @param string $authUrl
     * @param HttpClient $httpClient
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($user, $key, HttpClient $httpClient = null)
    {
        if (!isset($user)) {
            throw new Exception\InvalidArgumentException("The user cannot be empty");
        }
        if (!isset($key)) {
            throw new Exception\InvalidArgumentException("The key cannot be empty");
        }

        $this->setUser($user);
        $this->setKey($key);
        $this->setAuthUrl(self::AUTH_URL);
        $this->setHttpClient($httpClient ?: new HttpClient);
    }

    /**
     * @param HttpClient $httpClient
     * @return AbstractRackspace
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * get the HttpClient instance
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Sets whether to use ServiceNet
     *
     * ServiceNet is Rackspace's internal network. Bandwidth on ServiceNet is
     * not charged.
     *
     * @param boolean $useServiceNet
     */
    public function setServiceNet($useServiceNet = true)
    {
        $this->useServiceNet = $useServiceNet;
        return $this;
    }

    /**
     * Get whether we're using ServiceNet
     *
     * @return boolean
     */
    public function getServiceNet()
    {
        return $this->useServiceNet;
    }

    /**
     * Get User account
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get user key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get authentication URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->authUrl;
    }

    /**
     * Get the storage URL
     *
     * @return string|boolean
     */
    public function getStorageUrl()
    {
        if (empty($this->storageUrl)) {
            if (!$this->authenticate()) {
                return false;
            }
        }
        return $this->storageUrl;
    }

    /**
     * Get the CDN URL
     *
     * @return string|boolean
     */
    public function getCdnUrl()
    {
        if (empty($this->cdnUrl)) {
            if (!$this->authenticate()) {
                return false;
            }
        }
        return $this->cdnUrl;
    }

    /**
     * Get the management server URL
     *
     * @return string|boolean
     * @throws Exception\RuntimeException
     */
    public function getManagementUrl()
    {
        if (empty($this->managementUrl)) {
            if (!$this->authenticate()) {
                throw new Exception\RuntimeException('Authentication failed, you need a valid token to use the Rackspace API');
            }
        }
        return $this->managementUrl;
    }

    /**
     * Set the user account
     *
     * @param string $user
     * @return void
     */
    public function setUser($user)
    {
        if (!empty($user)) {
            $this->user = $user;
        }
    }

    /**
     * Set the authentication key
     *
     * @param string $key
     * @return void
     */
    public function setKey($key)
    {
        if (!empty($key)) {
            $this->key = $key;
        }
    }

    /**
     * Set the Authentication URL
     *
     * @param string $url
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function setAuthUrl($url)
    {
        if (!empty($url)) {
            $this->authUrl = $url;
        } else {
            throw new Exception\InvalidArgumentException("The authentication URL is not valid");
        }
    }

    /**
     * Get the authentication token
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function getToken()
    {
        if (empty($this->token)) {
            if (!$this->authenticate()) {
                throw new Exception\RuntimeException('Authentication failed, you need a valid token to use the Rackspace API');
            }
        }
        return $this->token;
    }

    /**
     * Get the error msg of the last HTTP call
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Get the error code of the last HTTP call
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Return true is the last call was successful
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return (empty($this->errorMsg));
    }

    /**
     * HTTP call
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param array $data
     * @param string $body
     * @return Zend\Http\Response
     */
    protected function httpCall($url,$method,$headers=array(),$data=array(),$body=null)
    {
        $client = $this->getHttpClient();
        $client->resetParameters();

        if (!empty($this->token)) {
            $headers[self::AUTHTOKEN]= $this->token;
        }

        if (empty($headers['Content-Type']) && $method == 'PUT' && empty($body)) {
            $headers['Content-Type'] = '';
        }
        $client->setMethod($method);
        if (empty($data['format'])) {
            $data['format']= self::API_FORMAT;
        }
        $client->setParameterGet($data);
        if (!empty($body)) {
            $client->setRawBody($body);
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type']= 'application/json';
            }
        }

        $client->setHeaders($headers);

        $client->setUri($url);
        $this->errorMsg = null;
        $this->errorCode = null;
        return $client->send();
    }

    /**
     * Authentication
     *
     * @return boolean
     */
    public function authenticate()
    {
        $headers['Content-Type'] = 'application/json';
        $data = json_encode(
            array(
                'auth' => array(
                    'RAX-KSKEY:apiKeyCredentials' => array(
                        'username' => $this->user,
                        'apiKey' => $this->key
                    )
                )
            )
        );

        $url= $this->authUrl .'/' .self::VERSION . '/tokens';

        //Authenticate
        $result = $this->httpCall($url,'POST', $headers, null, $data);
        $_content = json_decode($result->getBody());

        if($_content->access) {
            //Set endpoint urls
            foreach($_content->access->serviceCatalog as $_key => $_service) {

                if($_service->name == 'cloudServersOpenStack')
                    $this->managementUrl = $_content->access->serviceCatalog[$_key]->endpoints[0]->publicURL;

                if($_service->name == 'cloudFilesCDN')
                    $this->cdnUrl = $_content->access->serviceCatalog[$_key]->endpoints[0]->publicURL;

                if($_service->name == 'cloudFiles')
                    $this->cdnUrl = $_content->access->serviceCatalog[$_key]->endpoints[0]->publicURL;
            }

            //Set token info
            $this->token = $_content->access->token->id;

            return true;
        }

        $this->errorMsg = $result->getBody();
        $this->errorCode = $result->getStatusCode();
        return false;
    }
}