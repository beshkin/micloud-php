<?php


namespace Beshkin\MicloudPhp\MiCloud;


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use TrafficCophp\ByteBuffer\Buffer;

class MiCloudClient
{
    private Client $client;
    private array $config;
    private string $agent_id;
    private string $userAgent;
    private string $client_id;
    private mixed $ssecurity;
    private mixed $userId;
    private string $serviceToken;
    private string $locale = 'en';

    public function __construct($config)
    {
        $this->client = new Client(
            [
                'timeout' => 15.0,
            ]
        );
        $this->config = $config;
        $this->agent_id = $this->generateRandomString(13, 'ABCDEF');
        $this->userAgent = 'Android-7.1.1-1.0.0-ONEPLUS A3010-136-' .
            $this->agent_id
            . ' APP/xiaomi.smarthome APPV/62830';
        $this->client_id = strtoupper($this->generateRandomString(6));
    }

    /**
     * @param $deviceId
     * @param $method
     * @param $params
     * @return array|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function miioCall($deviceId, $method, $params): mixed
    {
        $request = [
            'method' => $method,
            'params' => $params
        ];
        $data = $this->request('/home/rpc/' . $deviceId, $request);
        return $data;
    }

    /**
     * @param $path
     * @param $data
     * @return array|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($path, $data): mixed
    {
        $url = $this->getApiUrl($this->config['country']) . $path;
        $params = [
            'data' => json_encode($data),
        ];

        $nonce = $this->generateNonce();
        $signedNonce = $this->signedNonce($this->ssecurity, $nonce);
        $signature = $this->generateSignature($path, $signedNonce, $nonce, $params);

        $body = [
            '_nonce' => $nonce,
            'data' => $params['data'],
            'signature' => $signature
        ];

        $options = [
            RequestOptions::BODY => http_build_query($body),
            RequestOptions::HEADERS => [
                'User-Agent' => $this->userAgent,
                'x-xiaomi-protocal-flag-cli' => 'PROTOCAL-HTTP2',
                'mishop-client-id' => '180100041079',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Cookie' => implode(
                    '; ',
                    [
                        'sdkVersion=accountsdk-18.8.15',
                        'deviceId=' . $this->client_id,
                        'userId=' . $this->userId,
                        'yetAnotherServiceToken=' . $this->serviceToken,
                        'serviceToken=' . $this->serviceToken,
                        'locale=' . $this->locale,
                        'channel=MI_APP_STORE'
                    ]
                ),
            ]
        ];

        $response = $this->client->request(
            'POST',
            $url,
            $options
        );

        $result = json_decode($response->getBody()->getContents());
        if ($result->message == 'ok') {
            return $result;
        }
        return [];
    }

    /**
     * @return bool
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function login(): bool
    {
        $sign = $this->loginStep1();
        $loginData = $this->loginStep2($sign);
        $this->serviceToken = $this->loginStep3(
            preg_match('/.*http.*/', $loginData['location']) ? $loginData['location'] : $sign
        );

        return true;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function loginStep1(): mixed
    {
        $url = 'https://account.xiaomi.com/pass/serviceLogin?sid=xiaomiio&_json=true';
        $response = file_get_contents($url);
        if (!$response) {
            throw new \Exception('Response step 1 error ');
        }
        $data = $this->parseJson($response);
        return $data['_sign'];
    }

    /**
     * @param $sign
     * @return mixed
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function loginStep2($sign): mixed
    {
        $formData = [
            'hash' => strtoupper(md5($this->config['password'])),
            '_json' => true,
            'sid' => 'xiaomiio',
            'callback' => 'https://sts.api.io.mi.com/sts',
            'qs' => '%3Fsid%3Dxiaomiio%26_json%3Dtrue',
            '_sign' => $sign,
            'user' => $this->config['login'],
        ];
        $url = 'https://account.xiaomi.com/pass/serviceLoginAuth2';
        $response = $this->client->request(
            'POST',
            $url,
            [
                RequestOptions::BODY => http_build_query($formData),
                RequestOptions::HEADERS => [
                    'User-Agent' => $this->userAgent,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cookie' => implode('; ', [
                        'sdkVersion=accountsdk-18.8.15',
                        'deviceId=' . $this->client_id . ';'
                    ])
                ]
            ]
        );
        if ($response->getStatusCode() != 200) {
            throw new \Exception('Response step 2 error with status ' . $response->getStatusCode());
        }
        $data = $this->parseJson($response->getBody());
        if ($data['result'] != 'ok') {
            throw new \Exception('Response step 2 error - result is not ok ' . json_encode($data));
        }
        $this->ssecurity = $data['ssecurity'];
        $this->userId = $data['userId'];
        $location = $data['location'];

        if (!$this->ssecurity || !$this->userId || !$location) {
            throw new \Exception('Login step 2 failed');
        }

        return $data;
    }

    /**
     * @param $string
     * @return mixed
     */
    private function parseJson($string): mixed
    {
        if (str_contains($string, '&&&START&&&')) {
            $string = str_replace('&&&START&&&', '', $string);
        }

        return json_decode($string, true);
    }

    /**
     * @param int $length
     * @param string $characters
     * @return string
     */
    private function generateRandomString(
        int $length = 10,
        string $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ): string {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param string $location
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function loginStep3(string $location): string
    {
        $url = $location;
        $response = $this->client->get($url);
        $cookies = $response->getHeaders()['Set-Cookie'];
        $serviceToken = '';
        foreach ($cookies as $cookieLine) {
            foreach (explode(';', $cookieLine) as $cookie) {
                list($key, $value) = explode('=', $cookie);
                if ($key == 'serviceToken') {
                    $serviceToken = $value . '=';
                }
            }
        }
        return $serviceToken;
    }

    /**
     * @param mixed $country
     * @return string
     */
    private function getApiUrl(mixed $country): string
    {
        $country = strtolower($country);
        return sprintf(
            'https://%sapi.io.mi.com/app',
            $country === 'cn' ? '' : $country . '.'
        );
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function generateNonce(): string
    {
        $buffer = new Buffer(12);
        $buffer->write(bin2hex(random_bytes(4)), 0); //expected 8 symbols
        $buffer->writeInt32BE((int)(time() / 60000), 8);
        return base64_encode($buffer);
    }

    /**
     * @param mixed $ssecret
     * @param string $nonce
     * @return string
     */
    private function signedNonce(mixed $ssecret, string $nonce): string
    {
        $ssecretDecoded = base64_decode($ssecret, true);
        $nonceDecoded = base64_decode($nonce, true);

        return base64_encode(hash('sha256', $ssecretDecoded . $nonceDecoded, true));
    }

    /**
     * @param $path
     * @param string $signedNonce
     * @param string $nonce
     * @param array $params
     * @return string
     */
    private function generateSignature($path, string $signedNonce, string $nonce, array $params): string
    {
        $exps = [
            $path,
            $signedNonce,
            $nonce
        ];

        foreach ($params as $key => $value) {
            $exps[] = $key . '=' . $value;
        }

        return base64_encode(hash_hmac('sha256', implode('&', $exps), base64_decode($signedNonce), true));
    }
}
