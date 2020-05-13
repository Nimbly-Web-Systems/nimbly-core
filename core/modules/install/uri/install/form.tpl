<form name="install" action="" method="post" accept-charset="utf-8" class="nb-form">
    [form-error]
    [form-key install]

    <label>[text Enter your email]
        <input type="email" name="email" value="[sticky email]" required />
    </label>
    <label>[text Create a password]
        <input type="password" id="password" name="password" required >
    </label>
    
    <label>[text Enter site name]
        <input type="text" name="sitename" value="[sticky sitename]" required />
    </label>
    
    <label>[text Enter Apache alias for base rewrite]
        <input type="text" maxlength="64" name="rewritebase" value="[sticky rewritebase default=[guess-alias]]"  />
    </label>
    <p class="help-text">[text help_rewritebase]</p>
    
    <label>[text Pepper code]
        <input type="text" maxlength="64" name="pepper" value="[sticky pepper default=[salt]]" required  />
        <span class="form-error nb-close">
            [text pepper_required]
        </span>
    </label>
    <p class="help-text">[text help_pepper]</p>
    
    <div class="button-group">
        <input type="submit" name="submit" value="[text Install]" class="nb-button"  />
    </div>
</form>