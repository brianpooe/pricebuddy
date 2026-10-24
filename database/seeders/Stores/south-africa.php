<?php

use App\Enums\ScraperService;

return [
    [
        'name' => 'Takealot',
        'slug' => 'takealot',
        'domains' => [
            ['domain' => 'takealot.com'],
            ['domain' => 'www.takealot.com'],
        ],
        'scrape_strategy' => [
            'title' => [
                'value' => '[data-ref="product-title"] h1',
                'type' => 'selector',
            ],
            'price' => [
                'value' => '[data-ref="buybox-offer"] [data-ref="price"] > .currency',
                'type' => 'selector',
            ],
            'image' => [
                'value' => 'img[data-ref="main-gallery-photo-0"], img[data-ref="main-gallery-photo-single"]|src',
                'type' => 'selector',
            ],
            'availability' => [
                'value' => '[data-ref="stock-availability-status"]',
                'type' => 'selector',
                'match' => [
                    'out_of_stock' => ['type' => 'regex', 'value' => 'out of stock|sold out|currently unavailable|not available'],
                    'pre_order' => ['type' => 'regex', 'value' => 'pre[- ]?order'],
                    'back_order' => ['type' => 'regex', 'value' => 'back[- ]?order'],
                    'default' => 'in_stock',
                ],
            ],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            'scraper_service_settings' => "wait-until=domcontentloaded\nsleep=5000",
            'test_url' => 'https://www.takealot.com/bosch-series-8-14-place-freestanding-dishwasher-a-silver-inox/PLID93203543',
            'locale_settings' => [
                'locale' => 'en_ZA',
                'currency' => 'ZAR',
            ],
        ],
    ],
    [
        'name' => 'GeeWiz',
        'slug' => 'geewiz',
        'domains' => [
            ['domain' => 'geewiz.co.za'],
            ['domain' => 'www.geewiz.co.za'],
        ],
        // Product pages repeat og/product meta tags (one with an empty og:title) and show
        // the price as "R8,890 incl. VAT", so the Product JSON-LD is the reliable source.
        'scrape_strategy' => [
            'title' => [
                'value' => null,
                'type' => 'schema_org',
            ],
            'price' => [
                'value' => null,
                'type' => 'schema_org',
            ],
            'image' => [
                'value' => null,
                'type' => 'schema_org',
            ],
            'availability' => [
                'value' => null,
                'type' => 'schema_org',
            ],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            'scraper_service_settings' => "wait-until=domcontentloaded\nsleep=1000",
            'test_url' => 'https://www.geewiz.co.za/seagate-nas-hdd-series-35-sata-drives/27746-seagate-10tb-ironwolf-7200-rpm-sata-iii-35-internal-nas-network-attached-storage-hard-disk-drive-hdd.html',
            'locale_settings' => [
                'locale' => 'en_ZA',
                'currency' => 'ZAR',
            ],
        ],
    ],
    [
        'name' => 'Amazon US (to South Africa)',
        'slug' => 'amazon-us-za',
        'domains' => [
            ['domain' => 'amazon.com'],
            ['domain' => 'www.amazon.com'],
        ],
        'scrape_strategy' => [
            'title' => [
                'value' => '#productTitle',
                'type' => 'selector',
            ],
            // Track the "Shipping & Fee Details" total (item, AmazonGlobal shipping and
            // import charges), so a cheaper item with higher fees is not a price drop.
            // Falls back to the buy box price when the page has no fee breakdown.
            'price' => [
                'value' => '(//div[@id="amazonGlobal_feature_div"]//div[contains(@class,"a-popover-preload")]//table[contains(@class,"a-lineitem")]//tr[normalize-space(td[1])="Total"]/td[last()])[1]'
                    .' | (//div[@id="corePrice_feature_div" or @id="corePriceDisplay_desktop_feature_div" or @id="apex_desktop"]//span[contains(concat(" ",normalize-space(@class)," ")," a-price ")]/span[@class="a-offscreen"][normalize-space()!=""])[1]'
                    .'[not(//div[@id="amazonGlobal_feature_div"]//div[contains(@class,"a-popover-preload")]//table[contains(@class,"a-lineitem")]//tr[normalize-space(td[1])="Total"])]',
                'type' => 'xpath',
            ],
            'image' => [
                'value' => '~"hiRes":"(.+?)"~',
                'type' => 'regex',
            ],
            'availability' => [
                'value' => '#availability',
                'type' => 'selector',
                'match' => [
                    'out_of_stock' => ['type' => 'regex', 'value' => 'currently unavailable|out of stock|cannot be shipped'],
                    'pre_order' => ['type' => 'regex', 'value' => 'pre[- ]?order'],
                    'default' => 'in_stock',
                ],
            ],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            'scraper_service_settings' => "wait-until=domcontentloaded\nsleep=3000",
            'test_url' => 'https://www.amazon.com/dp/B0GT8XG9Z5',
            'locale_settings' => [
                'locale' => 'en_ZA',
                'currency' => 'ZAR',
            ],
        ],
    ],
];
