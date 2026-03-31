<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\helpers\Cp;
use craft\models\Site;
use craft\web\Controller;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;
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
        $connection = $plugin->connection->getConnection();
        $isConnected = $plugin->connection->isConnected();

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
            ]);
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
            $authUrl = StandardSite::getInstance()->oauth->authorize($siteSettings->handle, $siteHandle);
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

        StandardSite::getInstance()->connection->deleteConnection();

        return $this->asJson(['success' => true]);
    }
}
