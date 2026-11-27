<?php
// Contact form handler (DreamHost PHP). Saves the message as a new inquiry in Clients, then emails the owner.
declare(strict_types=1);
require __DIR__ . '/../client/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['website'])) { header('Location: /contact/'); exit; }
$clean = fn($s) => trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$s)));
$first = mb_substr($clean($_POST['first'] ?? ''), 0, 80); $last = mb_substr($clean($_POST['last'] ?? ''), 0, 80);
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$msg = mb_substr(trim(strip_tags((string)($_POST['message'] ?? ''))), 0, 2000);
if (!$email || $first === '' || $msg === '') { header('Location: /contact/?e=1'); exit; }
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ea-intake');   // same key as client/new.php
$recent = db()->prepare("SELECT COUNT(*) FROM clients WHERE ip_hash = ? AND source = 'contact' AND created > ?"); $recent->execute([$ipHash, time() - 3600]);
if ((int)$recent->fetchColumn() >= 5) { header('Location: /thanks/'); exit; }   // over the limit: quiet thank-you
$name = trim("$first $last");
db()->prepare('INSERT INTO clients (name, email, message, status, source, ip_hash, created) VALUES (?,?,?,?,?,?,?)')->execute([$name, $email, $msg, 'new', 'contact', $ipHash, time()]);
send_owner_mail('New message from "Everett Allen Photography"', "Name: $first $last\nEmail: $email\n\n$msg\n", $email);
header('Location: /thanks/'); exit;
