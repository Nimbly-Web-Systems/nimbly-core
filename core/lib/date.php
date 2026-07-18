<?php

/**
 * @doc `[date input fmt=long]` formats a date. Named formats full/long/medium/short are locale-aware; other values use PHP date format syntax.
 */
function date_sc($params)
{
    $date = get_param_value($params, 'date', null);
    $fmt = get_param_value($params, 'fmt', null);
    $lang = get_param_value($params, 'lang', null);

    // bareword params (e.g. an already-resolved date value, or a format
    // like "Y-m-d") come through as $key === $value; the previous
    // count($params)-based logic dropped the date value whenever fmt=
    // was also present, silently falling back to "now".
    $positional = [];
    foreach ($params as $key => $value) {
        if ($key === $value) {
            $positional[] = $value;
        }
    }

    if ($date === null && !empty($positional)) {
        $date = array_shift($positional);
    }
    if ($fmt === null) {
        $fmt = !empty($positional) ? array_pop($positional) : 'd-m-Y';
    }
    if ($date === null) {
        $date = time();
    }

    if ($lang === null && date_format_is_named($fmt)) {
        load_library('detect-language');
        $lang = detect_language_sc();
    }

    return date_format_value($date, $fmt, $lang);
}

function date_format_value($value, string $fmt = 'd-m-Y', ?string $lang = null): string
{
    $timezone = new DateTimeZone(date_default_timezone_get());
    $date = date_format_parse_value($value, $timezone);
    if ($date === null) {
        return '';
    }

    if (!date_format_is_named($fmt)) {
        return $date->format($fmt);
    }

    $lang = $lang ?: 'en';
    $localized = date_format_localized($date, $fmt, $lang, $timezone);
    if ($localized !== null) {
        return $localized;
    }

    $fallbacks = [
        'full' => 'l, F j, Y',
        'long' => 'F j, Y',
        'medium' => 'M j, Y',
        'short' => 'Y-m-d',
    ];
    return $date->format($fallbacks[$fmt]);
}

function date_format_is_named(?string $fmt): bool
{
    return in_array($fmt, ['full', 'long', 'medium', 'short'], true);
}

function date_format_parse_value($value, DateTimeZone $timezone): ?DateTimeImmutable
{
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
    }

    if (is_numeric($value)) {
        return (new DateTimeImmutable('@' . (string)$value))->setTimezone($timezone);
    }

    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }

    try {
        return new DateTimeImmutable($value, $timezone);
    } catch (Throwable $error) {
        return null;
    }
}

function date_format_localized(DateTimeImmutable $date, string $fmt, string $lang, DateTimeZone $timezone): ?string
{
    if (!extension_loaded('intl') || !class_exists('IntlDateFormatter')) {
        date_format_log_intl_fallback();
        return null;
    }

    $styles = [
        'full' => IntlDateFormatter::FULL,
        'long' => IntlDateFormatter::LONG,
        'medium' => IntlDateFormatter::MEDIUM,
        'short' => IntlDateFormatter::SHORT,
    ];
    $locale = str_replace('-', '_', $lang);
    $cache_key = $locale . '|' . $fmt . '|' . $timezone->getName();
    static $formatters = [];

    try {
        if (!isset($formatters[$cache_key])) {
            $formatters[$cache_key] = new IntlDateFormatter(
                $locale,
                $styles[$fmt],
                IntlDateFormatter::NONE,
                $timezone->getName(),
                IntlDateFormatter::GREGORIAN
            );
        }
        $result = $formatters[$cache_key]->format($date);
        return $result === false ? null : $result;
    } catch (Throwable $error) {
        date_format_log_intl_fallback();
        return null;
    }
}

function date_format_log_intl_fallback(): void
{
    static $logged = false;
    if ($logged) {
        return;
    }
    $logged = true;
    error_log('Nimbly: php-intl unavailable or failed; using non-localized date fallback.');
}
