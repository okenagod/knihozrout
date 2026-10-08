<?php

namespace Tests\Feature;

use App\DTO\PriceOffer;
use App\Models\Book;
use App\Services\PriceService;
use App\Services\PriceServices\PriceSourceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'prices.sources' => [FakePriceSource::class, FailingPriceSource::class],
            'prices.llm_shops_enabled' => false,
        ]);
    }

    private function book(): Book
    {
        // bez observeru – ten by spustil zpracování fotek a OCR
        return Book::withoutEvents(fn () => Book::create(['title' => 'Vteřiny strachu', 'bin_number' => '1']));
    }

    public function test_it_replaces_automatic_prices_and_keeps_manual_ones()
    {
        $book = $this->book();
        $book->prices()->create(['value' => 500, 'source' => 'Ručně', 'is_manual' => true]);
        $book->prices()->create(['value' => 999, 'source' => 'Stará', 'is_manual' => false]);

        $offers = app(PriceService::class)->updateBook($book);

        $this->assertCount(2, $offers, 'duplicitní nabídka se uloží jen jednou');
        $this->assertEqualsCanonicalizing(['45.00', '120.00', '500.00'], $book->prices()->pluck('value')->all());

        $book->refresh();
        $this->assertSame('45.00', $book->minPrice);
        $this->assertSame('500.00', $book->maxPrice);
    }

    public function test_price_range_follows_manual_changes()
    {
        $book = $this->book();
        $price = $book->prices()->create(['value' => 80, 'source' => 'Ručně', 'is_manual' => true]);
        $book->prices()->create(['value' => 30, 'source' => 'Ručně', 'is_manual' => true]);

        $this->assertSame('30.00', $book->refresh()->minPrice);

        $price->delete();
        $this->assertSame('30.00', $book->refresh()->maxPrice);
    }
}

class FakePriceSource implements PriceSourceInterface
{
    public function name(): string
    {
        return 'Fake';
    }

    public function search(Book $book): array
    {
        return [
            new PriceOffer(45, 'Fake', 'https://fake.test/1', 'Vteřiny strachu', 'dobrý'),
            new PriceOffer(45, 'Fake', 'https://fake.test/1', 'Vteřiny strachu', 'dobrý'),
            new PriceOffer(120, 'Fake', 'https://fake.test/2', 'Vteřiny strachu'),
        ];
    }
}

class FailingPriceSource implements PriceSourceInterface
{
    public function name(): string
    {
        return 'Failing';
    }

    public function search(Book $book): array
    {
        throw new \RuntimeException('web nedostupný');
    }
}
