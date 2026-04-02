<?php

namespace studioespresso\standardsite\tests\transformers;

use craft\models\Site;
use PHPUnit\Framework\TestCase;
use studioespresso\standardsite\models\SiteSettings;
use studioespresso\standardsite\transformers\PublicationTransformer;

class PublicationTransformerTest extends TestCase
{
    public function test_record_has_correct_type(): void
    {
        $transformer = new PublicationTransformer();
        $record = $transformer->transformForSite($this->makeSite(), $this->makeSettings());

        $this->assertSame('site.standard.publication', $record['$type']);
    }

    public function test_url_is_site_base_url_without_trailing_slash(): void
    {
        $transformer = new PublicationTransformer();
        $record = $transformer->transformForSite($this->makeSite('https://example.com/'), $this->makeSettings());

        $this->assertSame('https://example.com', $record['url']);
    }

    public function test_uses_publication_name_from_settings(): void
    {
        $transformer = new PublicationTransformer();
        $settings = $this->makeSettings(name: 'My Blog');
        $record = $transformer->transformForSite($this->makeSite(), $settings);

        $this->assertSame('My Blog', $record['name']);
    }

    public function test_falls_back_to_site_name_when_no_publication_name(): void
    {
        $transformer = new PublicationTransformer();
        $settings = $this->makeSettings(name: '');
        $record = $transformer->transformForSite($this->makeSite('https://example.com', 'Example Site'), $settings);

        $this->assertSame('Example Site', $record['name']);
    }

    public function test_includes_description_when_set(): void
    {
        $transformer = new PublicationTransformer();
        $settings = $this->makeSettings(description: 'A great blog about things');
        $record = $transformer->transformForSite($this->makeSite(), $settings);

        $this->assertSame('A great blog about things', $record['description']);
    }

    public function test_omits_description_when_empty(): void
    {
        $transformer = new PublicationTransformer();
        $settings = $this->makeSettings(description: '');
        $record = $transformer->transformForSite($this->makeSite(), $settings);

        $this->assertArrayNotHasKey('description', $record);
    }

    public function test_get_collection_returns_correct_value(): void
    {
        $transformer = new PublicationTransformer();
        $this->assertSame('site.standard.publication', $transformer->getCollection());
    }

    private function makeSite(string $baseUrl = 'https://example.com', string $name = 'Test Site'): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getBaseUrl')->willReturn($baseUrl);
        $site->method('getName')->willReturn($name);
        return $site;
    }

    private function makeSettings(string $name = 'Test Publication', string $description = ''): SiteSettings
    {
        $settings = new SiteSettings();
        $settings->publicationName = $name;
        $settings->publicationDescription = $description;
        return $settings;
    }
}
