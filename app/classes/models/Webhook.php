<?php

if (!function_exists('fileExists')) {
    function fileExists($file="") {
        for ($i=0; $i<10 && !file_exists($file); $i++) {
            $file = "../" . $file;
        } return $file;
    }
}

include_once fileExists('app/classes/models/Extras.php');

class Webhook extends Extras {
    public function getOrderByReference($referenceId = '', $paymentId = '') {
        $referenceId = trim($referenceId);
        $paymentId = trim($paymentId);
        if ($referenceId === '' && $paymentId === '') {
            return false;
        }
        if ($referenceId !== '') {
            $referenceId = $this->db->real_escape_string($referenceId);
            $sql = "SELECT reference_id, status, amount, xendit_payment_id FROM orders
                    WHERE reference_id = '$referenceId' LIMIT 1";
        } else {
            $paymentId = $this->db->real_escape_string($paymentId);
            $sql = "SELECT reference_id, status, amount, xendit_payment_id FROM orders
                    WHERE xendit_payment_id = '$paymentId' LIMIT 1";
        }
        $result = $this->db->query($sql);
        if (!$result) {
            error_log('Xendit webhook lookup error: ' . $this->db->error);
            return false;
        }
        return $result->fetch_assoc();
    }

    public function updateOrderStatus($referenceId, $status) {
        $referenceId = trim($referenceId);
        $status = strtoupper(trim($status));
        if ($referenceId === '' || !in_array($status, ['PAID', 'FAILED'], true)) {
            return false;
        }

        $referenceId = $this->db->real_escape_string($referenceId);
        if ($status === 'PAID') {
            $sql = "UPDATE orders SET status = 'PAID', paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP)
                    WHERE reference_id = '$referenceId' AND status = 'PENDING'";
        } else {
            $sql = "UPDATE orders SET status = 'FAILED' WHERE reference_id = '$referenceId' AND status = 'PENDING'";
        }
        if (!$this->db->query($sql)) {
            error_log('Xendit webhook update error: ' . $this->db->error);
            return false;
        }
        return true;
    }

    public function process() {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            return $this->respond(405,false,'Method not allowed.');
        }

        $callbackToken = getenv('XENDIT_CALLBACK_TOKEN') ?: '';
        if ($callbackToken === '') {
            error_log('Xendit webhook: callback token is not configured.');
            return $this->respond(500,false,'Webhook configuration error.');
        }

        $receivedToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
        if ($receivedToken === '' || !hash_equals($callbackToken, $receivedToken)) {
            return $this->respond(401,false,'Invalid callback token.');
        }

        $rawBody = file_get_contents('php://input');
        $eventData = json_decode($rawBody, true);
        if (!is_array($eventData)) {
            return $this->respond(400,false,'Invalid webhook payload.');
        }

        $data = $eventData['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $event = strtolower(trim($eventData['event'] ?? ''));

        $referenceId = trim($data['reference_id'] ?? $eventData['reference_id'] ?? '');
        $paymentId = trim($data['payment_request_id'] ?? $data['session_id'] ?? $data['payment_id'] ?? $data['id'] ?? '');
        $paymentStatus = strtoupper(trim($data['status'] ?? $eventData['status'] ?? ''));
        if ($referenceId === '' && $paymentId === '') {
            return $this->respond(400,false,'Missing payment reference.');
        }

        $successfulEvents = [
            'payment.succeeded',
            'payment.capture',
            'payment.captured',
            'payment_request.succeeded',
            'payment_request.completed',
            'payment_captured'
        ];

        $failedEvents = [
            'payment.failed',
            'payment_request.failed',
            'payment_failed'
        ];

        $successStatuses = [
            'SUCCEEDED',
            'CAPTURED',
            'PAID'
        ];

        $failedStatuses = [
            'FAILED'
        ];

        $newStatus = null;

        if (in_array($event, $successfulEvents, true) || in_array($paymentStatus, $successStatuses, true)) {
            $newStatus = 'PAID';
        } elseif (in_array($event, $failedEvents, true) || in_array($paymentStatus, $failedStatuses, true)) {
            $newStatus = 'FAILED';
        } else {
            return $this->respond(200,true,'Webhook received; no status change required.');
        }

        try {
            $order = $this->getOrderByReference($referenceId,$paymentId);
            if ($order === false) {
                return $this->respond(500,false,'Could not retrieve order.');
            }

            if (!$order) {
                error_log('Xendit webhook: order not found. Reference=' .$referenceId . ', PaymentID=' . $paymentId);
                return $this->respond(200,true,'Webhook received; order not found.');
            }

            $currentStatus = strtoupper(trim($order['status'] ?? ''));
            if ($currentStatus === 'PAID') {
                return $this->respond(200,true,'Order is already paid.');
            }

            if ($currentStatus !== 'PENDING') {
                return $this->respond(200,true,'Order is not pending; no status change.');
            }

            if (!$this->updateOrderStatus($order['reference_id'],$newStatus)) {
                return $this->respond(500,false,'Could not update order status.');
            }
            return $this->respond(200,true,'Payment status processed.');
        } catch (Throwable $e) {
            error_log('Xendit webhook exception: ' . $e->getMessage());
            return $this->respond(500,false,'Internal webhook error.');
        }
    }

    private function respond($code, $status, $message) {
        http_response_code($code);
        echo json_encode(['status' => $status,'reply' => $message]);
        exit;
    }
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $Webhook = new Webhook();
    
    if ($action === 'webhook') {
        $Webhook->process();
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => false,'reply' => 'Invalid action.']);
    exit;
}

http_response_code(400);
echo json_encode(["status" => false, "reply" => "Invalid request."]);