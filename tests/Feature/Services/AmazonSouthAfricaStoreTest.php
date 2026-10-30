<?php

namespace Tests\Feature\Services;

use App\Console\Commands\CreateStores;
use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\Url;
use App\Services\Helpers\CurrencyHelper;
use App\Services\ScrapeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonSouthAfricaStoreTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('price_buddy.scraper_api_url', 'http://scraper:3000');
        Http::preventStrayRequests();

        $this->artisan(CreateStores::COMMAND, ['country' => 'south-africa'])->assertExitCode(0);
        $this->store = Store::where('slug', 'amazon-us-za')->firstOrFail();
    }

    public function test_preset_tracks_the_total_including_shipping_and_import_charges(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-with-fees.html')));

        $result = ScrapeUrl::new($this->store->test_url)->scrape();

        $this->assertSame('MINISFORUM MS-A2 Mini Workstation AMD Ryzen 7 7745HX', $result['title']);
        $this->assertSame("ZAR\u{a0}8,941.05", $result['price']);
        $this->assertSame(8941.05, CurrencyHelper::toFloat($result['price'], $this->store->locale, $this->store->currency));
        $this->assertSame('https://m.media-amazon.com/images/I/61ODmLMUKAL._AC_SL1295_.jpg', $result['image']);
        $this->assertSame(StockStatus::InStock, ScrapeUrl::resolveStockStatus($result, $this->store->scrape_strategy->availability));
        $this->assertSame('en_ZA', $this->store->locale);
        $this->assertSame('ZAR', $this->store->currency);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'http://scraper:3000/api/article?')
            && $request['url'] === $this->store->test_url
            && $request['full-content'] === true
            && $request['wait-until'] === 'domcontentloaded'
            && $request['sleep'] === '3000'
        );
        Http::assertSentCount(1);
    }

    public function test_item_price_is_used_when_there_is_no_fee_breakdown(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-without-fees.html')));

        $result = ScrapeUrl::new($this->store->test_url)->scrape();

        $this->assertSame('ZAR7,291.47', $result['price']);
        $this->assertSame(7291.47, CurrencyHelper::toFloat($result['price'], $this->store->locale, $this->store->currency));
    }

    public function test_unavailable_product_does_not_use_a_similar_item_price(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/unavailable.html')));

        $result = ScrapeUrl::new($this->store->test_url)->scrape();

        $this->assertSame('Apple 2024 MacBook Air 13-inch Laptop with M3 chip', $result['title']);
        $this->assertNull($result['price']);
        $this->assertSame(StockStatus::OutOfStock, ScrapeUrl::resolveStockStatus($result, $this->store->scrape_strategy->availability));
    }

    public function test_preset_matches_domains_with_and_without_www(): void
    {
        foreach (['amazon.com', 'www.amazon.com'] as $domain) {
            $store = ScrapeUrl::new('https://'.$domain.'/dp/B0GT8XG9Z5')->getStore();

            $this->assertNotNull($store);
            $this->assertTrue($this->store->is($store));
        }

        Http::assertNothingSent();
    }

    public function test_recorded_price_is_the_total(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-with-fees.html')));
        $url = Url::factory()->createOne([
            'url' => $this->store->test_url,
            'product_id' => Product::factory()->create()->id,
            'store_id' => $this->store->id,
        ]);

        $price = $url->updatePrice();

        $this->assertSame(8941.05, (float) $price->price);
    }

    public function test_preset_asks_amazon_for_rand_prices(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-with-fees.html')));

        ScrapeUrl::new($this->store->test_url)->scrape();

        Http::assertSent(fn (Request $request): bool => $request['extra-http-headers'] === 'Cookie:i18n-prefs=ZAR');
    }

    public function test_page_in_usd_is_retried_and_its_price_is_not_read_as_rand(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-in-usd.html')));

        $result = ScrapeUrl::new($this->store->test_url)->setSendUiNotifications(false)->scrape();

        $this->assertNull($result['price']);
        $this->assertSame('$', $result[ScrapeUrl::CURRENCY_MISMATCH_KEY]);
        $this->assertSame(StockStatus::InStock, ScrapeUrl::resolveStockStatus($result, $this->store->scrape_strategy->availability));
        Http::assertSentCount(3);
    }

    public function test_retry_uses_the_rand_page_when_amazon_answers_in_usd_first(): void
    {
        Http::fake([
            'http://scraper:3000/api/article*' => Http::sequence()
                ->push(['fullContent' => file_get_contents(base_path('tests/Fixtures/Amazon/product-in-usd.html'))])
                ->push(['fullContent' => file_get_contents(base_path('tests/Fixtures/Amazon/product-with-fees.html'))]),
        ]);

        $result = ScrapeUrl::new($this->store->test_url)->scrape();

        $this->assertSame("ZAR\u{a0}8,941.05", $result['price']);
        $this->assertArrayNotHasKey(ScrapeUrl::CURRENCY_MISMATCH_KEY, $result);
        Http::assertSentCount(2);
    }

    public function test_usd_price_is_not_recorded(): void
    {
        $this->fakeBrowserResponse(file_get_contents(base_path('tests/Fixtures/Amazon/product-in-usd.html')));
        $url = Url::factory()->createOne([
            'url' => $this->store->test_url,
            'product_id' => Product::factory()->create()->id,
            'store_id' => $this->store->id,
        ]);
        $url->prices()->delete();

        $this->assertNull($url->updatePrice());
        $this->assertSame(0, $url->prices()->count());
    }

    protected function fakeBrowserResponse(string $html): void
    {
        Http::fake([
            'http://scraper:3000/api/article*' => Http::response(['fullContent' => $html]),
        ]);
    }
}
