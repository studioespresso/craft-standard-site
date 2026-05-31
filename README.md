
# Standard.site for Craft CMS
Publish your Craft CMS content to the [AT Protocol](https://atproto.com) using the [Standard.site](https://standard.site) lexicons.

![Screenshot](https://www.studioespresso.co/assets/standard-site-github.png)

> **TL;DR** — This plugin puts your Craft CMS articles on AT Protocol (the network behind Bluesky). Your content gets its own place on the decentralized web, discoverable by readers like [Frontpage](https://frontpage.fyi) and any future app that speaks AT Protocol. Think of it as RSS for a new, open internet — except your content lives on infrastructure you control, tied to an identity you own.

## What is AT Protocol?

The [AT Protocol](https://atproto.com) is the decentralized social networking protocol behind [Bluesky](https://bsky.app). Unlike traditional platforms, your content lives on your own Personal Data Server (PDS) and your identity is portable through decentralized identifiers (DIDs). Applications are built on open, shared schemas called lexicons — meaning your data isn't locked into any single platform.

## What is standard.site?

[standard.site](https://standard.site) defines a set of lexicons that extend AT Protocol for long-form website content. It introduces two main record types:

- **`site.standard.publication`** — Represents your website or blog as a publication on AT Protocol
- **`site.standard.document`** — Represents an individual page or article

These lexicons bridge traditional websites with the decentralized AT Protocol ecosystem, making your content discoverable by AT Protocol readers and applications.

## What does this plugin do?

This plugin connects your Craft CMS site to AT Protocol through the standard.site lexicons. It:

- Authenticates with your PDS using OAuth 2.1
- Creates a `site.standard.publication` record that represents your site
- Publishes Craft entries as `site.standard.document` records on your PDS
- Keeps records in sync when entries are updated or deleted
- Serves the `/.well-known/site.standard.publication` endpoint for discovery

## Why use it?

- **Own your content** — Your articles live on your PDS, not locked into a CMS or hosting provider
- **Decentralized discovery** — Your content becomes part of the AT Protocol ecosystem, discoverable by readers like [Frontpage](https://frontpage.fyi) and other AT Protocol applications
- **Portable identity** — Your publication is tied to your AT Protocol identity (e.g. your domain or Bluesky handle), not to a specific platform
- **Future-proof** — As more readers and aggregators build on AT Protocol and standard.site, your content is already there

## Requirements

- Craft CMS 5.9.0 or later
- PHP 8.4 or later

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project's Control Panel and search for "Standard.site". Then press "Install".

#### With Composer

```bash
cd /path/to/my-project
composer require studioespresso/craft-standard-site
./craft plugin/install standard-site
```

## Setup

The plugin separates configuration into two parts: **plugin settings** (stored in project config, configured in development) and the **CP section page** (writes to the database, works on production with `allowAdminChanges = false`).

### Plugin Settings (development)

These are configured in **Settings > Plugins > Standard.site** and deploy with your project config:

1. **AT Protocol Handle** — Your handle for each site (e.g. `yourname.bsky.social` or your custom domain)
2. **Enabled Sections** — Which sections should be synced
3. **Field Mappings** — Per section, which field to use for content and cover image (or auto-detect)
4. **Publish on Save** — Toggle automatic syncing when entries are saved

### CP Section Page (production)

These happen on the **Standard.site** page in the CP sidebar and work regardless of `allowAdminChanges` — they write to the database, not project config:

1. **Connect to AT Protocol** — Authenticates with your PDS via OAuth 2.1. Connection data (tokens, DID, PDS URL) is stored in the database.
2. **Publication details** — Name, description, icon, theme colours and discovery (see [Link cards](#link-cards)). These live in the database rather than project config because the icon asset is referenced by an ID that is local to each environment, and so they can be edited on production.
3. **Create / Update Publication Record** — Saves the details above and pushes a `site.standard.publication` record to your PDS. Done on each environment separately since it registers your site with your PDS.

The typical workflow is: set the handle and enabled sections in development via plugin settings, deploy, then connect, fill in the publication details, and create the publication record on each environment.

## Multi-site

Everything is per-site. Each site can connect to its own AT Protocol identity and has its own publication record, enabled sections, and field mappings. Use the site switcher in the breadcrumbs (CP section page) or the site selector buttons (settings page) to switch between sites.

Connecting one site does not affect the others — the OAuth connection (handle, DID, PDS, tokens) is stored per-site, so you connect, disconnect, and publish each site independently.

## Publishing entries

### Automatic

With **Publish on Save** enabled, entries in enabled sections are automatically synced to your PDS when saved. Edited entries update the existing record; deleted entries are removed from the PDS.

### Manual

Each entry in an enabled section shows a **Standard.site** widget in the sidebar with:

- **Publish** — Push the entry to your PDS for the first time
- **Update** — Update the existing record with the current entry content
- **Unpublish** — Remove the record from your PDS

## Well-known endpoint

The plugin registers a `/.well-known/site.standard.publication` route that returns the publication AT-URI for the current site. This is a dynamic route — no file is written to disk.

## Link cards

These are standard.site fields, not anything Bluesky-specific. Any AT Protocol reader that understands the standard.site lexicons — [Bluesky](https://bsky.app), [Frontpage](https://frontpage.fyi), and future apps — can render your content as a rich link card that reflects your publication's identity (icon, brand colours, name and link) instead of a generic web preview. They live on your `site.standard.publication` record, so set them on the **Standard.site** CP page (per environment), then click **Create / Update Publication Record**:

- **Publication Icon** — a square image (at least 256×256). It's uploaded to your PDS as a blob when you create or update the publication record, so the chosen asset must exist in that environment.
- **Theme colours** — `Background`, `Foreground`, `Accent` and `Accent foreground`. All four must be set for the theme to be sent; otherwise it's omitted. They're stored as hex and converted to the `site.standard.theme.basic` RGB format.
- **Show in discovery feeds** — toggles `preferences.showInDiscover`, opting the publication into AT Protocol discovery feeds.

After changing any of these, click **Update Publication Record** on the Standard.site CP page to push the new publication record (per environment).

The author and any subscription prompts a reader may show on cards come from the connected AT Protocol identity itself (the repo DID's profile), not from per-entry fields.

### Document discovery tag

For a reader to map a shared article URL to its AT Protocol record, the entry's page must advertise it in the `<head>`:

```html
<link rel="site.standard.document" href="at://did:.../site.standard.document/...">
```

The plugin injects this automatically into published entry pages (front-end site requests), so no template changes are needed. If your `<head>` is rendered in a way that bypasses Craft's automatic head injection (e.g. a headless setup), output it manually:

```twig
{{ craft.standardSite.documentTag() }}        {# current entry #}
{{ craft.standardSite.documentTag(entry) }}   {# explicit entry #}
```

Without this tag, readers fall back to a generic web preview even when your records are correct.

## Console commands

### Backfill

Sync all existing published entries in enabled sections to AT Protocol:

```bash
./craft standard-site/backfill <siteHandle>
```

For example:

```bash
./craft standard-site/backfill default
```

- Entries already synced are automatically skipped.
- Progress is printed to the terminal as each entry is processed.
- Failed entries are logged and the backfill continues with the remaining entries.

Run the command once per site if you have multiple sites configured.

### Debug

Preview the `site.standard.document` record that would be generated for an entry, without pushing anything to AT Protocol:

```bash
./craft standard-site/debug <entryId>
./craft standard-site/debug <entryId> --site=default
```

Outputs the full document record as formatted JSON. Cover images show asset metadata instead of uploading.

## Content extraction

Every Craft site is different — content might live in a top-level CKEditor field, inside Matrix blocks, or be assembled from multiple fields. The plugin handles this in two layers:

### Field selector (simple)

In plugin settings, you can select which field to use for content and cover image per section. This works well when your content lives in a single top-level field.

### Event (advanced)

For complex field layouts, the `DocumentTransformer::EVENT_TRANSFORM_DOCUMENT` event lets you take full control of what content gets published. The event fires after the built-in extraction, passing the entry and the extracted values. Your listener can override any of them.

```php
use studioespresso\standardsite\transformers\DocumentTransformer;
use studioespresso\standardsite\events\TransformDocumentEvent;
use yii\base\Event;

Event::on(
    DocumentTransformer::class,
    DocumentTransformer::EVENT_TRANSFORM_DOCUMENT,
    function (TransformDocumentEvent $event) {
        $entry = $event->entry;

        // Override text content (plain text for search/previews)
        $event->textContent = strip_tags((string)$entry->myMatrixField->one()?->bodyText);

        // Override HTML content (rich content for rendering)
        $event->htmlContent = (string)$entry->myMatrixField->one()?->bodyText;

        // Override description
        $event->description = $entry->shortDescription;

        // Override tags
        $event->tags = $entry->topics->all()->map(fn($t) => $t->title);
    }
);
```

Available properties on the event:

| Property | Type | Description |
|----------|------|-------------|
| `entry` | `Entry` | The entry being transformed (read-only) |
| `textContent` | `?string` | Plain text content (HTML stripped) |
| `htmlContent` | `?string` | Rich HTML content |
| `description` | `?string` | Short description / excerpt |
| `tags` | `?array` | Array of tag strings |
| `coverImage` | `?array` | Cover image blob reference |

If you don't set a property, the plugin's built-in extraction (field selector or auto-detect) is used.

## Content format

Each document record includes:

- **`textContent`** — Plain text version of the entry content (HTML stripped), used for search and previews.
- **`content`** — Rich content using the `org.wordpress.html` content type (the established HTML standard for AT Protocol). Contains the raw HTML from CKEditor fields.

The content type is configurable via the `contentType` property in site settings.

---

## Credits

This plugin was inspired by [ATmosphere](https://github.com/Automattic/wordpress-atmosphere), the AT Protocol plugin for WordPress by Automattic.

Built with [Claude Code](https://claude.ai/code).
