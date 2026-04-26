# phpVMS — Agent Instructions

Instructions for AI coding agents (OpenCode, Claude Code, Cursor) working in this repo. Apply these rules to every change. Deviations require a comment explaining why.

## Project Overview

phpVMS is a virtual airline management system. Open-source flight crew + flight tracking + financial reporting platform.

- **Framework:** Laravel 12.48 (PHP 8.3+)
- **Admin panel:** Filament 5 (`filament/filament` ^5.4.1)
- **Modules:** `nwidart/laravel-modules` ^11.1.8 (modular monolith — `modules/` dir)
- **Theming:** `igaster/laravel-theme` (multi-theme support)
- **Testing:** Pest 4 (with `pest-plugin-laravel`, `pest-plugin-livewire`, `pest-plugin-arch`, `pest-plugin-type-coverage`)
- **Code style:** Laravel Pint
- **Static analysis:** Larastan (PHPStan + Laravel) level 5
- **Refactor tool:** Rector w/ `rector-laravel`
- **Repository pattern:** `prettus/l5-repository` ^3.0.1
- **Auth providers:** Discord, VATSIM, IVAO via socialiteproviders
- **Queue:** `arxeiss/sansdaemon` (sansdaemon worker)
- **License:** BSD-3-Clause
- **Size:** ~58k PHP LOC excluding vendor

## Coding Standards

### Laravel Pint

Pint config at `pint.json`:

```json
{
  "preset": "laravel",
  "rules": {
    "binary_operator_spaces": {"operators": {"=>": "align_single_space_minimal"}},
    "new_with_parentheses": true,
    "not_operator_with_successor_space": false,
    "phpdoc_align": {"align": "vertical"},
    "spaces_inside_parentheses": false
  },
  "exclude": ["modules/"]
}
```

```bash
# Format dirty files only (default)
composer pint

# Format everything
vendor/bin/pint

# Dry-run (CI mode)
vendor/bin/pint --test
```

**Note:** `modules/` is excluded — each module has its own conventions. Don't auto-format `modules/*` without checking the module's own style first.

### `.editorconfig`

PHP: 4 spaces. Blade: 2 spaces. JS/YAML: 2 spaces. LF line endings. Trailing newline mandatory.

### Naming + structure conventions

- **Controllers:** `app/Http/Controllers/` — single-action style preferred for new code
- **Models:** `app/Models/` — Eloquent, repository pattern via `prettus/l5-repository`
- **Repositories:** `app/Repositories/` — extend `Prettus\Repository\Eloquent\BaseRepository`. Note: `phpstan.neon` ignores undefined methods on repositories because Eloquent Builder methods aren't documented on the abstract base
- **Services:** `app/Services/` — business logic; injected via Laravel container
- **Filament resources:** `app/Filament/` — admin panel pages; one resource per Eloquent model
- **Modules:** `modules/{ModuleName}/` — Awards, Sample, Vacentral. Each follows nwidart's structure (Config/, Database/, Http/, Models/, Providers/, etc.)
- **Helpers:** `app/helpers.php` — global helper functions, autoloaded via composer

## Static Analysis

### Larastan (PHPStan + Laravel)

```bash
# Run on app/ at level 5 (highest configured)
vendor/bin/phpstan analyse

# Strict mode
vendor/bin/phpstan analyse --level=8
```

- **Config:** `phpstan.neon`
- **Level:** 5
- **Paths:** `app/` only (modules excluded — each module owns its own analysis)
- **Extensions:** `larastan/larastan` + `nesbot/carbon`
- **Ignored errors (3 known cases):**
  - Pivot dynamic properties (`Pivot::$column` access — Eloquent magic)
  - Repository undefined methods (Eloquent Builder methods leak via `__call`)
  - `FilesTrait::files()` morphMany on Aircraft/Airline/Airport/Subfleet (trait-defined polymorphic relation, larastan can't statically resolve)

**IMPORTANT** Run PHPStan after changes. CI enforces level 5.

### Rector

`rector.php` runs Laravel-specific rules + general code quality.

```bash
# Dry-run (preview)
vendor/bin/rector --dry-run

# Apply
vendor/bin/rector
```

- **Config:** `rector.php`
- **Paths:** `app/`, `config/`, `resources/`, `tests/`
- **PHP set:** PHP 8.4
- **Sets:** `LaravelLevelSetList::UP_TO_LARAVEL_110` + `SetList::CODE_QUALITY`
- **Skipped:** `CompactToVariablesRector` (compact() in Blade controllers stays as-is)

**RECOMMENDED upgrade — bump to `withPreparedSets()` API:**

The current config uses the legacy `sets([...])` style. Modern Rector exposes the level-based API which is more granular and dry-run-friendly:

```php
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/resources',
        __DIR__.'/tests',
    ])
    ->withSkip([CompactToVariablesRector::class])
    ->withPhpSets(php84: true)
    ->withComposerBased(laravel: true, phpunit: true)
    ->withTypeCoverageLevel(15)
    ->withDeadCodeLevel(15)
    ->withCodeQualityLevel(15)
    ->withPreparedSets(
        naming: true,
        privatization: true,
        codingStyle: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_110,
    ]);
```

Bump levels in stages — 0→5→10→15. Don't jump straight to 15. Each bump: dry-run, review, commit.

### Laravel IDE Helper

```bash
# Regenerate facade phpdocs + meta (auto-runs on composer update)
php artisan ide-helper:generate
php artisan ide-helper:meta
```

`_ide_helper.php` (1.1M file at root) is regenerated via `barryvdh/laravel-ide-helper`. Don't edit by hand — it's overwritten.

## Code Investigation

phpVMS uses three layered tools depending on the question. Pick the right tool first; falling back to the wrong one wastes tokens.

### Decision matrix

| Need | Tool | Why |
|------|------|-----|
| Eloquent query, relation, or model attribute | **laravel/boost** | Knows Eloquent natively |
| Artisan command listing or signature | **laravel/boost** | Reads `$signature` properties |
| Route inventory (URI → controller@action) | **laravel/boost** | Reads `routes/web.php` + `routes/api.php` + Filament + module routes |
| Migration history / schema | **laravel/boost** | Reads `database/migrations/` + introspects DB |
| Filament resource discovery | **laravel/boost** | Knows Filament `Resource` convention |
| Type-aware refs across files (FQN, `use` statements) | **Serena MCP** | Intelephense LSP |
| Symbol overview, find-by-name, refactor (rename, replace body) | **Serena MCP** | LSP-driven |
| Cross-session memory of architectural decisions | **Serena MCP** | `write_memory` / `read_memory` |
| Plain-text search (regex, fast) | `rg` | Faster than any indexer |
| Structural pattern (e.g. `new $Class($args)`) | `ast-grep` | Tree-sitter AST |
| Bulk refactor across many files | `comby` | Multi-line, namespace-aware |

### laravel/boost (primary Laravel MCP)

`laravel/boost` ^2.0 is already in `composer.json` (require-dev). Official Laravel MCP server (3.4k stars, MIT, https://github.com/laravel/boost). Knows the framework — Eloquent models, Artisan commands, routes, migrations, Filament resources.

**Setup:** boost registers via `Mcp::local('laravel-boost', Boost::class)` in `vendor/laravel/boost/src/BoostServiceProvider.php`. Already configured in `opencode.jsonc`:

```jsonc
{
  "mcp": {
    "laravel-boost": {
      "type": "local",
      "command": ["php", "artisan", "mcp:start", "laravel-boost"],
      "enabled": true
    }
  }
}
```

Inspector for ad-hoc testing: `php artisan mcp:inspector laravel-boost`.

**Boost tools available:**
- `database/connections`, `database/query`, `database/schema` — query DB + introspect schema
- `application-info`, `list-available-env-vars`, `last-error` — runtime app state
- `list-routes` — full route table (web, api, Filament, modules)
- `list-artisan-commands` — every registered Artisan command + signature
- `tinker` — execute PHP in app context (powerful, dangerous; use w/ care)
- `read-log-entries` — tail `storage/logs/`
- `browser-logs` — surface browser console errors (Pail integration)
- `get-config`, `list-available-config-keys` — `config('foo.bar')` reads
- `search-docs` — searches Laravel docs
- `get-absolute-url` — `route()` helper resolution

Resources: `application-info`, `package-guideline`. Prompts: `package-guideline`, `upgrade-livewire-v4`, `laravel-code-simplifier`.

**Use boost for:**
- "List all Filament resources for the Pirep model" → `php artisan filament:list` + boost introspection
- "What routes hit AcarsController@store?" → boost route table
- "Which migrations touch the `aircraft` table?" → boost schema history
- "What relationships does the Subfleet model have?" → boost Eloquent introspection

**Don't use boost for:** non-Eloquent code, bulk file edits, refactor — boost is read-only for Laravel concepts.

### Serena MCP (semantic, LSP-based)

Same Serena setup as other PHP projects. Bundles Intelephense; uses LSP for type-aware refs. Already documented in user's global config (`~/.config/opencode/opencode.jsonc`, may be commented out).

**Setup for phpvms** in `opencode.jsonc`:

```jsonc
{
  "mcp": {
    "serena": {
      "type": "local",
      "command": [
        "uvx", "--from", "git+https://github.com/oraios/serena",
        "serena", "start-mcp-server",
        "--context", "ide-assistant",
        "--project", "/Users/nshahzad/source/phpvms/phpvms"
      ],
      "enabled": true
    }
  }
}
```

**Use Serena for:**
- `find_symbol` — find a class/method by name across the whole codebase
- `find_referencing_symbols` — find every place a symbol is used (FQN-aware)
- `get_symbols_overview` — top-level symbols in a file (file path required, not directory)
- `replace_symbol_body`, `rename_symbol`, `safe_delete_symbol` — refactor
- `write_memory` / `read_memory` — persist findings across sessions

**API quirks** (same as manashop):
- `find_symbol` arg = `name_path_pattern` (NOT `name_path`)
- `find_referencing_symbols` arg = `name_path` (different)
- `get_symbols_overview` rejects directories — file paths only
- Reference response is **nested-dict** by file → by symbol-kind → list. Not flat
- `include_kinds=[5]` filters to Class. Other kinds: 12=Function, 6=Method, 7=Property, 14=Constant
- Files >1MB skipped (Intelephense limit). phpvms unlikely to hit this; check `_ide_helper.php` (1.1M — borderline)

**Cold start:** ~1.3s to ready, ~5ms Intelephense startup, incremental indexing as queries hit files.

**Limitations:**
- No call graph (LSP doesn't build them). For "what triggers X?" → use boost (routes/jobs/listeners/observers) + grep
- No architectural surfaces beyond what boost provides
- Magic methods, dynamic dispatch invisible — typical PHP/Laravel issue

### When to fall back to rg/ast-grep

- One-off text/regex search across 58k LOC: `rg <pattern>` is faster than any indexer (~16-58ms)
- Structural query w/o needing types: `ast-grep --pattern 'new $Class($args)' --lang php`
- Already know the file: `Read` w/ offset/limit is cheaper than firing up an MCP

## Refactoring Tooling

phpVMS has more dev tools available than most Laravel apps. Use them.

### Decision matrix

| Need | Tool | Why |
|------|------|-----|
| Rename class, move namespace, extract method | **phpactor** CLI | Type-aware, respects `use` statements |
| Bump PHP/Laravel version syntax, modernize patterns | **Rector** + `rector-laravel` | Laravel-aware rules |
| Architecture rules (no Models→Controllers, no Services→Filament) | **deptrac** or **phparkitect** | Codify layer boundaries |
| Mutation testing (verify tests catch regressions) | **infection** | Selective, on hotspots |
| Maintainability heatmap | **phpmetrics** | One-shot HTML report |
| Single-file regex sub | `sd` | Faster than `sed -i` |
| Multi-file regex w/ interactive review | `fastmod` | Per-match approve/skip |
| Multi-line PHP w/ namespace handling | `comby` | Already in shell tools |
| Single-language AST pattern | `ast-grep` | Already in shell tools |
| Negative structural conditions ("rewrite EXCEPT inside Test class") | **GritQL** (Beta for PHP) | `where { $x <: not within $TestCase }` syntax unique |
| Codify CI guardrails (block bad patterns in PR) | **GritQL** `grit check` | Patterns + assertions in `.grit/` |
| Cross-file refactor + memory of intent | **Serena MCP** | Already documented |
| Diff review for refactor PRs | **difftastic** (`difft`) | Structural diff |

### Already installed -- underused

#### Rector + rector-laravel (BIG free win)

Already configured but legacy `sets([...])` API. Modern API unlocks granular bump levels — see "Static Analysis → Rector" above for the upgraded config.

Workflow:
```bash
vendor/bin/rector --dry-run | tee /tmp/rector-plan.txt
# Review carefully, then apply
vendor/bin/rector
vendor/bin/phpstan analyse  # must still pass
composer test
```

Bump one level at a time; never jump 0→15 in one shot.

#### Pest 4 architecture tests

`pestphp/pest-plugin-arch` is already installed. Use it for codified architecture rules **inside** the test suite:

```php
// tests/Arch/LayerTest.php
test('Models do not depend on Controllers')
    ->expect('App\Models')
    ->not->toUse('App\Http\Controllers');

test('Controllers do not call Repositories directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Repositories');

test('Filament resources only live under app/Filament')
    ->expect('Filament\Resources\Resource')
    ->toOnlyBeUsedIn('App\Filament');
```

Run via `vendor/bin/pest --filter=Arch`. Add to CI. **No extra install needed** — Pest 4 + arch plugin already in composer.json.

#### Pest 4 type-coverage plugin

`pestphp/pest-plugin-type-coverage` is installed. Run:

```bash
vendor/bin/pest --type-coverage
```

Reports % of typed parameters/returns/properties. Use as a non-failing CI metric to prevent regressions.

#### IDE Helper (already runs on composer update)

`_ide_helper.php` is auto-regenerated. If it goes stale (added new model/relation), run:

```bash
php artisan ide-helper:generate
php artisan ide-helper:meta
php artisan ide-helper:models -W   # write back to model files (optional)
```

Improves Serena's Intelephense-backed refs because the helper provides phpdoc types Eloquent doesn't expose at runtime.

#### Migrations Generator

`kitloong/laravel-migrations-generator` ^7.1.2 is installed. Reverse-engineer migrations from existing DB:

```bash
php artisan migrate:generate
```

Useful when refactoring schema and you want a clean baseline.

### Recommended new installs

#### deptrac (architecture enforcement, YAML)

```bash
composer require --dev deptrac/deptrac
```

Sample `deptrac.yaml` for phpvms's Laravel layered architecture:

```yaml
deptrac:
  paths:
    - ./app
  layers:
    - name: Controller
      collectors:
        - { type: classNameRegex, value: '#^App\\Http\\Controllers\\\\.+#' }
    - name: Service
      collectors:
        - { type: classNameRegex, value: '#^App\\Services\\\\.+#' }
    - name: Repository
      collectors:
        - { type: classNameRegex, value: '#^App\\Repositories\\\\.+#' }
    - name: Model
      collectors:
        - { type: classNameRegex, value: '#^App\\Models\\\\.+#' }
    - name: Filament
      collectors:
        - { type: classNameRegex, value: '#^App\\Filament\\\\.+#' }
  ruleset:
    Controller: [Service, Repository, Model]
    Filament:   [Service, Repository, Model]
    Service:    [Repository, Model]
    Repository: [Model]
    Model:      []
```

Run: `vendor/bin/deptrac analyse`. Add to CI.

**Alternative:** Pest 4 arch tests (above) cover similar ground without the install. Pick one.

#### phparkitect (PHP-based rules)

```bash
composer require --dev phparkitect/phparkitect
```

PHP-as-config. v1.0 released Apr 2026 (892 stars, MIT). More expressive than deptrac YAML or Pest arch tests when rules are composite (e.g. "Filament resource classes must extend `Resource` AND have `protected static $model`").

#### phpmetrics (HTML maintainability heatmap)

```bash
composer require --dev phpmetrics/phpmetrics
vendor/bin/phpmetrics --report-html=/tmp/phpmetrics app/
open /tmp/phpmetrics/index.html
```

Halstead complexity, maintainability index, LCOM (cohesion), coupling. **Best one-shot "what to refactor first" view.** Run quarterly.

#### infection (mutation testing on hotspots)

```bash
composer require --dev infection/infection
vendor/bin/infection --filter=app/Services/PirepService.php --threads=8
```

**Don't run whole repo** — 58k LOC + Pest setup = slow. Use `--filter` on services/controllers/models that handle critical logic (Pirep submission, ACARS telemetry, financial reporting). MSI <60% on a critical path = strengthen tests before refactor.

#### phpactor (CLI class moves, refactor commands)

```bash
composer global require phpactor/phpactor
phpactor class:move app/Old/Path.php app/New/Path.php   # auto-updates use statements
phpactor class:transform app/Foo.php                    # menu-driven refactor
phpactor references:class 'App\\Models\\Pirep'          # type-aware refs (alt to Serena)
```

Better than ast-grep for class moves; Serena's `rename_symbol` is the alternative when already inside an MCP session.

#### difftastic (structural diff)

```bash
brew install difftastic
git config --global diff.external difft
```

Reflowed args, reordered imports, split methods — `git diff` shows everything as changed; `difft` shows only structural changes. Big PR review time saver.

#### GritQL (Beta for PHP, codified CI guardrails)

```bash
curl -fsSL https://docs.grit.io/install | bash
```

Tree-sitter PHP support is **Beta**. Stdlib mostly JS/TS — write your own patterns for PHP/Laravel.

Unique value: `where` clauses with negative structural conditions:

```grit
language php

`Cache::remember($key, $ttl, fn() => $body)` => `Cache::flexible($key, $ttl, fn() => $body)`
where {
    $body <: not contains `Cache::tags($_)`,
    $key <: not within `class $_ extends TestCase { $_ }`
}
```

Use case for phpvms: enforce "no `DB::raw()` outside repository methods", "no `request()->get()` outside controllers", "no `Carbon::now()` — use `now()` helper". Wire into CI via `grit check`.

### Skip these

| Tool | Why skip |
|------|----------|
| Psalm | Vimeo abandoned 2024; larastan covers Laravel-aware analysis better |
| Phan | Mostly inactive |
| php-cs-fixer | Pint is the Laravel standard; pick one |
| codemod (Facebook) | Python tool, abandoned |
| `sed -i` for refactors | No AST awareness; breaks Blade/heredocs |

### Refactor playbook

Sequence for any non-trivial refactor:

1. **Identify candidate** — phpmetrics heatmap or eyeballed hotspot
2. **Verify test coverage** — `vendor/bin/infection --filter=<file>` — MSI must be reasonable before refactor
3. **Make changes** — Rector dry-run / phpactor class:move / Serena `replace_symbol_body`
4. **Static check** — `vendor/bin/phpstan analyse`
5. **Format** — `composer pint`
6. **Test** — `composer test` (full Pest suite)
7. **Architecture check** — Pest arch tests still pass (or update them deliberately)
8. **Diff review** — `GIT_EXTERNAL_DIFF=difft git diff` before commit

Skip steps 2 + 7 for trivial renames.

## Testing

### Pest 4

```bash
# Full suite
composer test
# OR
vendor/bin/pest

# Specific file
vendor/bin/pest tests/Feature/Acars/AcarsTest.php

# Specific test
vendor/bin/pest --filter='it submits a pirep'

# With coverage (slow)
vendor/bin/pest --coverage --min=70

# Architecture tests only
vendor/bin/pest --filter=Arch

# Type coverage report
vendor/bin/pest --type-coverage

# Parallel
vendor/bin/pest --parallel
```

**Plugins active:**
- `pest-plugin-laravel` — Laravel-specific helpers (`actingAs`, `assertDatabaseHas`, etc.)
- `pest-plugin-livewire` — Livewire component tests
- `pest-plugin-arch` — Architecture rule tests (see Refactoring section)
- `pest-plugin-type-coverage` — Static type coverage report

### Test style

Pest's expectation API. Mockery for mocks (`mockery/mockery` ^1.5.0):

```php
it('submits a pirep and updates user hours', function () {
    $user = User::factory()->create(['flight_time' => 0]);
    $pirep = Pirep::factory()->make(['user_id' => $user->id, 'flight_time' => 60]);

    actingAs($user)
        ->post('/api/pireps', $pirep->toArray())
        ->assertOk();

    expect($user->fresh()->flight_time)->toBe(60);
});
```

### Mandatory pre-PR verification

Before opening PR, all four commands must pass:

```bash
composer pint --test            # 1. Format check
vendor/bin/phpstan analyse      # 2. Static analysis (level 5, app/)
composer test                   # 3. Full Pest suite
vendor/bin/rector --dry-run     # 4. Rector clean (no pending rewrites)
```

Anything failing = PR not ready.

## Project Structure

```
app/
├── Bootstrap/         # App boot config
├── Console/           # Artisan commands
├── Contracts/         # Interfaces
├── Cron/              # Scheduled task definitions
├── Events/            # Eloquent + custom events
├── Exceptions/        # Custom exception types
├── Filament/          # Admin panel resources
├── Http/
│   ├── Controllers/   # HTTP controllers
│   └── Middleware/    # Request middleware
├── Listeners/         # Event listeners
├── Models/            # Eloquent models
├── Notifications/     # Mail/database notifications
├── Policies/          # Authorization policies
├── Providers/         # Service providers
├── Queries/           # Custom query builders
├── Repositories/      # Eloquent repositories (l5-repository)
├── Services/          # Business logic services
├── Support/           # Helper classes
├── Utils/             # Generic utilities
├── Widgets/           # Filament dashboard widgets
└── helpers.php        # Global helpers (autoloaded)

modules/
├── Awards/            # Awards module (nwidart)
├── Sample/            # Reference module
└── Vacentral/         # vaCentral integration module

config/                # Laravel config
database/
├── migrations/        # Schema migrations
├── seeders/           # Test seed data
└── factories/         # Model factories

resources/
├── views/             # Blade templates (legacy theme)
└── lang/              # Translations

routes/                # Route definitions
storage/               # Logs, cache, uploads
tests/                 # Pest tests
.hooks/                # Custom git hooks (setup-git-hooks.sh)
```

## Key Conventions

### Repository pattern (`prettus/l5-repository`)

```php
namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;

class PirepRepository extends BaseRepository
{
    public function model(): string
    {
        return Pirep::class;
    }
}
```

PHPStan ignores undefined methods on repositories — Eloquent Builder methods leak via `__call`. Don't add `@method` phpdocs unless you're sure of the signature; let larastan stay lenient here.

### Filament resources

```php
// app/Filament/Resources/PirepResource.php
class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    // ... form, table, pages
}
```

One resource per model. Resources self-discover via `App\Providers\Filament\AdminPanelProvider`. Don't manually register.

### Modules (nwidart/laravel-modules)

Each module is a self-contained mini-app. Module structure follows `Modules/{Name}/{Config,Database,Http,Models,Providers,...}`. Module dependencies declared in `module.json`.

```bash
# List modules
php artisan module:list

# Enable/disable
php artisan module:enable Awards
php artisan module:disable Awards

# Make new module
php artisan module:make NewModule
```

**Don't import from `Modules\Awards\...` into `App\...`** — modules are leaves, not core. If something needs to be shared, move it to `App\Contracts\` or `App\Services\`.

### Queue jobs (sansdaemon)

`arxeiss/sansdaemon` replaces the standard queue worker. Config in `config/queue.php`. Use the standard Laravel queue API:

```php
dispatch(new ProcessPirepJob($pirep))->onQueue('pireps');
```

### Activity logging (`spatie/laravel-activitylog`)

Models that need audit trails use `LogsActivity` trait + `getActivitylogOptions()` method. Don't add audit logs ad-hoc — pick the model + use the trait.

### Permissions (`spatie/laravel-permission`)

Role-based access via `Role` and `Permission` models. Filament Shield (`bezhansalleh/filament-shield`) generates permissions for Filament resources automatically:

```bash
php artisan shield:generate --all
php artisan shield:super-admin --user=1
```

### Eloquent Has Many Deep / Belongs To Through

`staudenmeir/eloquent-has-many-deep` and `belongs-to-through` allow nested relations:

```php
// User → Pireps → Aircraft → Subfleet → Airline
public function airlinesViaPireps(): \Staudenmeir\EloquentHasManyDeep\HasManyDeep
{
    return $this->hasManyDeep(
        Airline::class,
        [Pirep::class, Aircraft::class, Subfleet::class],
        ['user_id', 'id', 'id', 'id'],
        ['id', 'aircraft_id', 'subfleet_id', 'airline_id'],
    );
}
```

Don't reach for raw queries when these can express the chain.

### Attribute events (`jpkleemans/attribute-events`)

Eloquent attribute changes emit events automatically. Define in model:

```php
protected $dispatchesEvents = [
    'status:changed' => PirepStatusChanged::class,
];
```

Listeners receive the event w/ before/after values.

### Multi-provider auth (Discord, VATSIM, IVAO)

Socialite providers configured in `config/services.php`. Routes in `routes/web.php`. Don't add new providers without a corresponding test.

## CRITICAL: Verify, do not speculate

**The default failure mode is confident speculation.** Stating inferences as facts. Paraphrasing training-data memories of library behavior. Reasoning from patterns ("this is probably how Laravel does it") instead of reading the file. **This is forbidden.** Speculation dressed as fact wastes the user's time, misleads debugging, and erodes trust faster than any other failure mode.

This rule is not narrow. It covers every kind of technical claim:

- Codebase facts (what calls what, which class implements which interface, what a method returns)
- Framework / library behavior (Laravel, Filament, Eloquent, PHPUnit, vlucas/phpdotenv, Carbon, anything in `vendor/`)
- Language semantics (PHP, Blade, JS, SQL — version-specific behavior)
- Tool / CLI behavior (Pest flags, Artisan command options, composer, git, gh, jq, rg)
- Config / runtime semantics (load order, override precedence, default values, immutable vs mutable, lazy vs eager)
- Schema / data shape (column types, indexes, foreign keys, JSON structures)
- API / protocol behavior (HTTP status codes a route returns, response shapes, headers)
- Test behavior (what a fixture provides, what a factory defaults to, what RefreshDatabase actually does this version)

If you state any of the above as fact, you must have just verified it from a primary source — code, the live database, a doc fetched in this turn. Not your memory. Not "I'm pretty sure." Not "this is the typical pattern."

### The verification ladder

For any claim about the system, climb this ladder until you have a primary-source answer:

1. **Codebase introspection** — laravel/boost (routes, jobs, listeners, migrations, schema), Serena (`find_symbol`, `find_referencing_symbols`), Artisan (`php artisan route:list`, `php artisan tinker`)
2. **Read the source file** — `app/`, `config/`, `database/`, `routes/`, `tests/`, `modules/` for in-repo code. `vendor/` is fair game and fast — `vendor/laravel/framework/src/Illuminate/...`, `vendor/filament/filament/src/...`, `vendor/vlucas/phpdotenv/src/...`, `vendor/pestphp/pest/src/...`, `vendor/phpunit/phpunit/src/...`. Open the file. Cite `path:line`.
3. **Run the thing** — `php artisan tinker`, `vendor/bin/pest --filter=...`, a one-off `dd()` in a controller, `database/query` via boost, a test fixture `var_dump`. Empirical > deduction.
4. **Fetch upstream docs** — when source is opaque or you need the official semantics: `WebFetch` to laravel.com/docs, filamentphp.com/docs, phpunit.de, github.com/{owner}/{repo}. Cite the URL.
5. **Say "I don't know, let me check"** — if you can't reach steps 1–4 right now, surface the uncertainty *before* stating. Never bluff.

### Red flags — internal monologue you must stop on

If any of these phrases form in your response or your reasoning, halt and verify:

- "I think…" / "I believe…" / "I'm pretty sure…" → STOP. Belief without a cite is speculation.
- "Probably…" / "Typically…" / "Usually…" / "By default…" → STOP. These are pattern-matches against training data. Verify against this codebase / this version / this config.
- "From memory, the syntax/flag/method is…" → STOP. The file is two seconds away. Read it.
- "X library does Y" / "PHPUnit handles Z like…" / "Laravel auto-loads…" → STOP. Need a `vendor/...:line` cite or a fetched doc URL or it doesn't ship.
- "It should…" / "It would…" / "I'd expect…" → STOP. Don't predict; check.
- "Looks like a simple change" → STOP. Check observers, queue listeners, Filament hooks, module providers, middleware, traits, attribute events first.
- "The test probably covers this" → STOP. Run the test. Read its assertions.
- "Based on the pattern in similar Laravel apps…" → STOP. This app is not "similar Laravel apps." Read this app.

### Speculation traps specific to phpvms / Laravel

Even after climbing the ladder, these are the spots where memory most often goes wrong. Always re-verify in this codebase:

- **Service container resolution.** `app(SomeInterface::class)` returns whatever is bound — check `App\Providers\AppServiceProvider::register()` and `App\Providers\*ServiceProvider` first
- **Eloquent observers / model events.** Side effects fire on save/delete that aren't visible in the controller
- **Job queues.** Dispatching a job ≠ running it — the queue worker runs it later, possibly in a different request lifecycle
- **Middleware groups.** Routes inherit middleware from the group declaration in `RouteServiceProvider` or `routes/*.php`
- **Filament hooks.** Filament resources have lifecycle hooks (`mutateFormDataBeforeCreate`, etc.) that aren't obvious from the resource class file
- **Module event broadcasting.** Modules can listen to events from `App\` — check `Modules/{Name}/Providers/EventServiceProvider.php`
- **Trait method overrides.** `FilesTrait::files()` morphMany — relations defined in traits are invisible to static analysis but real at runtime
- **Attribute events** (`jpkleemans/attribute-events`). Setting `$model->status = 'X'` can fire a listener defined in `$dispatchesEvents` — check the model
- **Has-Many-Deep / Belongs-To-Through.** Multi-hop relations can produce non-obvious SQL — verify with `->toRawSql()` not from memory
- **Repository `__call` pass-through.** `prettus/l5-repository` forwards undefined methods to Eloquent Builder. PHPStan ignores this; runtime works. Don't claim a method "doesn't exist" without checking the parent
- **Pest `beforeEach` / `RefreshDatabase` order.** `tests/Pest.php` is authoritative — read it before claiming what runs per test
- **Env var + config loading order.** PHPUnit `<env>` applies before Laravel boots; then `Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables` runs `vlucas/phpdotenv` in immutable mode (`Dotenv::createImmutable`). First writer into `$_ENV`/`$_SERVER` wins; later loads do NOT override. `force="true"` on `<env>`, `<server>` tags, `.env.{APP_ENV}` precedence, shell-exported vars all have specific rules. Verify in `vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/LoadEnvironmentVariables.php` + the PHPUnit XSD, not from memory
- **Filament Shield permissions.** Generated automatically per resource — check `database/seeders/Shield*` and `php artisan shield:generate` output, don't infer
- **`config/*.php` values at runtime.** Test environments override via phpunit.xml `<env>` + `.env.testing` + bootstrap. The on-disk default is *not* what the running app sees. Use `boost get-config` or `dd(config('foo.bar'))` from a test

### Verify, then act

Before writing code or stating a non-trivial fact, you should be able to answer:

1. **Source** — what file:line / boost query / doc URL backs this claim?
2. **Entry point** — for code paths: route / command / event / listener / job
3. **Path** — controller → service → repository → model
4. **Side effects** — observers, events dispatched, queue jobs scheduled, attribute events
5. **Tests covering this path** — file:line refs

If any of these is "from memory" or "I'm pretty sure," stop and verify. If you can't verify right now, say so explicitly in the response: *"I haven't verified X — checking now"* or *"I don't know without reading vendor/foo/bar.php; want me to look?"*

### How to phrase verified vs unverified claims

- **Verified:** "Pest's `RefreshDatabase` rolls back inside a transaction per test — see `vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:42`."
- **Unverified, surfaced honestly:** "I think Pest's `RefreshDatabase` uses transactions, but I haven't checked this version. Want me to read the file?"
- **Forbidden:** "Pest's `RefreshDatabase` uses transactions per test." (no cite, stated as fact)

The middle form is acceptable. The third form is the one to stamp out.

---

*Last updated: 2026-04-26*

