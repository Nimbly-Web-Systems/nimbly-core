[#get-system-log#]
<script>
    var _system_log_records=[#fmt var=system_log empty=[] json#];
</script>
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary"
    x-data="{
        search_term: '',
        issue_type: '',
        records: _system_log_records,
        types() {
            return [...new Map(this.records.map(record => [record.category, record.category_label])).entries()]
                .map(([value, label]) => ({value, label}))
                .sort((a, b) => a.label.localeCompare(b.label));
        },
        matches(record) {
            if (this.issue_type !== '' && record.category !== this.issue_type) {
                return false;
            }
            const search = this.search_term.trim().toLowerCase();
            return search.length < 3
                || `${record.category_label} ${record.type} ${record.message}`.toLowerCase().includes(search);
        },
        filtered_count() {
            return this.records.filter(record => this.matches(record)).length;
        }
    }">
	<nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
		[#breadcrumb-home#]
		<span aria-hidden="true">/</span>
		<span class="text-neutral-700">[#text System log#]</span>
	</nav>
	<div class="flex justify-between flex-wrap md:flex-nowrap">
	<div>
		<h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text System log#]</h1>
		<h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">
            <span x-text="filtered_count()"></span> [#text of#] [#count system_log#] [#text entries#]
        </h3>
	</div>
	<div class="[#feature-cond clear-system-log echo_else=hidden#]">
		<form action="[#url#]" method="post" accept-charset="utf-8" id="clearlog">
			[#form-key clearlog#]
			<button type="submit" class="[#btn-class-secondary#]">
				[#text Clear log#]
			</button>
		</form>
	</div>
	</div>

	<div class="mt-4 rounded-box border border-base-300 bg-base-100 p-2 sm:p-3">
		<div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
			<label class="input w-full sm:w-56 lg:w-64">
				<svg class="size-4 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
				</svg>
				<input type="search" placeholder="[#text Search#]" x-model.debounce.150ms="search_term" />
			</label>
			<label class="select w-full sm:w-auto sm:min-w-48">
				<span class="label">[#text Type#]</span>
				<select x-model="issue_type">
					<option value="">[#text All#]</option>
					<template x-for="type in types()" :key="type.value">
						<option :value="type.value" x-text="type.label"></option>
					</template>
				</select>
			</label>
		</div>
	</div>

	<div class="mt-4 w-full px-4 py-2 rounded-md shadow-md bg-neutral-50">
		<div class="overflow-x-auto">
		<table class="min-w-full">
			<thead>
				<tr>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						[#text Date#]
					</th>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						[#text Type#]
					</th>
					<th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">
						[#text Description#]
					</th>
				</tr>
			</thead>
			<tbody>
				[#repeat system_log var=record empty=system_log_empty#]
				<tr x-show="records.length > 0 && filtered_count() === 0" x-cloak>
					<td colspan="3" class="text-neutral-600 py-3 border-b border-neutral-200">[#text No matching entries#]</td>
				</tr>
			</tbody>
		</table>
		</div>
	</div>
</section>
