<?php

namespace studioespresso\standardsite\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;

class BackfillJob extends BaseJob
{
    public string $siteUid;

    public function execute($queue): void
    {
        $plugin = StandardSite::getInstance();
        $settings = $plugin->getSettings();
        $siteSettings = $settings->getSiteSettings($this->siteUid);

        if (!$plugin->oauth->isConnected()) {
            Craft::warning('[standard-site] Backfill skipped: not connected', __METHOD__);
            return;
        }

        if (!PublicationRecord::getAtUri($this->siteUid)) {
            Craft::warning('[standard-site] Backfill skipped: no publication record', __METHOD__);
            return;
        }

        if (empty($siteSettings->enabledSections)) {
            Craft::warning('[standard-site] Backfill skipped: no enabled sections', __METHOD__);
            return;
        }

        // Resolve site ID from UID
        $site = null;
        foreach (Craft::$app->getSites()->getAllSites() as $s) {
            if ($s->uid === $this->siteUid) {
                $site = $s;
                break;
            }
        }

        if (!$site) {
            return;
        }

        // Get section IDs from UIDs
        $sectionIds = [];
        foreach ($siteSettings->enabledSections as $sectionUid) {
            $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);
            if ($section) {
                $sectionIds[] = $section->id;
            }
        }

        if (empty($sectionIds)) {
            return;
        }

        // Query all enabled, published entries in enabled sections
        $entries = Entry::find()
            ->siteId($site->id)
            ->sectionId($sectionIds)
            ->status('live')
            ->orderBy('postDate ASC')
            ->all();

        $total = count($entries);
        Craft::info("[standard-site] Backfill: {$total} entries to process for site {$site->handle}", __METHOD__);

        foreach ($entries as $i => $entry) {
            $this->setProgress($queue, ($i + 1) / $total, "Syncing entry {$i}/{$total}: {$entry->title}");

            // Skip if already published
            if ($plugin->publisher->isPublished($entry->id, $entry->siteId)) {
                continue;
            }

            try {
                $plugin->publisher->publishEntry($entry);
            } catch (\Throwable $e) {
                Craft::error("[standard-site] Backfill failed for entry #{$entry->id}: {$e->getMessage()}", __METHOD__);
            }
        }

        Craft::info("[standard-site] Backfill complete for site {$site->handle}", __METHOD__);
    }

    protected function defaultDescription(): ?string
    {
        return "Backfilling entries to AT Protocol (site: {$this->siteUid})";
    }
}
