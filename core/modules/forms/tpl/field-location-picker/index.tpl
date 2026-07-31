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
                <span class="block h-6 w-6 rounded-full border-2 border-white bg-primary shadow-md"></span>
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
