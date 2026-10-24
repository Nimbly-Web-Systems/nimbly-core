<?php

load_library('data');
load_library('thumbnail');
load_library('get-document-records');

/**
 * The cover of a document record, queued by "job:document-cover" in the
 * events of its .meta. An empty cover, or one made of an earlier file, gets
 * the first page of the file; a cover someone chose stays.
 */
function document_cover_job($job)
{
    $due = document_cover_due($job['payload'] ?? []);
    if ($due === null) {
        return;
    }
    [$resource, $uuid, $field, $file_uuid] = $due;
    $file = data_read('.files_meta', $file_uuid);
    $path = data_path('.files', $file_uuid);
    $extension = thumbnail_office_extension($file_uuid);
    $img = $extension !== '' ? thumbnail_open_office($path, $extension) : thumbnail_open_pdf($path);
    if ($img === false) {
        // tried again later, LibreOffice may have been busy
        throw new Exception('No first page of ' . $file_uuid);
    }
    $cover_uuid = document_cover_store($img, (string)($file['name'] ?? ''), $file_uuid);
    if ($cover_uuid === '' || !data_update($resource, $uuid, [$field => $cover_uuid])) {
        throw new Exception('Cover of ' . $uuid . ' not saved');
    }
}

/** The cover field, while the page waits for the job, see job_fields(). */
function document_cover_job_fields($payload): array
{
    $due = document_cover_due(is_array($payload) ? $payload : []);
    return $due !== null ? [$due[2]] : [];
}

/** [resource, uuid, cover field, file uuid] when the record needs a new cover, else null. */
function document_cover_due(array $payload): ?array
{
    $resource = (string)($payload['resource'] ?? '');
    $uuid = (string)($payload['uuid'] ?? '');
    if ($resource === '' || $uuid === '' || !data_exists($resource, $uuid)) {
        return null;
    }
    $fields = document_explorer_fields($resource, data_meta($resource));
    if ($fields['file'] === null || $fields['cover'] === null) {
        return null;
    }
    $record = data_read($resource, $uuid);
    $file_uuid = (string)($record[$fields['file']] ?? '');
    $cover_uuid = (string)($record[$fields['cover']] ?? '');
    if ($file_uuid === '' || !data_exists('.files_meta', $file_uuid)) {
        return null;
    }
    if ($cover_uuid !== '' && data_exists('.files_meta', $cover_uuid)) {
        $cover = data_read('.files_meta', $cover_uuid);
        if (!isset($cover['cover_of']) || $cover['cover_of'] === $file_uuid) {
            return null;
        }
    }
    // a picture is its own cover, see the document explorer
    $file = data_read('.files_meta', $file_uuid);
    if (thumbnail_preview_kind((string)($file['type'] ?? ''), (string)($file['name'] ?? '')) !== 'page') {
        return null;
    }
    return [$resource, $uuid, $fields['cover'], $file_uuid];
}

/** The page as a JPEG upload of its own; '' when it could not be written. */
function document_cover_store($img, string $name, string $source_uuid): string
{
    $scale = 1200 / max(imagesx($img), imagesy($img));
    if ($scale < 1) {
        $img = imagescale($img, (int)round(imagesx($img) * $scale), (int)round(imagesy($img) * $scale));
    }
    $dir = $GLOBALS['SYSTEM']['data_base'] . '/.files/';
    @mkdir($dir, 0750, true);
    $tmp = tempnam($dir, '.cover');
    if ($tmp === false || !imagejpeg($img, $tmp, 85)) {
        @unlink((string)$tmp);
        return '';
    }
    // the checksum is the uuid, as for an upload, see files_post()
    $uuid = hash_file('md5', $tmp);
    if (data_exists('.files', $uuid)) {
        @unlink($tmp);
        return $uuid;
    }
    [$width, $height] = [imagesx($img), imagesy($img)];
    $now = time();
    $meta = [
        'name' => pathinfo($name, PATHINFO_FILENAME) . '.jpg',
        'uuid' => $uuid,
        'type' => 'image/jpeg',
        'size' => filesize($tmp),
        'width' => $width,
        'height' => $height,
        'orientation' => $width >= $height ? 'landscape' : 'portrait',
        'aspect_ratio' => $width / $height,
        // made from this file, so a new file gets a new cover
        'cover_of' => $source_uuid,
        '_created' => $now,
        '_modified' => $now,
        '_uploaded' => $now,
    ];
    @chmod($tmp, 0640);
    if (!rename($tmp, $dir . $uuid) || !data_create('.files_meta', $uuid, $meta)) {
        @unlink($tmp);
        return '';
    }
    return $uuid;
}
