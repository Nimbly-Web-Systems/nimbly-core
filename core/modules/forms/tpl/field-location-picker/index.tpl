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
            [#text Click_the_map_to_place_the_location._Drag_the_pin_to_adjust_it.#]
        </p>

        <div class="relative mt-4 overflow-hidden rounded-md bg-neutral-100">
            <div x-ref="map" class="h-80 w-full sm:h-96" aria-label="[#text Choose_the_location_on_the_world_map#]"></div>
            <div x-show="loading"
                class="pointer-events-none absolute inset-0 z-[500] flex items-center justify-center bg-white/80 text-sm font-semibold text-neutral-700">
                [#text Loading_map&hellip;#]
            </div>
            <div x-show="load_error" x-cloak
                class="absolute inset-0 z-[500] flex items-center justify-center bg-white p-6 text-center text-sm text-red-700">
                [#text The_map_could_not_be_loaded._Check_your_connection_and_reload_the_page.#]
            </div>
        </div>
    </div>
</div>
<script>[#include file=[#base-path#]core/modules/forms/tpl/field-location-picker/index.js#]</script>
