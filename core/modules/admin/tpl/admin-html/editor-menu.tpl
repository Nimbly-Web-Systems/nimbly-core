<ul class="nb-menu">
    <li><a href="[base-url]/">Site home</a></li>
</ul>
<ul class="nb-menu">
    [get-user-resources]
	[repeat data.user-resources]
</ul>
<ul class="nb-menu">
	[set edit-menu-extend=""]
	[edit-menu-extend]
</ul>
[feature-cond admin tpl=admin-menu]
<ul class="nb-menu">
    <li><a href="[base-url]/logout">Logout</a></li>
</ul>

