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

/**
 * Ubuntu builds GD without AVIF, so libavif's own decoder (avifdec, package
 * libavif-bin) reads it there. '' when this server has neither.
 */
function thumbnail_avif_reader(): string
{
    if (function_exists('imagecreatefromavif')) {
        return 'gd';
    }
    foreach (['/usr/bin/avifdec', '/usr/local/bin/avifdec'] as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    return '';
}

function thumbnail_open_avif(string $path)
{
    $reader = thumbnail_avif_reader();
    if ($reader === 'gd') {
        return imagecreatefromavif($path);
    }
    if ($reader === '') {
        return false;
    }
    $png = tempnam(sys_get_temp_dir(), 'avif');
    if ($png === false) {
        return false;
    }
    // avifdec picks the output format from the extension
    $process = proc_open([$reader, '--jobs', '1', $path, $png . '.png'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    $img = is_resource($process) && proc_close($process) === 0 ? @imagecreatefrompng($png . '.png') : false;
    @unlink($png);
    @unlink($png . '.png');
    return $img;
}

// The first page of a PDF, with pdftoppm from Ubuntu's poppler-utils; a server without it gives none.
function thumbnail_open_pdf(string $path)
{
    if (!is_executable('/usr/bin/pdftoppm')) {
        return false;
    }
    $png = tempnam(sys_get_temp_dir(), 'pdf');
    if ($png === false) {
        return false;
    }
    // pdftoppm adds the extension to the name it is given
    $process = proc_open(['/usr/bin/timeout', '30', '/usr/bin/pdftoppm', '-png', '-singlefile', '-f', '1', '-l', '1', '-scale-to', '1920', $path, $png],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    $img = is_resource($process) && proc_close($process) === 0 ? @imagecreatefrompng($png . '.png') : false;
    @unlink($png);
    @unlink($png . '.png');
    return $img;
}

// Office and text documents LibreOffice can turn into a PDF, by extension.
const THUMBNAIL_OFFICE_EXTENSIONS = ['doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'];

// Extensions of the documents whose first page this server can show.
function thumbnail_page_extensions(): array
{
    static $extensions = null;
    if ($extensions === null) {
        // an Illustrator file is a PDF inside
        $extensions = is_executable('/usr/bin/pdftoppm') ? ['pdf', 'ai'] : [];
        if ($extensions !== [] && is_executable('/usr/bin/soffice')) {
            $extensions = array_merge($extensions, THUMBNAIL_OFFICE_EXTENSIONS);
        }
    }
    return $extensions;
}

/**
 * What the thumbnail of an upload shows: 'image' for a picture, 'page' for
 * the first page of a document, '' when this server can make none.
 */
function thumbnail_preview_kind(string $type, string $name): string
{
    if (str_starts_with($type, 'image/')) {
        return 'image';
    }
    $extension = $type === 'application/pdf' ? 'pdf' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($extension, thumbnail_page_extensions(), true) ? 'page' : '';
}

function thumbnail_office_extension(string $uuid): string
{
    load_library('data');
    $meta = data_exists('.files_meta', $uuid) ? data_read('.files_meta', $uuid) : [];
    $extension = strtolower(pathinfo((string)($meta['name'] ?? ''), PATHINFO_EXTENSION));
    return in_array($extension, THUMBNAIL_OFFICE_EXTENSIONS, true) ? $extension : '';
}

/**
 * The first page of an office or text document: LibreOffice makes a PDF of
 * it, see thumbnail_open_pdf(). One conversion at a time in one profile, so
 * a grid of new documents does not start a LibreOffice per thumbnail.
 */
function thumbnail_open_office(string $path, string $extension)
{
    if (!is_executable('/usr/bin/soffice')) {
        return false;
    }
    $profile = $GLOBALS['SYSTEM']['data_base'] . '/.tmp/libreoffice';
    $work = $GLOBALS['SYSTEM']['data_base'] . '/.tmp/libreoffice-' . bin2hex(random_bytes(8));
    @mkdir($profile, 0750, true);
    if (!is_dir($profile) || !@mkdir($work, 0750)) {
        return false;
    }
    // LibreOffice picks its import filter from the extension
    $input = $work . '/document.' . $extension;
    $img = false;
    if (@copy($path, $input)) {
        $process = proc_open(['/usr/bin/flock', '-w', '45', $profile . '.lock',
            '/usr/bin/timeout', '60', '/usr/bin/soffice', '-env:UserInstallation=file://' . $profile,
            '--headless', '--norestore', '--convert-to', 'pdf', '--outdir', $work, $input],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes,
            $work, ['HOME' => $profile, 'PATH' => '/usr/bin:/bin']);
        if (is_resource($process) && proc_close($process) === 0 && is_file($work . '/document.pdf')) {
            $img = thumbnail_open_pdf($work . '/document.pdf');
        }
    }
    array_map('unlink', glob($work . '/*') ?: []);
    @rmdir($work);
    return $img;
}

function thumbnail_cache_is_valid($path, $source_mtime, $format)
{
    if (!is_file($path) || filesize($path) <= 0 || filemtime($path) < $source_mtime) {
        return false;
    }

    if ($format !== 'jpg') {
        return true;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false || fseek($handle, -2, SEEK_END) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    $end_marker = fread($handle, 2);
    fclose($handle);
    return $end_marker === "\xff\xd9";
}

function thumbnail_create($uuid, $size, $ratio = 0, $mode = 'h', $format = '', $private = false)
{
    if ($format !== 'jpg' && $format !== 'png') {
        $format = '';
    }

    $MAX_UPSCALE = 1.0;

    // 1. Create thumbnail from original

    $result = "";
    $org_path = sprintf("%s/.files/%s", $GLOBALS['SYSTEM']['data_base'], $uuid);

    // A cached thumbnail is returned before the original is opened
    $static_path = ($private ? $GLOBALS['SYSTEM']['data_base'] . '/.tmp/cache/_thumb_/' : $GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_/')
        . $GLOBALS['SYSTEM']['request_uri'];

    $query_ratio = get_variable('ratio', null, 'url');
    if (!empty($query_ratio)) {
        $static_path .= '_r' . $query_ratio;
    }
    if ($format !== '') {
        $static_path .= '_f' . $format;
    }

    clearstatcache(true, $static_path);
    $source_mtime = @filemtime($org_path) ?: 0;
    if (thumbnail_cache_is_valid($static_path, $source_mtime, $format)) {
        return $static_path;
    }
    // PDF and SVG have no image size, they are recognised by their MIME type
    list($org_w, $org_h, $org_type) = @getimagesize($org_path) ?: [0, 0, @mime_content_type($org_path)];
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
        case IMAGETYPE_WEBP:
            $org_img = imagecreatefromwebp($org_path);
            break;
        case IMAGETYPE_AVIF:
            $org_img = thumbnail_open_avif($org_path);
            break;
        case 'image/svg+xml':
            return $org_path;
            break;
        case 'application/pdf':
            $org_img = thumbnail_open_pdf($org_path);
            if ($org_img !== false) {
                // from here on the page is the PNG it was rendered to
                [$org_w, $org_h, $org_type] = [imagesx($org_img), imagesy($org_img), IMAGETYPE_PNG];
            }
            break;
        default:
            // office files are recognised by their name, their contents read as zip or text
            $extension = thumbnail_office_extension($uuid);
            if ($extension === '') {
                load_library('system-messages');
                system_message('unknown image type: ' . $org_type);
                return $result;
            }
            $org_img = thumbnail_open_office($org_path, $extension);
            if ($org_img !== false) {
                [$org_w, $org_h, $org_type] = [imagesx($org_img), imagesy($org_img), IMAGETYPE_PNG];
            }
            break;
    }

    if (empty($org_img)) {
        return $result;
    }

    if ($org_type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($org_path);
        $orientation = $exif['Orientation'] ?? 1;
        if ($orientation === 3) {
            $org_img = imagerotate($org_img, 180, 0);
        } else if ($orientation === 6) {
            $org_img = imagerotate($org_img, -90, 0);
            [$org_w, $org_h] = [$org_h, $org_w];
        } else if ($orientation === 8) {
            $org_img = imagerotate($org_img, 90, 0);
            [$org_w, $org_h] = [$org_h, $org_w];
        }
    }

    $asp = $org_w / $org_h;
    $org_x = 0;
    $org_y = 0;
    $max_w = min(get_variable('max_img_w', 1920), $org_w * $MAX_UPSCALE); // max enlarging
    $max_h = min(get_variable('max_img_h', 1080), $org_h * $MAX_UPSCALE);

    //2: Calc thumbnail size given height and aspect ratio
    $no_ratio = empty($ratio) || ($ratio < 0) || (abs($asp - $ratio) < 0.01) || $mode === 'f' || $mode === 'c';
    $a = $no_ratio ? $asp : $ratio;

    if ($mode === 'f') {
        if ($size < $max_w) {
            $max_w = $size;
        }
        if ($ratio > 0 && $size / $ratio < $max_h) {
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

    @mkdir(dirname($static_path), 0750, true);

    $write_path = tempnam(dirname($static_path), basename($static_path) . '.tmp.');
    if ($write_path === false) {
        return $result;
    }

    switch ($org_type) {
        case IMAGETYPE_AVIF:
        case IMAGETYPE_JPEG:
        case IMAGETYPE_BMP:
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            thumbnail_sharpen($thumb_img);
            /*$wm = get_variable('watermark_image', false);
            if (!empty($wm) && ($w > 640 || $h > 640)) {
                thumbnail_stamp($thumb_img, $wm, $w, $h, get_variable('watermark_position', 'rightbottom'));
            }*/
            /* not yet supported in edge (grr)
            if (imageavif($thumb_img, $static_path, 65)) {
                $result = $static_path;
            }
            */
            if ($format === 'jpg') {
                if (imagejpeg($thumb_img, $write_path, 85)) {
                    $result = $write_path;
                }
            } else if ($format === 'png') {
                if (imagepng($thumb_img, $write_path, 6)) {
                    $result = $write_path;
                }
            } else if (imagewebp($thumb_img, $write_path, 85)) {
                $result = $write_path;
            }
            break;
        case IMAGETYPE_WEBP:
        case IMAGETYPE_PNG:
            imagealphablending($thumb_img, false);
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            thumbnail_sharpen($thumb_img);
            imagesavealpha($thumb_img, true);

            if ($format === 'jpg') {
                // JPEG has no alpha channel: flatten onto white before encoding.
                $flat_img = imagecreatetruecolor($w, $h);
                imagefill($flat_img, 0, 0, imagecolorallocate($flat_img, 255, 255, 255));
                imagecopy($flat_img, $thumb_img, 0, 0, 0, 0, $w, $h);
                if (imagejpeg($flat_img, $write_path, 85)) {
                    $result = $write_path;
                }
            } else if ($format === 'png') {
                if (imagepng($thumb_img, $write_path, 6)) {
                    $result = $write_path;
                }
            } else if (imagewebp($thumb_img, $write_path, 85)) {
                $result = $write_path;
            }
            break;
        case IMAGETYPE_GIF:
            imagealphablending($thumb_img, false);
            imagecopyresampled($thumb_img, $org_img, 0, 0, $org_x, $org_y, $w, $h, $org_w, $org_h);
            $bg = imagecolorallocate($thumb_img, 0, 0, 0);
            imagecolortransparent($thumb_img, $bg);
            imagesavealpha($thumb_img, true);
            if ($format === 'jpg') {
                $flat_img = imagecreatetruecolor($w, $h);
                imagefill($flat_img, 0, 0, imagecolorallocate($flat_img, 255, 255, 255));
                imagecopy($flat_img, $thumb_img, 0, 0, 0, 0, $w, $h);
                if (imagejpeg($flat_img, $write_path, 85)) {
                    $result = $write_path;
                }
            } else if ($format === 'png') {
                if (imagepng($thumb_img, $write_path, 6)) {
                    $result = $write_path;
                }
            } else if (imagewebp($thumb_img, $write_path)) {
                $result = $write_path;
            }
            break;
    }

    //4: return result

    if ($result === $write_path) {
        @chmod($write_path, 0644);
    }

    if ($result === $write_path && @rename($write_path, $static_path)) {
        $result = $static_path;
    } else {
        @unlink($write_path);
        clearstatcache(true, $static_path);
        if (is_file($static_path) && filesize($static_path) > 0) {
            $result = $static_path;
        }
    }

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
