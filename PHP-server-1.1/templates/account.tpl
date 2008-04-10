<fieldset>
<legend>My Profile</legend>

<p class="justified">
This is your account profile.  Values you enter here can be sent to
sites that support OpenID Simple Registration.  When you authenticate
to such a site, you'll be asked for permission to transmit this
information.  All fields are optional!
</p>

<form method="post" action="{$SERVER_URL}">
<input type="hidden" name="action" value="account">
<table>
  <tr>
    <td align="right">Nickname:</td>
    <td><input type="text" name="profile[nickname]" value="{ $profile.nickname }"></td>
  </tr>
  <tr>
    <td align="right">Full Name:</td>
    <td><input type="text" name="profile[fullname]" value="{ $profile.fullname }"></td>
  </tr>
  <tr>
    <td align="right">E-mail address:</td>
    <td><input type="text" name="profile[email]" value="{ $profile.email }"></td>
  </tr>
  <tr>
    <td align="right">Birth date:</td>
    <td>
      {html_select_date time=$profile.dob start_year=1900 end_year=+0 reverse_years=true field_array="profile[dob]" year_empty="----" day_empty="--" month_empty="--"}
    </td>
  </tr>
  <tr>
    <td align="right">Postal code:</td>
    <td><input type="text" name="profile[postcode]" value="{ $profile.postcode }"></td>
  </tr>
  <tr>
    <td align="right">Gender:</td>
    <td>
      <select name="profile[gender]">
        <option value=""{if $profile.gender == ''} SELECTED{/if}>--</option>
        <option value="M"{if $profile.gender == 'M'} SELECTED{/if}>Male</option>
        <option value="F"{if $profile.gender == 'F'} SELECTED{/if}>Female</option>
      </select>
    </td>
  </tr>
  <tr>
    <td align="right">Country:</td>
    <td>
      <select name="profile[country]">
      <option value=""{if $profile.gender == ''} SELECTED{/if}>--</option>
      {foreach from=$countries item="country"}
        <option value="{$country[0]}"{if $profile.country == $country[0]} SELECTED{/if}>
          {$country[1]}</option>
      {/foreach}
      </select>
    </td>
  </tr>
  <tr>
    <td align="right">Time zone:</td>
    <td>
      <select name="profile[timezone]">
      <option value=""{if $profile.timezone == ''} SELECTED{/if}>--</option>
      {foreach from=$timezones item="tz"}
        <option value="{$tz}"{if $profile.timezone == $tz} SELECTED{/if}>
          {$tz}</option>
      {/foreach}
      </select>
    </td>
  </tr>
  <tr>
    <td align="right">Preferred language:</td>
    <td>
      <select name="profile[language]">
      <option value=""{if $profile.language == ''} SELECTED{/if}>--</option>
      {foreach from=$languages item="lang"}
        <option value="{$lang[0]}"{if $profile.language == $lang[0]} SELECTED{/if}>
          {$lang[1]}</option>
      {/foreach}
      </select>
    </td>
  </tr>
</table>
<input type="submit" value="Save Changes" name="save_profile">
</form>
</fieldset>
