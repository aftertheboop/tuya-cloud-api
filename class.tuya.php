<?php
/**
 * Tuya Class
 *
 * This class facilitates communication with the Tuya IoT Cloud Platform.
 * In its current incarnation it allows you to get an access token and get a 
 * user's UID by username and password. This can obviously be expanded upon to
 * allow access to a host of other Tuya Smart integrations.
 */
class Tuya {

    /**
     * Constructor
     * 
     * @param String $schema The `schema` name provided by Tuya
     * @param String $client_id The `AccessId` supplied by the Cloud Platform application
     * @param String $secret The `Secret` string provided by the Cloud Platform application
     */
    public function __construct($schema, $client_id, $secret) {

        $this->schema = $schema;
        $this->client_id = $client_id;
        $this->secret = $secret;
        $this->base_url = $this->getUrl(2);
        $this->access_token = '';
        $this->refresh_token = '';
        $this->expire_time = '';
        $this->last_request = $this->getTime();
        $this->uid = '';

    }

    /**
     * Get URL
     * Gets the API URL to use as the base. Set by default to EU
     *
     * @return void
     */
    private function getUrl($urlKey = 2) {

        $urls = array(
            'https://openapi.tuyacn.com',
            'https://openapi.tuyaus.com',
            'https://openapi.tuyaeu.com'
        );
        return $urls[$urlKey];
    }

    /**
     * Get Time
     * Gets a 13 digit expression of the current microsecond count.
     * Required for all API calls
     *
     * @return string
     */
    private function getTime() {
        // Get the current microtime string as `microseconds seconds`
        $time_arr = microtime();
        // Add the two numbers together
        $time = array_sum(explode(' ', $time_arr));
        // Multiply by 1000 and return a 13 digit timestamp
        $microtime = $time * 1000;
        return substr($microtime, 0, 13);        
    }

    /**
     * Calc Sign
     *
     * Generates a hashed signature based on API parameters. Required for
     * all API calls.
     * 
     * @param String $clientId
     * @param String $secret
     * @param String $timestamp
     * @return String
     */
    private function calcSign($clientId, $secret, $timestamp) {

        $str = $clientId . $this->access_token . $timestamp;
        $hash = hash_hmac('sha256', $str, $secret);
        $hashInBase64 = (string)$hash;
        $signUp = strtoupper($hashInBase64);
        return $signUp;
    }

    /**
     * Request Token
     * Requests an access token from the Tuya API.
     *
     * @return void
     */
    public function requestToken() {
        
        $result = $this->doRequest('v1.0/token?grant_type=1', 'GET');

        $this->_setToken($result->result->access_token);
        $this->_setRefreshToken($result->result->refresh_token);
        $this->_setExpireTime($result->result->expire_time);
        $this->_setUid($result->result->uid);

    }

    /**
     * Set Token
     *
     * @param String $token
     * @return void
     */
    private function _setToken($token) {
        $this->access_token = $token;
    }

    /**
     * Set Refresh Token
     *
     * @param String $token
     * @return void
     */
    private function _setRefreshToken($token) {
        $this->refresh_token = $token;
    }

    /**
     * Set Expire Time
     *
     * @param String $seconds
     * @return void
     */
    private function _setExpireTime($seconds) {
        $this->expire_time = $seconds;
    }

    /**
     * Set UID
     *
     * @param String $uid
     * @return void
     */
    private function _setUid($uid) {
        $this->uid = $uid;
    }

    /**
     * Get Countries
     * Gets a list of supported countries. 
     * TODO: Implement GeoIP tracking to correctly select a user's
     * country ID from the returned object.
     *
     * @return Object
     */
    public function getCountries() {

        $result = $this->doRequest('v1.0/all-countries', 'GET');

        return $result;

    }

    /**
     * Get User
     * Gets a user's object from the API based on their username and password.
     *
     * @param String $username
     * @param String $password
     * @return Object
     */
    public function getUser($username, $password) {
        // If the username is an email address, set the username type
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $username_type = "2";
        } else {
            $username_type = "1";
        }
        // Create the API endpoint
        $url = "v1.0/apps/$this->schema/user";
        // Define the data to be sent in the request
        $data = array(
            'country_code' => '386',
            'username' => $username,
            'password' => md5($password),
            'username_type' => (string)$username_type
        );

        $result = $this->doRequest($url, 'POST', $data);

        return $result;

    }

    /**
     * Do Request
     * Global API request function, currently able to handle GET and POST requests.
     *
     * @param String $endpoint
     * @param String $type
     * @param Array $data
     * @return Object
     */
    private function doRequest($endpoint, $type = 'GET', $data = array()) {
        // Initialize the cURL request
        $curl = curl_init();
        // Determine the request type and create the appropriate cURL OPTS
        // array.
        switch($type) {
            case 'GET':
                $curlOpts = $this->setGetCurlOpts($endpoint);
            break;
            case 'POST':
                $curlOpts = $this->setPostCurlOpts($endpoint, $data);
            default:
            break;
        }
        // Set the options, execute the request and return the response
        // TODO: Exception and error trapping
        curl_setopt_array($curl, $curlOpts);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
    }

    /**
     * Set POST cURL Opts
     * Creates a cURL Opts array for a cURL POST
     *
     * @param String $endpoint
     * @param Array $data
     * @return Array
     */
    private function setPostCurlOpts($endpoint, $data) {
        // Sets the default headers for the request
        $headersData = $this->setHeaders();
        // Convert data to a JSON string
        $dataString = json_encode($data);
        // Define the endpoint
        $url = "$this->base_url/$endpoint";
        // Assign the variables to the request opts
        $opts = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headersData,
            CURLOPT_POSTFIELDS => $dataString
        );
        return $opts;
    }

    /**
     * Set GET cURL Opts
     * Creates a cURL Opts array for a cURL GET
     *
     * @param String $endpoint
     * @return void
     */
    private function setGetCurlOpts($endpoint) {
        // Convert the variables to header data
        $headersData = $this->setHeaders();
        // Define the endpoint
        $url = "$this->base_url/$endpoint";
        // Assign the variables to the request opts
        // TODO: Handle additional GET data 
        $opts = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headersData
        );
        return $opts;
    }

    /**
     * Set Headers
     * Sets the default headers for an API request
     * 
     * @return Array
     */
    private function setHeaders() {
        // Data required for the token request
        $data = array(
            'client_id' => $this->client_id,
            'sign' => $this->calcSign($this->client_id, $this->secret, $this->getTime()),
            't' => $this->getTime(),
            'sign_method' => 'HMAC-SHA256',
            'Content-Type' => 'application/json'
        );
        // If we have an access token, include it in the request
        if($this->access_token !== '') {
            $data['access_token'] = $this->access_token;
        }
        // Create a $dataHeaders array and assign it with colon separated key value pairs
        $dataHeaders = array();
        foreach($data as $k => $d) {
            $dataHeaders[] = "$k:$d";
        }
        return $dataHeaders;
    }

    
    private function dataArrayToString($data) {

        $dataArr = [];

        foreach($data as $k => $d) {
            $dataArr[] = $k . '=' . $d;
        }

        return implode('&', $dataArr);

    }

}