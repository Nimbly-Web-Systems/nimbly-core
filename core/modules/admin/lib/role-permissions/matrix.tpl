<section>
    <div class="mb-3">
        <h3 class="text-base font-semibold text-neutral-800">[#_matrix.title#]</h3>
        <p class="mt-1 text-sm text-neutral-500">[#_matrix.description#]</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-neutral-200 bg-white shadow-md" x-data="{search:''}">
        <div class="border-b border-neutral-200 p-3">
            <input type="search" placeholder="[#text Filter resources...#]" x-model="search"
                class="w-full max-w-xs rounded border border-neutral-300 px-3 py-1.5 text-sm focus:outline-2 focus:outline-cnormal">
        </div>
        <table class="min-w-full text-sm">
            <caption class="sr-only">[#_matrix.title#]</caption>
            <thead>
                <tr class="border-b border-neutral-200 bg-neutral-100/80 text-left text-xs uppercase tracking-wide text-neutral-500">
                    <th scope="col" class="sticky left-0 z-10 min-w-52 bg-neutral-100 px-3 py-2 font-semibold">[#text Resource#]</th>
                    <th scope="col" class="bg-amber-50 px-3 py-2 text-center font-semibold text-amber-700">[#text Manage#]</th>
                    [#_matrix.operation_headers#]
                </tr>
            </thead>
            <tbody>
                [#_matrix.rows#]
            </tbody>
        </table>
    </div>
</section>
