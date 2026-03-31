<?php

namespace studioespresso\standardsite;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use studioespresso\standardsite\jobs\PublishEntryJob;
use studioespresso\standardsite\models\Settings;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\services\ApiService;
use studioespresso\standardsite\services\ConnectionService;
use studioespresso\standardsite\services\DPopService;
use studioespresso\standardsite\services\EncryptionService;
use studioespresso\standardsite\services\OAuthService;
use studioespresso\standardsite\services\PublisherService;
use studioespresso\standardsite\services\ResolverService;
use studioespresso\standardsite\variables\StandardSiteVariable;
use yii\base\Event;

/**
 * Standard Site plugin — publish Craft CMS entries to AT Protocol via standard.site lexicons.
 *
 * @method static StandardSite getInstance()
 * @method Settings getSettings()
 * @property-read EncryptionService $encryption
 * @property-read ResolverService $resolver
 * @property-read DPopService $dpop
 * @property-read OAuthService $oauth
 * @property-read ApiService $api
 * @property-read PublisherService $publisher
 * @property-read ConnectionService $connection
 * @author Studio Espresso <info@studioespresso.co>
 * @copyright Studio Espresso
 * @license MIT
 */
class StandardSite extends Plugin
{
    public string $schemaVersion = '1.2.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'encryption' => EncryptionService::class,
                'resolver' => ResolverService::class,
                'dpop' => DPopService::class,
                'oauth' => OAuthService::class,
                'api' => ApiService::class,
                'publisher' => PublisherService::class,
                'connection' => ConnectionService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerUrlRules();
        $this->registerEventHandlers();
        $this->registerTwigVariable();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Standard.site';
        $item['icon'] = '@studioespresso/standardsite/icon-mask.svg';
        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('standard-site/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerEventHandlers(): void
    {
        // Publish entry on save
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Skip propagating saves, resaving, drafts, and revisions
                if ($entry->propagating || $entry->resaving || $entry->getIsDraft() || $entry->getIsRevision()) {
                    return;
                }

                $settings = $this->getSettings();
                $site = Craft::$app->getSites()->getSiteById($entry->siteId);
                $siteSettings = $settings->getSiteSettings($site->uid);

                // Must be connected and have publish-on-save enabled for this site
                if (!$this->connection->isConnected() || !$siteSettings->publishOnSave) {
                    return;
                }

                // Must have a publication record for this site
                if (!PublicationRecord::getAtUri($site->uid)) {
                    return;
                }

                // Check if this section is enabled for this site
                $section = $entry->getSection();
                if (!$section || !in_array($section->uid, $siteSettings->enabledSections, true)) {
                    return;
                }

                if ($entry->enabled) {
                    // Push publish job to queue
                    Craft::$app->getQueue()->push(new PublishEntryJob([
                        'entryId' => $entry->id,
                        'siteId' => $entry->siteId,
                    ]));
                } else {
                    // Entry disabled — unpublish if it was published
                    if ($this->publisher->isPublished($entry->id, $entry->siteId)) {
                        $this->publisher->unpublishEntry($entry);
                    }
                }
            }
        );

        // Delete records when entry is deleted
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_DELETE,
            function(Event $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if ($this->connection->isConnected() && $this->publisher->isPublished($entry->id, $entry->siteId)) {
                    $this->publisher->unpublishEntry($entry);
                }
            }
        );

        // Entry sidebar widget
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Only show for saved entries (not new ones)
                if (!$entry->id) {
                    return;
                }

                $settings = $this->getSettings();
                $site = Craft::$app->getSites()->getSiteById($entry->siteId);
                $siteSettings = $settings->getSiteSettings($site->uid);
                $section = $entry->getSection();

                // Only show for enabled sections on this site
                if (!$section || !in_array($section->uid, $siteSettings->enabledSections, true)) {
                    return;
                }

                $isConnected = $this->connection->isConnected();
                $isPublished = $isConnected && $this->publisher->isPublished($entry->id, $entry->siteId);
                $atUri = $isPublished ? $this->publisher->getDocumentUri($entry->id, $entry->siteId) : null;

                $event->html .= Craft::$app->getView()->renderTemplate('standard-site/_entry-sidebar', [
                    'entry' => $entry,
                    'isConnected' => $isConnected,
                    'isPublished' => $isPublished,
                    'atUri' => $atUri,
                ]);
            }
        );
    }

    private function registerTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $event->sender->set('standardSite', StandardSiteVariable::class);
            }
        );
    }

    private function registerUrlRules(): void
    {
        // CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['standard-site'] = 'standard-site/cp/index';
            }
        );

        // Site routes (publicly accessible — needed for PDS to fetch metadata and redirect back)
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['.well-known/site.standard.publication'] = 'standard-site/well-known/publication';
                $event->rules['standard-site/oauth/client-metadata'] = 'standard-site/oauth/client-metadata';
                $event->rules['standard-site/oauth/callback'] = 'standard-site/oauth/callback';
            }
        );
    }
}
