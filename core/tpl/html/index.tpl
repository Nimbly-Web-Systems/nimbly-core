[module user]
[set app-name="Nimbly Framework"]
[data .config uuid=site]
[set site-name="[get-key data.config.site name]"]
[set language=en]
[set body-classes=]
[set html-classes="[logged-in]"]
[set page-title=Home]
[set head=]
[init]
<!doctype html>
<html class="[html-classes]" lang="[language]">
    <head>
        [meta]
        <title>[page-title] | [site-name]</title>
        [stylesheets]
        [head]
        [scripts]
        [favicon]
    </head>
    <body class="[body-classes] [feature-cond manage-content echo=nimbly-bar]">
        [feature-cond manage-content echo=nimbly-bar]
        [feature-cond manage-content tpl=nimbly-bar]
        [callouts]
        [mobile-menu]
        [header]
        [body]
        [footer]
        <script>
            [load-scripts]
        </script>
    </body>
</html>
