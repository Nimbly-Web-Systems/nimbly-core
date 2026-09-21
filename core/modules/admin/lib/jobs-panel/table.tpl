<div class="mt-4 w-full rounded-md bg-neutral-50 px-3 py-2 shadow-md sm:px-4">
    <div class="overflow-x-auto">
    <table class="min-w-full">
        <caption class="sr-only">[#text Jobs#]</caption>
        <thead>
            <tr>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-left">[#text Status#]</th>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-left">[#text Type#]</th>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-center">[#text Attempts#]</th>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-left">[#text Last error#]</th>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-left">[#text Updated#]</th>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 pr-4 text-right [#feature-cond manage-.jobs echo_else=hidden#]"><span class="sr-only">[#text Actions#]</span></th>
            </tr>
        </thead>
        <tbody>
            [#_jp.rows#]
            <tr x-show="records.length > 0 && filtered_count() === 0" x-cloak>
                <td colspan="6" class="py-3 pr-4 text-neutral-600">[#text No matching jobs#]</td>
            </tr>
        </tbody>
    </table>
    </div>
</div>
