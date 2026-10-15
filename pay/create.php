<?php
/**
 * Создание платежа в ЮKassa (сценарий redirect).
 *
 * Секретный ключ НЕ хранится в этом файле и никогда не отдаётся на клиент.
 * Он лежит в отдельном конфиге ВНЕ веб-корня: <data>/yookassa_secret.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$configPath = getenv('YOOKASSA_CONFIG') ?: dirname(__DIR__, 3) . '/yookassa_secret.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Конфигурация не найдена'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;
$shopId    = (string)($config['shop_id'] ?? '');
$secretKey = (string)($config['secret_key'] ?? '');
$returnUrl = (string)($config['return_url'] ?? 'https://incollect.ru/payments.html?status=success');
$sendReceipt = !empty($config['send_receipt']);
$vatCode = (int)($config['vat_code'] ?? 1);

if ($shopId === '' || $secretKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Конфигурация некорректна'], JSON_UNESCAPED_UNICODE);
    exit;
}

function field(string $key, int $max): string
{
    $v = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    return mb_substr($v, 0, $max);
}

$amountRaw = str_replace([' ', ','], ['', '.'], field('amount', 32));
$name      = field('name', 128);
$contract  = field('contract', 64);
$email     = field('email', 128);

if ($amountRaw === '' || !is_numeric($amountRaw)) {
    http_response_code(422);
    echo json_encode(['error' => 'Укажите корректную сумму'], JSON_UNESCAPED_UNICODE);
    exit;
}

$amount = (float)$amountRaw;
if ($amount < 1 || $amount > 1000000) {
    http_response_code(422);
    echo json_encode(['error' => 'Сумма должна быть от 1 до 1 000 000 ₽'], JSON_UNESCAPED_UNICODE);
    exit;
}
$amountValue = number_format($amount, 2, '.', '');

$description = 'Оплата задолженности';
if ($contract !== '') {
    $description .= ', договор ' . $contract;
}
$description = mb_substr($description, 0, 128);

$payload = [
    'amount' => ['value' => $amountValue, 'currency' => 'RUB'],
    'capture' => true,
    'confirmation' => [
        'type' => 'redirect',
        'return_url' => $returnUrl,
    ],
    'description' => $description,
];

$meta = array_filter([
    'name' => $name,
    'contract' => $contract,
    'email' => $email,
]);
if ($meta) {
    $payload['metadata'] = $meta;
}

if ($sendReceipt && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $payload['receipt'] = [
        'customer' => ['email' => $email],
        'items' => [[
            'description' => 'Оплата задолженности',
            'quantity' => '1.00',
            'amount' => ['value' => $amountValue, 'currency' => 'RUB'],
            'vat_code' => $vatCode,
            'payment_subject' => 'service',
            'payment_mode' => 'full_payment',
        ]],
    ];
}

$idempotenceKey = bin2hex(random_bytes(16));

$ch = curl_init('https://api.yookassa.ru/v3/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Idempotence-Key: ' . $idempotenceKey,
    ],
    CURLOPT_USERPWD => $shopId . ':' . $secretKey,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
]);

$response  = curl_exec($ch);
$httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Нет связи с ЮKassa'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)$response, true);

if ($httpCode >= 200 && $httpCode < 300 && !empty($data['confirmation']['confirmation_url'])) {
    echo json_encode([
        'confirmation_url' => $data['confirmation']['confirmation_url'],
        'payment_id' => $data['id'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(502);
echo json_encode([
    'error' => 'ЮKassa отклонила запрос',
    'details' => is_array($data) ? ($data['description'] ?? null) : null,
], JSON_UNESCAPED_UNICODE);
