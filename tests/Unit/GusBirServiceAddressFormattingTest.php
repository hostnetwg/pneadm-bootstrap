<?php

namespace Tests\Unit;

use App\Services\GusBirService;
use ReflectionMethod;
use Tests\TestCase;

class GusBirServiceAddressFormattingTest extends TestCase
{
    public function test_address_with_street_keeps_street_and_building(): void
    {
        $result = $this->mapSearchResult(<<<'XML'
<root>
  <dane>
    <Regon>123456789</Regon>
    <Nazwa>Firma z ulicą</Nazwa>
    <Ulica>ul. Mickiewicza</Ulica>
    <NrNieruchomosci>5</NrNieruchomosci>
    <NrLokalu>12</NrLokalu>
    <Miejscowosc>Warszawa</Miejscowosc>
    <MiejscowoscPoczty>Warszawa</MiejscowoscPoczty>
    <KodPocztowy>00-001</KodPocztowy>
  </dane>
</root>
XML);

        $this->assertSame('ul. Mickiewicza 5/12', $result['address']);
        $this->assertSame('Warszawa', $result['city']);
    }

    public function test_address_without_street_uses_locality_and_building(): void
    {
        $result = $this->mapSearchResult(<<<'XML'
<root>
  <dane>
    <Regon>987654321</Regon>
    <Nazwa>Firma bez ulicy</Nazwa>
    <Ulica></Ulica>
    <NrNieruchomosci>7</NrNieruchomosci>
    <NrLokalu></NrLokalu>
    <Miejscowosc>Węgój</Miejscowosc>
    <MiejscowoscPoczty>Biskupiec</MiejscowoscPoczty>
    <KodPocztowy>11-300</KodPocztowy>
  </dane>
</root>
XML);

        $this->assertSame('Węgój 7', $result['address']);
        $this->assertSame('Biskupiec', $result['city']);
    }

    public function test_address_without_street_includes_unit_when_present(): void
    {
        $result = $this->mapSearchResult(<<<'XML'
<root>
  <dane>
    <Regon>111222333</Regon>
    <Nazwa>Firma bez ulicy z lokalem</Nazwa>
    <Ulica></Ulica>
    <NrNieruchomosci>3</NrNieruchomosci>
    <NrLokalu>2</NrLokalu>
    <Miejscowosc>Węgój</Miejscowosc>
    <MiejscowoscPoczty>Węgój</MiejscowoscPoczty>
    <KodPocztowy>11-300</KodPocztowy>
  </dane>
</root>
XML);

        $this->assertSame('Węgój 3/2', $result['address']);
        $this->assertSame('Węgój', $result['city']);
    }

    /**
     * @return array{nip: string, regon: string, name: string, postcode: string, city: string, address: string}
     */
    private function mapSearchResult(string $xml): array
    {
        $method = new ReflectionMethod(GusBirService::class, 'mapSearchResult');
        $method->setAccessible(true);

        /** @var array{nip: string, regon: string, name: string, postcode: string, city: string, address: string} $result */
        $result = $method->invoke(new GusBirService, $xml, '1234567890');

        return $result;
    }
}
