<?php
require_once __DIR__ . '/../includes/security_headers.php';

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$location = '../auth/logout.php';
if ($queryString !== '') {
	$location .= '?' . $queryString;
}

header('Location: ' . $location, true, 302);
exit();
