<h3 class="modal-caption">[#text Forgot password?#]</h3>
<a data-close="#modal" class="icon-close">×</a>
<div class="signup-wrapper">
    <form name="forgot-password" action="[#url#]" method="post" accept-charset="utf-8" class="nb-form">
        [#form-key forgot-password#]
        <label>
            [#text Email#]<br>
            <input type="email" maxlength="64" name="email" id="email" placeholder="[#text your email#]" value="[#sticky email#]" required />
        </label>
        <button class="nb-button"
            data-submit="form[name=forgot-password]"
            data-trigger="modal_password_reset"
        >
             [#text Request new password#]
        </button> 
        [#form-error#]
    </form>
    <div id="password-reset-success-message" class="nb-close">
        <h2>[#text Check your email!#]</h2>
        <p>[#text str="Instructions to reset your password have been sent to your email address."#]</p>
    </div>
</div>

<script>
    $(document).on('modal_password_reset', function(e, data) {
        $('#modal form[name=forgot-password]').addClass('nb-close');
        $('#password-reset-success-message').removeClass('nb-close');
    });
</script>