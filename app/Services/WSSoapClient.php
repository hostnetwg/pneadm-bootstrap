<?php

namespace App\Services;

use SoapClient;
use SoapVar;
use SoapHeader;

class WSSoapClient extends SoapClient
{
    private string $username;
    private string $password;

    public function __construct($wsdl, $options = [], $username = '', $password = '')
    {
        parent::__construct($wsdl, $options);
        $this->username = $username;
        $this->password = $password;
    }

    private function addWsSecurityHeader(): void
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = base64_encode(random_bytes(16));
        $passwordDigest = base64_encode(sha1($nonce . $timestamp . $this->password, true));

        $wsseHeader = '
            <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                           xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <wsse:UsernameToken wsu:Id="UsernameToken-1">
                    <wsse:Username>' . htmlspecialchars($this->username, ENT_XML1) . '</wsse:Username>
                    <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">' . $passwordDigest . '</wsse:Password>
                    <wsse:Nonce>' . $nonce . '</wsse:Nonce>
                    <wsu:Created>' . $timestamp . '</wsu:Created>
                </wsse:UsernameToken>
            </wsse:Security>';

        $soapVarHeader = new SoapVar($wsseHeader, XSD_ANYXML);
        $soapHeader = new SoapHeader(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'Security',
            $soapVarHeader,
            true
        );
        $this->__setSoapHeaders([$soapHeader]);
    }

    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $this->addWsSecurityHeader();
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }
}
