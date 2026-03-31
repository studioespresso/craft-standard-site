<?php

namespace studioespresso\standardsite\transformers;

use Craft;
use craft\ckeditor\Field as CKEditorField;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Assets as AssetsField;
use craft\fields\PlainText;
use craft\base\Component;
use studioespresso\standardsite\events\TransformDocumentEvent;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;

class DocumentTransformer extends Component
{
    public const EVENT_TRANSFORM_DOCUMENT = 'transformDocument';

    public bool $dryRun = false;

    public function transform(Entry $entry): array
    {
        $settings = StandardSite::getInstance()->getSettings();
        $site = Craft::$app->getSites()->getSiteById($entry->siteId);
        $siteSettings = $settings->getSiteSettings($site->uid);

        $record = [
            '$type' => 'site.standard.document',
            'site' => PublicationRecord::getAtUri($site->uid),
            'title' => $entry->title,
            'publishedAt' => $entry->postDate->format(\DateTime::ATOM),
        ];

        // Path: entry URL relative to site base URL
        $entryUrl = $entry->getUrl();
        if ($entryUrl && $site) {
            $baseUrl = rtrim($site->getBaseUrl(), '/');
            $record['path'] = '/' . ltrim(str_replace($baseUrl, '', $entryUrl), '/');
        }

        $section = $entry->getSection();
        $contentFieldUid = $siteSettings->sectionContentFields[$section->uid] ?? null;
        $imageFieldUid = $siteSettings->sectionImageFields[$section->uid] ?? null;

        // Built-in extraction
        $description = $this->extractSeoDescription($entry);
        $textContent = $this->extractTextContent($entry, $contentFieldUid);
        $htmlContent = $this->extractHtmlContent($entry, $contentFieldUid);
        $tags = $this->extractTags($entry);
        $coverBlob = $this->uploadCoverImage($entry, $imageFieldUid);

        // Fire event to allow overrides
        $event = new TransformDocumentEvent([
            'entry' => $entry,
            'textContent' => $textContent,
            'htmlContent' => $htmlContent,
            'description' => $description,
            'tags' => $tags ?: null,
            'coverImage' => $coverBlob,
        ]);
        $this->trigger(self::EVENT_TRANSFORM_DOCUMENT, $event);

        // Use event values (listeners may have overridden them)
        if ($event->description) {
            $record['description'] = $event->description;
        }

        if ($event->textContent) {
            $record['textContent'] = $event->textContent;

            // Fallback description from content if no description set
            if (!isset($record['description'])) {
                $truncated = mb_substr($event->textContent, 0, 300);
                if (mb_strlen($event->textContent) > 300) {
                    $truncated .= '...';
                }
                $record['description'] = $truncated;
            }
        }

        if ($event->htmlContent) {
            $record['content'] = [
                '$type' => $siteSettings->contentType,
                'html' => $event->htmlContent,
            ];
        }

        if (!empty($event->tags)) {
            $record['tags'] = $event->tags;
        }

        if ($event->coverImage) {
            $record['coverImage'] = $event->coverImage;
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

    private function extractTextContent(Entry $entry, ?string $contentFieldUid = null): ?string
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        $parts = [];

        foreach ($fieldLayout->getCustomFields() as $field) {
            // If a specific field is configured, only use that one
            if ($contentFieldUid && $field->uid !== $contentFieldUid) {
                continue;
            }

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

    private function extractHtmlContent(Entry $entry, ?string $contentFieldUid = null): ?string
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        $parts = [];

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($contentFieldUid && $field->uid !== $contentFieldUid) {
                continue;
            }

            if ($field instanceof CKEditorField) {
                $value = $entry->getFieldValue($field->handle);
                if ($value) {
                    $html = trim((string)$value);
                    if ($html) {
                        $parts[] = $html;
                    }
                }
            } elseif ($field instanceof PlainText) {
                $value = $entry->getFieldValue($field->handle);
                if ($value && is_string($value)) {
                    $trimmed = trim($value);
                    if ($trimmed) {
                        $parts[] = '<p>' . htmlspecialchars($trimmed) . '</p>';
                    }
                }
            }
        }

        return !empty($parts) ? implode("\n", $parts) : null;
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
     * Find the cover image and upload it as a blob.
     * If a field UID is configured, use that specific field. Otherwise auto-detect the first Assets field.
     */
    private function uploadCoverImage(Entry $entry, ?string $imageFieldUid = null): ?array
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        $fields = [];

        if ($imageFieldUid) {
            // Use the configured field
            foreach ($fieldLayout->getCustomFields() as $field) {
                if ($field->uid === $imageFieldUid && $field instanceof AssetsField) {
                    $fields = [$field];
                    break;
                }
            }
        } else {
            // Auto-detect: all Assets fields
            foreach ($fieldLayout->getCustomFields() as $field) {
                if ($field instanceof AssetsField) {
                    $fields[] = $field;
                }
            }
        }

        foreach ($fields as $field) {
            $assets = $entry->getFieldValue($field->handle);
            if (!$assets) {
                continue;
            }

            /** @var Asset|null $asset */
            $asset = $assets->kind('image')->one();
            if (!$asset) {
                continue;
            }

            if ($this->dryRun) {
                return [
                    '_dryRun' => true,
                    'asset' => $asset->title,
                    'filename' => $asset->filename,
                    'mimeType' => $asset->getMimeType() ?: 'image/jpeg',
                    'size' => $asset->size,
                ];
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
