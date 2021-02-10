<div class="login-container">
    <h1>[page-title]</h1>
    <p>Hi there!</p>
    <p style="line-height: 18px; margin-bottom: 18px;">Please type in your new password in the form below to complete the password reset.</p>
    <form name="create-password" id="create-password" action="[url]" method="post" accept-charset="utf-8" class="nb-form" autocomplete="off">
        [form-key create-password]
        <label>Password
            <input type="password" maxlength="64" name="password" id="password" required />
        </label>
        <input type="submit" value="Create new password" class="nb-button"  />
        [form-error]
    </form>
    <img src="[base-url]/img/nimbly-logo.svg" class="nimbly-logo" data-link='https://nimblycms.com/' alt="nimbly">
</div>