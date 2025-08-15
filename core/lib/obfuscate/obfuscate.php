<?php

function obfuscate_sc($params)
{
	$txt = implode(' ', $params);
	$result = '<span x-data x-cloak x-init="$el.querySelectorAll(\'[data-ch]\').forEach(e=>{e.textContent=e.dataset.ch;})">';
	$invisibles = ["\u{200B}", "\u{200C}", "\u{200D}", "\u{2060}"];

	for ($i = 0; $i < strlen($txt); $i++) {
		$result .= obfuscate_rnd(mt_rand(1, 6));
		$result .= $invisibles[array_rand($invisibles)];
		$result .= '<span data-ch="' . htmlspecialchars($txt[$i], ENT_QUOTES) . '"></span>';
		$result .= obfuscate_rnd(mt_rand(1, 6));
		$result .= $invisibles[array_rand($invisibles)];
	}
	$result .= '</span>';
	return $result;
}

function obfuscate_rnd($length = 4)
{
	$tags  = ['span', 'i', 'strong', 'b', 'small', 'em'];
	$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$tag   = $tags[mt_rand(0, count($tags) - 1)];
	$result = '<' . $tag . ' class="hidden" aria-hidden="true">';
	$result .= substr(str_shuffle(str_repeat($chars, (int)ceil($length / strlen($chars)))), 0, $length);
	$result .= '</' . $tag . '>';
	return $result;
}
