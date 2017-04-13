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
    const PORT = '9443';

    protected $endpoint;
    protected $certificate_pem;
    protected $certificate_pass;
    protected $ip;
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
     * @param $amount
     * @param $description
     * @return array
     */
    public function sendTransaction($amount, $description)
    {
        $params = [
            'command' => 'v',
            'amount' => $amount,
            'currency' => $this->currency,
            'client_ip_addr' => $this->ip,
            'description' => $description,
        ];

        return $this->sendRequest($params);
    }

    /**
     * @param $trans_id
     * @return array
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
        return $this->endpoint . '/ecomm/ClientHandler?trans_id=' . urlencode($trans_id);
    }

    /**
     * @param $params
     * @return array
     * @throws EcommException
     */
    protected function sendRequest($params)
    {
        $url = $this->endpoint . ':' . static::PORT . '/ecomm/MerchantHandler';

        $ch = curl_init();

        $tempPemFile = tmpfile();
        fwrite($tempPemFile, $this->certificate_pem);
        $tempPemPath = stream_get_meta_data($tempPemFile);
        $tempPemPath = $tempPemPath['uri'];

        curl_setopt($ch, CURLOPT_SSLCERT, $tempPemPath);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certificate_pass);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

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