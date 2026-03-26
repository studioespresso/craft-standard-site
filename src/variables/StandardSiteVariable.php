<?php

namespace studioespresso\standardsite\variables;

use craft\elements\Entry;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;

class StandardSiteVariable
{
    public function getDocumentUri(Entry $entry): ?string
    {
        return StandardSite::getInstance()->publisher->getDocumentUri($entry->id, $entry->siteId);
    }

    public function isPublished(Entry $entry): bool
    {
        return StandardSite::getInstance()->publisher->isPublished($entry->id, $entry->siteId);
    }

    public function getPublicationRecord(string $siteUid): ?PublicationRecord
    {
        return PublicationRecord::findBySiteUid($siteUid);
    }

    public function getPublicationAtUri(string $siteUid): ?string
    {
        return PublicationRecord::getAtUri($siteUid);
    }
}
