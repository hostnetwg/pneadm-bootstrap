<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;

class GusBirService
{
    private const SERVICE_NAMESPACE = 'http://CIS/BIR/PUBL/2014/07';

    private const DATA_NAMESPACE = 'http://CIS/BIR/PUBL/2014/07/DataContract';

    public function lookupByNip(string $nip): ?array
    {
        $nip = $this->normalizeNip($nip);
        if ($nip === null) {
            throw new RuntimeException('Nieprawidłowy NIP.');
        }

        $sid = null;

        try {
            $sid = $this->login();
            $resultXml = $this->searchByNip($sid, $nip);

            return $this->mapSearchResult($resultXml, $nip);
        } finally {
            if ($sid) {
                $this->logout($sid);
            }
        }
    }

    public function normalizeNip(string $nip): ?string
    {
        $digits = preg_replace('/\D+/', '', $nip) ?? '';

        return strlen($digits) === 10 ? $digits : null;
    }

    private function login(): string
    {
        $userKey = trim((string) config('services.gus_bir.user_key'));
        if ($userKey === '') {
            throw new RuntimeException('Brak konfiguracji klucza GUS BIR.');
        }

        $xml = $this->soapEnvelope('Zaloguj', sprintf(
            '<Zaloguj xmlns="%s"><pKluczUzytkownika>%s</pKluczUzytkownika></Zaloguj>',
            self::SERVICE_NAMESPACE,
            $this->xmlEscape($userKey)
        ));

        $response = $this->soapRequest('Zaloguj', $xml);
        $sid = $this->extractSoapResult($response, 'ZalogujResult');

        if ($sid === '') {
            throw new RuntimeException('GUS BIR nie zwrócił identyfikatora sesji.');
        }

        return $sid;
    }

    private function searchByNip(string $sid, string $nip): string
    {
        $xml = $this->soapEnvelope('DaneSzukajPodmioty', sprintf(
            '<DaneSzukajPodmioty xmlns="%s"><pParametryWyszukiwania xmlns:d4p1="%s" xmlns:i="http://www.w3.org/2001/XMLSchema-instance"><d4p1:Nip>%s</d4p1:Nip></pParametryWyszukiwania></DaneSzukajPodmioty>',
            self::SERVICE_NAMESPACE,
            self::DATA_NAMESPACE,
            $this->xmlEscape($nip)
        ));

        $response = $this->soapRequest('DaneSzukajPodmioty', $xml, $sid);

        return $this->extractSoapResult($response, 'DaneSzukajPodmiotyResult');
    }

    private function logout(string $sid): void
    {
        $xml = $this->soapEnvelope('Wyloguj', sprintf(
            '<Wyloguj xmlns="%s"><pIdentyfikatorSesji>%s</pIdentyfikatorSesji></Wyloguj>',
            self::SERVICE_NAMESPACE,
            $this->xmlEscape($sid)
        ));

        try {
            $this->soapRequest('Wyloguj', $xml, $sid);
        } catch (\Throwable $e) {
            Log::warning('GUS BIR logout failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function soapRequest(string $method, string $xml, ?string $sid = null): string
    {
        $endpoint = trim((string) config('services.gus_bir.endpoint'));
        if ($endpoint === '') {
            throw new RuntimeException('Brak konfiguracji endpointu GUS BIR.');
        }

        $action = self::SERVICE_NAMESPACE.'/IUslugaBIRzewnPubl/'.$method;
        $contentType = 'application/soap+xml; charset=UTF-8; action="'.$action.'"';

        $headers = [
            'Accept' => 'application/soap+xml, text/xml',
            'Content-Type' => $contentType,
        ];

        if ($sid) {
            $headers['sid'] = $sid;
        }

        $response = Http::timeout((int) config('services.gus_bir.timeout', 10))
            ->withHeaders($headers)
            ->withBody($xml, $contentType)
            ->post($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException('GUS BIR zwrócił błąd HTTP '.$response->status().'.');
        }

        return $this->extractSoapXml($response->body());
    }

    private function mapSearchResult(string $resultXml, string $nip): ?array
    {
        $resultXml = trim($resultXml);
        if ($resultXml === '' || str_contains($resultXml, 'ErrorCode')) {
            return null;
        }

        $xml = $this->loadXml($resultXml);
        $records = $xml->xpath('//*[local-name()="dane"]') ?: [];
        if ($records === []) {
            return null;
        }

        $record = $records[0];
        $street = $this->field($record, 'Ulica');
        $building = $this->field($record, 'NrNieruchomosci');
        $unit = $this->field($record, 'NrLokalu');
        $locality = $this->field($record, 'Miejscowosc');

        return [
            'nip' => $nip,
            'regon' => $this->field($record, 'Regon'),
            'name' => $this->field($record, 'Nazwa'),
            'postcode' => $this->field($record, 'KodPocztowy'),
            'city' => $this->field($record, 'MiejscowoscPoczty') ?: $locality,
            // Bez ulicy (typowe dla małych miejscowości): w polu adresu „miejscowość + nr”,
            // a w polu miasta zostaje miejscowość poczty / miejscowość.
            'address' => $this->formatAddress($street, $building, $unit, $locality),
        ];
    }

    private function extractSoapResult(string $xml, string $resultNode): string
    {
        $soap = $this->loadXml($xml);
        $nodes = $soap->xpath('//*[local-name()="'.$resultNode.'"]') ?: [];

        return isset($nodes[0]) ? trim((string) $nodes[0]) : '';
    }

    private function extractSoapXml(string $body): string
    {
        $body = trim($body);

        foreach (['s', 'soap'] as $prefix) {
            $startTag = '<'.$prefix.':Envelope';
            $endTag = '</'.$prefix.':Envelope>';
            $start = strpos($body, $startTag);
            $end = strpos($body, $endTag);

            if ($start !== false && $end !== false) {
                return substr($body, $start, $end - $start + strlen($endTag));
            }
        }

        return $body;
    }

    private function field(SimpleXMLElement $record, string $name): string
    {
        $nodes = $record->xpath('./*[local-name()="'.$name.'"]') ?: [];

        return isset($nodes[0]) ? trim((string) $nodes[0]) : '';
    }

    private function formatAddress(string $street, string $building, string $unit, string $locality = ''): string
    {
        // Gdy GUS nie zwraca ulicy, linia adresu to nazwa miejscowości + numer (np. „Węgój 7”).
        $linePrefix = $street !== '' ? $street : $locality;
        $address = trim(implode(' ', array_filter(
            [$linePrefix, $building],
            static fn (string $part): bool => $part !== ''
        )));

        if ($unit !== '') {
            $address .= '/'.$unit;
        }

        return trim($address);
    }

    private function soapEnvelope(string $method, string $body): string
    {
        $endpoint = trim((string) config('services.gus_bir.endpoint'));
        $action = self::SERVICE_NAMESPACE.'/IUslugaBIRzewnPubl/'.$method;

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">'
            .'<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">'
            .'<wsa:Action>'.$this->xmlEscape($action).'</wsa:Action>'
            .'<wsa:To>'.$this->xmlEscape($endpoint).'</wsa:To>'
            .'</soap:Header>'
            .'<soap:Body>'.$body.'</soap:Body>'
            .'</soap:Envelope>';
    }

    private function loadXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed instanceof SimpleXMLElement) {
            throw new RuntimeException('Nie udało się odczytać odpowiedzi XML z GUS BIR.');
        }

        return $parsed;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
