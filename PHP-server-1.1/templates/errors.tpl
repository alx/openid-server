{if $errors}
<div class="errors">
  {foreach from=$errors item="error"}
  <span class="error">{$error}</span>
  {/foreach}
</div>
{/if}
