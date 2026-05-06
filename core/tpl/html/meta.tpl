<meta charset="utf-8" />
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="generator" content="nimbly">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
[#set page-description="[#get-key data.config.site description default=nimbly#]"#]
[#set og-type=website#]
<meta name="description" content="[#page-description#]">
<meta property="og:type" content="[#og-type#]">
<meta property="og:title" content="[#page-title#]">
<meta property="og:description" content="[#page-description#]">
<meta property="og:url" content="[#url absolute#]">
<meta property="og:site_name" content="[#site-name#]">
<meta property="og:locale" content="[#language#]">
[#if og-image=(not-empty) tpl=meta-og-image#]
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="[#page-title#]">
<meta name="twitter:description" content="[#page-description#]">
