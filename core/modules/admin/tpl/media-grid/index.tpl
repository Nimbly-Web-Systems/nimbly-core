<div
    class="grid grid-cols-2 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 md:gap-6 lg:gap-8">
    <template x-for="(file, index) in page">
        <div :key="file.uuid" class="overflow-hidden cursor-pointer shadow bg-neutral-50 aspect-square text-neutral-500"
            @click="select_media(index)">

            <!-- image -->
            <template x-if="file_type(index) === 'img'">
                <figure class="flex items-center justify-center h-full">
                    <img :src="`[base-url]/img/${file.uuid}/480x480f`" :width="file.width" :height="file.height"
                        loading="lazy" class="object-scale-down max-h-full">
                </figure>
            </template>

            <!-- video -->
            <template x-if="file_type(index) === 'vid'">
                <div class="relative w-full h-full">
                    <video width="480" height="480" class="flex items-center justify-center h-full">
                        <source :src="`[base-url]/video/${file.uuid}`" :type="`video/${vid_type(index)}`">
                    </video>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-[64px] h-[64px] absolute 
                      top-[calc(50%-32px)] left-[calc(50%-32px)]
                       stroke-neutral-500/40
                       fill-neutral-500/10">
                        <path stroke-linecap="round"
                            d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                    </svg>

                </div>
            </template>

            <!-- document -->
            <template x-if="file_type(index) === 'doc'">
                <div class=" flex flex-col items-center justify-center h-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.0"
                        stroke="currentColor" class="w-16 h-16 ">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <div class="text-sm text-neutral-600 -mt-8 bg-white uppercase font-bold" x-text="doc_type(index)">
                    </div>
                    <div class="text-xs text-neutral-400 mt-4 " x-text="file.title || file.name">

                    </div>
                </div>
            </template>
        </div>
    </template>
</div>