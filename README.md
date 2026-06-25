# Popup Promoter

Popup Promoter is a Craft CMS 4 and Craft CMS 5 plugin for showing promotional modal popups from entries.

It can use an existing section and field setup, or create a default `Popups` section with fields for description, image, call to action URL, call to action label, and cancel button label.

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

The call to action label and cancel button text can both be mapped from entry fields, with fallback text configured in plugin settings. Button colors can be configured independently for the primary call to action and cancel button.

Use the **Create default section + fields** button to create:

- `popups` section
- `popupDescription` plain text field
- `popupImage` assets field
- `popupCtaUrl` plain text field
- `popupCtaLabel` plain text field
- `popupCancelLabel` plain text field

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

You can either choose one default variant or enable randomized variants to mix the display style automatically on each popup render.

The frontend integration is registered automatically by default. To control placement yourself, disable automatic injection and add this to your layout:

```twig
{{ craft.popupPromoter.register() }}
```

If you use your own Vite/Vue frontend component, disable **Load default Vue component** in the plugin settings. The plugin will then skip its bundled Vue/CSS asset bundle. You can still expose the popup endpoint to your frontend with:

```twig
{{ craft.popupPromoter.registerConfig() }}
```

That outputs `window.CraftPopupPromoterConfig.endpoint`. You can also read the endpoint directly in Twig:

```twig
{{ craft.popupPromoter.endpointUrl() }}
```

The endpoint returns the CTA text as `popup.cta.label` and `popup.cta.text`, with the same value also available as `popup.ctaLabel` and `popup.ctaText` for custom frontends.

When a popup is closed, the component sets a per-entry cookie. The cookie duration is configurable in plugin settings; use `0` for a session cookie.

The popup can also be delayed by a configurable number of seconds so it does not open immediately on page load.

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
