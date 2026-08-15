<?php

$dir = __DIR__.'/../storage/app/public/qris';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$im = imagecreatetruecolor(400, 400);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 15, 23, 42);
$gray = imagecolorallocate($im, 100, 116, 139);
imagefilledrectangle($im, 0, 0, 400, 400, $white);
imagefilledrectangle($im, 40, 40, 360, 360, $black);
imagefilledrectangle($im, 60, 60, 340, 340, $white);

for ($i = 0; $i < 20; $i++) {
    for ($j = 0; $j < 20; $j++) {
        if (($i + $j) % 3 === 0) {
            imagefilledrectangle($im, 80 + $i * 12, 80 + $j * 12, 90 + $i * 12, 90 + $j * 12, $black);
        }
    }
}

imagestring($im, 5, 155, 370, 'QRIS DEMO', $gray);
imagepng($im, $dir.'/demo-qris.png');
imagedestroy($im);

echo "created\n";
