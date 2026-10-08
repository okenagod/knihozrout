<?php

namespace Tests\Unit\PriceServices;

use App\Models\Book;
use App\Services\PriceServices\TrhKnihSource;
use Tests\TestCase;

class TrhKnihSourceTest extends TestCase
{
    public function test_it_parses_search_results()
    {
        $issues = (new TrhKnihSource)->parseSearch(file_get_contents(base_path('tests/fixtures/trhknih-search.html')));

        $this->assertCount(3, $issues);
        $this->assertSame([
            'path' => '/kniha/betv6g67',
            'title' => 'Z očí do očí',
            'author' => 'Antonín Přidal',
            'year' => '1994',
            'publisher' => 'Ivo Železný',
            'hasOffers' => true,
        ], $issues[0]);
        $this->assertSame('Georgij Ivanovič Zubkov', $issues[2]['author']);
        $this->assertFalse($issues[2]['hasOffers']);
    }

    public function test_it_selects_issues_of_the_same_book()
    {
        $source = new TrhKnihSource;
        $issues = $source->parseSearch(file_get_contents(base_path('tests/fixtures/trhknih-search.html')));
        $book = new Book(['title' => 'Z očí do očí : rozhovory ze stejnojmenného pořadu ČT Brno', 'author' => 'Antonín Přidal', 'year' => '1994']);

        $selected = $source->selectIssues($issues, $book);

        $this->assertCount(1, $selected);
        $this->assertSame('/kniha/betv6g67', $selected[0]['path']);
    }

    public function test_it_parses_offers_from_detail()
    {
        $offers = (new TrhKnihSource)->parseOffers(
            file_get_contents(base_path('tests/fixtures/trhknih-detail.html')),
            'https://www.trhknih.cz/kniha/10xxq7uz6s',
            'Vteřiny strachu'
        );

        $this->assertCount(2, $offers);
        $this->assertSame(62.0, $offers[0]->value);
        $this->assertSame('Dobrý', $offers[0]->condition);
        $this->assertSame('Trh knih', $offers[0]->source);
        $this->assertSame('https://www.trhknih.cz/kniha/10xxq7uz6s', $offers[0]->url);
        $this->assertSame(53.0, $offers[1]->value);
    }
}
