[#data .config.[#url-key#] var=page_settings#]
[#set page-title="[#get page_settings.page_title default="[#get page_title#]"#]"#]
[#page-settings-description#]
[#if page_settings.share_image=(not-empty) tpl=page-settings-share-image#]
