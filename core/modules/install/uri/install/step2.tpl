<div class="relative mb-6 border" data-te-input-wrapper-init>
    <input type="email" class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" id="email"
        name="email" value="[#sticky email#]" placeholder="[#text Email#]" required />
    <label for="email" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            [#text Enter your email#]
    </label>
</div>

<div class="relative mb-6" data-te-input-wrapper-init>
    <input type="password" class="
      border-neutral-400
        peer block min-h-[auto] w-full rounded border-0 
        bg-transparent px-2 py-[0.2rem] leading-[2.15] 
        outline-none transition-all duration-200 ease-linear 
        focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 
        motion-reduce:transition-none 
        [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" id="password"
        name="password" placeholder="[#text Create a password#]" required />
    <label for="password" class="pointer-events-none absolute left-3 top-0 mb-0 max-w-[90%] 
            origin-[0_0] truncate pt-[0.37rem] leading-[2.15] text-neutral-600 
            transition-all duration-200 ease-out peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
        [#text Create a password#]
    </label>
</div>

<div class="relative mb-6 border" data-te-input-wrapper-init>
    <input type="text" class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" id="sitename"
        name="sitename" value="[#sticky sitename#]" placeholder="[#text Enter site name#]" required />
    <label for="sitename" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            [#text Enter site name#]
    </label>
</div>

<div class="relative border" data-te-input-wrapper-init>
    <input type="text" maxlength="64" 
        class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" id="rewritebase"
        name="rewritebase" value="[#sticky rewritebase#]" placeholder="[#text Enter Apache alias for base rewrite#]" />
    <label for="rewritebase" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            [#text Enter Apache alias for base rewrite#]
    </label>
</div>
<p class="text-sm text-neutral-600 p-2 mb-6" id="rewritebasehelp">[#text help_rewritebase#]</p>

<div class="relative border" data-te-input-wrapper-init>
    <input type="text" maxlength="64" required
        class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" id="pepper"
        name="pepper" value="[#sticky pepper default=[#salt#]#]" placeholder="[#text Pepper code#]" />
    <label for="pepper" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            [#text Pepper code#]
    </label>
</div>
<p class="text-sm text-neutral-600 p-2 mb-6" id="pepperhelp">[#text help_pepper#]</p>

<div class="text-right mt-8">
    <input type="submit" name="submit" value="[#text Next#]" class="[#btn-class-primary#]"  /> 
</div>
