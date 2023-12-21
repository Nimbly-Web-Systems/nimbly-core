[#module downloads#]
[#module user#]
[#data .config uuid=site#]
[#set site-name="[#get-key data.config.site name#]"#]
[#set app-name="Nimbly Framework"#]
[#set language=en#]
[#set body-classes=#]
[#set html-classes="[#logged-in#]"#]
[#set head=#]
[#set footer=#]
[#set header=#]
[#set main=#]
[#init#]
<!doctype html>
<html class="[#html-classes#] scroll-smooth" lang="[#language#]">
    <head>
        <title>[#page-title#] | [#site-name#]</title>
        [#meta#]
        [#fonts#]
        [#stylesheets#]
        [#head#]
        [#favicon#]
    </head>
    <body class="[#body-classes#]">
        [#feature-cond manage-content tpl=nimblybar#]
        [#callouts#]
        <div id="page">
            <header id="header">
                [#header#]
            </header> 
            <main id="main">
                [#main#]
               </main> 
            <footer id="footer">
                [#footer#]
            </footer>
        </div>
        <script> 
            [#scripts#]
        </script>
    </body>
</html>