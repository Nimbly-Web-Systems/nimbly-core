<?php

function slug_sc($params) {
    $result = is_array($params) ? implode(' ', $params) : $params;
    $result = mb_strtolower($result, 'UTF-8'); // Lowercase with Unicode support
    $result = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $result); // Allow letters and numbers (Unicode-aware)
    $result = trim($result, '-'); // Remove leading/trailing hyphens
    return $result;
}