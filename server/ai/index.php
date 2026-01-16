<?php

$openrouter_api_key = 'REPLACE_WITH_OPENROUTER_KEY';
$server_token = 'REPLACE_WITH_SERVER_TOKEN';
$daily_limit = 50;
$monthly_limit = 1000;
$allowed_models = array(
    'openai/gpt-4o',
    'openai/gpt-4o-mini',
    'anthropic/claude-3.5-sonnet',
    'google/gemini-1.5-pro',
    'mistralai/mistral-large',
);
$max_tokens = array(
    'content'  => 3500,
    'planning' => 800,
    'images'   => 600,
);
$system_prefix = 'Ti si server-side AI koordinator za Blog Pisac. Poštuj zahteve pretplate i sistemske smernice.';

$log_dir = __DIR__ . '/logs';
$usage_file = $log_dir . '/usage.json';
$log_file = $log_dir . '/requests.log';

if ( ! file_exists( $log_dir ) ) {
    mkdir( $log_dir, 0755, true );
}

header( 'Content-Type: application/json' );

$incoming_token = $_SERVER['HTTP_X_BP_SERVER_TOKEN'] ?? '';
if ( empty( $incoming_token ) || $incoming_token !== $server_token ) {
    http_response_code( 401 );
    echo json_encode( array( 'error' => 'Unauthorized' ) );
    exit;
}

$payload = json_decode( file_get_contents( 'php://input' ), true );
if ( empty( $payload['messages'] ) || empty( $payload['model'] ) ) {
    http_response_code( 400 );
    echo json_encode( array( 'error' => 'Invalid payload' ) );
    exit;
}

$model = $payload['model'];
if ( ! in_array( $model, $allowed_models, true ) ) {
    http_response_code( 403 );
    echo json_encode( array( 'error' => 'Model not allowed' ) );
    exit;
}

$task = $payload['task'] ?? 'content';
$max_tokens_for_task = $max_tokens[ $task ] ?? $max_tokens['content'];

$domain = basename( dirname( __DIR__ ) );

$usage = array(
    'day' => array( 'date' => gmdate( 'Y-m-d' ), 'count' => 0 ),
    'month' => array( 'date' => gmdate( 'Y-m' ), 'count' => 0 ),
);

if ( file_exists( $usage_file ) ) {
    $stored = json_decode( file_get_contents( $usage_file ), true );
    if ( is_array( $stored ) ) {
        $usage = array_merge( $usage, $stored );
    }
}

if ( $usage['day']['date'] !== gmdate( 'Y-m-d' ) ) {
    $usage['day'] = array( 'date' => gmdate( 'Y-m-d' ), 'count' => 0 );
}
if ( $usage['month']['date'] !== gmdate( 'Y-m' ) ) {
    $usage['month'] = array( 'date' => gmdate( 'Y-m' ), 'count' => 0 );
}

if ( $usage['day']['count'] >= $daily_limit || $usage['month']['count'] >= $monthly_limit ) {
    http_response_code( 429 );
    echo json_encode( array( 'error' => 'Usage limit exceeded' ) );
    exit;
}

$messages = $payload['messages'];
array_unshift(
    $messages,
    array(
        'role'    => 'system',
        'content' => $system_prefix,
    )
);

$request_body = array(
    'model'       => $model,
    'messages'    => $messages,
    'max_tokens'  => $max_tokens_for_task,
);

$headers = array(
    'Authorization: Bearer ' . $openrouter_api_key,
    'Content-Type: application/json',
    'HTTP-Referer: https://' . $domain . '/',
    'X-Title: Blog Pisac - ' . $domain,
);

$ch = curl_init( 'https://openrouter.ai/api/v1/chat/completions' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $request_body ) );
curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
$raw_response = curl_exec( $ch );
$curl_error = curl_error( $ch );
$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

$log_payload = array(
    'time'     => gmdate( 'c' ),
    'domain'   => $domain,
    'task'     => $task,
    'model'    => $model,
    'request'  => $request_body,
    'response' => $curl_error ? $curl_error : $raw_response,
);

if ( file_exists( $log_file ) && filesize( $log_file ) > 10 * 1024 * 1024 ) {
    file_put_contents( $log_file, '' );
}
file_put_contents( $log_file, json_encode( $log_payload ) . PHP_EOL, FILE_APPEND );

if ( $curl_error ) {
    http_response_code( 500 );
    echo json_encode( array( 'error' => $curl_error ) );
    exit;
}

$response_body = json_decode( $raw_response, true );
$content = $response_body['choices'][0]['message']['content'] ?? '';

$usage['day']['count']++;
$usage['month']['count']++;
file_put_contents( $usage_file, json_encode( $usage ) );

if ( empty( $content ) ) {
    http_response_code( 500 );
    echo json_encode( array( 'error' => 'Empty response' ) );
    exit;
}

if ( $http_code >= 400 ) {
    http_response_code( $http_code );
}

echo json_encode( array( 'content' => $content ) );
