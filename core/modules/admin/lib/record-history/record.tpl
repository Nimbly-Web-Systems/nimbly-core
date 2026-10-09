<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/[#get history-home#]">[#resource-name [#resource-id#] plural#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text History#]</span>
    </nav>
    <div class="mb-4 flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-neutral-800 md:text-3xl">[#text History#]</h1>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            [#_rh.clear#]
            [#_rh.back#]
        </div>
    </div>
    [#_rh.forget#]
    <form action="[#base-url#]/nb-admin/[#get history-slug#]/[#get uuid#]/history" method="post" accept-charset="utf-8"
        class="rounded-box border border-base-300 bg-base-100 p-2 sm:p-3">
        [#form-key restore_record#]
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">[#text When#]</th>
                        <th scope="col">[#text Who#]</th>
                        <th scope="col">[#text Change#]</th>
                        <th scope="col"><span class="sr-only">[#text Actions#]</span></th>
                    </tr>
                </thead>
                <tbody>
                    [#_rh.rows#][#_rh.empty#]
                </tbody>
            </table>
        </div>
    </form>
</section>
