<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * @doc `[email recipient@ema.il subject="test email" tpl="welcome_email"]` sends an email using email template `welcome_email`
 */
function email_sc($params)
{
	$config_id = current($params);
	load_library('data');
	$data = data_read('.emails', $config_id);
	email($data);
}

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

	echo 'A';

	$tpl = find_template($email_data['tpl']);
	if (!$tpl) {
		throw new Exception('Template not found');
		return false;
	}

	echo 'B';

	$email_data['tpl'] = $tpl;
	$service = $email_data['service'] ?? 'system';

	$result = false;


	if ($service === 'system') {
		$result = email_via_system($email_data);
	} else if ($service === 'mailgun') {
		$result = email_via_mailgun($email_data);
	} else if ($service === 'phpmailer') {
		$result = email_via_phpmailer($email_data);
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

function email_via_phpmailer($email_data)
{
	require_once 'php_mailer/PHPMailer.php';
	require_once 'php_mailer/SMTP.php';

	$mail = new PHPMailer(false);
	echo 'here we go';

	try {
		//Server settings
		$mail->SMTPDebug = 2;                      					
		$mail->isSMTP();                                            
		$mail->Host       = $email_data['server'];                    
		$mail->SMTPAuth   = true;                                  
		$mail->Username   = $email_data['user'];                    
		$mail->Password   = $email_data['pw'];                             
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
		$mail->Port       = $email_data['port'] ?? 465;             

		$mail->setFrom($email_data['from'] ?? 'info@nimblycms.com', $email_data['from_name'] ?? 'Nimbly CMS');
		$mail->addAddress($email_data['recipient'] ?? $email_data['to'], $email_data['recipient_name'] ?? '');   
		
		$html = run_buffered($email_data['tpl']);
		load_library('plain-text');
		$plain = plain_text($html);
		
		//Content
		$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = $email_data['subject'];
		$mail->Body    = $html;
		$mail->AltBody = $plain;

		$mail->send();
		echo 'email send';
		return true;
	} catch (Exception $e) {
		return false;
	}
}
