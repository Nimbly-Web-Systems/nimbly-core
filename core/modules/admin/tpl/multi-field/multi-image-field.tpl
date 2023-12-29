<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4">
    <label for="" class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 
            -translate-y-[10px]
            bg-neutral-50">
        [#text [#field-name name="[#item.name#]"#]#]
    </label>
    <table class="min-w-full text-left text-sm font-light">
        <thead class="border-b font-medium dark:border-neutral-500">
            <tr>
                <th scope="col" class="px-6 py-4">#</th>
                <th scope="col" class="px-6 py-4">[#text Image#]</th>
                <th scope="col" class="px-6 py-4">[#text Actions#]</th>
            </tr>
        </thead>
        <tbody 
            x-init='init_multi_image_field("[#item.key#]", [#get record.[#item.key#] default="[]" json#])'
            data-nb-edit-multi-image="[#item.key#]">
            <template>
                <tr class="border-b dark:border-neutral-500">
                    <td class="whitespace-nowrap px-6 py-4 font-medium">1</td>
                    <td class="whitespace-nowrap px-6 py-4">Mark</td>
                    <td class="whitespace-nowrap px-6 py-4">@mdo</td>
                </tr>
            </template>
        </tbody>
    </table>
</div>