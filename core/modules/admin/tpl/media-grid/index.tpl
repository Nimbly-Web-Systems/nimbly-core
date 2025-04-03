<div id="nb-media-grid"
    class="grid grid-cols-2 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 md:gap-6 lg:gap-8">
    <template x-for="(file, index) in page">
        <div :key="file.uuid" class="overflow-hidden cursor-pointer shadowaspect-square bg-neutral-50 text-neutral-500 relative
                transition-all
                hover:outline-clight/50 hover:rounded hover:outline hover:outline-4"
            :class="file_info && file_info.uuid==file.uuid? 'outline-clight/50 outline-4 outline rounded' : 'outline-none'"
            @click="select_media(index)">

            <template x-if="file.in_use === false">
                <div class="absolute top-1 right-1 w-6 h-6 text-yellow-600 bg-neutral-50/80 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                        class="w-6 h-6 shadow-sm">
                        <title>[#text File not in use#]</title>
                        <path fill-rule="evenodd"
                            d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
            </template>

            <!-- image -->
            <template x-if="file_type(index) === 'img'">
                <figure class="flex items-center justify-center h-full">
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                        :src="`[#base-url#]/img/${file.uuid}/300x300f`" :width="file.width" :height="file.height"
                        loading="lazy" class="object-scale-down max-h-full">
                </figure>
            </template>

            <!-- svg vector image -->
            <template x-if="file_type(index) === 'svg'">
                <figure class="flex items-center justify-center h-full">
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                        :src="`[#base-url#]/img/${file.uuid}`" class="object-scale-down max-h-full">
                </figure>
            </template>

            <!-- video -->
            <template x-if="file_type(index) === 'vid'">
                <div class="relative w-full h-full">
                    <video loading="lazy" width="300" height="300" class="flex items-center justify-center h-full">
                        <source :src="`[#base-url#]/video/${file.uuid}`" :type="`video/${vid_type(index)}`">
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

            <!-- audio -->
            <template x-if="file_type(index) === 'audio'">
                <div class="flex flex-col items-center justify-center h-full p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-8 h-8 stroke-neutral-500/40 
                        ">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z" />
                    </svg>
                    <div class="text-xs text-center text-neutral-500 mt-2" x-text="file.title || file.name">

                    </div>
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
                    <div class="text-sm text-center text-neutral-600 -mt-8 bg-white uppercase font-bold"
                        x-text="doc_type(index)">
                    </div>
                    <div class="text-xs text-center text-neutral-400 mt-4 " x-text="file.title || file.name">

                    </div>
                </div>
            </template>
        </div>
    </template>
</div>