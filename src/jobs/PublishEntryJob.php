<?php

namespace studioespresso\standardsite\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use studioespresso\standardsite\StandardSite;

class PublishEntryJob extends BaseJob
{
    public int $entryId;
    public int $siteId;

    public function execute($queue): void
    {
        $entry = Entry::find()
            ->id($this->entryId)
            ->siteId($this->siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            Craft::warning("[standard-site] Entry #{$this->entryId} not found, skipping publish", __METHOD__);
            return;
        }

        if (!$entry->enabled) {
            // Entry was disabled between save and job execution — unpublish
            StandardSite::getInstance()->publisher->unpublishEntry($entry);
            return;
        }

        StandardSite::getInstance()->publisher->publishEntry($entry);
    }

    protected function defaultDescription(): ?string
    {
        return "Publishing entry #{$this->entryId} to AT Protocol";
    }
}
