<div class="nb-field my-10" x-data="location_picker({
        latitude_field: '[#_f.key#]',
        longitude_field: '[#_f.longitude_field#]'
    })">
    <input type="hidden" name="[#_f.key#]" [#_f.x_init#] x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]>

    <div class="rounded-md border border-neutral-300 bg-neutral-50 p-3 sm:p-4">
        <h3 class="font-bold text-neutral-800">
            [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
        </h3>
        <p class="mt-1 text-sm text-neutral-600">
            [#text Click the map to place the location. Drag the pin to adjust it.#]
        </p>

        <div class="relative mt-4 overflow-hidden rounded-md bg-neutral-100">
            <div x-ref="map" class="h-80 w-full sm:h-96" aria-label="[#text Choose the location on the world map#]"></div>
            <template x-ref="marker_icon">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="white" stroke-width="1"
                    class="h-8 w-8 text-base-content drop-shadow-md" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M11.54 22.351l.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 2.144-1.58c1.61-1.349 3.535-3.724 3.535-6.588a6.139 6.139 0 1 0-12.278 0c0 2.864 1.925 5.239 3.535 6.588a16.975 16.975 0 0 0 2.144 1.58ZM12 16.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z"
                        clip-rule="evenodd" />
                </svg>
            </template>
            <div x-show="loading"
                class="pointer-events-none absolute inset-0 z-[500] flex items-center justify-center bg-white/80 text-sm font-semibold text-neutral-700">
                [#text Loading map&hellip;#]
            </div>
            <div x-show="load_error" x-cloak
                class="absolute inset-0 z-[500] flex items-center justify-center bg-white p-6 text-center text-sm text-red-700">
                [#text The map could not be loaded. Check your connection and reload the page.#]
            </div>
        </div>
    </div>
</div>
<script>[#include file=[#base-path#]core/modules/forms/tpl/field-location-picker/index.js#]</script>
