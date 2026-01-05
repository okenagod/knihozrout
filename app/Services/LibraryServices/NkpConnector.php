<?php

namespace App\Services\LibraryServices;

use App\DTO\BookData;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class NkpConnector implements LibraryConnectorInterface
{
	public function fetch(string $isbn): ?BookData
	{
		$baseUrl = 'https://aleph.nkp.cz/api/sru/nkc';
		$isbnClean = preg_replace('/[^0-9X]/i', '', $isbn);
		if (empty($isbnClean))
		{
			return null;
		}

		$response = Http::get($baseUrl, [
			'operation' => 'searchRetrieve',
			'query' => 'isb=' . $isbnClean,
			'version' => '1.1',
			'maximumRecords' => 1,
			'recordSchema' => 'marccxml',
		]);

		if (!$response->successful())
		{
			return null;
		}

		//		dd($response->body());

		return $this->parseMarcXml($response->body());
	}

	protected function parseMarcXml(string $xmlContent): ?BookData
	{
		try
		{
			$xml = new SimpleXMLElement($xmlContent);
			$xml->registerXPathNamespace('zs', 'http://www.loc.gov/zing/srw/');
			$xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');

			$records = $xml->xpath('//marc:record');

			if (empty($records))
			{
				return null;
			}

			$record = $records[0];
			$data = [];

			// Autor (100 $a)
			$author = $record->xpath('./marc:datafield[@tag="100"]/marc:subfield[@code="a"]');
			$data['author'] = $author ? (string)$author[0] : null;
			if ($data['author'])
			{
				$data['author'] = rtrim($data['author'], '., ');
			}

			// Název (245 $a $b)
			$titleA = $record->xpath('./marc:datafield[@tag="245"]/marc:subfield[@code="a"]');
			$titleB = $record->xpath('./marc:datafield[@tag="245"]/marc:subfield[@code="b"]');

			$title = $titleA ? rtrim((string)$titleA[0], ' /:.,') : '';
			if ($titleB)
			{
				$title .= ' ' . trim((string)$titleB[0], ' /:.,');
			}
			$data['title'] = trim($title);

			// Vydavatel (260 $b nebo 264 $b)
			$publisher = $record->xpath('./marc:datafield[@tag="260" or @tag="264"]/marc:subfield[@code="b"]');
			$data['publisher'] = $publisher ? (string)$publisher[0] : null;
			if ($data['publisher'])
			{
				$data['publisher'] = trim($data['publisher'], ' ,:;.');
			}

			// Rok (260 $c nebo 264 $c)
			$year = $record->xpath('./marc:datafield[@tag="260" or @tag="264"]/marc:subfield[@code="c"]');
			$data['year'] = $year ? (string)$year[0] : null;
			if ($data['year'])
			{
				if (preg_match('/\d{4}/', $data['year'], $matches))
				{
					$data['year'] = $matches[0];
				}
			}

			return new BookData($data['title'], $data['author'], $data['publisher'], (int)$data['year']);
		}
		catch (\Exception $e)
		{
			return null;
		}
	}
}
