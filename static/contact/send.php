<?php
// Contact form handler (DreamHost PHP). Sends mail to the addresses below.
$to = 'andrew@everettallenphotography.com';
$bcc = 'thedrewan@gmail.com';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['website'])) { header('Location: /contact/'); exit; }
$clean = fn($s) => trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$s)));
$first = $clean($_POST['first'] ?? ''); $last = $clean($_POST['last'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$msg = trim(strip_tags((string)($_POST['message'] ?? '')));
if (!$email || $first === '' || $msg === '') { http_response_code(400); echo 'Please go back and fill in your name, a valid email, and a message.'; exit; }
$body = "Name: $first $last\nEmail: $email\n\n$msg\n";
$headers = "From: email@everettallenphotography.com\r\nReply-To: $email\r\nBcc: $bcc\r\nContent-Type: text/plain; charset=UTF-8\r\n";
mail($to, 'New message from "Everett Allen Photography"', $body, $headers);
header('Location: /thanks/'); exit;
