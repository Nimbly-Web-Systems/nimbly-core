<?php

function thumbnail_sc($params)
{
    $uuid = get_param_value($params, "uuid", current($params));
    $mode = get_param_value($params, "mode", "h");
    $size = get_param_value($params, "size", 240) + 0;
    $ratio = get_param_value($params, "ratio", 0) + 0;
    return thumbnail_create($uuid, $size, $ratio, $mode);
}

function thumbnail_sharpen($img)
{
    static $sharpen = false;
    static $divisor = false;
    if (!$sharpen || !$divisor) {
        $sharpen = array(
            array(-1, -1, -1),
            array(-1, 20, -1),
            array(-1, -1, -1),
        );
        $divisor = array_sum(array_map('array_sum', $sharpen));
    }
    imageconvolution($img, $sharpen, $divisor, 0);
}

function thumbnail_create($uuid, $size, $ratio = 0, $mode = 'h')
{

    $MAX_UPSCALE = 1.0; // @todo: make this dynamic

    // 1. Create thumbnail from original

    $result = "";
    $org_path = sprintf("%s/.files/%s", $GLOBALS['SYSTEM']['data_base'], $uuid);
    list($org_w, $org_h, $org_type) = @getimagesize($org_path);
    switch ($org_type) {
        case IMAGETYPE_GIF:
            $org_img = imagecreatefromgif($org_path);
            break;
        case IMAGETYPE_JPEG:
            $org_img = imagecreatefromjpeg($org_path);
            break;
        case IMAGETYPE_PNG:
            $org_img = imagecreatefrompng($org_path);
            break;
        case IMAGETYPE_BMP:
            $org_img = imagecreatefrombmp($org_path);
            break;
        default:
            load_library('system-messages');
            system_message('unknown image type: ' . $org_type);
            return $result;
    }
    
    if (empty($org_img)) {
        return $result;
    }

    $asp = $org_w / $org_h;
    $org_x = 0;
    $org_y = 0;
    $max_w = min(get_variable('max_img_w', 1920), $org_w * $MAX_UPSCALE); // max enlarging
    $max_h = min(get_variable('max_img_h', 1080), $org_h * $MAX_UPSCALE);

    //2: Calc thumbnail size given height and aspect ratio
    $no_ratio = empty($ratio) || ($ratio < 0) || (abs($asp - $ratio) < 0.01) || $mode === 'f';
    $a = $no_ratio ? $asp : $ratio;

    if ($mode === 'f') {
        if ($size < $max_w) {
            $max_w = $size;
        }
        if ($size / $ratio < $max_h) {
            $max_h = $size / $ratio;
        }
        $w = $size;
        $h = $size / $asp;
    } else if ($mode === 'w') {
        $w = $size;
        $h = $size / $a;
    } else { // defaults to mode 'h'
        $h = $size;
        $w = $a * $size;
    }

    if ($no_ratio === false) {
        if ($ratio > $asp) {
            $new_h = $org_w / $ratio;
            $org_y = ($org_h - $new_h) / 2;
            $org_h = $new_h;
        } else {
            $new_w = $org_h * $ratio;
            $org_x = ($org_w - $new_w) / 2;
            $org_w = $new_w;
        }
    }

    if ($w > $max_w) {
        $w = $max_w;
        $h = $w / $a;
    }

    if ($h > $max_h) {
        $h = $max_h;
        $w = $a * $h;
    }

    //3. handling based on image type, e.g. transparency and store to static cache

    $thumb_img = imagecreatetruecolor($w, $h);
    $static_path = $GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_/' . $GLOBALS['SYSTEM']['request_uri'];
    @mkdir(dirname($static_path), 0750, true);

    switch ($org_type) {
        case IMAGETYPE_JPEG:
        case IMAGETYPE_BMP:
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            thumbnail_sharpen($thumb_img);
            /*$wm = get_variable('watermark_image', false);
            if (!empty($wm) && ($w > 640 || $h > 640)) {
                thumbnail_stamp($thumb_img, $wm, $w, $h, get_variable('watermark_position', 'rightbottom'));
            }*/
            if (imageavif($thumb_img, $static_path, 65)) {
                $result = $static_path;
            }
            break;
        case IMAGETYPE_PNG:
            imagealphablending($thumb_img, false);
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            thumbnail_sharpen($thumb_img);
            imagesavealpha($thumb_img, true);

            if (imagewebp($thumb_img, $static_path, 85)) {
                $result = $static_path;
            }
            break;
        case IMAGETYPE_GIF:
            imagealphablending($thumb_img, false);
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            $bg = imagecolorallocate($thumb_img, 0, 0, 0);
            imagecolortransparent($thumb_img, $bg);
            imagesavealpha($thumb_img, true);
            if (imagewebp($thumb_img, $static_path)) {
                $result = $static_path;
            }
            break;
    }

    //4: clean up and return result
    imagedestroy($org_img);
    imagedestroy($thumb_img);
    return $result;
}

function thumbnail_stamp($img, $wm_path, $w, $h, $position)
{
    if (!@file_exists($wm_path)) {
        return 0;
    }
    $wm_img = imagecreatefrompng($wm_path);
    $ww = imagesx($wm_img);
    $wh = imagesy($wm_img);
    $ratio = $wh / $ww;
    $max_w = 0.70 * $w;
    $max_h = $ratio * $max_w;
    if ($position === "center" && $ww <= $max_w && $wh <= $max_h) {
        $x = ($w - $ww) / 2;
        $y = ($h - $wh) / 2;
    } else if ($position === "center") {
        $x = ($w - $max_w) / 2;
        $y = ($h - $max_h) / 2;
    } else if ($position === "righttop") {
        $x = $w - $ww - 5;
        $y = 5;
    } else if ($position === "rightbottom") {
        $x = $w - $ww - 5;
        $y = $h - $wh - 5;
    }

    if ($ww <= $max_w && $wh <= $max_h) {
        imagecopy($img, $wm_img, $x, $y, 0, 0, $ww, $wh);
    } else {
        imagecopyresampled($img, $wm_img, $x, $y, 0, 0, $max_w, $max_h, $ww, $wh);
    }

    return $ww;
}
