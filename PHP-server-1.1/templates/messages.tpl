{if $messages}
<div class="messages">
  {foreach from=$messages item="message"}
  <span class="message">{$message}</span>
  {/foreach}
</div>
{/if}
