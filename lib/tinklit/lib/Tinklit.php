<?php
namespace Tinklit;

class Tinklit
{
    const VERSION           = '1.2.2';
    const USER_AGENT_ORIGIN = 'Tinkl.it PHP Library';

    public static $client_id  = '';
	public static $token  = '';
    public static $environment = '';
    public static $user_agent  = '';
    public static $curlopt_ssl_verifypeer = FALSE;

    public static function config($authentication)
    {	
        if (isset($authentication['client_id']))
            self::$client_id = $authentication['client_id'];
		
	if (isset($authentication['token']))
            self::$token = $authentication['token'];

        if (isset($authentication['environment']))
            self::$environment = $authentication['environment'];

        if (isset($authentication['user_agent']))
            self::$user_agent = $authentication['user_agent'];
    }

    public static function testConnection($authentication = array())
    {
        try {
            self::request('/auth/test', 'GET', array(), $authentication);
            return true;
        } catch (\Exception $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
    }

    public static function request($url, $method = 'POST', $params = array(), $authentication = array())
    {
	$client_id  = isset($authentication['client_id']) ? $authentication['client_id'] : self::$client_id;
	$token  = isset($authentication['token']) ? $authentication['token'] : self::$token;
        $environment = isset($authentication['environment']) ? $authentication['environment'] : self::$environment;
        $user_agent  = isset($authentication['user_agent']) ? $authentication['user_agent'] : (isset(self::$user_agent) ? self::$user_agent : (self::USER_AGENT_ORIGIN . ' v' . self::VERSION));
        $curlopt_ssl_verifypeer = isset($authentication['curlopt_ssl_verifypeer']) ? $authentication['curlopt_ssl_verifypeer'] : self::$curlopt_ssl_verifypeer;

	# Check if credentials was passed
        if (empty($client_id))
            \Tinklit\Exception::throwException(400, array('reason' => 'CredentialsMissing', 'message' => 'Set up your Client ID on plugin\'s settings'));
		
	if (empty($token))
            \Tinklit\Exception::throwException(400, array('reason' => 'CredentialsMissing', 'message' => 'Set up your Token on plugin\'s settings'));

        # Check if right environment passed
        $environments = array('live', 'staging');

        if (!in_array($environment, $environments)) {
            $availableEnvironments = join(', ', $environments);
            \Tinklit\Exception::throwException(400, array('reason' => 'BadEnvironment', 'message' => "Environment does not exist. Available environments: $availableEnvironments"));
        }

        $url       = ($environment === 'staging' ? 'https://api-staging.tinkl.it/v1' : 'https://api.tinkl.it/v1') . $url;
        $headers   = array();
		$headers[] = 'X-CLIENT-ID:' . $client_id;
		$headers[] = 'X-AUTH-TOKEN: ' . $token;
		
        $curl      = curl_init();

        $curl_options = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL            => $url
        );

        if ($method == 'POST') {
            $headers[] = 'Content-Type: application/json';
            array_merge($curl_options, array(CURLOPT_POST => 1));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        curl_setopt_array($curl, $curl_options);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $curlopt_ssl_verifypeer);
		
        $response    = json_decode(curl_exec($curl), TRUE);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
	/*
	if ($http_status === 200)
            return $response;
        else
            \Tinklit\Exception::throwException($http_status, $response);
	*/
		
        if (array_key_exists('guid', $response)){
            return $response;
        }
        else {
            \Tinklit\Exception::throwException($http_status, $response);
        }
		
    }
}
