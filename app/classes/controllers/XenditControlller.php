<?php
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('fileExists')) {
    function fileExists($file="") {
        for ($i=0; $i<10 && !file_exists($file); $i++) {
            $file = "../" . $file;
        } return $file;
    }
}


include_once fileExists('app/classes/models/XenditModel.php');

class XenditController extends XenditModel {

    private $XENDIT_API_URL;
    private $XENDIT_CARD_API_URL;
    private $XENDIT_SECRET_KEY;
    private $XENDIT_CALLBACK_TOKEN;
    private $BASE_URL;

    public function __construct() {
        parent::__construct();

        $this->XENDIT_API_URL = getenv('XENDIT_API_URL') ?: '';
        $this->XENDIT_CARD_API_URL = getenv('XENDIT_CARD_API_URL') ?: '';
        $this->XENDIT_SECRET_KEY = getenv('XENDIT_SECRET_KEY') ?: '';
        $this->XENDIT_CALLBACK_TOKEN = getenv('XENDIT_CALLBACK_TOKEN') ?: '';
        $this->BASE_URL = rtrim(getenv('BASE_URL') ?: '', '/');
    }

    private function respond($status, $message, $data = []) {
        $redirectUrl = $data['redirect_url'] ?? '';
        $referenceId = $data['reference_id'] ?? '';

        if ($redirectUrl !== '' && $referenceId !== '') {
            $this->saveSystemLog(json_encode(array_merge(['status' => $status,'reply' => $message], $data)));
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        }
        
        $this->saveSystemLog(json_encode(array_merge(['status' => $status,'reply' => $message], $data)));
        echo json_encode(array_merge(['status' => $status,'reply' => $message], $data));
        exit;
    }

    public function createPayment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(false, 'Invalid request method.');
        }

        if ($this->XENDIT_SECRET_KEY === '' || $this->BASE_URL === '') {
            $this->respond(false, 'Xendit configuration is incomplete.');
        }

        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
        $paymentMethod = strtoupper(trim($_POST['payment_method'] ?? ''));
        $walletNumber = trim($_POST['wallet_number'] ?? '');
        $walletConfirmed = $_POST['wallet_confirmed'] ?? '';

        $allowedMethods = ['CARDS', 'GCASH', 'PAYMAYA'];

        if ($amount <= 0) {
            $this->respond(false, 'Invalid payment amount.');
        }

        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $this->respond(false, 'Invalid payment method.');
        }

        if (in_array($paymentMethod, ['GCASH', 'PAYMAYA'], true)) {
            if ($walletNumber === '' || $walletConfirmed !== '1') {
                $this->respond(false, 'Please confirm your wallet number.');
            }
        }

        $referenceId = 'ORDER-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
        if (!$this->createOrder($referenceId,$amount,$paymentMethod,$walletNumber !== '' ? $walletNumber : null)) {
            $this->respond(false, 'Could not create the order.');
        }

        $successUrl = $this->BASE_URL . '/success.php?reference_id=' . urlencode($referenceId);
        $failureUrl = $this->BASE_URL . '/failure.php?reference_id=' . urlencode($referenceId);

        $payload = [
            'reference_id' => $referenceId,
            'country' => 'PH',
            'currency' => 'PHP'
        ];

        if ($paymentMethod === 'CARDS') {
            $apiUrl = $this->XENDIT_CARD_API_URL;
            $payload = array_merge($payload, [
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'amount' => $amount,
                'allowed_payment_channels' => ['CARDS'],
                'success_return_url' => $successUrl,
                'cancel_return_url' => $failureUrl
            ]);
        } else {
            $apiUrl = $this->XENDIT_API_URL;
            $payload = array_merge($payload, [
                'type' => 'PAY',
                'request_amount' => $amount,
                'capture_method' => 'AUTOMATIC',
                'channel_code' => $paymentMethod,
                'channel_properties' => [
                    'success_return_url' => $successUrl,
                    'failure_return_url' => $failureUrl,
                    'cancel_return_url' => $failureUrl
                ]
            ]);
        }

        $headers = [
            "Content-Type: application/json",
            "api-version: 2024-11-11",
            'Authorization: Basic ' . base64_encode($this->XENDIT_SECRET_KEY . ':')
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->saveSystemLog('Xendit API connection error: ' . $curlError);
            $this->updateOrderStatus($referenceId, 'FAILED');
            $this->respond(false, 'Could not connect to Xendit.');
        }

        $responseData = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || !is_array($responseData)) {
            $this->saveSystemLog('Xendit API error (' . $httpCode . '): ' . $response);
            $this->updateOrderStatus($referenceId, 'FAILED');
            $this->respond(false, 'Xendit could not create the payment.');
        }

        $paymentId = $responseData['payment_request_id'] ?? $responseData['payment_session_id'] ?? $responseData['session_id'] ?? $responseData['id'] ?? null;
        $redirectUrl = $responseData['payment_link_url'] ?? $responseData['checkout_url'] ?? $responseData['redirect_url'] ?? null;
        if (!$redirectUrl && !empty($responseData['actions']) && is_array($responseData['actions'])) {
            foreach ($responseData['actions'] as $action) {
                if (!empty($action['url']) && (strtoupper($action['url_type'] ?? '') === 'WEB' || strtoupper($action['type'] ?? '') === 'REDIRECT_CUSTOMER' || strtoupper($action['descriptor'] ?? '') === 'WEB_URL')) {
                    $redirectUrl = $action['url'];
                    break;
                }
            }

            if (!$redirectUrl) {
                foreach ($responseData['actions'] as $action) {
                    if (!empty($action['url']) && filter_var($action['url'], FILTER_VALIDATE_URL)) {
                        $redirectUrl = $action['url'];
                        break;
                    }

                    if (strtoupper($action['type'] ?? '') === 'REDIRECT_CUSTOMER' && !empty($action['value']) && filter_var($action['value'], FILTER_VALIDATE_URL)) {
                        $redirectUrl = $action['value'];
                        break;
                    }
                }
            }
        }

        if (!$paymentId || !$redirectUrl) {
            $this->saveSystemLog('Unexpected Xendit response: ' . json_encode(['http_code' => $httpCode,'response' => $responseData]));
            $this->updateOrderStatus($referenceId, 'FAILED');
            $this->respond(false, 'Xendit did not return a valid checkout URL.');
        }

        if (!$this->updateOrderPaymentId($referenceId, $paymentId)) {
            $this->saveSystemLog('Could not save Xendit payment ID for order: ' . $referenceId);
            $this->respond(false, 'Payment was created, but the order could not be updated.');
        }

        $this->saveSystemLog('Xendit payment created for order: ' . $referenceId);
        $this->respond(true, 'Payment created successfully.', ['reference_id' => $referenceId,'redirect_url' => $redirectUrl]);
    }

    public function webhook() {
        if ($this->XENDIT_CALLBACK_TOKEN === '') {
            http_response_code(500);
            $this->respond(false, 'Webhook configuration is incomplete.');
        }

        $callbackToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';

        if (!hash_equals($this->XENDIT_CALLBACK_TOKEN, $callbackToken)) {
            http_response_code(401);
            $this->respond(false, 'Invalid callback token.');
        }

        $rawBody = file_get_contents('php://input');
        $eventData = json_decode($rawBody, true);

        if (!is_array($eventData)) {
            http_response_code(400);
            $this->respond(false, 'Invalid webhook payload.');
        }

        $referenceId = $eventData['data']['reference_id'] ?? $eventData['reference_id'] ?? null;
        $event = strtolower($eventData['event'] ?? '');
        $status = strtoupper($eventData['data']['status'] ?? $eventData['status'] ?? '');

        if (!$referenceId) {
            http_response_code(400);
            $this->respond(false, 'Missing order reference.');
        }

        if ($status === 'SUCCEEDED' || $status === 'COMPLETED' || $event === 'payment.capture' || $event === 'payment.succeeded' || $event === 'payment_session.completed') {
            $this->updateOrderStatus($referenceId, 'PAID');
        } elseif ($status === 'FAILED' || $event === 'payment.failed' || $event === 'payment.failure' ) {
            $this->updateOrderStatus($referenceId, 'FAILED');
        }

        echo json_encode(['status' => true, 'reply' => 'Webhook received.']);
        exit;
    }
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $Xendit = new XenditController();
    
    if ($action == 'createPayment') {
        $Xendit->createPayment();
        exit;
    }

    if ($action === 'webhook') {
        $Xendit->webhook();
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => false,'reply' => 'Invalid action.']);
    exit;
}

http_response_code(400);
echo json_encode(["status" => false, "reply" => "Invalid request."]);