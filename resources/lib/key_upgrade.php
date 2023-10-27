<?php


use AmazonPayApiSdkExtension\Client\KeyUpgradeClient;
use phpseclib3\Crypt\RSA;

$merchantId = SdkRestApi::getParam('merchantId');
$accessKeyId = SdkRestApi::getParam('accessKeyId');
$secretKey = SdkRestApi::getParam('secretKey');
$privateKeyObject = RSA::createKey(2048);
$privateKey = $privateKeyObject->toString('PKCS1');
/** @var RSA\PublicKey $publicKeyObject */
$publicKeyObject = $privateKeyObject->getPublicKey();
$publicKey = $publicKeyObject->toString('PKCS8');

$keyUpgradeClient = new KeyUpgradeClient();
try {
    $publicKeyId = $keyUpgradeClient->fetchPublicKeyId(
        $merchantId,
        $accessKeyId,
        $secretKey,
        $publicKey
    );
} catch (\Exception $e) {
    return [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString(),
        'data' => [
            $merchantId,
            $accessKeyId,
            $secretKey,
            $publicKey,
            'https://' . KeyUpgradeClient::API_DOMAIN . KeyUpgradeClient::API_PATH
        ]
    ];
}

return [
    'publicKeyId' => $publicKeyId,
    'privateKey' => $privateKey,
];