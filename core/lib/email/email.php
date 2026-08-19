<?php

use PHPMailer\PHPMailer\PHPMailer;

function email($email_data)
{
	$result = email_result($email_data);
	$GLOBALS['NB_LAST_EMAIL_RESULT'] = $result;
	if (empty($result['success'])) {
		load_library("log");
		load_library("set");
		set_variable("email.result", "error: " . ($result['error'] ?? 'email not sent'));
		log_system("Error: " . ($result['error'] ?? 'email not sent'));
	}
	return !empty($result['success']);
}

function email_last_result(): array
{
	return is_array($GLOBALS['NB_LAST_EMAIL_RESULT'] ?? null)
		? $GLOBALS['NB_LAST_EMAIL_RESULT']
		: [];
}

function email_clear_last_result(): void
{
	unset($GLOBALS['NB_LAST_EMAIL_RESULT']);
}

function email_result($email_data)
{
	try {
		if (empty($email_data['recipient'])) {
			throw new Exception('Email recipient not set');
		}
		if (empty($email_data['subject'])) {
			throw new Exception('Email subject not set');
		}

		$email_data = email_prepare($email_data);
		$service = $email_data['service'] ?? 'system';
		if ($service === 'resend') {
			return email_via_resend_result($email_data);
		}

		$success = false;
		if ($service === 'system') {
			$success = email_via_system($email_data);
		} else if ($service === 'mailgun') {
			$success = email_via_mailgun($email_data);
		} else if ($service === 'smtp' || $service === 'phpmailer') {
			$success = email_via_smtp($email_data);
		}

		return ['success' => $success === true, 'id' => null, 'error' => $success === true ? null : 'Email provider rejected the message'];
	} catch (Throwable $e) {
		return ['success' => false, 'id' => null, 'error' => $e->getMessage()];
	}
}

function email_prepare($email_data)
{
	if (isset($email_data['html'])) {
		$email_data['html'] = (string)$email_data['html'];
	} else {
		if (empty($email_data['tpl'])) {
			throw new Exception('Email template or rendered HTML not set');
		}
		$tpl = find_template($email_data['tpl']);
		if (!$tpl) {
			throw new Exception('Email template not found');
		}
		$email_data['tpl'] = $tpl;
		load_library('run');
		$email_data['html'] = run_buffered($tpl);
	}
	$email_data['text'] = isset($email_data['text']) ? (string)$email_data['text'] : plain_text($email_data['html']);
	return $email_data;
}

function email_batch_result($messages, $options = [])
{
	if (!is_array($messages) || count($messages) < 1 || count($messages) > 100) {
		return ['success' => false, 'ids' => [], 'error' => 'Email batch must contain between 1 and 100 messages'];
	}
	try {
		$prepared = [];
		foreach ($messages as $message) {
			$prepared[] = email_prepare(array_merge($options, $message));
		}
		return email_via_resend_batch_result($prepared, $options);
	} catch (Throwable $e) {
		return ['success' => false, 'ids' => [], 'error' => $e->getMessage()];
	}
}

function email_via_system($email_data)
{
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=iso-8859-1';
	$recipient = is_array($email_data['recipient']) ? implode(',', $email_data['recipient']) : $email_data['recipient'];

	if (!empty($email_data['from'])) {
		$headers[] = sprintf('From: %s', $email_data['from']);
	}

	if (!empty($email_data['cc'])) {
		$cc = is_array($email_data['cc']) ? implode(',', $email_data['cc']) : $email_data['cc'];
		$headers[] = sprintf('Cc: %s', $cc);
	}

	if (!empty($email_data['bcc'])) {
		$bcc = is_array($email_data['bcc']) ? implode(',', $email_data['cc']) : $email_data['bcc'];
		$headers[] = sprintf('Bcc: %s', $bcc);
	}

	load_library("set");

	if (@mail($recipient, $email_data['subject'], $email_data['html'], implode("\r\n", $headers))) {
		set_variable("email.result", "email sent");
		return true;
	}
	return false;
}

function email_via_mailgun($email_data)
{
	load_libraries(['curl', 'util']);
	$url = sprintf(
		'%s/v3/%s/messages',
		$email_data['api_base_url'] ?? 'https://api.mailgun.net',
		$email_data['domain']
	);
	$ch = _curl_init($url);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $email_data['api_key']);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, [
		'from' => $email_data['from'],
		'to' => $email_data['recipient'],
		'subject' => $email_data['subject'],
		'html' => $email_data['html'],
		'text' => plain_text($email_data['html'])
	]);
	$response = _curl_exec($ch);
	return curl_result($response);
}

function email_via_resend($email_data)
{
	return !empty(email_via_resend_result(email_prepare($email_data))['success']);
}

function email_via_resend_result($email_data)
{
	$result = email_resend_request('/emails', email_resend_payload($email_data), $email_data);
	return [
		'success' => !empty($result['success']) && !empty($result['body']['id']),
		'id' => $result['body']['id'] ?? null,
		'error' => $result['error'] ?? null,
	];
}

function email_via_resend_batch_result($messages, $options = [])
{
	$payload = array_map('email_resend_payload', $messages);
	$result = email_resend_request('/emails/batch', $payload, $options);
	$ids = [];
	foreach (($result['body']['data'] ?? []) as $item) {
		if (!empty($item['id'])) {
			$ids[] = $item['id'];
		}
	}
	return [
		'success' => !empty($result['success']) && count($ids) === count($messages),
		'ids' => $ids,
		'error' => $result['error'] ?? null,
	];
}

function email_resend_payload($email_data)
{
	load_library('env');
	$from = $email_data['from'] ?? env('MAIL_FROM');
	$from_name = $email_data['from_name'] ?? env('MAIL_FROM_NAME');
	if (!empty($from_name)) {
		$from = $from_name . ' <' . $from . '>';
	}
	$payload = [
		'from' => $from,
		'to' => email_recipients($email_data['recipient']),
		'subject' => $email_data['subject'],
		'html' => $email_data['html'],
		'text' => $email_data['text'],
	];
	foreach (['cc', 'bcc', 'reply_to', 'headers', 'tags'] as $key) {
		if (!empty($email_data[$key])) {
			$payload[$key] = in_array($key, ['cc', 'bcc'], true) ? email_recipients($email_data[$key]) : $email_data[$key];
		}
	}
	return $payload;
}

function email_resend_request($path, $payload, $options = [])
{
	if (isset($options['request']) && is_callable($options['request'])) {
		return $options['request']($path, $payload, $options);
	}
	load_libraries(['curl', 'env']);
	$api_key = $options['api_key'] ?? env('RESEND_API_KEY');
	if (empty($api_key)) {
		return ['success' => false, 'body' => [], 'error' => 'Resend is not configured'];
	}
	$headers = ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'];
	if (!empty($options['idempotency_key'])) {
		$headers[] = 'Idempotency-Key: ' . $options['idempotency_key'];
	}
	$base_url = rtrim($options['api_base_url'] ?? 'https://api.resend.com', '/');
	$ch = _curl_init($base_url . $path, $headers);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
	$response = _curl_exec($ch);
	$body = json_decode((string)$response, true);
	if (!is_array($body)) {
		return ['success' => false, 'body' => [], 'error' => 'Email provider returned an invalid response'];
	}
	if (!empty($body['message']) && empty($body['id']) && empty($body['data'])) {
		return ['success' => false, 'body' => $body, 'error' => 'Email provider rejected the request'];
	}
	return ['success' => true, 'body' => $body, 'error' => null];
}

function email_recipients($recipients)
{
	if (is_array($recipients)) {
		return array_values(array_filter(array_map('trim', $recipients)));
	}
	return array_values(array_filter(array_map('trim', explode(',', (string)$recipients))));
}

function email_via_smtp($email_data)
{
	require_once 'php_mailer/Exception.php';
	require_once 'php_mailer/PHPMailer.php';
	require_once 'php_mailer/SMTP.php';
	load_libraries(['env', 'run', 'util']);

	$mail = new PHPMailer(false);

	if (isset($email_data['pw']) && is_array($email_data['pw']) && isset($email_data['pw']['cipher'])) {
		load_library('encrypt');
		$email_data['pw'] = decrypt_2way($email_data['pw'], $email_data['salt']);
	}

	try {
		$mail->SMTPDebug = 0;
		$mail->isSMTP();
		$mail->Host       = $email_data['server'] ?? env('SMTP_HOST');
		$mail->SMTPAuth   = true;
		$mail->Username   = $email_data['user'] ?? env('SMTP_USER');
		$mail->Password   = $email_data['pw'] ?? env('SMTP_PASSWORD');
		$smtp_secure      = $email_data['secure'] ?? env('SMTP_SECURE', 'smtps');
		$mail->SMTPSecure = in_array($smtp_secure, ['tls', 'starttls'], true)
			? PHPMailer::ENCRYPTION_STARTTLS
			: PHPMailer::ENCRYPTION_SMTPS;
		$mail->Port       = (int)($email_data['port'] ?? env('SMTP_PORT', $mail->SMTPSecure === PHPMailer::ENCRYPTION_STARTTLS ? 587 : 465));

		$mail->setFrom($email_data['from'] ?? env('MAIL_FROM', 'info@nimblycms.com'), $email_data['from_name'] ?? env('MAIL_FROM_NAME', 'Nimbly CMS'));

		$recipients = explode(',', $email_data['recipient'] ?? $email_data['to']);
		$recipient_names = explode(',', $email_data['recipient_name'] ?? '');

		foreach ($recipients as $ix => $recipient) {
			$jx = $ix > (count($recipient_names) - 1) ? (count($recipient_names) - 1) : $ix;
			$mail->addAddress($recipient, $recipient_names[$jx]);
		}

		$html = $email_data['html'];
		$plain = $email_data['text'];

		if (!empty($email_data['reply_to'])) {
			$mail->addReplyTo($email_data['reply_to']);
		}
		foreach (($email_data['headers'] ?? []) as $name => $value) {
			$mail->addCustomHeader($name, $value);
		}

		//Content
		$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = $email_data['subject'];
		$mail->Body    = $html;
		$mail->AltBody = $plain;

		$mail->send();
		return true;
	} catch (Exception $e) {
		return false;
	}
}
