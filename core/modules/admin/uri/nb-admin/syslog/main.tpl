[#get-system-log#]
<script>
	[#include file=[#base-path#]core/modules/admin/tpl/utility-table/data_table.js#]
</script>
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

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="utility_table">
	<div class="w-full px-4 py-2 rounded-md shadow-md bg-neutral-50">
		<div class="flex flex-row items-center py-2">
			<input id="syslog_search" type="search" class="ml-auto py-1.5 px-4 focus:outline-2 focus:outline-cnormal"
				@input.debounce.150ms="search($event.target.value)" placeholder="[#text Search#]">
		</div>
		<div class="overflow-x-auto">
		<table class="min-w-full">
			<thead>
				<tr>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						<div class="flex items-center gap-2 group cursor-pointer" @click="toggle_sort(0)">
							<span>[#text Date#]</span>
							<button x-show="is_sorted_asc(0)" class="block">[#svg-chevron-up-4#]</button>
							<button x-show="is_sorted_desc(0)" class="block">[#svg-chevron-down-4#]</button>
							<button x-show="!is_sorted_asc(0) && !is_sorted_desc(0)" x-cloak class="group-hover:visible invisible">[#svg-chevron-down-4#]</button>
						</div>
					</th>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						<div class="flex items-center gap-2 group cursor-pointer" @click="toggle_sort(1)">
							<span>[#text Type#]</span>
							<button x-show="is_sorted_asc(1)" class="block">[#svg-chevron-up-4#]</button>
							<button x-show="is_sorted_desc(1)" class="block">[#svg-chevron-down-4#]</button>
							<button x-show="!is_sorted_asc(1) && !is_sorted_desc(1)" x-cloak class="group-hover:visible invisible">[#svg-chevron-down-4#]</button>
						</div>
					</th>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						<div class="flex items-center gap-2 group cursor-pointer" @click="toggle_sort(2)">
							<span>[#text Description#]</span>
							<button x-show="is_sorted_asc(2)" class="block">[#svg-chevron-up-4#]</button>
							<button x-show="is_sorted_desc(2)" class="block">[#svg-chevron-down-4#]</button>
							<button x-show="!is_sorted_asc(2) && !is_sorted_desc(2)" x-cloak class="group-hover:visible invisible">[#svg-chevron-down-4#]</button>
						</div>
					</th>
				</tr>
			</thead>
			<tbody x-ref="body">
				[#repeat system_log var=record#]
			</tbody>
		</table>
		</div>
		<div class="flex flex-row items-center py-4">
			<div class="ml-auto text-sm text-neutral-700">
				[#text Rows per page:#]
				<select x-model="page_size" class="px-2 pt-1 pb-2 ml-2 border border-neutral-200 focus:border-primary rounded" @change="page = 1; render()">
					<option value="10">10</option>
					<option value="25">25</option>
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="200">200</option>
					<option value="9999999">[#text All#]</option>
				</select>
			</div>
			<div x-text="Math.min(((page - 1) * page_size) + 1, total_count()) + ' - ' + Math.min(total_count(), page * page_size) + ' of ' + total_count()"
				class="ml-6 text-sm text-neutral-700"></div>
			<div class="mx-4">
				<button class="pt-1.5 text-neutral-700 disabled:text-neutral-300 disabled:pointer-events-none hover:bg-neutral-100"
					@click="prev()" :disabled="page < 2">
					<span class="text-lg leading-none">&lt;</span>
				</button>
			</div>
			<div>
				<button class="pt-1.5 text-neutral-700 disabled:text-neutral-300 disabled:pointer-events-none hover:bg-neutral-100"
					@click="next()" :disabled="page >= page_count()">
					<span class="text-lg leading-none">&gt;</span>
				</button>
			</div>
		</div>
	</div>
</section>
