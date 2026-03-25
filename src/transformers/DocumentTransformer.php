<?php

namespace studioespresso\standardsite\transformers;

use Craft;
use craft\ckeditor\Field as CKEditorField;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Assets as AssetsField;
use craft\fields\PlainText;
use studioespresso\standardsite\StandardSite;

class DocumentTransformer
{
    public function transform(Entry $entry): array
    {
        $settings = StandardSite::getInstance()->getSettings();
        $site = Craft::$app->getSites()->getSiteById($entry->siteId);
        $siteSettings = $settings->getSiteSettings($site->uid);

        $record = [
            '$type' => 'site.standard.document',
            'site' => $siteSettings->publicationAtUri,
            'title' => $entry->title,
            'publishedAt' => $entry->postDate->format(\DateTime::ATOM),
        ];

        // Path: entry URL relative to site base URL
        $entryUrl = $entry->getUrl();
        if ($entryUrl && $site) {
            $baseUrl = rtrim($site->getBaseUrl(), '/');
            $record['path'] = '/' . ltrim(str_replace($baseUrl, '', $entryUrl), '/');
        }

        // Description: try SEO field first, then truncated text content
        $description = $this->extractSeoDescription($entry);
        if ($description) {
            $record['description'] = $description;
        }

        // Text content: auto-detect CKEditor and PlainText fields
        $textContent = $this->extractTextContent($entry);
        if ($textContent) {
            $record['text'] = $textContent;

            // Fallback description from content if no SEO description
            if (!isset($record['description'])) {
                $truncated = mb_substr($textContent, 0, 300);
                if (mb_strlen($textContent) > 300) {
                    $truncated .= '...';
                }
                $record['description'] = $truncated;
            }
        }

        // Tags: from category fields
        $tags = $this->extractTags($entry);
        if (!empty($tags)) {
            $record['tags'] = $tags;
        }

        // Cover image: first image from an Assets field
        $coverBlob = $this->uploadCoverImage($entry);
        if ($coverBlob) {
            $record['coverImage'] = $coverBlob;
        }

        // updatedAt: only if entry was modified after publish
        if ($entry->dateUpdated && $entry->postDate && $entry->dateUpdated > $entry->postDate) {
            $record['updatedAt'] = $entry->dateUpdated->format(\DateTime::ATOM);
        }

        return $record;
    }

    public function getCollection(): string
    {
        return 'site.standard.document';
    }

    private function extractSeoDescription(Entry $entry): ?string
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($field instanceof \studioespresso\seofields\fields\SeoField) {
                $value = $entry->getFieldValue($field->handle);
                if ($value && !empty($value->metaDescription)) {
                    return $value->metaDescription;
                }
            }
        }

        return null;
    }

    private function extractTextContent(Entry $entry): ?string
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        $parts = [];

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($field instanceof CKEditorField) {
                $value = $entry->getFieldValue($field->handle);
                if ($value) {
                    $stripped = strip_tags((string)$value);
                    $stripped = trim(preg_replace('/\s+/', ' ', $stripped));
                    if ($stripped) {
                        $parts[] = $stripped;
                    }
                }
            } elseif ($field instanceof PlainText) {
                $value = $entry->getFieldValue($field->handle);
                if ($value && is_string($value)) {
                    $trimmed = trim($value);
                    if ($trimmed) {
                        $parts[] = $trimmed;
                    }
                }
            }
        }

        return !empty($parts) ? implode("\n\n", $parts) : null;
    }

    private function extractTags(Entry $entry): array
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return [];
        }

        $tags = [];

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($field instanceof \craft\fields\Categories) {
                $categories = $entry->getFieldValue($field->handle);
                if ($categories) {
                    foreach ($categories->all() as $category) {
                        $tags[] = $category->title;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Find the first image from an Assets field and upload it as a blob.
     */
    private function uploadCoverImage(Entry $entry): ?array
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            if (!$field instanceof AssetsField) {
                continue;
            }

            $assets = $entry->getFieldValue($field->handle);
            if (!$assets) {
                continue;
            }

            /** @var Asset|null $asset */
            $asset = $assets->kind('image')->one();
            if (!$asset) {
                continue;
            }

            try {
                $stream = $asset->getStream();
                $binaryData = stream_get_contents($stream);
                fclose($stream);

                if (!$binaryData) {
                    return null;
                }

                $mimeType = $asset->getMimeType() ?: 'image/jpeg';
                return StandardSite::getInstance()->api->uploadBlob($binaryData, $mimeType);
            } catch (\Throwable $e) {
                Craft::warning("[standard-site] Failed to upload cover image: {$e->getMessage()}", __METHOD__);
                return null;
            }
        }

        return null;
    }
}
