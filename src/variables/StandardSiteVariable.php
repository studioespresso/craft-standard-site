<?php

namespace studioespresso\standardsite\variables;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\helpers\Template;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;
use Twig\Markup;

class StandardSiteVariable
{
    public function getDocumentUri(Entry $entry): ?string
    {
        return StandardSite::getInstance()->publisher->getDocumentUri($entry->id, $entry->siteId);
    }

    /**
     * Render the <link rel="site.standard.document"> discovery tag for an entry
     * (defaults to the current page's matched entry). Published entry pages get
     * this injected automatically; use this only if you need to place it
     * manually (e.g. a headless or non-standard <head>).
     */
    public function documentTag(?Entry $entry = null): Markup
    {
        $entry ??= Craft::$app->getUrlManager()->getMatchedElement() ?: null;

        $empty = new Markup('', 'UTF-8');
        if (!$entry instanceof Entry) {
            return $empty;
        }

        $uri = $this->getDocumentUri($entry);
        if (!$uri) {
            return $empty;
        }

        return Template::raw(Html::tag('link', '', ['rel' => 'site.standard.document', 'href' => $uri]));
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
