<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text My account#]</span>
    </nav>
    <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text My account#]</h1>
        <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#text Edit your account information.#]
        </h3>
    </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <form autocomplete="false" x-data="form_account('[#userfield uuid#]')" @submit.prevent="submit"
        class="bg-neutral-50 rounded-2xl p-10 shadow-md">
        <div class="max-w-lg">
            [#form-key my_account#]
            

            <div class="form-control my-6">
                <label for="name" class="label">
                    <span class="label-text">[#text Name#]</span>
                </label>
                <input type="text" value="[#userfield name#]" name="name" placeholder="" 
                    x-init="form_data.name='[#userfield name#]'"
                    x-model="form_data.name"
                    required
                    class="input input-bordered w-full bg-neutral-50"
                />
            </div>

            <div class="mt-8"></div>
            <input type="submit" value="Save" method="post" class="[#btn-class-primary#]" />
        </div>
        </div>
    </form>

    <form name="change-password" action="[#url#]" method="post" accept-charset="utf-8" autocomplete="off"
        class="bg-neutral-50 rounded-2xl p-10 shadow-md mt-6">
        <div class="max-w-lg">
            [#form-key change-password#]
            <h2 class="text-lg font-semibold text-neutral-800">[#text Change password#]</h2>

            <div class="form-control my-6">
                <label for="current_password" class="label">
                    <span class="label-text">[#text Current password#]</span>
                </label>
                <input type="password" id="current_password" name="current_password" maxlength="64" required
                    autocomplete="current-password" class="input input-bordered w-full bg-neutral-50" />
            </div>

            <div class="form-control my-6">
                <label for="new_password" class="label">
                    <span class="label-text">[#text New password#]</span>
                </label>
                <input type="password" id="new_password" name="new_password" minlength="8" maxlength="64" required
                    autocomplete="new-password" class="input input-bordered w-full bg-neutral-50" />
                [#password-hint#]
            </div>

            <input type="submit" value="[#text Change password#]" class="[#btn-class-primary#]" />

            [#form-errors#]
        </div>
    </form>
</section>
