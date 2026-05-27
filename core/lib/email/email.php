<?php

use PHPMailer\PHPMailer\PHPMailer;

function email($email_data)
{
	if (empty($email_data['recipient'])) {
		throw new Exception('Email field "recipient" not set');
		return false;
	}

	if (empty($email_data['subject']) || empty($email_data['tpl'])) {
		throw new Exception('Email subject not set or template not found');
		return false;
	}

	$tpl = find_template($email_data['tpl']);
	if (!$tpl) {
		throw new Exception('Template not found');
		return false;
	}

	$email_data['tpl'] = $tpl;
	$service = $email_data['service'] ?? 'system';

	$result = false;

	if ($service === 'system') {
		$result = email_via_system($email_data);
	} else if ($service === 'mailgun') {
		$result = email_via_mailgun($email_data);
	} else if ($service === 'smtp' || $service === 'phpmailer') {
		$result = email_via_smtp($email_data);
	} else if ($service === 'resend') {
		$result = email_via_resend($email_data);
	}

	if ($result !== true) {
		load_library("log");
		load_library("set");
		set_variable("email.result", "error: email not sent");
		log_system("Error: email not sent");
	}
	return $result;
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

	if (@mail($recipient, $email_data['subject'], run_buffered($email_data['tpl']), implode("\r\n", $headers))) {
		set_variable("email.result", "email sent");
		return true;
	}
	return false;
}

function email_via_mailgun($email_data)
{
	$email_data['html'] = run_buffered($email_data['tpl']);
	load_libraries(['curl', 'plain-text']);
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
	load_libraries(['curl', 'plain-text', 'env', 'run']);

	$api_key = env('RESEND_API_KEY');
	$html = run_buffered($email_data['tpl']);
	$text = plain_text($html);

	$from = $email_data['from'] ?? env('MAIL_FROM');
	$from_name = $email_data['from_name'] ?? env('MAIL_FROM_NAME');
	if (!empty($from_name)) {
		$from = $from_name . ' <' . $from . '>';
	}

	$ch = _curl_init('https://api.resend.com/emails', [
		'Authorization: Bearer ' . $api_key,
		'Content-Type: application/json',
	]);
	curl_setopt($ch, CURLOPT_POST, true);
	$post_fields = [
		'from'    => $from,
		'to'      => email_recipients($email_data['recipient']),
		'subject' => $email_data['subject'],
		'html'    => $html,
		'text'    => $text,
	];
	if (!empty($email_data['cc'])) {
		$post_fields['cc'] = email_recipients($email_data['cc']);
	}
	if (!empty($email_data['bcc'])) {
		$post_fields['bcc'] = email_recipients($email_data['bcc']);
	}

	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));

	$response = _curl_exec($ch);
	$result = json_decode($response, true);
	return !empty($result['id']);
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
	load_libraries(['env', 'run', 'plain-text']);

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

		$html = run_buffered($email_data['tpl']);
		$plain = plain_text($html);

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
