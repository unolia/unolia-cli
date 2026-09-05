# Unolia CLI rules

Read by Claude Code as `CLAUDE.md` and by other agents through the same file.

## What this is

`unolia` is the command line interface for app.unolia.com, built for humans at a terminal and for
agents and scripts alike. Every command has two faces: prompts and tables on a TTY, JSON and exit
codes in a pipe.

The response shapes the CLI relies on are the documented Unolia API (https://app.unolia.com/docs),
mirrored as fixtures under `tests/Fixtures/api/`. When the two disagree, the API is the one that
regressed: fix the contract, not the client. `error.code` values are part of that contract and the
CLI maps them to exit codes.

## Settled decisions

Agreed with the owner on 2026-09-05. Do not reopen them without asking.

| Decision | Answer |
| --- | --- |
| Command grammar | Space grammar like gh (`website deploy`). Colon forms stay as hidden aliases for one major. The canonical name keeps the colon so Symfony's namespaces work. |
| Noun for a deployable thing | `website`. `site` is a silent alias, never printed. |
| Where watch lives | Both `unolia watch <kind> <id>` and `<noun> watch`. One implementation. |
| JSON filtering | `--json [fields]` always works. `--jq` shells out to an installed jq and exits 2 with a hint when missing. `--format` covers table, json, ndjson, csv, yaml. |
| Config layout | Herd's committed `herd.yml`, Unolia's committed `.unolia/config.json`, gitignored `.unolia/local.json`. Never write keys into `herd.yml` that Herd does not document. |
| Exit codes | 0 ok, 1 remote failure, 2 usage, 3 auth, 4 not found, 5 forbidden or plan, 6 timeout, 7 awaiting input, 130 interrupted. No `--exit-status` flag. |
| Confirmations | Anything that changes something remote or the machine asks. A prompt on a TTY, exit 2 in a pipe unless `--yes`. `--dry-run` never mutates. |
| Streaming transport | Long-poll JSON with a cursor on the resource itself (`wait`, `after`). No SSE. |
| Team for personal tokens | The `X-Unolia-Team` header. Paths are identical for team and personal tokens. |
| `compare local` scope | PHP, Laravel and Composer first. Database and Node appear as the API exposes them. |
| Provider ids | Forge server and site ids go into `herd.yml` under `integrations.forge.<domain>`. The CLI never calls Forge, GitHub or Cloudflare directly. |
| Framework | Bare Symfony Console, no Laravel Zero. |

## Non negotiables

**Bare Symfony Console.** No Laravel Zero, no Illuminate container, config or `.env` loading, no
Termwind, no Collision. Allowed libraries: `symfony/console`, `symfony/process`, `symfony/yaml`,
`laravel/prompts`, `saloonphp/saloon` with its pagination plugin, `react/dns`,
`laravel-zero/phar-updater`. Adding a dependency needs the owner's approval.

**Every command has two faces.** On a TTY it may prompt with Laravel Prompts. On a pipe, in CI or
with `--no-input` it never prompts, exits 2 with the missing flag and candidates, and honors
`--json`, `--format`, `--jq`. Prompts go through `Console\Ask`, output through `Console\Out`,
failures are `Console\CliError` with an exit code from `Console\ExitCode`. No `exit()`, no direct
writes to `STDERR`.

**Mutations preview.** Any command that changes something remote or writes a file outside
`~/.config/unolia` accepts `--dry-run` and `--yes`, and shows its plan before asking.

**Never call providers directly.** Forge, GitHub, Cloudflare and the rest are reached only through
the Unolia API. The one exception is Herd and other tools on the developer's own machine.

**Secrets stay out of output and logs.** Tokens are written to `hosts.json` with mode 0600, never
printed, never included in debug output or error messages.

## Conventions

- PHP 8.2 or newer. `declare(strict_types=1)` in every file. Typed properties, return types,
  constructor promotion, `final` on classes that are not extended.
- Command classes live in `src/Command/<Namespace>/<Verb>Command.php` and extend `Command\BaseCommand`.
  Canonical names use colons (`website:deploy`), display names use spaces. Register group metadata in
  `Console\Groups`.
- Services are wired in `Runtime`. Commands get the runtime by constructor and ask it for what they
  need. Tests override entries on the runtime.
- HTTP calls are Saloon requests under `src/Api/Requests`, one class per endpoint, sent through
  `Api\Client` so error mapping happens in one place.
- User facing copy: no semicolons, no em dashes, no emojis. Short sentences. Errors say what went
  wrong and which command fixes it.
- Tests are Pest. Feature tests run the real `Application` through `tests/Support/CliTester` with
  `FakeApi` (Saloon `MockClient`) and a temporary home directory. Every command has a test in both
  faces. Fixtures mirror the documented API responses.

## Before every commit

```bash
composer qa      # pint, phpstan level 6, pest
```

Run `php bin/unolia <command> --help` for anything you touched and read it as a user would.

## Git

Work on a branch, open a pull request, do not merge with failing checks. Never push to `main`
directly. Do not push branches unless the owner asks.
