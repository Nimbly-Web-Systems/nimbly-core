<ul class="list-disc text-sm bg-green-100 border border-green-300 text-green-900 pl-10 py-4 pr-4">
    [#data .config uuid=site#]
    <li>Site name: [#get-key data.config.site name#]</li>
    <li>[#text htaccess_created_[#htaccess_ok#]#]</li>
    <li>[#text readme_created_[#readme_ok#]#]</li>
    <li>[#text gitignore_created_[#gitignore_ok#]#]</li>
    <li>[#text user_created_[#user_ok#]#]</li>
</ul>

<p class="text-sm text-neutral-800 py-4">
    [#text step3_proceed#]
</p>

<div class="text-right mt-8">
    <a href="[#base-url#]/login"  class="[#btn-class-primary#]"  /> [#text Next#]</a>
</div>