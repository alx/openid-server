<fieldset>
<legend>Server Administration</legend>

<h3>Account Search</h3>
<form method="post" action="{$SERVER_URL}">
<input type="hidden" name="action" value="admin">
<input type="text" name="search"> <input type="submit" value="Search Accounts">
<input type="submit" name="showall" value="Show All">
</form>

{if $search || $showall}
{if $search_results}
<form method="post" action="{$SERVER_URL}">
<h3>Search Results:</h3>
<input type="hidden" name="action" value="admin">
<table>
  {foreach from=$search_results item="account"}
  <tr>
    <td><input id="account[{$account}]" type="checkbox" name="account[{$account}]"></td>
    <td><label for="account[{$account}]">{$account}</label></td>
  </tr>
  {/foreach}
</table>
<input type="hidden" name="search" value="{$search}">
<input type="submit" name="remove" value="Remove Selected Accounts">
</form>
{else}
  {if $showall}
    No accounts found.
  {else}
    No results found for '{$search}'.
  {/if}
{/if}
{/if}

<h3>Account Creation</h3>
<form method="post" action="{$SERVER_URL}">
<input type="hidden" name="action" value="admin">
<table>
  <tr>
    <td>Username:</td>
    <td><input type="text" name="username"></td>
  </tr>
  <tr>
    <td>Password:</td>
    <td><input type="password" name="pass1"></td>
  </tr>
  <tr>
    <td>Confirm password:</td>
    <td><input type="password" name="pass2"></td>
  </tr>
</table>
<input type="submit" value="Create Account">
</form>

</fieldset>
