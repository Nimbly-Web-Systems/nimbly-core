[#module downloads#]
[#module user#]
[#data .config uuid=site#]
[#set site-name="[#get-key data.config.site name#]"#]
[#set app-name="Nimbly Framework"#]
[#set language=[#detect-language#]#]
[#set body-classes=#]
[#set html-classes="[#logged-in#]"#]
[#set head=#]
[#set footer=#]
[#set header=#]
[#set main=#]
[#init#]
<!doctype html>
<html class="[#html-classes#] scroll-smooth" lang="[#language#]">
<!--

# This website is developed with #
 __ _  __  _  _  ____  __    _  _ 
(  ( \(  )( \/ )(  _ \(  )  ( \/ )
/    / )( / \/ \ ) _ (/ (_/\ )  / 
\_)__)(__)\_)(_/(____/\____/(__/  

-->
<head>
    <title>[#page-title#] | [#site-name#]</title>
    [#meta#]
    [#fonts#]
    [#stylesheets#]
    [#head#]
    [#favicon#]
</head>

<body class="[#body-classes#]">
    [#feature-cond manage-content,nimblybar tpl=nimblybar#]
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
    [#scripts#]
</body>

</html>