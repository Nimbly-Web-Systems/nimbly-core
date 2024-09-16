<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text My account#]</h1>
        <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#text Edit your account information.#]
        </h3>
    </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <form autocomplete="false" x-data="form_account('[#userfield uuid#]')" @submit.prevent="submit"
        class="bg-neutral-50 rounded-2xl p-10 shadow-md">
        <div class="max-w-lg mx-auto">
            [#form-key my_account#]
            

            <div class="relative my-6" data-te-input-wrapper-init>
                <input type="text" value="[#userfield name#]" name="name" placeholder="" 
                    x-init="form_data.name='[#userfield name#]'"
                    x-model="form_data.name"
                    required
                    class="
                        peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                        leading-[2.15] outline-none transition-all duration-200 ease-linear 
                        focus:placeholder:opacity-100 
                        motion-reduce:transition-none
                        data-[te-input-state-active]:placeholder:opacity-100 
                        [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
                />
                <label for="name" class="pointer-events-none absolute left-3 top-0 mb-0 
                        max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
                        text-neutral-600 transition-all duration-200 ease-out 
                        peer-focus:-translate-y-[1.15rem] 
                        peer-focus:scale-[0.8] peer-focus:text-primary 
                        peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                        peer-data-[te-input-state-active]:scale-[0.8] 
                        motion-reduce:transition-none">
                        [#text Name#]
                </label>
            </div>

            <div class="mt-8"></div>
            <input type="submit" value="Save" method="post" class="[#btn-class-primary#]" />
        </div>
        </div>
    </form>
</section>