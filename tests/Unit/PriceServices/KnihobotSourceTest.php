<?php

namespace Tests\Unit\PriceServices;

use App\Models\Book;
use App\Services\PriceServices\KnihobotSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnihobotSourceTest extends TestCase
{
    private function page(array $items): string
    {
        $data = ['props' => ['pageProps' => ['componentProps' => ['items' => $items]]]];

        return '<html><script id="__NEXT_DATA__" type="application/json">' . json_encode($data) . '</script></html>';
    }

    private function item(array $override = []): array
    {
        return $override + [
            'grandmothers_id' => 236861,
            'grandmothers_title' => 'Osudné dědictví',
            'authors_name' => 'Peter Cave',
            'publisher' => 'Brána',
            'isbn' => '9788085946918',
            'highlight_price' => 59,
            'books_count' => 3,
            'is_in_stock' => true,
        ];
    }

    public function test_it_finds_book_by_isbn()
    {
        Http::fake(['knihobot.cz/p/q/9788085946918' => Http::response($this->page([$this->item()]))]);

        $offers = (new KnihobotSource)->search(new Book(['isbn' => '80-85946-91-2', 'title' => 'Osudné dědictví']));

        $this->assertCount(1, $offers);
        $this->assertSame(59.0, $offers[0]->value);
        $this->assertSame('https://knihobot.cz/g/236861', $offers[0]->url);
        $this->assertSame('použitá (nejlevnější z 3 ks)', $offers[0]->condition);
    }

    public function test_title_search_skips_other_authors_and_sold_out_items()
    {
        Http::fake(['knihobot.cz/*' => Http::response($this->page([
            $this->item(['isbn' => '']),
            $this->item(['authors_name' => 'Edgar Wallace', 'isbn' => '', 'grandmothers_id' => 1]),
            $this->item(['is_in_stock' => false, 'isbn' => '', 'grandmothers_id' => 2]),
        ]))]);

        $offers = (new KnihobotSource)->search(new Book(['title' => 'Osudné dědictví', 'author' => 'Peter Cave, Růžena Loulová']));

        $this->assertCount(1, $offers);
        $this->assertSame('https://knihobot.cz/g/236861', $offers[0]->url);
    }
}
