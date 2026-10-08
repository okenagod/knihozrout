<?php

namespace Tests\Unit\PriceServices;

use App\Models\Book;
use App\Services\Llm\LlmConnector;
use App\Services\PriceServices\LlmShopSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmShopSourceTest extends TestCase
{
    private const PAGE = <<<'HTML'
        <html><body><nav>menu</nav>
        <div class="item"><a href="/kniha/spion-jemuz-neverili-613346">Špion, jemuž nevěřili</a> František Moravec <b>223 Kč</b> Běžně 249 Kč</div>
        <div class="item"><a href="/kniha/spion-123">Špion</a> Clive Cussler <b>491 Kč</b></div>
        </body></html>
        HTML;

    private function source(): LlmShopSource
    {
        return new LlmShopSource(new LlmConnector('http://llm.test', 'text-model'), 'Testshop', 'https://shop.test/hledat?q={query}');
    }

    private function book(): Book
    {
        return new Book(['title' => 'Špión, jemuž nevěřili', 'author' => 'František Moravec']);
    }

    public function test_it_extracts_offers_via_llm()
    {
        Http::fake([
            'shop.test/*' => Http::response(self::PAGE),
            'llm.test/api/chat' => Http::response(['model' => 'text-model', 'message' => ['content' => json_encode(['offers' => [
                ['title' => 'Špion, jemuž nevěřili', 'author' => 'František Moravec', 'price' => 223, 'condition' => null, 'format' => 'pevná vazba', 'url' => 'https://shop.test/kniha/spion-jemuz-neverili-613346'],
            ]])]]),
        ]);

        $offers = $this->source()->search($this->book());

        $this->assertCount(1, $offers);
        $this->assertSame(223.0, $offers[0]->value);
        $this->assertSame('https://shop.test/kniha/spion-jemuz-neverili-613346', $offers[0]->url);
        $this->assertSame('pevná vazba', $offers[0]->condition);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'llm.test')
            && $request['options']['num_ctx'] === config('prices.llm_num_ctx')
            && str_contains($request['messages'][0]['content'], '[Špion, jemuž nevěřili](https://shop.test/kniha/spion-jemuz-neverili-613346)'));
    }

    public function test_it_skips_llm_when_title_is_not_on_page()
    {
        Http::fake(['shop.test/*' => Http::response('<html>Nic nenalezeno</html>')]);

        $this->assertSame([], $this->source()->search($this->book()));

        Http::assertSentCount(1);
    }

    public function test_it_drops_hallucinated_or_foreign_offers()
    {
        $text = "[Špion, jemuž nevěřili](https://shop.test/kniha/a) František Moravec 223 Kč\n[Špion](https://shop.test/kniha/b) Clive Cussler 491 Kč";

        $offers = $this->source()->toOffers([
            ['title' => 'Špion, jemuž nevěřili', 'author' => 'František Moravec', 'price' => 199, 'url' => 'https://shop.test/kniha/a'], // cena není na stránce
            ['title' => 'Špion', 'author' => 'Clive Cussler', 'price' => 491, 'url' => 'https://shop.test/kniha/b'], // jiná kniha
            ['title' => 'Špion, jemuž nevěřili', 'author' => 'František Moravec', 'price' => 223, 'url' => 'https://jinde.test/x'], // cizí odkaz
        ], $this->book(), $text, 'https://shop.test/hledat?q=x');

        $this->assertCount(1, $offers);
        $this->assertSame(223.0, $offers[0]->value);
        $this->assertSame('https://shop.test/hledat?q=x', $offers[0]->url);
    }
}
