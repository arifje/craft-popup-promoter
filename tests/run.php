<?php

declare(strict_types=1);

// Uses an existing Craft vendor directory, without bootstrapping a site or database.
$vendor = $argv[1] ?? dirname(__DIR__) . '/vendor';
require $vendor . '/autoload.php';
require_once $vendor . '/yiisoft/yii2/Yii.php';
require_once $vendor . '/craftcms/cms/src/Craft.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'arifje\\craftpopuppromoter\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use arifje\craftpopuppromoter\helpers\Url;
use arifje\craftpopuppromoter\models\Settings;
use arifje\craftpopuppromoter\services\PopupService;
use craft\elements\Entry;
use craft\elements\db\EntryQuery;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message . "\n" . json_encode(Yii::getLogger()->messages));
    }
}

function invoke(object $object, string $method, mixed ...$args): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($object, ...$args);
}

final class FixtureEntry extends Entry
{
    public array $values = [];
    public ?string $fixtureUrl = null;

    public function getUrl(): ?string
    {
        return $this->fixtureUrl;
    }

    public function getFieldValue(string $fieldHandle): mixed
    {
        return $this->values[$fieldHandle] ?? null;
    }
}

final class FixtureQuery extends EntryQuery
{
    public static array $entries = [];
    public static array $batches = [];
    public mixed $fixtureSection = null;

    public function section(mixed $value): static
    {
        $this->fixtureSection = $value;
        return $this;
    }

    public function behaviors(): array
    {
        return [];
    }

    public function ids(?\yii\db\Connection $db = null): array
    {
        check($this->fixtureSection === 'popups' && $this->siteId === 1 && $this->status === 'live', 'Selection must retain its section/site/live criteria.');
        return array_keys(self::$entries);
    }

    public function all($db = null): array
    {
        check(is_array($this->id) && count($this->id) <= 50 && $this->limit === 50, 'Entry hydration must be bounded.');
        check($this->fixedOrder === true, 'Preserve shuffled ID order.');
        self::$batches[] = $this->id;
        return array_map(static fn(int $id): Entry => self::$entries[$id], $this->id);
    }
}

final class FixturePopupService extends PopupService
{
    protected function createEntryQuery(): EntryQuery
    {
        return new FixtureQuery(Entry::class);
    }
}

class FixtureApp
{
    public string $charset = 'UTF-8';
    public object $request;

    public function getIsInstalled(): bool
    {
        return false;
    }

    public function getRequest(): object
    {
        return $this->request;
    }

    public function getSecurity(): \yii\base\Security
    {
        return new \yii\base\Security();
    }

    public function getSites(): object
    {
        return new class {
            public function getCurrentSite(): object
            {
                return (object)['id' => 1];
            }
        };
    }
}

Craft::$app = new FixtureApp();
$service = new FixturePopupService();
$settings = new Settings(['sectionHandle' => 'popups', 'showPopupFieldHandle' => 'popupShow']);

foreach (['https://example.com/a?x=1#b', 'HTTP://example.com', '/offers', 'offers/summer', '?offer=1', '#offer', '//example.com/offer', 'mailto:hello@example.com', 'tel:+31201234567'] as $url) {
    check(Url::safeCta($url) === $url, 'Safe URL rejected: ' . $url);
}
foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', "java\nscript:alert(1)", "java\tscript:alert(1)", 'data:text/html,test', 'vbscript:msgbox(1)', 'file:///etc/passwd', '\\javascript:alert(1)', 'https:example.com', "https://example.com/\x00"] as $url) {
    check(Url::safeCta($url) === '', 'Unsafe URL accepted: ' . $url);
}

for ($id = 1; $id <= 123; $id++) {
    $entry = (new ReflectionClass(FixtureEntry::class))->newInstanceWithoutConstructor();
    $entry->id = $id;
    $entry->uid = 'entry-' . $id;
    $entry->values = ['popupShow' => false, 'popupCtaUrl' => 'javascript&#58;alert(1)'];
    FixtureQuery::$entries[$id] = $entry;
}
$entry = FixtureQuery::$entries[1];
check(invoke($service, 'urlFieldValue', $entry, 'popupCtaUrl') === '', 'Entity-encoded script URL must be rejected after normalization.');
$entry->fixtureUrl = 'javascript:alert(1)';
$entry->values['popupCtaUrl'] = $entry;
check(invoke($service, 'urlFieldValue', $entry, 'popupCtaUrl') === '', 'Direct element URLs must be validated.');
$entry->values['popupCtaUrl'] = new class ($entry) {
    public function __construct(private Entry $entry)
    {
    }

    public function one(): Entry
    {
        return $this->entry;
    }
};
check(invoke($service, 'urlFieldValue', $entry, 'popupCtaUrl') === '', 'Related element URLs must be validated.');
$entry->fixtureUrl = '/safe-offer';
check(invoke($service, 'urlFieldValue', $entry, 'popupCtaUrl') === '/safe-offer', 'Safe related element URLs should be preserved.');

$cookieName = invoke($service, 'cookieName', $entry, $settings);
$_COOKIE = [$cookieName => '1'];
foreach ([true, false] as $validation) {
    // Skip Request::init(), which resolves a live site; exercise its real cookie loaders.
    $request = (new ReflectionClass(\craft\web\Request::class))->newInstanceWithoutConstructor();
    $request->enableCookieValidation = $validation;
    $request->cookieValidationKey = 'popup-promoter-regression-test-key';
    Craft::$app->request = $request;
    check(invoke($service, 'hasDismissalCookie', $entry, $settings), 'Browser dismissal cookie must be recognized.');
    check(!invoke($service, 'hasDismissalCookie', FixtureQuery::$entries[2], $settings), 'Dismissal must remain per entry.');
}

check(invoke($service, 'pickEntry', $settings, true) === null, 'Disabled popups must not be selected.');
check(count(FixtureQuery::$batches) === 3, 'All candidates must be checked across bounded batches.');
check(count(array_unique(array_merge(...FixtureQuery::$batches))) === 123, 'Every candidate should be visited exactly once.');
$entry->values['popupShow'] = true;
check(invoke($service, 'pickEntry', $settings, true) === null, 'Dismissed candidate must be skipped.');
check(invoke($service, 'pickEntry', $settings, false) === $entry, 'Preview should ignore dismissal cookies.');
FixtureQuery::$entries = [];
check(invoke($service, 'pickEntry', $settings, true) === null, 'Empty sections should return no popup.');

echo "URL, cookie, eligibility, preview and bounded-selection regressions passed.\n";

check(Yii::getLogger()->messages === [], 'Selection should not swallow unexpected failures.');
require __DIR__ . '/setup.php';
