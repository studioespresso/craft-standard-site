
# Standard Site
Publish your Craft CMS content to the [AT Protocol](https://atproto.com) using the [standard.site](https://standard.site) lexicons.

![Screenshot](https://www.studioespresso.co/assets/standard-site-github.png)
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

Go to the Plugin Store in your project's Control Panel and search for "Standard Site". Then press "Install".

#### With Composer

```bash
cd /path/to/my-project
composer require studioespresso/craft-standard-site
./craft plugin/install standard-site
```

## Setup

The plugin separates configuration into two parts: **plugin settings** (stored in project config, configured in development) and the **CP section page** (writes to the database, works on production with `allowAdminChanges = false`).

### Plugin Settings (development)

These are configured in **Settings > Plugins > Standard Site** and deploy with your project config:

1. **AT Protocol Handle** — Your handle for each site (e.g. `yourname.bsky.social` or your custom domain)
2. **Publication Name & Description** — How your publication appears on AT Protocol
3. **Enabled Sections** — Which sections should be synced
4. **Field Mappings** — Per section, which field to use for content and cover image (or auto-detect)
5. **Publish on Save** — Toggle automatic syncing when entries are saved

### CP Section Page (production)

These actions happen on the **Standard.site** page in the CP sidebar and work regardless of `allowAdminChanges`:

1. **Connect to AT Protocol** — Authenticates with your PDS via OAuth 2.1. Connection data (tokens, DID, PDS URL) is stored in the database, not project config.
2. **Create Publication Record** — Pushes a `site.standard.publication` record to your PDS. This must be done on each environment separately since it registers your site with your PDS.

The typical workflow is: configure everything in development via plugin settings, deploy to production, then connect and create the publication record on production.

## Multi-site

All settings are per-site. Each site can have its own AT Protocol handle, publication record, enabled sections, and field mappings. Use the site switcher in the breadcrumbs (CP section page) or the site selector buttons (settings page) to switch between sites.

The OAuth connection is shared across sites (one AT Protocol identity per installation).

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
