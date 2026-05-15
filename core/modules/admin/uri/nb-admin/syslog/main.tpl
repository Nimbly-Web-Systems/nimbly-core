[#get-system-log#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between flex-wrap md:flex-nowrap">
	<div>
		<h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text System log#]</h1>
		<h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count system_log#] [#text entries#]</h3>
	</div>
	<div>
		<form action="[#url#]" method="post" accept-charset="utf-8" id="clearlog">
			[#form-key clearlog#]
			<button type="submit" class="[#btn-class-secondary#]">
				[#text Clear log#]
			</button>
		<form>
	</div>
</section>

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="nb_table">
	<div class="rounded-2xl shadow-md bg-neutral-50 p-4">
		<div class="form-control mb-4 max-w-xs">
			<label for="syslog_search" class="label">
				<span class="label-text">[#text Search#]</span>
			</label>
			<input id="syslog_search" type="search" class="input input-bordered input-sm bg-neutral-50"
				x-model="search" @input="filter_rows" placeholder="[#text Search#]">
		</div>
		<div class="overflow-x-auto">
		<table class="table table-zebra">
			<thead>
				<tr>
					<th><button type="button" class="font-semibold" @click="sort_by(0)">[#text Date#]</button></th>
					<th><button type="button" class="font-semibold" @click="sort_by(1)">[#text Type#]</button></th>
					<th><button type="button" class="font-semibold" @click="sort_by(2)">[#text Description#]</button></th>
				</tr>
			</thead>
			<tbody x-ref="body">
				[#repeat system_log var=record#]
			</tbody>
		</table>
		</div>
	</div>
</section>
