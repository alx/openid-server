<div class="login">
{if $identity_url}
<p>
Before you can authenticate using your identity URL
(<code>{$identity_url}</code>), you must first log in.
</p>
{/if}

<table class="login"><tr><td>
<form name="loginform" method="post" action="{$SERVER_URL}">
{if $next_action}
<input type="hidden" name="next_action" value="{$next_action}">
{/if}
<input type="hidden" name="action" value="login">
<table>
  <tr>
    <td>Username:</td>
    <td><input class="disabled_bold" type="text" name="username" value="{$required_user}"{if $required_user} disabled><input type="hidden" name="username" value="{$required_user}"{/if}></td>
  </tr>
  <tr>
    <td>Password:</td>
    <td><input type="password" name="passwd"></td>
  </tr>
  <tr>
    <td align="center" colspan="2"><input type="submit" value="Log in"></td>
  <tr>
</table>
</form>
</td></tr></table>
</div>
