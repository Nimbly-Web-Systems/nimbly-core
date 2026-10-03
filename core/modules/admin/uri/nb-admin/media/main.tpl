<div x-data="media_library">
  <section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
      [#breadcrumb-home#]
      <span aria-hidden="true">/</span>
      <span class="text-neutral-700">[#text Media Library#]</span>
    </nav>
    <div class="flex justify-between flex-wrap gap-2">
      <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Media Library#]</h1>
      <div>
        [#feature-cond features="delete-.files" tpl=btn_delete_all#]
      </div>
    </div>
    <div class="pt-4">
      [#media-toolbar#]
    </div>
  </section>

  <section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">

    <div class="flex flex-wrap flex-col-reverse sm:flex-row sm:flex-nowrap  gap-4 md:gap-6 lg:gap-8 ">
      <div class="grow bg-neutral-100">
        [#media-grid#]
      </div>
      <div class="flex-none w-[300px] p-2 mx-auto sm:p-4 bg-neutral-200 shadow sm:sticky sm:top-4 sm:self-start sm:max-h-[calc(100vh-2rem)] sm:overflow-y-auto">
        [#media-side-panel#]
      </div>
    </div>

  </section>
</div>
