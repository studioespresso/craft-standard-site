<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\standardsite\StandardSite;
use yii\web\Response;

class CpController extends Controller
{
    public function actionIndex(): Response
    {
        $plugin = StandardSite::getInstance();
        $connection = $plugin->connection->getConnection();
        $isConnected = $plugin->connection->isConnected();

        return $this->renderTemplate('standard-site/cp/index', [
            'isConnected' => $isConnected,
            'connection' => $connection,
        ]);
    }

    public function actionConnect(): Response
    {
        $this->requireAcceptsJson();

        $siteUid = Craft::$app->getRequest()->getRequiredBodyParam('siteUid');
        $settings = StandardSite::getInstance()->getSettings();
        $siteSettings = $settings->getSiteSettings($siteUid);

        if (!$siteSettings->handle) {
            return $this->asJson(['success' => false, 'error' => 'Set the handle in plugin settings first.']);
        }

        try {
            $authUrl = StandardSite::getInstance()->oauth->authorize($siteSettings->handle);
            return $this->asJson(['success' => true, 'authUrl' => $authUrl]);
        } catch (\Throwable $e) {
            Craft::error("standard-site connect error: {$e->getMessage()}", __METHOD__);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actionDisconnect(): Response
    {
        $this->requireAcceptsJson();

        StandardSite::getInstance()->connection->deleteConnection();

        return $this->asJson(['success' => true]);
    }
}
