# deny access to certain file extensions, including PHP files
<FilesMatch "\.(tpl|inc|php|htaccess|md)$">
    deny from all
</FilesMatch>

# index.php is the only PHP file which can be accessed from the browser
<Files index.php>
    allow from all
</Files>

# never show directory listings for URLs which map to a directory.
Options -Indexes

# follow symbolic links in this directory.
Options +FollowSymLinks

# disable MultiViews
Options -MultiViews

# set the one and only default handler
DirectoryIndex index.php

# pass the default character set
AddDefaultCharset utf-8

# set the default language
DefaultLanguage en-US

# set the security pepper hash code (unique per installation)
SetEnv PEPPER %%PEPPER%%

# compress text, html, javascript, css, xml:
AddOutputFilterByType DEFLATE text/plain
AddOutputFilterByType DEFLATE text/html
AddOutputFilterByType DEFLATE text/xml
AddOutputFilterByType DEFLATE text/css
AddOutputFilterByType DEFLATE application/xml
AddOutputFilterByType DEFLATE application/xhtml+xml
AddOutputFilterByType DEFLATE application/rss+xml
AddOutputFilterByType DEFLATE application/javascript
AddOutputFilterByType DEFLATE application/x-javascript
AddType application/manifest+json .webmanifest

# cache control headers
<IfModule mod_headers.c>

    Header unset ETag
    FileETag None

    # 480 weeks
    <FilesMatch ".(ico|pdf|webm|mp4|jpg|jpeg|png|gif|js|css|svg|webp|avif)$">
    Header set Cache-Control "max-age=290304000, public"
    </FilesMatch>

    <FilesMatch "^(manifest\.webmanifest|service-worker\.js)$">
    Header set Cache-Control "no-cache, must-revalidate"
    </FilesMatch>
</IfModule>

# rewrite: initialize
RewriteEngine on
RewriteBase %%REWRITE_BASE%%

# pass the Authorization header through to PHP-FPM (Apache strips it by
# default; the API's Bearer token auth reads it via getallheaders())
CGIPassAuth On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]

# rewrite: use EXT static/_thumb_  if available for requested img file
RewriteCond %{REQUEST_URI} ^/%%REWRITE_BASE_PATH%%img/.*
RewriteCond %{QUERY_STRING} ^ratio=((?:0|[1-9][0-9]*)(?:\.[0-9]+)?)$
RewriteRule ^ - [E=IMG_RATIO:_r%1]

RewriteCond %{QUERY_STRING} ^$ [OR]
RewriteCond %{ENV:IMG_RATIO} !^$
RewriteCond %{REQUEST_URI} ^/%%REWRITE_BASE_PATH%%img/(.*)
RewriteCond ext/static/_thumb_/img/%1%{ENV:IMG_RATIO} -F
Header set Content-Type "image/webp" "expr=-z %{CONTENT_TYPE}"
RewriteRule ^ ext/static/_thumb_/img/%1%{ENV:IMG_RATIO} [END]

# rewrite: use EXT static if available for the requested file
RewriteCond %{REQUEST_URI} ^/%%REWRITE_BASE_PATH%%(.*)
RewriteCond ext/static/%1 -F
RewriteRule ^ ext/static/%1 [END]

# rewrite: use CORE static if availble for the requested file
RewriteCond %{REQUEST_URI} ^/%%REWRITE_BASE_PATH%%(.*)
RewriteCond core/static/%1 -F
RewriteRule ^ core/static/%1 [END]

# block direct .php requests
RewriteCond %{THE_REQUEST} \s/+.*\.php[?\s] [NC]
RewriteCond %{REQUEST_URI} !^/index\.php$
RewriteCond %{REQUEST_URI} !^/install\.php$
RewriteRule ^ - [F]

# rewrite: redirect anything that is not a file to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [END]

#rewrite: redirect any attempt to access a hidden file/dir (starting with a .) to index.php
RewriteRule ^\..*$ index.php [END]

# rewrite: don't allow a direct request to a static file folder (redirect to index.php)
RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ /[^\ ]+/(ext|core)/static/.*($|\ ) [NC]
RewriteRule ^ index.php [END]
