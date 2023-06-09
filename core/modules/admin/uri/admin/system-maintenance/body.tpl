<h1>System Maintenance</h1>

<p>Disk space available: [disk-space-free]Gb</br>
Disk space total: [disk-space-total]Gb</p>

<table class="nb-table">
	<thead>
        <tr>
          	<th>Name</th>
			<th>Description</th>
        </tr>
	</thead>
	<tbody>
		<tr>
			<td><a href='[base-url]/admin/.config/site'>Site config</a></td>
			<td>Site configuration settings.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/modules'>Modules</a></td>
			<td>Overiew of modules and module installer.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/resources'>Resources</a></td>
			<td>Overview of all resources.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/.block-templates'>Block Templates</a></td>
			<td>Manage the editor buttons for different block templates.</td>
		</tr>
		<tr>
			<td>
				<form action="[url]" method="post" accept-charset="utf-8" id="ccache">
					[form-key ccache]
					<a href='#' data-submit-std="form#ccache" data-confirm="Press OK to continue and clear cached JS, CSS and HTML files.">Clear cache</a>
				</form>
			</td>
			<td>Clears server-side javascript and css cache.</td>
		</tr>
		<tr>
			<td>
				<form action="[url]" method="post" accept-charset="utf-8" id="ccache_thumbs">
					[form-key ccache_thumbs]
					<a href='#' data-submit-std="form#ccache_thumbs" data-confirm="Press OK to continue and clear cached thumbnails.">Clear thumbnail cache</a>
				</form>
			</td>
			<td>Clears thumbnail cache.</td>
		</tr>
		<tr>
			<td>
				<form action="[url]" method="post" accept-charset="utf-8" id="ccache_sessions">
					[form-key ccache_sessions]
					<a href='#' data-submit-std="form#ccache_sessions" data-confirm="Press OK to kill all sessions and log out any users">Kill sessions</a>
				</form>
			</td>
			<td>Removes all sessions. This will log out all users (including yourself!).</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/debug'>Debug</a></td>
			<td>Show nimbly system info, including current set variables, session and <a href='[base-url]/admin/debug/php'>PHP Info</a>.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/log'>PHP Error Log</a></td>
			<td>Show php error log file.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/.log-entries'>System log entries</a></td>
			<td>Show system log entries.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/shortcodes'>Shortcodes</a></td>
			<td>Overview of shortcodes with description of what they do.</td>
		</tr>
		<tr>
			<td><a href='[base-url]/admin/reinstall'>Reinstall Nimbly Core</a></td>
			<td>Reinstall to update .htaccess; resets admin and editor role.</td>
		</tr>
		<tr>
			<td colspan="2">[admin-menu-extend]</td>
		</tr>
	</tbody>
</table>