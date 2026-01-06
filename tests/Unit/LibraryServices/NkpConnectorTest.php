<?php

namespace Tests\Unit\LibraryServices;

use App\Services\LibraryServices\NkpConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NkpConnectorTest extends TestCase
{
    public function test_it_can_fetch_book_data_from_nkp()
    {
        $isbn = '807226575X';
        $xmlResponse = <<<'XML'
<zs:searchRetrieveResponse xmlns:zs="http://www.loc.gov/zing/srw/">
    <zs:numberOfRecords>1</zs:numberOfRecords>
    <zs:records>
        <zs:record>
            <zs:recordSchema>marccxml</zs:recordSchema>
            <zs:recordData>
                <marc:record xmlns:marc="http://www.loc.gov/MARC21/slim">
                    <marc:datafield tag="100" ind1="1" ind2=" ">
                        <marc:subfield code="a">Čapek, Karel,</marc:subfield>
                    </marc:datafield>
                    <marc:datafield tag="245" ind1="1" ind2="0">
                        <marc:subfield code="a">Válka s mloky /</marc:subfield>
                        <marc:subfield code="b">Karel Čapek</marc:subfield>
                    </marc:datafield>
                    <marc:datafield tag="260" ind1=" " ind2=" ">
                        <marc:subfield code="b">Československý spisovatel,</marc:subfield>
                        <marc:subfield code="c">1954</marc:subfield>
                    </marc:datafield>
                </marc:record>
            </zs:recordData>
        </zs:record>
    </zs:records>
</zs:searchRetrieveResponse>
XML;

        Http::fake([
            'aleph.nkp.cz/*' => Http::response($xmlResponse, 200),
        ]);

        $connector = new NkpConnector;
        $result = $connector->fetch($isbn);

        $this->assertNotNull($result);
        $this->assertEquals('Válka s mloky Karel Čapek', $result->title);
        $this->assertEquals('Čapek, Karel', $result->author);
        $this->assertEquals('Československý spisovatel', $result->publisher);
        $this->assertEquals(1954, $result->year);
    }

    public function test_it_returns_null_when_nkp_returns_no_records()
    {
        $isbn = '0000000000';
        $xmlResponse = <<<'XML'
<zs:searchRetrieveResponse xmlns:zs="http://www.loc.gov/zing/srw/">
    <zs:numberOfRecords>0</zs:numberOfRecords>
    <zs:records></zs:records>
</zs:searchRetrieveResponse>
XML;

        Http::fake([
            'aleph.nkp.cz/*' => Http::response($xmlResponse, 200),
        ]);

        $connector = new NkpConnector;
        $result = $connector->fetch($isbn);

        $this->assertNull($result);
    }
}
