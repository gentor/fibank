<?php

namespace Gentor\Fibank\Service;


/**
 * Class Ecomm
 * @package Gentor\Fibank\Service
 */
class Ecomm
{
    const SANDBOX_URL = 'https://mdpay-test.fibank.bg';
    const LIVE_URL = 'https://mdpay.fibank.bg';
    const PORT = '10443';

    protected $endpoint;
    protected $certificate_pem;
    protected $certificate_pass;
    protected $ip;
    protected $connect_timeout;
    protected $ssl_verify = true;
    protected $currency = 975; // BGN

    /**
     * Ecomm constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if ($config['live_mode']) {
            $this->endpoint = static::LIVE_URL;
        } else {
            $this->endpoint = static::SANDBOX_URL;
        }

        if (isset($config['ssl_verify'])) {
            $this->ssl_verify = $config['ssl_verify'];
        }

        $this->certificate_pem = $config['certificate'];
        $this->certificate_pass = $config['password'];
    }

    /**
     * @param $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @param $seconds
     */
    public function setConnectTimeout($seconds)
    {
        $this->connect_timeout = $seconds;
    }

    /**
     * @param $amount
     * @param $description
     * @param array $additionalParams
     * @return array
     * @throws EcommException
     */
    public function sendTransaction($amount, $description, $additionalParams = [])
    {
        $params = array_merge([
            'command' => 'v',
            'amount' => $amount,
            'currency' => $this->currency,
            'client_ip_addr' => $this->ip,
            'description' => $description,
        ], $additionalParams);

        return $this->sendRequest($params);
    }

    /**
     * @param $trans_id
     * @param null $amount
     * @return array
     * @throws EcommException
     */
    public function refundTransaction($trans_id, $amount = null)
    {
        $params = [
            'command' => 'k',
            'trans_id' => $trans_id,
            'amount' => $amount,
            'client_ip_addr' => $this->ip,
        ];

        return $this->sendRequest($params);
    }

    /**
     * @param $trans_id
     * @return array
     * @throws EcommException
     */
    public function checkTransactionStatus($trans_id)
    {
        $params = [
            'command' => 'c',
            'trans_id' => $trans_id,
            'client_ip_addr' => $this->ip,
        ];

        return $this->sendRequest($params);
    }

    /**
     * @param $trans_id
     * @return string
     */
    public function getRedirectUrl($trans_id)
    {
        return $this->endpoint . '/ecomm_v2/ClientHandler?trans_id=' . urlencode($trans_id);
    }

    /**
     * @param $params
     * @return array
     * @throws EcommException
     */
    protected function sendRequest($params)
    {
        $url = $this->endpoint . ':' . static::PORT . '/ecomm_v2/MerchantHandler';

        $ch = curl_init();

        $tempPemFile = tmpfile();
        fwrite($tempPemFile, $this->certificate_pem);
        $tempPemPath = stream_get_meta_data($tempPemFile);
        $tempPemPath = $tempPemPath['uri'];

        curl_setopt($ch, CURLOPT_SSLCERT, $tempPemPath);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certificate_pass);

        if ($this->endpoint == self::SANDBOX_URL) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/ca/test.pem');
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/ca/live.pem');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout ?: 0);

        $result = curl_exec($ch);
        fclose($tempPemFile);

        if ($error = curl_error($ch)) {
            curl_close($ch);
            throw new EcommException($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 != $http_code) {
            curl_close($ch);
            throw new EcommException('Error: ' . $http_code, $http_code);
        }

        curl_close($ch);

        $response = [];

        if (substr($result, 0, 5) == 'error') {
            $error = substr($result, 6);
            throw new EcommException($error);
        } else {
            foreach (explode("\n", $result) as $nvp) {
                list($key, $value) = explode(': ', $nvp);
                $response[$key] = $value;
            }
        }

        return $response;
    }

}