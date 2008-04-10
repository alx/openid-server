<?xml version="1.0" encoding="UTF-8"?>
<xrds:XRDS
    xmlns:xrds="xri://$xrds"
    xmlns:openid="http://openid.net/xmlns/1.0"
    xmlns="xri://$xrd*($v*2.0)">
  <XRD>
    <Service>
      <Type>http://openid.net/signon/1.1</Type>
      <Type>http://openid.net/sreg/1.0</Type>
      <URI>{$SERVER_URL}index.php/serve</URI>
      <openid:Delegate>{$openid_url}</openid:Delegate>
    </Service>
  </XRD>
</xrds:XRDS>
