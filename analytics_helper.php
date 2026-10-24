<?php

function parseUserAgent($userAgent) {
    $browser = 'Unknown';
    $os = 'Unknown';
    $deviceType = 'Desktop';

    if (!$userAgent) {
        return ['browser' => $browser, 'os' => $os, 'device_type' => $deviceType];
    }

    // Determine OS and Device Type
    if (preg_match('/windows|win32/i', $userAgent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/linux/i', $userAgent) && !preg_match('/android/i', $userAgent)) {
        $os = 'Linux';
    } elseif (preg_match('/android/i', $userAgent)) {
        $os = 'Android';
        $deviceType = 'Mobile';
    } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $os = 'iOS';
        $deviceType = preg_match('/ipad/i', $userAgent) ? 'Tablet' : 'Mobile';
    }

    // Determine Browser
    if (preg_match('/MSIE/i', $userAgent) || preg_match('/Trident/i', $userAgent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Edge|Edg/i', $userAgent)) {
        $browser = 'Edge';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/OPR|Opera/i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    }

    // Social In-App Browsers
    if (preg_match('/Instagram/i', $userAgent)) $browser = 'Instagram In-App';
    if (preg_match('/FBAN|FBAV/i', $userAgent)) $browser = 'Facebook In-App';
    if (preg_match('/Snapchat/i', $userAgent)) $browser = 'Snapchat In-App';
    if (preg_match('/TikTok/i', $userAgent)) $browser = 'TikTok In-App';

    return ['browser' => $browser, 'os' => $os, 'device_type' => $deviceType];
}

function getAnalyticsSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
    }
    $id = $_SESSION['analytics_session_id'];
    session_write_close(); // Prevent locking!
    return $id;
}

function getLocationFromIp($ip) {
    if (!$ip || $ip == '127.0.0.1' || $ip == '::1') {
        return ['country' => 'Local', 'city' => 'Local'];
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if we already cached this IP's location in the session to avoid rate limits
    if (isset($_SESSION['cached_ip_location']) && $_SESSION['cached_ip_location']['ip'] === $ip) {
        $location = $_SESSION['cached_ip_location']['data'];
        session_write_close(); // Important: release session lock instantly!
        return $location;
    }

    $locationData = ['country' => 'Unknown', 'city' => 'Unknown'];

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}?fields=status,country,city");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                $locationData = [
                    'country' => $data['country'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown'
                ];
            }
        }
    } catch (Exception $e) {
        // Silently fail if API is down
    }

    // Cache it for future clicks/views in the same session
    $_SESSION['cached_ip_location'] = [
        'ip' => $ip,
        'data' => $locationData
    ];
    
    session_write_close(); // Prevent locking subsequent requests
    return $locationData;
}
