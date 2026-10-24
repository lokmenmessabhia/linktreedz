<?php
header('Content-Type: application/json');

$url = $_GET['url'] ?? '';

if (empty($url)) {
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Use cURL to follow redirects and get the final URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true); // Don't download the body
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

curl_exec($ch);
$expanded_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($expanded_url) {
    echo json_encode(['expanded_url' => $expanded_url]);
} else {
    echo json_encode(['error' => 'Could not expand URL']);
}
