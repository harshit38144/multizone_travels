<?php

/**
 * PayU hosted payment page (hash-based checkout).
 * Docs: https://docs.payu.in/docs/collect-payments-online
 */
class PayUCheckout
{
    /** @var array<string,mixed> */
    private $cfg;

    public function __construct()
    {
        $path = __DIR__ . '/payu_config.php';
        if (!is_readable($path)) {
            throw new RuntimeException('PayU config missing: includes/payu_config.php');
        }
        $this->cfg = require $path;
    }

    public function isSandbox(): bool
    {
        return ($this->cfg['mode'] ?? 'sandbox') !== 'production';
    }

    public function merchantKey(): string
    {
        return trim((string) ($this->cfg['merchant_key'] ?? ''));
    }

    public function merchantSalt(): string
    {
        return trim((string) ($this->cfg['merchant_salt'] ?? ''));
    }

    public function isConfigured(): bool
    {
        return $this->merchantKey() !== '' && $this->merchantSalt() !== '';
    }

    public function paymentEndpoint(): string
    {
        return $this->isSandbox()
            ? 'https://test.payu.in/_payment'
            : 'https://secure.payu.in/_payment';
    }

    public function verifyApiEndpoint(): string
    {
        return $this->isSandbox()
            ? 'https://test.payu.in/merchant/postservice?form=2'
            : 'https://info.payu.in/merchant/postservice?form=2';
    }

    /**
     * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
     * @return array<string,string>
     */
    public function buildPaymentFields(
        string $txnid,
        float $amountInr,
        array $customer,
        string $productinfo,
        string $successUrl,
        string $failureUrl
    ): array {
        $key = $this->merchantKey();
        $salt = $this->merchantSalt();
        $amount = number_format($amountInr, 2, '.', '');
        $firstname = substr(trim($customer['name']), 0, 60);
        $email = substr(trim($customer['email']), 0, 60);
        $phone = preg_replace('/\D/', '', (string) ($customer['mobile'] ?? ''));
        $udf1 = substr($firstname, 0, 255);
        $udf2 = substr($email, 0, 255);
        $udf3 = substr($phone, 0, 255);
        $udf4 = substr(trim((string) ($customer['remarks'] ?? '')), 0, 255);
        $udf5 = '';

        $hashString = $key . '|' . $txnid . '|' . $amount . '|' . $productinfo . '|'
            . $firstname . '|' . $email . '|' . $udf1 . '|' . $udf2 . '|' . $udf3 . '|' . $udf4 . '|' . $udf5
            . '||||||' . $salt;
        $hash = strtolower(hash('sha512', $hashString));

        return [
            'key' => $key,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => $successUrl,
            'furl' => $failureUrl,
            'udf1' => $udf1,
            'udf2' => $udf2,
            'udf3' => $udf3,
            'udf4' => $udf4,
            'udf5' => $udf5,
            'hash' => $hash,
            'service_provider' => 'payu_paisa',
        ];
    }

    /**
     * @param array<string,mixed> $post
     */
    public function verifyReturnHash(array $post): bool
    {
        $salt = $this->merchantSalt();
        $key = $this->merchantKey();
        if ($salt === '' || $key === '') {
            return false;
        }

        $status = (string) ($post['status'] ?? '');
        $txnid = (string) ($post['txnid'] ?? '');
        $amount = (string) ($post['amount'] ?? '');
        $productinfo = (string) ($post['productinfo'] ?? '');
        $firstname = (string) ($post['firstname'] ?? '');
        $email = (string) ($post['email'] ?? '');
        $udf1 = (string) ($post['udf1'] ?? '');
        $udf2 = (string) ($post['udf2'] ?? '');
        $udf3 = (string) ($post['udf3'] ?? '');
        $udf4 = (string) ($post['udf4'] ?? '');
        $udf5 = (string) ($post['udf5'] ?? '');
        $postedHash = strtolower((string) ($post['hash'] ?? ''));

        $hashString = $salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|'
            . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
        $calc = strtolower(hash('sha512', $hashString));

        return $postedHash !== '' && hash_equals($calc, $postedHash);
    }

    /**
     * @return array{ok:bool, json?:array|null, error?:string}
     */
    public function verifyTransaction(string $txnid): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'PayU merchant key/salt not configured.'];
        }

        $key = $this->merchantKey();
        $salt = $this->merchantSalt();
        $command = 'verify_payment';
        $hash = strtolower(hash('sha512', $key . '|' . $command . '|' . $txnid . '|' . $salt));

        $body = http_build_query([
            'key' => $key,
            'command' => $command,
            'var1' => $txnid,
            'hash' => $hash,
        ]);

        $ch = curl_init($this->verifyApiEndpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => $cerr ?: 'PayU verify request failed.'];
        }

        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Invalid PayU verify response.'];
        }

        return ['ok' => true, 'json' => $json];
    }

    public static function verifyStatusIsSuccess(?array $json, string $txnid): bool
    {
        if (!is_array($json)) {
            return false;
        }
        $details = $json['transaction_details'][$txnid] ?? null;
        if (!is_array($details)) {
            return false;
        }
        $status = strtoupper(trim((string) ($details['status'] ?? '')));
        return in_array($status, ['SUCCESS', 'CAPTURED'], true);
    }
}
