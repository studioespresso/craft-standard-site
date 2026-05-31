<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;
use studioespresso\standardsite\helpers\Tid;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\records\PublicationSettingsRecord;
use studioespresso\standardsite\StandardSite;
use studioespresso\standardsite\transformers\PublicationTransformer;
use yii\web\Response;

class CpController extends Controller
{
    public ?Site $site = null;

    public function init(): void
    {
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        if ($siteHandle) {
            $this->site = Craft::$app->getSites()->getSiteByHandle($siteHandle)
                ?? Craft::$app->getSites()->getPrimarySite();
        } else {
            $this->site = Craft::$app->getSites()->getPrimarySite();
        }
        parent::init();
    }

    public function actionIndex(): Response
    {
        $plugin = StandardSite::getInstance();
        $plugin->connection->setActiveSiteUid($this->site->uid);
        $connection = $plugin->connection->getConnection($this->site->uid);
        $isConnected = $plugin->connection->isConnected($this->site->uid);

        $sites = Craft::$app->getSites()->getEditableSites();
        $settings = $plugin->getSettings();
        $currentSiteSettings = $settings->getSiteSettings($this->site->uid);

        $crumbs = ['label' => $this->site->name];

        if (Craft::$app->getIsMultiSite()) {
            $crumbs['menu'] = [
                'label' => Craft::t('site', 'Select site'),
                'items' => Cp::siteMenuItems($sites, $this->site),
            ];
        }

        return $this->asCpScreen()
            ->title('Standard.site')
            ->selectedSubnavItem('standard-site')
            ->crumbs([
                $crumbs,
            ])
            ->contentTemplate('standard-site/cp/index', [
                'isConnected' => $isConnected,
                'connection' => $connection,
                'selectedSite' => $this->site,
                'currentSiteSettings' => $currentSiteSettings,
                'publicationRecord' => PublicationRecord::findBySiteUid($this->site->uid),
                'publicationSettings' => PublicationSettingsRecord::forSite($this->site->uid),
            ]);
    }

    /**
     * Save the per-site publication settings to the database and, if connected,
     * push the publication record to the PDS. Stored in the DB (not project
     * config) so it works on production and references the icon asset by an ID
     * local to this environment.
     */
    public function actionSavePublication(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $siteUid = $request->getRequiredBodyParam('siteUid');
        $plugin = StandardSite::getInstance();
        $plugin->connection->setActiveSiteUid($siteUid);

        $site = Craft::$app->getSites()->getSiteByUid($siteUid);
        if (!$site) {
            Craft::$app->getSession()->setError('Site not found.');
            return $this->redirectToCp($siteUid);
        }

        // Save the settings to the database.
        $icon = $request->getBodyParam('iconAssetId');
        $config = PublicationSettingsRecord::forSite($siteUid);
        $config->name = $request->getBodyParam('name') ?: null;
        $config->description = $request->getBodyParam('description') ?: null;
        $config->iconAssetId = is_array($icon) ? ((int)($icon[0] ?? 0) ?: null) : ((int)$icon ?: null);
        $config->themeBackground = $request->getBodyParam('themeBackground') ?: null;
        $config->themeForeground = $request->getBodyParam('themeForeground') ?: null;
        $config->themeAccent = $request->getBodyParam('themeAccent') ?: null;
        $config->themeAccentForeground = $request->getBodyParam('themeAccentForeground') ?: null;
        $config->showInDiscover = (bool)$request->getBodyParam('showInDiscover', true);
        $config->save();

        // Without a connection we can only store the settings.
        if (!$plugin->connection->isConnected($siteUid)) {
            Craft::$app->getSession()->setNotice('Publication details saved. Connect to AT Protocol to publish the record.');
            return $this->redirectToCp($siteUid);
        }

        // Push (create or update) the publication record on the PDS.
        $transformer = new PublicationTransformer();
        $record = $transformer->transformForSite($site, $config);
        $pubRecord = PublicationRecord::findBySiteUid($siteUid);

        try {
            if ($pubRecord) {
                $result = $plugin->api->putRecord($transformer->getCollection(), $pubRecord->rkey, $record);
                $pubRecord->atUri = $result['uri'] ?? $pubRecord->atUri;
                $pubRecord->cid = $result['cid'] ?? null;
                $pubRecord->save();
            } else {
                $rkey = Tid::generate();
                $result = $plugin->api->createRecord($transformer->getCollection(), $record, $rkey);
                $pubRecord = new PublicationRecord();
                $pubRecord->siteUid = $siteUid;
                $pubRecord->rkey = $rkey;
                $pubRecord->atUri = $result['uri'] ?? "at://{$plugin->connection->getDid($siteUid)}/{$transformer->getCollection()}/{$rkey}";
                $pubRecord->cid = $result['cid'] ?? null;
                $pubRecord->save();
            }

            Craft::$app->getSession()->setNotice('Publication record saved and published.');
        } catch (\Throwable $e) {
            Craft::error("standard-site publication push failed: {$e->getMessage()}", __METHOD__);
            Craft::$app->getSession()->setError("Saved, but publishing failed: {$e->getMessage()}");
        }

        return $this->redirectToCp($siteUid);
    }

    private function redirectToCp(string $siteUid): Response
    {
        $site = Craft::$app->getSites()->getSiteByUid($siteUid);
        return $this->redirect(UrlHelper::cpUrl('standard-site', $site ? ['site' => $site->handle] : []));
    }

    public function actionConnect(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $siteUid = Craft::$app->getRequest()->getRequiredBodyParam('siteUid');
        $settings = StandardSite::getInstance()->getSettings();
        $siteSettings = $settings->getSiteSettings($siteUid);

        if (!$siteSettings->handle) {
            return $this->asJson(['success' => false, 'error' => 'Set the handle in plugin settings first.']);
        }

        // Resolve site handle from UID for cleaner OAuth URLs
        $site = Craft::$app->getSites()->getSiteByUid($siteUid);
        $siteHandle = $site->handle;

        try {
            $authUrl = StandardSite::getInstance()->oauth->authorize($siteSettings->handle, $siteHandle, $siteUid);
            return $this->asJson(['success' => true, 'authUrl' => $authUrl]);
        } catch (\Throwable $e) {
            Craft::error("standard-site connect error: {$e->getMessage()}", __METHOD__);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actionDisconnect(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $siteUid = Craft::$app->getRequest()->getRequiredBodyParam('siteUid');
        StandardSite::getInstance()->connection->deleteConnection($siteUid);

        return $this->asJson(['success' => true]);
    }
}
