<div data-te-modal-init x-data="modal_settings('[#url-key#]')"
  class="fixed left-0 top-0 z-[1055] hidden h-full right-[30px] overflow-y-auto overflow-x-hidden outline-none"
  id="nb-modal-settings" tabindex="-1" aria-labelledby="modal_settings" aria-hidden="true">
  <div data-te-modal-dialog-ref
    class="pointer-events-none relative w-auto h-[calc(100%-50px)] translate-y-[-50px] opacity-0 transition-all duration-300 ease-in-out min-[576px]:mx-auto min-[576px]:mt-7 min-[576px]:max-w-[500px] min-[786px]:max-w-[710px]">
    <div
      class="min-[576px]:shadow-[0_0.5rem_1rem_rgba(#000, 0.15)]  pointer-events-auto relative flex max-h-[100%] w-full flex-col rounded-md border-none bg-white bg-clip-padding text-current shadow-lg outline-none">
      <div
        class="flex flex-shrink-0 items-center justify-between rounded-t-md border-b border-solid border-neutral-100 border-opacity-100 p-4">
        <!--Modal title-->
        <h5 class="text-xl font-medium leading-normal text-neutral-800" id="modal_settings_label">
          Instellingen
        </h5>
        <!--Close button-->
        <button type="button"
          class="box-content rounded-none border-none hover:no-underline hover:opacity-75 focus:opacity-100 focus:shadow-none focus:outline-none"
          data-te-modal-dismiss aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="h-6 w-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!--Modal body-->
      <div class="relative px-4 py-2 pb-0 overflow-y-auto" data-te-modal-body-ref>
        <form autocomplete="false" class="mt-2">

          <div class="relative my-6" data-te-input-wrapper-init>
            <input type="text" value="[#get page_settings.page_title#]" name="page_title" placeholder=""
              x-init='settings.page_title="[#get page_settings.page_title#]"' x-model="settings.page_title" class="
                  peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                  leading-[2.15] outline-none transition-all duration-200 ease-linear 
                  focus:placeholder:opacity-100 
                  motion-reduce:transition-none
                  data-[te-input-state-active]:placeholder:opacity-100 
                  [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" />
            <label for="rewritebase" class="pointer-events-none absolute left-3 top-0 mb-0 
                  max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
                  text-neutral-600 transition-all duration-200 ease-out 
                  peer-focus:-translate-y-[1.15rem] 
                  peer-focus:scale-[0.8] peer-focus:text-primary 
                  peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                  peer-data-[te-input-state-active]:scale-[0.8] 
                  motion-reduce:transition-none">
              [#text Page title#]
            </label>
          </div>

          [#set page-settings-fields=#]
          [#page-settings-fields#]
        </form>
      </div>


      <!-- Modal footer -->
      <div class="mt-auto flex flex-shrink-0 gap-4 flex-wrap items-center justify-end rounded-b-md p-4 
border-t border-neutral-200 min-[0px]:rounded-none">
        <button type="button" class="[#btn-class-secondary#]" data-te-modal-dismiss>
          [#text Cancel#]
        </button>
        <button type="button" class="[#btn-class-primary#]" @click="save">[#text Save#]</button>
      </div>

    </div>
  </div>
</div>
</div>