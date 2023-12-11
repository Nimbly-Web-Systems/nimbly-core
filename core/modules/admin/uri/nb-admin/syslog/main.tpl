[get-system-log]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between flex-wrap md:flex-nowrap">
	<div>
		<h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[text System log]</h1>
		<h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[count system_log] [text entries]</h3>
	</div>
	<div>
		<form action="[url]" method="post" accept-charset="utf-8" id="clearlog">
			[form-key clearlog]
			<button type="submit" class="[btn-class-secondary]">
				[text Clear log]
			</button>
		<form>
	</div>
</section>

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
	<div data-te-datatable-init class="rounded-2xl shadow-md bg-neutral-50 p-4"
		data-te-no-found-message="[text No entries]" data-te-class-color="bg-neutral-50">
		<table>
			<thead>
				<tr>
					<th>[text Date]</th>
					<th>[text Type]</th>
					<th>[text Description]</th>
				</tr>
			</thead>
			<tbody>
				[repeat system_log var=record]
			</tbody>
		</table>
	</div>
</section>