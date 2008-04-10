
<fieldset>
<legend>New Account</legend>

<p class="justified">
Fill out the form below to create an OpenID.
</p>

<form method="post" action="{$SERVER_URL}">
<input type="hidden" name="action" value="register">
<table>
  <tr>
    <td align="right">Your Username:</td>
    <td><input type="text" name="username" value="{$username}"></td>
  </tr>
  <tr>
    <td align="right">Password:</td>
    <td><input type="password" name="pass1" value=""></td>
  </tr>
  <tr>
    <td align="right">Confirm Password:</td>
    <td><input type="password" name="pass2" value=""></td>
  </tr>
  <tr>
    <td></td><td><img class="captcha" src="{$SERVER_URL}?action=captcha"><br/>
    Please enter the text in the image exactly as shown.
  </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><input type="text" name="captcha_text" value="">
    </td>
  </tr>
</table>
<input type="submit" value="Sign Up" name="save_profile">
</form>
</fieldset>
