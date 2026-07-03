[#get-system-log#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
	<nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
		[#breadcrumb-home#]
		<span aria-hidden="true">/</span>
		<span class="text-neutral-700">[#text System log#]</span>
	</nav>
	<div class="flex justify-between flex-wrap md:flex-nowrap">
	<div class="[#feature-cond clear-system-log echo_else=hidden#]">
		<h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text System log#]</h1>
		<h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count system_log#] [#text entries#]</h3>
	</div>
	<div>
		<form action="[#url#]" method="post" accept-charset="utf-8" id="clearlog">
			[#form-key clearlog#]
			<button type="submit" class="[#btn-class-secondary#]">
				[#text Clear log#]
			</button>
		</form>
	</div>
	</div>
</section>

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
	<div class="w-full px-4 py-2 rounded-md shadow-md bg-neutral-50">
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
			</tbody>
		</table>
		</div>
	</div>
</section>
