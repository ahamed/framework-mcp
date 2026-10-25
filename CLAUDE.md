# Project Instructions for Claude

Adapted from the `themeum/framework` CLAUDE.md. The PHP standards in section 3
are enforced by the custom PHPCS sniffs copied into `phpcs/Framework/Sniffs/`.
The sniffs are the authority: if a rule here and a sniff disagree, the sniff
wins and this file should be corrected.

---

## 0. What this repository is

`ahamed/framework-mcp`: MCP server support for WordPress plugins built on
`themeum/framework`. It is a Composer library, published as a standalone
package and installed alongside the framework by plugins such as Kirki
Ecommerce. It sits on top of the canonical WordPress MCP Adapter plugin
(`WP\MCP\...`), which the site installs separately.

Consequences that shape everything below:

- **It has no application of its own.** The public API (`Server`, `Tool`,
  `Resource`, `Prompt`, `Response`, `Request`, `JsonSchema`, the `Mcp` facade)
  is what consuming plugins call, so backwards compatibility matters. Prefer
  additive changes.
- **`themeum/framework` is a peer.** It is required (`^3.2.7`), never bundled,
  never modified from here. Use only its public API. If something genuinely
  needs a framework change, say so: that is a change to the other repository.
- **It is scoped by consumers, together with the framework.** A consuming
  plugin runs php-scoper over this package and the framework with one prefix,
  so `Framework\Route` becomes `Kirki\Ecommerce\Framework\Route` and
  `Ahamed\FrameworkMcp\Tool` becomes `Kirki\Ecommerce\Ahamed\FrameworkMcp\Tool`.
  The package itself is never scoped here. See section 3a.

`example/` is a local WordPress playground plugin, run under Docker, that
exercises the package end to end. It is **not** library code. Don't apply the
library's conventions to it reflexively, and don't ship changes there as if
they were the feature.

---

## 1. Behavioral Guidelines (always apply)

Behavioral guidelines to reduce common LLM coding mistakes.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

### Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:

- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:

- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:

- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: every changed line should trace directly to the user's request.

### Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:

- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

---

## 1a. Planning Workflow

When entering plan mode in this project, always use the **OpenSpec workflow**
instead of writing a freeform plan. Reach for the `openspec-*` / `opsx:*`
skills:

- `opsx:explore` — think through the problem before committing to a change
- `opsx:propose` — generate a full proposal (spec deltas, design, tasks)
- `opsx:apply` — implement tasks from an existing change
- `opsx:sync` — sync delta specs into main specs
- `opsx:archive` — finalize and archive a completed change

Before implementing tasks from an existing change, always ask me to run
`opsx:apply` manually by myself instead of applying automatically.

Artifacts live in `openspec/`, which **is committed** in this repository. The
main specs are in `openspec/specs/` (`mcp-servers`, `mcp-abilities`,
`mcp-json-schema`, `mcp-generators`). The original proposal and design are in
`openspec/changes/archive/2026-10-25-add-mcp-server-support/`.

---

## 2. Verification

The two commands that gate every change:

```bash
composer phpcs && composer test
```

**Always run these through Composer, never the bare binaries.**

- `composer test` adds `--prepend tests/prepend.php`, which defines `ABSPATH`
  before anything loads.
- `composer phpcs` runs `scripts/run-phpcs.php` with `-d auto_prepend_file=`
  and injects `--standard=phpcs.xml.dist`.

| Command | What it does |
|---|---|
| `composer test` | Full unit suite |
| `composer test -- --filter 'SomeTest'` | Narrow to one test class |
| `composer phpcbf` | Auto-fix what PHPCS can fix |
| `make up` / `make example-install` / `make init` | Bring up the Docker WordPress playground |
| `make test` | Run the suite inside the php container |
| `make wp CMD="plugin list"` | WP-CLI inside the container |

Do not use browser-preview or dev-server tooling to verify anything here.
Static checks plus the unit suite are the verification. Anything that needs a
real WordPress request goes through the Docker playground and `curl` against
`https://localhost:20300/wp-json/mcp-example/v1/mcp/mcp-example`. If a change
genuinely needs a human to look at it, say so and let me check.

The unit suite fires WordPress hooks in a fixed order. Real WordPress does not.
For example, core fires `wp_abilities_api_init` lazily, the first time
something reads the registry. Over REST, `mcp_adapter_init` fires before that.
When a change touches the lifecycle, check it in the playground too.

---

## 3. PHP Coding Standards

Target PHP **7.4** (`composer.json` `config.platform.php`). PSR-12 plus the
custom `Framework` standard, enforced by `phpcs.xml.dist`.

**PHPCS scans `src/` only**, with `src/Console/stubs/` excluded. `tests/`,
`example/`, `bin/` and `scoper.example.php` are unchecked: match their
neighbours.

### File header — order is enforced

`Framework.Files.FileDeclarationOrder` and `Framework.Commenting.FileDocblock`
require exactly this shape in any file declaring a class, interface, or trait:

```php
<?php
/**
 * One or two sentences on what this file is and why it exists.
 * Say what is non-obvious — the design constraint, the reason it is separate
 * from its neighbour — not what the class name already says.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\McpManager;
use Framework\Supports\Facades\Log;
use WP_Error;

use function Framework\app;
```

- The file docblock comes **before** `namespace`, and must have a description,
  `@package FrameworkMcp`, and `@since`.
- `@subpackage` is **required** when the namespace is more than two segments
  deep. `Ahamed\FrameworkMcp` needs none; `Ahamed\FrameworkMcp\Registrar` needs
  `@subpackage Registrar`.
- Tag values are aligned in the file docblock.
- `defined('ABSPATH') || exit;` comes immediately after the namespace.
- `use function` imports go in their own group after the class imports.

### Classes and files

- Class names: **PascalCase**. One class per file, filename matches, PSR-4
  (`Ahamed\FrameworkMcp\` → `src/`, `Ahamed\FrameworkMcp\Tests\` → `tests/`).
- **Never `final`**: consumers subclass freely.

### Methods, properties, variables

- Methods and variables: **snake_case**, enforced by the `SnakeCaseMethod`
  and `SnakeCaseVariable` sniffs. The only exceptions are methods a native PHP
  interface mandates, and PHPUnit's own `setUp`/`tearDown`.
- Class constants: **SCREAMING_SNAKE_CASE**.
- **`private` is forbidden**, enforced by `Framework.Visibility.NoPrivate`.
  Use `protected` for internals and `public` for the API surface.
- Names must express intent; no `$a`, `$b`, `$temp`.

### `static::` vs `self::`

Use `static::` everywhere PHP 7.4 allows it. Use `self::` only where
`static::` is forbidden: property defaults, default parameter values and
constant expressions.

### Docblocks — required on every method and property

A description is mandatory. `@param` is mandatory when the method takes
parameters. `@return` is always mandatory, including `@return void` and on
constructors. Tags appear in the order **`@template`, `@param`, `@return`,
`@throws`, `@since`**, with a blank line between tag groups.

- `{@inheritDoc}` short-circuits the sniff; use it for genuine overrides.
- `@since`: `1.0.0` for everything in the first release. Ask before giving a
  new public API a later version.
- Omit `@throws` unless the method really throws.

### Syntax

- Short array syntax `[]`, never `array()`.
- No `declare(strict_types=1)`.
- **No Yoda conditions.**
- Prefer early returns.
- Comments are for *why*, not *what*. Inline `//` comments stay rare.

### Type hints

Use them deliberately, matching the surrounding class. PHP 7.4 syntax only:
no union types, no constructor property promotion, no enums, no `match`, no
named arguments, no nullsafe operator, no `str_contains`.

### Facades

When `McpManager` gains a public method, add the matching `@method static`
line to `src/Facades/Mcp.php`. The facade docblock is the IDE contract.

### WordPress

- Escape, sanitize, and internationalize per WordPress conventions at the
  boundaries.
- Prefer the framework's own seams (`Log`, `Route`, `Validator`, `app()`)
  over raw WordPress globals.

---

## 3a. Scope safety

Every line in `src/` must survive php-scoper with a consumer's prefix. The
README's "Using with php-scoper" section and `scoper.example.php` are the
recipe; `tests/Unit/ScoperRecipeTest.php` guards it.

- **Reference classes through imports or `::class`, never strings.**
  php-scoper rewrites `use Framework\Route;` and `Route::class`, but not
  `'Framework\\Route'` built into a string. No dynamic class-name building.
- **Docblock types are patched only on tag lines** (`@method`, `@param`,
  `@return`, `@var`, `@property`, `@see`, `@throws`), and only fully qualified
  `\Framework\...` or `\Ahamed\FrameworkMcp\...` names.
- **`src/Console/stubs/*.stub` are copied verbatim** by php-scoper, and
  `bin/framework-mcp-prefix-stubs` prefixes them afterwards. Keep stub imports
  as plain `use Ahamed\FrameworkMcp\...;` / `use Framework\...;` lines.
- **Never prefix `WP\MCP\`.** The adapter is a shared plugin. Anything that
  must stay global goes outside the `Framework` and `Ahamed\FrameworkMcp`
  namespaces.
- New files must stay under `src/`, which is what the recipe's finder scans.

When a change touches any of this, scope it for real before calling it done:
run php-scoper over the framework and this package with one prefix, then load
the scoped classes.

---

## 4. Where code goes

| Path | Contents |
|---|---|
| `src/` | The library (`Ahamed\FrameworkMcp\`) |
| `src/Registrar/` | Ability registration, invocation, middleware and server-scoped hooks |
| `src/JsonSchema/` | The schema builder |
| `src/Console/` | `make:mcp-*` generators and their stubs |
| `src/Facades/` | The `Mcp` facade |
| `tests/Unit/` | Tests, mirroring `src/` |
| `tests/Support/` | WordPress and adapter doubles, fixtures, and test doubles copied from the framework |
| `docs/` | User-facing documentation |
| `bin/` | Consumer-facing scripts |
| `example/`, `docker/` | The playground: not library code |
| `openspec/` | Specs and changes |

**Never hand-edit:** `vendor/`, `example/vendor/`.

---

## 5. Tests

PHPUnit 9 with the Yoast polyfills, bootstrapped by `tests/Unit/bootstrap.php`.
There is no WordPress in the test process. `tests/Support/StubsWordPressFunctions.php`
stands in for the WordPress functions, and `tests/Support/AdapterDoubles.php`
stands in for the `WP\MCP\` classes.

- Test classes extend `Ahamed\FrameworkMcp\Tests\Unit\TestCase`, or
  `McpTestCase` for anything that boots the provider. `TestCase` resets the
  container, `Str` macros, route state and the facade cache in `tearDown()`.
- Test methods: `test_snake_case_describing_the_behaviour()`.
- **No docblocks, no ABSPATH guard, no file header** in test files.
- Typed properties and `: void` return types are used freely here.
- Add new WordPress function stubs to `StubsWordPressFunctions.php`, not
  inline in a test.

Write the test that would have caught the bug. A change to `src/` that adds
behaviour and no test is incomplete unless I said otherwise.

---

## 6. Documentation

`docs/mcp.md` is the user guide: a table of contents, numbered `## N. Topic`
sections, quick start first, and a **"Where this differs from Laravel"**
section that is honest about the gaps. The README covers installation,
scoping and the playground. Keep both in the same change as the code.

---

## 7. Commits

Don't commit or push unless I ask. When I do ask, commit the changes with an
inferred commit message that is good enough for a PR title and description,
and push on my behalf.

---

## 8. Grilling behavior

When using the /grill-me skill, ask me questions one by one and use the
graphical interface so that I can select my answer graphically. Always mention
your recommendation while questioning.
