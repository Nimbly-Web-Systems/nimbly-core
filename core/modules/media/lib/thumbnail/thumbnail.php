<?php

function thumbnail_sc($params)
{
    $uuid = get_param_value($params, "uuid", current($params));
    $mode = get_param_value($params, "mode", "h");
    $size = get_param_value($params, "size", 240) + 0;
    $ratio = get_param_value($params, "ratio", 0) + 0;
    $resource = get_param_value($params, "resource", ".files");
    return thumbnail_create($resource, $uuid, $size, $ratio, $mode);
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

function thumbnail_create($resource, $uuid, $size, $ratio=0, $mode='h')
{
    $MAX_UPSCALE = 1.0; // @todo: make this dynamic

    // 1. Check cache

    load_library('data', 'data');
    $watermark = get_variable('watermark', false);
    $wm_id = empty($watermark) ? '' : '_' . base_convert(md5(serialize($watermark)), 16, 36);
    $file_name = sprintf("%s_%s%s_%s%s.jpg", $uuid, $size, $mode, str_replace('.', '_', round($ratio, 5)), $wm_id);
    $path = sprintf(".tmp/thumb/%s/%s", $resource, $file_name);
    $cache_path = $GLOBALS['SYSTEM']['data_base'] . '/' . $path;

    if (@file_exists($cache_path)) {
        return $cache_path;
    }

    // 2. Create thumbnail from original

    $org_path = sprintf("%s/%s/%s", $GLOBALS['SYSTEM']['data_base'], $resource, $uuid);
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
    }
    $result = "";

    if (empty($org_img)) {
        return $result;
    }

    $asp = $org_w / $org_h;
    $org_x = 0;
    $org_y = 0;
    $max_w = min(get_variable('max_img_w', 1920), $org_w * $MAX_UPSCALE); // max enlarging
    $max_h = min(get_variable('max_img_h', 1080), $org_h * $MAX_UPSCALE);

    //3: Calc thumbnail size given height and aspect ratio
    $no_ratio = empty($ratio) || ($ratio < 0) || (abs($asp - $ratio) < 0.01) || $mode === 'f';
    $a = $no_ratio? $asp : $ratio;

    if ($mode === 'f') {
        if ($size < $max_w) {
            $max_w = $size;
        }
        if ($size / $ratio < $max_h) {
            $max_h = $size / $ratio;
        }
        $w = $size;
        $h = $size / $asp;
    } elseif ($mode === 'w') {
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


    $thumb_img = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);

    thumbnail_sharpen($thumb_img);

    if (!empty($watermark) && ($w > 640 || $h > 640) && is_array($watermark)) {
        $pos = ['lefttop' => [5, 5], 'righttop' => [$w - 5, 5], 'leftbottom' => [5, $h - 5], 'rightbottom' => [$w - 5, $h - 5]];
        foreach ($watermark as $wm) {
            if (!empty($wm['image'])) {
                thumbnail_stamp_image($thumb_img, $wm, $w, $h, $pos);
            } elseif (!empty($wm['text'])) {
                thumbnail_stamp_text($thumb_img, $wm, $w, $h, $pos);
            }
        }
    }

    //4: save image to cache

    @mkdir(dirname($cache_path), 0750, true);

    if (imagejpeg($thumb_img, $cache_path, 90)) {
        $result = $cache_path;
    }

    //5: clean up and return result

    imagedestroy($org_img);
    imagedestroy($thumb_img);
    return $result;
}

function _px_size($size, $len = 0) {
    if (empty($size)) {
        return 0;
    }
    if (strpos($size, 'px') > 0) {
        return (int) trim($size, 'px ');
    }
    if ($size > 1 || $size < -1) {
        return $size;
    }
    return $size * $len;
}

function thumbnail_stamp_pos_and_size($wm, $img_w, $img_h, $stamp_w, $stamp_h, &$pos)
{
    $size = $wm['size'] ?? 0.7;
    $position = $wm['position'];
    $ratio = $stamp_w / $stamp_h;
    if ($ratio > 1) {
        $max_h = min($stamp_h, _px_size($size, $img_h), $img_h - 10);
        $max_w = $ratio * $max_h;
    } else {
        $max_w = min($stamp_w, _px_size($size, $img_w), $img_w - 10);
        $max_h = $max_w / $ratio;
    }

    $offset_y = _px_size($wm['offset_y']  ?? 0, $img_h);
    $offset_x = _px_size($wm['offset_x'] ?? 0, $img_w);

    if ($position === "center") {
        $x = ($img_w - $max_w) / 2;
        $y = ($img_h - $max_h) / 2;
    } elseif ($position === "righttop") {
        $x = $pos['righttop'][0] - $max_w + $offset_x;
        $y = $pos['righttop'][1] + $offset_y;
        $pos['righttop'] = [$x, $y];
    } elseif ($position === "rightbottom") {
        $x = $pos['rightbottom'][0] - $max_w + $offset_x;
        ;
        $y = $pos['rightbottom'][1] - $max_h + $offset_y;
        $pos['rightbottom'] = [$x, $y];
    } elseif ($position === 'leftbottom') {
        $x = $pos['leftbottom'][0] + $offset_x;
        $y - $pos['leftbottom'][1] - $max_h + $offset_y;
        $pos['leftbottom'] = [$x, $y];
    } else {
        $x = $pos['lefttop'][0] + $offset_x;
        $y = $pos['lefttop'][1] + $offset_y;
        $pos['lefttop'] = [$x + $max_w, $y];
    }

    $fits = $x >= 5 && $y >= 5 && ($x + $max_w + 5) <= $img_w && ($y + $max_h + 5) <= $img_h;
    return compact('x', 'y', 'size', 'ratio', 'max_h', 'max_w', 'img_w', 'img_h', 'stamp_w', 'stamp_h', 'fits');
}

function thumbnail_stamp_image($img, $wm, $w, $h, &$pos)
{
    if (!@file_exists($wm['image'])) {
        return 0;
    }
    $wm_img = imagecreatefrompng($wm['image']);
    extract(thumbnail_stamp_pos_and_size($wm, $w, $h, imagesx($wm_img), imagesy($wm_img), $pos));
    if (!$fits) {
        return 0;
    }
    imagecopyresampled($img, $wm_img, $x, $y, 0, 0, $max_w, $max_h, $stamp_w, $stamp_h);
    return $stamp_w;
}

function thumbnail_stamp_text($img, $wm, $w, $h, &$pos)
{
    $white40 = imagecolorallocatealpha($img, 255, 255, 255, 76); //alpha: 127 - 40% = 76
    $font = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'OpenSans-Light.ttf';
    $box = imagettfbbox(100, 0, $font, $wm['text']);
    $stamp_w = abs($box[4] - $box[0]);
    $stamp_h = abs($box[5] - $box[1]);
    extract(thumbnail_stamp_pos_and_size($wm, $w, $h, $stamp_w, $stamp_h, $pos));
    if (!$fits) {
        return 0;
    }
    imagettftext($img, $max_h, 0, $x, $y + $max_h, $white40, $font, $wm['text']);
    return $stamp_w;
}
