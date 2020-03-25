<div class="login-container">
    <h1>[text Log in]</h1>
    <form name="login" action="[url]" method="post" accept-charset="utf-8" class="nb-form">
        [form-key login]
        <label>[text Email]
            <input type="text" maxlength="64" name="email" id="email"
                placeholder="[text your email]" value="[sticky email]" required />
        </label>
        <label>[text Password]
            <input type="password" maxlength="64" name="password" id="password"
                placeholder="[text your password]" required />
        </label>
        <input type="submit" value="[text Log in]" class="nb-button"  />
        [form-error]
    </form>
    <p><a href="" data-modal='{"url": "forgot-password"}'>[text Forgot password?]</a></p>
    <img src="[base-url]/img/nimbly-logo.svg" class="nimbly-logo" data-link='https://nimblycms.com/' alt="nimbly">
</div>