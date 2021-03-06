<?php

namespace Miaoxing\Tencentyun\Service;

use Guzzle\Common\Exception\GuzzleException;
use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;

/**
 * 腾讯云对象存储服务V5
 *
 * @link https://github.com/tencentyun/cos-php-sdk-v5
 */
class TencentyunCosV5 extends Tencentyun
{
    /**
     * 应用ID
     */
    protected $appId;

    /**
     * @var string
     */
    protected $secretId;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var string
     */
    protected $region;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        // TODO 被 File::setAppId 覆盖了
        $this->appId = $options['appId'];
    }

    public function signUrl($url, $seconds = 60)
    {
        if (!$url) {
            return $url;
        }

        if ($this->domain) {
            return $url . '?sign=' . $this->generateSign($seconds);
        }

        return $this->getClient()->getObjectUrl($this->bucket, $url, $seconds);
    }

    protected function generateSign($seconds)
    {
        $appId = $this->appId;
        $bucket = $this->bucket;
        $secretId = $this->secretId;
        $secretKey = $this->secretKey;
        $current = time();
        $expired = $current + $seconds;
        $rdm = rand();

        $sign = 'a=' . $appId . '&b=' . $bucket . '&k=' . $secretId
            . '&e=' . $expired . '&t=' . $current . '&r=' . $rdm . '&f=';
        $sign = base64_encode(hash_hmac('SHA1', $sign, $secretKey, true) . $sign);

        return $sign;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new Client([
                'region' => $this->region,
                'credentials' => [
                    'appId' => $this->appId,
                    'secretId' => $this->secretId,
                    'secretKey' => $this->secretKey,
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function write($file, $ext = '', $customName = '')
    {
        if ($this->isVoiceExt($this->getExt($file, $ext))) {
            return $this->processWriteForVoice($file, $ext, $customName);
        } else {
            return $this->processWrite($file, $ext, $customName);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function callUploadApi($file, $customName)
    {
        !$customName && $customName = $this->getFileUrl($file);

        try {
            /** @var \Guzzle\Service\Resource\Model $result */
            $result = $this->getClient()->putObject([
                'Bucket' => $this->bucket,
                'Key' => $customName,
                'Body' => fopen($file, 'rb'),
            ]);
            if ($result->get('ObjectURL')) {
                return [
                    'code' => 0, // 兼容已有的接口 TODO 更改为ret
                    'url' => $this->domain . '/' . $customName,
                ];
            } else {
                return $this->err([
                    'message' => '请求失败',
                    'result' => $result,
                ]);
            }
        } catch (ServiceResponseException $e) {
            return $this->err($e->getMessage(), $e->getStatusCode());
        } catch (GuzzleException $e) {
            return $this->err($e->getMessage(), $e->getCode());
        }
    }
}
