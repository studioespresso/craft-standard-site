<?php

namespace studioespresso\standardsite\services;

use Craft;
use craft\elements\Entry;
use studioespresso\standardsite\helpers\Tid;
use studioespresso\standardsite\records\EntryRecord;
use studioespresso\standardsite\StandardSite;
use studioespresso\standardsite\transformers\DocumentTransformer;
use yii\base\Component;

class PublisherService extends Component
{
    public function publishEntry(Entry $entry): void
    {
        $plugin = StandardSite::getInstance();
        $settings = $plugin->getSettings();
        $site = Craft::$app->getSites()->getSiteById($entry->siteId);
        $siteSettings = $settings->getSiteSettings($site->uid);

        if (!$plugin->oauth->isConnected()) {
            Craft::warning('[standard-site] Cannot publish: not connected to PDS', __METHOD__);
            return;
        }

        if (!$siteSettings->publicationAtUri) {
            Craft::warning('[standard-site] Cannot publish: no publication record for site ' . $site->handle, __METHOD__);
            return;
        }

        $transformer = new DocumentTransformer();
        $record = $transformer->transform($entry);
        $collection = $transformer->getCollection();

        // Check if already published
        $existing = $this->getRecord($entry->id, $entry->siteId, $collection);

        if ($existing) {
            // Update existing record
            $result = $plugin->api->putRecord($collection, $existing->rkey, $record);

            $existing->cid = $result['cid'] ?? null;
            $existing->atUri = $result['uri'] ?? $existing->atUri;
            $existing->save();

            Craft::info("[standard-site] Updated document record for entry #{$entry->id}", __METHOD__);
        } else {
            // Create new record
            $rkey = Tid::generate();
            $result = $plugin->api->createRecord($collection, $record, $rkey);

            $dbRecord = new EntryRecord();
            $dbRecord->entryId = $entry->id;
            $dbRecord->siteId = $entry->siteId;
            $dbRecord->collection = $collection;
            $dbRecord->rkey = $rkey;
            $dbRecord->atUri = $result['uri'] ?? "at://{$settings->did}/{$collection}/{$rkey}";
            $dbRecord->cid = $result['cid'] ?? null;
            $dbRecord->save();

            Craft::info("[standard-site] Created document record for entry #{$entry->id}", __METHOD__);
        }
    }

    public function unpublishEntry(Entry $entry): void
    {
        $plugin = StandardSite::getInstance();

        if (!$plugin->oauth->isConnected()) {
            return;
        }

        $records = EntryRecord::find()
            ->where(['entryId' => $entry->id, 'siteId' => $entry->siteId])
            ->all();

        foreach ($records as $record) {
            try {
                $plugin->api->deleteRecord($record->collection, $record->rkey);
                Craft::info("[standard-site] Deleted {$record->collection} record for entry #{$entry->id}", __METHOD__);
            } catch (\Throwable $e) {
                Craft::error("[standard-site] Failed to delete record: {$e->getMessage()}", __METHOD__);
            }

            $record->delete();
        }
    }

    public function isPublished(int $entryId, int $siteId): bool
    {
        return EntryRecord::find()
            ->where([
                'entryId' => $entryId,
                'siteId' => $siteId,
                'collection' => 'site.standard.document',
            ])
            ->exists();
    }

    public function getDocumentUri(int $entryId, int $siteId): ?string
    {
        $record = $this->getRecord($entryId, $siteId, 'site.standard.document');
        return $record?->atUri;
    }

    private function getRecord(int $entryId, int $siteId, string $collection): ?EntryRecord
    {
        return EntryRecord::find()
            ->where([
                'entryId' => $entryId,
                'siteId' => $siteId,
                'collection' => $collection,
            ])
            ->one();
    }
}
