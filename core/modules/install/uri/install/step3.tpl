<p>
<ul>
	[data .config uuid=site]
	<li>Site name: [get-key data.config.site name]</li>
    <li>[text htaccess_created_[htaccess_ok]]</li>
    <li>[text readme_created_[readme_ok]]</li>
    <li>[text gitignore_created_[gitignore_ok]]</li>
    <li>[text user_created_[user_ok]]</li>
</ul>
</p>
<p>
    [text step3_proceed]
</p>


<div class="button-group">
    <a href="[base-url]/login" class="nb-button" >Next</a>
</div>
