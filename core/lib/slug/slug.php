<?php

function slug_sc($params) {
	$result = is_array($params)? implode(' ', $params) : $params;
	$result = strtolower($result);
	$result = preg_replace("![^a-z0-9]+!i", "-", $result);
	return $result;
}