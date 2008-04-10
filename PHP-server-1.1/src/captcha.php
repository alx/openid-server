<?php

/**
 * Generate a captcha image with some noise and randomly-chosen,
 * randomly-rotated lettering.  The parameters supplied will determine
 * the size of the resulting captcha image.
 *
 * @param string $font_path The path to a truetype font file to be
 * used for rendering the captcha characters.
 *
 * @param integer $char_count The number of characters to put into the
 * captcha image.
 *
 * @param integer $text_size The size of the captcha text, in pixels.
 */
function generateCaptcha($font_path, $char_count, $text_size = 45) {
    $width = $text_size * $char_count + 10;
    $height = $text_size + 30;
    $image = imagecreate($width, $height);

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);

    $textcolor = imagecolorallocate($image, 0, 0, 0); // Set text color

    // Generate a string.
    // $text1 = "L4HB0"; // Here is our text
    $text = "";
    for ($i = 0; $i < $char_count; $i++) {
        // Generate an ascii code in the range 49-57, 65-90 (1-9, A-Z).
        $ordinal = rand(49, 90);
        if ($ordinal > 57 &&
            $ordinal < 65) {
            $ordinal += 7;
        }
        $text .= chr($ordinal);
    }

    $x = 10;
    $y = $text_size + 10;
    $deg_window = 23;
    $color_max = 200;
    $x_deviation_min = -3;
    $x_deviation_max = 3;

    for ($i = 0; $i < strlen($text); $i++) {
        $degrees = rand(0, $deg_window * 2) - $deg_window;
        imagettftext($image, $text_size, $degrees,
                     $x + ($i * ($text_size - 3)) + rand($x_deviation_min,
                                                         $x_deviation_max),
                     $y, $black, $font_path, $text[$i]);
    }

    // Generate 20 random colors and draw random ellipses.
    $color_count = 20;
    $color_values = array();
    $spot_count = intval($width * $height * 0.007);

    $color_min = 80;
    $color_max = 220;

    $spot_r_min = 2;
    $spot_r_max = 7;
    $r_deviation_max = 3;

    for ($i = 0; $i < $color_count; $i++) {
        $color_values[$i] = imagecolorallocate($image,
                                               rand($color_min, $color_max),
                                               rand($color_min, $color_max),
                                               rand($color_min, $color_max));
    }

    for ($i = 0; $i < $spot_count; $i++) {
        $r = rand($spot_r_min, $spot_r_max);
        $x_deviation = rand(0, $r_deviation_max);
        $y_deviation = rand(0, $r_deviation_max);
        imagefilledellipse($image, rand(0, $width - 1), rand(0, $height - 1),
                           $r + $x_deviation,
                           $r + $y_deviation,
                           $color_values[rand(0, $color_count - 1)]);
    }

    if (imagetypes() & IMG_JPG) {
        header("Content-type: image/jpeg");
        imagejpeg($image, '', 90);
    } else if (imagetypes() & IMG_PNG) {
        header("Content-type: image/png");
        imagepng($image);
    } else if (imagetypes() & IMG_GIF) {
        header("Content-type: image/gif");
        imagegif($image);
    }

    imagedestroy($image);

    return md5($text);
}

?>
