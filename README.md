# Popup Promoter

Popup Promoter is a Craft CMS 4 and Craft CMS 5 plugin for showing promotional modal popups from entries.

It can use an existing section and field setup, or create a default `Popups` section with fields for description, image, call to action URL, and call to action label.

## Requirements

- Craft CMS `^4.0 || ^5.0`
- PHP `^8.0.2`

## Installation

Install the plugin with Composer:

```bash
composer require arifje/craft-popup-promoter
php craft plugin/install craft-popup-promoter
```

For local development, add a path repository to the Craft project:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../craft-popup-promoter"
    }
  ]
}
```

Then require it:

```bash
composer require arifje/craft-popup-promoter:@dev
```

## Setup

Open the plugin settings in the control panel and choose the entry section and field mappings. Only live entries are eligible, so disabled, pending, and expired entries are skipped automatically.

Field mapping dropdowns show the custom fields available on the selected popup section. Use the test modal button to preview a random live entry with the current mappings directly from the settings page.

Use the **Create default section + fields** button to create:

- `popups` section
- `popupDescription` plain text field
- `popupImage` assets field
- `popupCtaUrl` plain text field
- `popupCtaLabel` plain text field

The same setup can be run from the command line:

```bash
php craft craft-popup-promoter/setup/install-defaults
```

## Entry Selection

On each frontend page request, the plugin endpoint:

1. Queries live entries from the configured section.
2. Skips entries with an active dismissal cookie for the current visitor.
3. Randomly selects one remaining entry.

If nothing is eligible, no popup is rendered.

## Frontend

The frontend is a Vue 3 component powered by `vue-final-modal`. It supports these variants:

- Centered modal
- Full page modal
- Top banner
- Bottom banner
- Left drawer
- Right drawer
- Corner modal

Frontend assets are injected automatically by default. To control placement yourself, disable automatic injection and add this to your layout:

```twig
{{ craft.popupPromoter.register() }}
```

When a popup is closed, the component sets a per-entry cookie. The cookie duration is configurable in plugin settings; use `0` for a session cookie.

## Development

Install frontend dependencies and build the browser asset:

```bash
npm install
npm run build
```

The build writes:

- `src/web/assets/dist/popup-promoter.iife.js`
- `src/web/assets/dist/popup-promoter.css`

## Events

The frontend dispatches browser events for analytics hooks:

- `craft-popup-promoter:shown`
- `craft-popup-promoter:dismissed`
- `craft-popup-promoter:error`
