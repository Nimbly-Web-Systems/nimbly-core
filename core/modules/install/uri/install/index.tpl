[module install forms admin user i18n data]
[check-fresh-install]
[if not fresh_install="yes" redirect=errors/404]
[set page-title="Installation"]
[session-test]
[if session_ok=pass tpl=post]
[set app-name="Nimbly"]
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>[page-title] | [app-name]</title>
        <style>
		    [include file=[base-path]core/modules/html-jquery/uri/css/app.css/css/style.css]

		    .callout {
		    	border-radius: 5px;
		    	padding: 10px!important;
		    	color: #fff;
		    	margin-bottom: 2em;
		    }

		    .callout.alert {
		    	background-color: #f44336;
		    	opacity: .8;
		    	font-weight: bold;
		    }
		</style>
        <link rel="shortcut icon" href="[base-url]/favicon.png" />
    </head>
    <body class="install">
        <div class="admin-wrapper">
            <div class="admin-body">
                <div class="admin-content">
                    <div class="nb-container">
						<h1>[app-name] [text Installation]</h1>
						[check-requirements]
						[if require_all=fail tpl=requirements-failed]
						[if require_all=pass tpl=form]
					</div>
                </div>
            </div>
        </div>
    </body>
</html>