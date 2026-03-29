<?php
session_start();

$code = (string) random_int(1000, 9999);
$_SESSION['captcha'] = $code;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (function_exists('imagecreate')) {
	$image = imagecreate(100, 40);
	imagecolorallocate($image, 255, 255, 255);
	$textColor = imagecolorallocate($image, 0, 0, 0);

	imagestring($image, 5, 28, 12, $code, $textColor);

	header('Content-Type: image/png');
	imagepng($image);
	imagedestroy($image);
	exit;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="40" viewBox="0 0 100 40">';
echo '<rect width="100" height="40" fill="white"/>';
echo '<text x="50" y="26" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" fill="black">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</text>';
echo '</svg>';
