# Standard Site

Publish your Craft CMS content to the [AT Protocol](https://atproto.com) using the [standard.site](https://standard.site) lexicons. Entries are synced as `site.standard.document` records on your Personal Data Server (PDS), making your content available across the decentralized AT Protocol ecosystem.

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

### 1. Configure your handle

Go to **Settings > Plugins > Standard Site** and enter your AT Protocol handle (e.g. `yourname.bsky.social` or your custom domain) for each site.

### 2. Connect to AT Protocol

Visit the **Standard.site** page in the CP sidebar. Select your site and click **Connect with AT Protocol**. This initiates an OAuth 2.1 flow with your PDS.

### 3. Create a publication record

Back in **Settings > Plugins > Standard Site**, fill in your publication name and description, then click **Create Publication Record**. This creates a `site.standard.publication` record on your PDS.

### 4. Enable sections

Under **Sync Settings**, check which sections should be synced to AT Protocol. For each enabled section you can configure:

- **Content Field** — Which CKEditor or PlainText field to use for the document text content. Defaults to auto-detecting all text fields.
- **Cover Image Field** — Which Assets field to use for the cover image. Defaults to auto-detecting the first image field.

### 5. Enable auto-publish

Toggle **Publish on Save** to automatically sync entries when they are saved. When disabled, you can still publish manually from the entry sidebar.

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

## Content format

Each document record includes:

- **`textContent`** — Plain text version of the entry content (HTML stripped), used for search and previews.
- **`content`** — Rich content using the `org.wordpress.html` content type (the established HTML standard for AT Protocol). Contains the raw HTML from CKEditor fields.

The content type is configurable via the `contentType` property in site settings.
