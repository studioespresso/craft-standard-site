<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\standardsite\StandardSite;
use yii\web\Response;

class OauthController extends Controller
{
    protected array|int|bool $allowAnonymous = ['client-metadata', 'callback'];

    /**
     * Serve OAuth client metadata JSON.
     * This is a site-facing route (not CP-only) so the PDS can fetch it.
     */
    public function actionClientMetadata(): Response
    {

        $oauth = StandardSite::getInstance()->oauth;

        $metadata = [
            'client_id' => $oauth->getClientId(),
            'client_name' => 'Standard Site for Craft CMS',
            'client_uri' => rtrim(Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/'),
            'redirect_uris' => [$oauth->getRedirectUri()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'atproto transition:generic',
            'application_type' => 'web',
            'dpop_bound_access_tokens' => true,
        ];

        return $this->asJson($metadata);
    }

    /**
     * OAuth callback from PDS. Exchange code for tokens.
     */
    public function actionCallback(): Response
    {
        $request = Craft::$app->getRequest();
        $code = $request->getQueryParam('code');
        $state = $request->getQueryParam('state');

        if (!$code || !$state) {
            Craft::$app->getSession()->setError('Missing authorization code or state.');
            return $this->redirect(\craft\helpers\UrlHelper::cpUrl('standard-site'));
        }

        $error = $request->getQueryParam('error');
        if ($error) {
            $description = $request->getQueryParam('error_description', $error);
            Craft::$app->getSession()->setError("Authorization failed: {$description}");
            return $this->redirect(\craft\helpers\UrlHelper::cpUrl('standard-site'));
        }

        try {
            Craft::info("OAuth callback received. code length: " . strlen($code) . ", state: {$state}", __METHOD__);
            StandardSite::getInstance()->oauth->handleCallback($code, $state);
            Craft::info("OAuth callback success — tokens stored.", __METHOD__);
            Craft::$app->getSession()->setNotice('Connected to AT Protocol successfully.');
        } catch (\Throwable $e) {
            Craft::error("OAuth callback failed: {$e->getMessage()}\n{$e->getTraceAsString()}", __METHOD__);
            Craft::$app->getSession()->setError("Callback failed: {$e->getMessage()}");
        }

        return $this->redirect(\craft\helpers\UrlHelper::cpUrl('standard-site'));
    }

}
