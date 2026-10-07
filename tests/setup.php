<?php

declare(strict_types=1);

use arifje\craftpopuppromoter\services\DefaultContentService;
use craft\models\EntryType;
use craft\models\Section;

class FixtureFields
{
    public array $fields = [];

    public function getFieldByHandle(string $handle): ?object
    {
        return $this->fields[$handle] ?? null;
    }

    public function getFieldByUid(string $uid): ?object
    {
        foreach ($this->fields as $field) {
            if ($field->uid === $uid) {
                return $field;
            }
        }
        return null;
    }

    public function saveField(object $field): bool
    {
        if (property_exists($field, 'groupId')) {
            check($field->groupId === 1, 'Craft 4 fields require a group.');
        }
        $field->id = count($this->fields) + 1;
        $field->uid = \craft\helpers\StringHelper::UUID();
        $this->fields[$field->handle] = $field;
        return true;
    }
}

final class FixtureFields4 extends FixtureFields
{
    public array $groups = [];

    public function getAllGroups(): array
    {
        return $this->groups;
    }

    public function saveGroup(object $group): bool
    {
        $group->id = 1;
        $this->groups[] = $group;
        return true;
    }
}

class FixtureSections
{
    public ?Section $section = null;
    public int $typeSaves = 0;
    public bool $failSection = false;

    public function getSectionByHandle(string $handle): ?Section
    {
        return $this->section;
    }

    public function saveSection(Section $section): bool
    {
        if ($this->failSection) {
            $section->addError('name', 'Fixture section save failure');
            return false;
        }
        if (method_exists($this, 'getEntryTypeByHandle')) {
            check(count($section->getEntryTypes()) === 1, 'Craft 5 section requires an entry type before saving.');
        } else {
            // Craft 4 creates the entry type in its section service.
            $section->setEntryTypes([new EntryType(['name' => 'Popups', 'handle' => 'popups'])]);
        }
        $section->id = 1;
        $this->section = $section;
        return true;
    }

    public function saveEntryType(EntryType $type): bool
    {
        $this->typeSaves++;
        $type->id = 1;
        return true;
    }
}

final class FixtureSections5 extends FixtureSections
{
    public ?EntryType $type = null;

    public function getAllSections(): array
    {
        return $this->section ? [$this->section] : [];
    }

    public function getEntryTypeByHandle(string $handle): ?EntryType
    {
        return $this->type;
    }

    public function getEntryType(EntryType $type): EntryType
    {
        return $type;
    }

    public function saveEntryType(EntryType $type): bool
    {
        check($type->getFieldLayout()->isFieldIncluded('title'), 'New Craft 5 entry type requires its native title field.');
        $this->type = $type;
        return parent::saveEntryType($type);
    }
}

final class SetupApp extends FixtureApp
{
    public string $language = 'en';
    public object $fields;
    public object $sections;

    public function getFields(): object
    {
        return $this->fields;
    }

    public function getPlugins(): object
    {
        return new class {
            public function getPluginHandleByClass(string $class): ?string
            {
                return null;
            }
        };
    }

    public function getEntries(): object
    {
        return $this->sections;
    }

    public function getSections(): object
    {
        return $this->sections;
    }

    public function getI18n(): object
    {
        return new class {
            public function translate($category, $message, $params, $language): string
            {
                foreach ($params as $key => $value) {
                    $message = str_replace('{' . $key . '}', (string)$value, $message);
                }
                return $message;
            }
        };
    }

    public function getSites(): object
    {
        return new class {
            public function getAllSites(): array
            {
                return [(object)['id' => 1]];
            }
        };
    }
}

$app = new SetupApp();
$isCraft5 = method_exists(\craft\services\Entries::class, 'getAllSections');
$app->sections = $isCraft5 ? new FixtureSections5() : new FixtureSections();
$app->fields = $isCraft5 ? new FixtureFields() : new FixtureFields4();
Craft::$app = $app;
$setup = new DefaultContentService();
$fields = invoke($setup, 'ensureFields');
check(count($fields) === 7, 'All default fields should be created.');
check(invoke($setup, 'ensureFields') === $fields, 'Retry must reuse existing fields.');
if (!$isCraft5) {
    check(count($app->fields->groups) === 1, 'Retry must reuse the Craft 4 group.');
}

$app->sections->failSection = true;
try {
    invoke($setup, 'ensureSection');
    throw new RuntimeException('Setup should report a section save failure.');
} catch (\yii\base\Exception $exception) {
    check(str_contains($exception->getMessage(), 'Fixture section save failure'), 'Setup should preserve the validation reason.');
}
$app->sections->failSection = false;
$section = invoke($setup, 'ensureSection');
check(invoke($setup, 'ensureSection') === $section, 'Retry must reuse the section.');
if ($isCraft5) {
    check($app->sections->typeSaves === 1, 'A failed section save must not duplicate the Craft 5 entry type on retry.');
}
invoke($setup, 'ensureFieldLayout', $section, $fields);
$saves = $app->sections->typeSaves;
invoke($setup, 'ensureFieldLayout', $section, $fields);
check($app->sections->typeSaves === $saves, 'Retry must not add duplicate layout fields.');

echo 'Setup creation, failure reporting and retry regressions passed for Craft ' . ($isCraft5 ? '5' : '4') . ".\n";
