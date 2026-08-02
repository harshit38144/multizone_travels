<?php

/**
 * Minimal PhonePe Standard Checkout client (OAuth + create pay + order status).
 * Docs: https://developer.phonepe.com/payment-gateway/website-integration/standard-checkout/
 */
class PhonePeCheckout
{
    /** @var array<string,mixed> */
    private $cfg;

    public function __construct()
    {
        $path = __DIR__ . '/phonepe_config.php';
        if (!is_readable($path)) {
            throw new RuntimeException('PhonePe config missing: includes/phonepe_config.php');
        }
        $this->cfg = require $path;
    }

    public function isSandbox(): bool
    {
        return ($this->cfg['mode'] ?? 'sandbox') === 'sandbox';
    }

    public function oauthUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token'
            : 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token';
    }

    public function payUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay'
            : 'https://api.phonepe.com/apis/pg/checkout/v2/pay';
    }

    public function orderStatusUrl(string $merchantOrderId): string
    {
        $enc = rawurlencode($merchantOrderId);
        return $this->isSandbox()
            ? "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/order/{$enc}/status"
            : "https://api.phonepe.com/apis/pg/checkout/v2/order/{$enc}/status";
    }

    /**
     * @return array{ok:bool, access_token?:string, error?:string}
     */
    public function getAccessToken(): array
    {
        $body = http_build_query([
            'client_id' => (string) ($this->cfg['client_id'] ?? ''),
            'client_version' => (string) ($this->cfg['client_version'] ?? '1'),
            'client_secret' => (string) ($this->cfg['client_secret'] ?? ''),
            'grant_type' => 'client_credentials',
        ], '', '&', PHP_QUERY_RFC3986);

        $res = $this->httpPostForm($this->oauthUrl(), $body);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'OAuth request failed'];
        }
        $data = $res['json'];
        if (!is_array($data) || empty($data['access_token'])) {
            $msg = is_array($data) ? ($data['message'] ?? json_encode($data)) : 'Invalid OAuth response';
            return ['ok' => false, 'error' => $msg];
        }
        return ['ok' => true, 'access_token' => (string) $data['access_token']];
    }

    /**
     * @param array<string,string> $metaInfo udf1.. keys for PhonePe metaInfo
     * @return array{ok:bool, redirectUrl?:string, orderId?:string, state?:string, raw?:mixed, error?:string}
     */
    public function createPayment(string $merchantOrderId, int $amountPaisa, string $redirectUrl, array $metaInfo = []): array
    {
        $token = $this->getAccessToken();
        if (!$token['ok']) {
            return $token;
        }
        $access = $token['access_token'];

        $paymentFlow = [
            'type' => 'PG_CHECKOUT',
            'message' => 'Multizone Travels',
            'merchantUrls' => [
                'redirectUrl' => $redirectUrl,
            ],
            // V2 schema: INTENT + QR + COLLECT so mobile UPI apps work (V1 UPI_INTENT keys are ignored on V2).
            'paymentModeConfig' => [
                'version' => 'V2',
                'enabledPaymentModes' => [
                    [
                        'type' => 'UPI',
                        'flows' => ['INTENT', 'QR', 'COLLECT'],
                    ],
                    [
                        'type' => 'CARD',
                        'types' => ['DEBIT_CARD', 'CREDIT_CARD'],
                    ],
                    ['type' => 'NET_BANKING'],
                ],
            ],
        ];

        $payload = [
            'merchantOrderId' => $merchantOrderId,
            'amount' => $amountPaisa,
            'expireAfter' => 1800,
            'paymentFlow' => $paymentFlow,
        ];
        if ($metaInfo !== []) {
            $payload['metaInfo'] = $metaInfo;
        }

        $res = $this->httpPostJson($this->payUrl(), $payload, $access);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Create payment failed'];
        }
        $data = $res['json'];
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Invalid JSON from PhonePe'];
        }
        if (!empty($data['redirectUrl'])) {
            return [
                'ok' => true,
                'redirectUrl' => (string) $data['redirectUrl'],
                'orderId' => isset($data['orderId']) ? (string) $data['orderId'] : '',
                'state' => isset($data['state']) ? (string) $data['state'] : '',
                'raw' => $data,
            ];
        }
        $err = ($data['message'] ?? $data['code'] ?? json_encode($data));
        return ['ok' => false, 'error' => is_string($err) ? $err : 'Unknown error', 'raw' => $data];
    }

    /**
     * @return array{ok:bool, json?:array|null, error?:string}
     */
    public function getOrderStatus(string $merchantOrderId): array
    {
        $token = $this->getAccessToken();
        if (!$token['ok']) {
            return $token;
        }
        $url = $this->orderStatusUrl($merchantOrderId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: O-Bearer ' . $token['access_token'],
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'error' => $err ?: 'cURL error'];
        }
        $json = json_decode($body, true);
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => is_array($json) ? ($json['message'] ?? $body) : $body];
        }
        return ['ok' => true, 'json' => is_array($json) ? $json : null];
    }

    /**
     * @return array{ok:bool, json?:array|null, error?:string}
     */
    private function httpPostForm(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['ok' => false, 'error' => $cerr ?: 'Network error'];
        }
        $json = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($json) ? ($json['message'] ?? $json['code'] ?? $resp) : $resp;
            return ['ok' => false, 'error' => is_string($msg) ? $msg : 'HTTP ' . $code, 'json' => is_array($json) ? $json : null];
        }
        return ['ok' => true, 'json' => is_array($json) ? $json : null];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool, json?:array|null, error?:string}
     */
    private function httpPostJson(string $url, array $payload, string $accessToken): array
    {
        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($jsonBody === false) {
            return ['ok' => false, 'error' => 'JSON encode failed'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: O-Bearer ' . $accessToken,
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['ok' => false, 'error' => $cerr ?: 'Network error'];
        }
        $json = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($json) ? ($json['message'] ?? $json['code'] ?? $resp) : $resp;
            return ['ok' => false, 'error' => is_string($msg) ? $msg : 'HTTP ' . $code, 'json' => is_array($json) ? $json : null];
        }
        return ['ok' => true, 'json' => is_array($json) ? $json : null];
    }
}
