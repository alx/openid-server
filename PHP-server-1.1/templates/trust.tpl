<div class="form">
<p>
Do you wish to confirm your identity URL (<code>{$identity}</code>) with <code>{$trust_root}</code>?
</p>

<form method="post" action="{$SERVER_URL}">

{if $profile}
  <div class="sreg">

  The server has also requested the transfer of profile information.
  The required and optional fields are listed below.  Uncheck the
  "Send?" checkbox for fields that you don't want to send.

  <table border="1" width="100%">
    <tr>
      <th>Send?</th>
      <th>Name</th>
      <th>Value</th>
      <th>Status</th>
    </tr>
    {foreach from=$profile item="item"}
    <tr>
      <td width="1%"><input type="checkbox" name="sreg[{$item.real_name}]" CHECKED></td>
      <td>{$item.name}</td>
      <td>{if $item.value}{$item.value}{else}&nbsp;{/if}</td>
      <td>
        {if $item.required}<span class="required">Required</span>{/if}
        {if $item.optional}<span class="optional">Optional</span>{/if}
      </td>
    </tr>
    {/foreach}
  </table>

  {if $policy_url}
    The server's profile data usage policy can be found at:<br/>
    <code><a href="{$policy_url}">{$policy_url}</a></code>
  {else}
    The server supplied no data policy URL.
  {/if}
  </div>
{/if}

<p>
  <input type="hidden" name="action" value="trust">
  <input type="submit" name="trust_once" value="Allow Once" />
  <input type="submit" name="trust_forever" value="Allow Forever" />
  <input type="submit" value="Deny" />
</p>
</form>
</div>

