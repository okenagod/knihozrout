<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Filament\Resources\BookResource\RelationManagers\PricesRelationManager;
use App\Jobs\FetchBookPricesJob;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BookPricesAdminTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->book = Book::withoutEvents(fn () => Book::create(['title' => 'Vteřiny strachu', 'bin_number' => '1']));
        $this->book->prices()->create(['value' => 62, 'source' => 'Trh knih', 'url' => 'https://www.trhknih.cz/kniha/10xxq7uz6s', 'condition' => 'dobrý']);
        $this->book->prices()->create(['value' => 49, 'source' => 'Knihobot', 'url' => 'https://knihobot.cz/g/302704']);
    }

    public function test_edit_page_shows_price_table()
    {
        $this->get(EditBook::getUrl(['record' => $this->book]))
            ->assertOk()
            ->assertSeeLivewire(PricesRelationManager::class);

        Livewire::test(PricesRelationManager::class, ['ownerRecord' => $this->book, 'pageClass' => EditBook::class])
            ->assertCanSeeTableRecords($this->book->prices)
            ->assertSee('trhknih.cz')
            ->assertSee('Knihobot');
    }

    public function test_admin_can_add_manual_price_and_range_is_updated()
    {
        Livewire::test(PricesRelationManager::class, ['ownerRecord' => $this->book, 'pageClass' => EditBook::class])
            ->callTableAction('create', data: ['value' => 300, 'source' => 'Ručně'])
            ->assertHasNoTableActionErrors()
            ->assertDispatched('prices-updated');

        $this->assertTrue($this->book->prices()->where('value', 300)->value('is_manual'));
        $this->book->refresh();
        $this->assertSame('49.00', $this->book->minPrice);
        $this->assertSame('300.00', $this->book->maxPrice);
    }

    public function test_fetch_prices_action_dispatches_job()
    {
        Queue::fake();

        Livewire::test(PricesRelationManager::class, ['ownerRecord' => $this->book, 'pageClass' => EditBook::class])
            ->callTableAction('fetchPrices');

        Queue::assertPushed(FetchBookPricesJob::class, fn ($job) => $job->book->is($this->book));
    }
}
