<div x-data="modal_settings('[#url-key#]')"
  class="fixed inset-0 z-[1054] hidden overflow-y-auto overflow-x-hidden bg-black/30 p-2 text-neutral-700 sm:p-4"
  id="nb-modal-settings" tabindex="-1" aria-labelledby="modal_settings_label" aria-hidden="true"
  @click.self="nb.modal.close('nb-modal-settings')" @keydown.escape.window="nb.modal.close('nb-modal-settings')">
  <div class="relative mx-auto flex min-h-full w-full max-w-[710px] items-center">
    <div class="relative flex max-h-[calc(100vh-2rem)] w-full flex-col rounded-md bg-white text-current shadow-lg outline-none">
      <div class="flex flex-shrink-0 items-center justify-between rounded-t-md border-b border-solid border-neutral-100 p-4">
        <h5 class="text-xl font-medium leading-normal text-neutral-800" id="modal_settings_label">
          [#text Page settings#]
        </h5>
        <button type="button"
          class="box-content rounded-none border-none hover:no-underline hover:opacity-75 focus:opacity-100 focus:shadow-none focus:outline-none"
          @click="nb.modal.close('nb-modal-settings')" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="h-6 w-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="relative overflow-y-auto px-4 py-2 pb-0">
        <form autocomplete="false" class="mt-2">

          <div class="relative my-6">
            <input type="text" value="[#get page_settings.page_title#]" name="page_title" placeholder=" "
              x-init='settings.page_title="[#get page_settings.page_title#]"' x-model="settings.page_title" class="
                  peer block min-h-[auto] w-full rounded border border-neutral-300 bg-transparent px-2 py-[0.45rem]
                  leading-[1.6] outline-none transition-all duration-200 ease-linear
                  focus:border-primary focus:ring-0" />
            <label for="page_title" class="pointer-events-none absolute left-3 top-0 mb-0 max-w-[90%] origin-[0_0] -translate-y-[1.15rem] scale-[0.8] truncate bg-white px-1 pt-[0.37rem] leading-[2.15] text-neutral-600 transition-all duration-200 ease-out peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-[1.15rem] peer-focus:scale-[0.8] peer-focus:text-primary motion-reduce:transition-none">
              [#text Page title#]
            </label>
          </div>

          [#set page-settings-fields=#]
          [#page-settings-fields#]
        </form>
      </div>

      <div class="mt-auto flex flex-shrink-0 flex-wrap items-center justify-end gap-4 rounded-b-md border-t border-neutral-200 p-4">
        <button type="button" class="[#btn-class-secondary#]" @click="nb.modal.close('nb-modal-settings')">
          [#text Cancel#]
        </button>
        <button type="button" class="[#btn-class-primary#]" @click="save">[#text Save#]</button>
      </div>

    </div>
  </div>
</div>
