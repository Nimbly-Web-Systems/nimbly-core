<div class="flex h-screen bg-neutral-100">
    <div class="m-auto">
        <section class="container mx-auto lg:w-[420px]">
            <div class="shadow-lg p-4 md:p-6 rounded-lg bg-neutral-50 font-primary m-4">
                <h1 class="font-bold my-4">[#text Password#]</h1>
                <form name="password_wall" action="[#url#]" method="post" accept-charset="utf-8" class="mt-8">
                    [#form-key password_wall#]
                    <input type="hidden" name="name" value="[#get password_wall_name#]" />
                    <input type="hidden" name="return" value="[#get password_wall_return#]" />
                    <div class="form-control mb-6">
                        <input type="password" class="input input-bordered w-full bg-neutral-50" name="password"
                            placeholder="[#text Password#]" aria-label="[#text Password#]" autocomplete="current-password" required autofocus />
                    </div>
                    <input type="submit" value="[#text Open#]" class="[#btn-class-primary#]" />
                    [#form-errors#]
                </form>
            </div>
        </section>
    </div>
</div>
