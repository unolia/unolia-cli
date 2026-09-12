# Unolia CLI

![GitHub release (with filter)](https://img.shields.io/github/v/release/unolia/unolia-cli)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/unolia/unolia-cli/php)
![Packagist License (custom server)](https://img.shields.io/packagist/l/unolia/unolia-cli)

Unolia CLI is the command line interface for [app.unolia.com](https://app.unolia.com). It drives your
projects, websites, deployments, CI runs, automations, issues, DNS and providers from one place, and
it is built for two audiences at once. At a terminal it prompts, draws tables and colors. In a pipe,
in CI or in an agent it never prompts, answers JSON and exits with a code that says what happened.

## Install

```bash
composer global require unolia/unolia-cli
```

Without installing anything, through [cpx](https://github.com/laravel/cpx):

```bash
cpx unolia/unolia-cli
```

Or download `unolia.phar` from the [releases](https://github.com/unolia/unolia-cli/releases) and put
it on your `PATH`.

## Log in

If this is your first time on Unolia, [connect your providers](https://app.unolia.com/providers) first.

```bash
unolia login                                  # sign in through the browser with a one time code
unolia login --scopes project:read,env:read   # ask for chosen scopes instead of the defaults
unolia login --with-token < token             # from a script, a token you already have
unolia auth refresh --scopes deployment:write # add a scope to the current token
unolia auth token                             # print the token in use, for curl and friends
```

`unolia login` shows a short code, opens the browser to `https://app.unolia.com/oauth/device`, and waits
for you to approve it there, the way `gh auth login` does. The token is named `user@hostname` in the
dashboard, `--name` picks another. It lasts a year, and `unolia status` warns during the last two weeks.
When a command answers that the token lacks a scope, the error names the exact
`unolia auth refresh --scopes <scope>` to run: it signs you in again with the wider set and revokes the
old token. `--remove-scopes` narrows it. The browser login cannot grant `*`: a token with every ability
is created on the dashboard and stored with `unolia login --token`. `unolia auth login`, `auth logout`
and `auth status` are the same commands as the top level ones.

Tokens live in `~/.config/unolia/hosts.json` with mode 0600 and are never printed, except by
`unolia auth token` when you ask for it. A token from v1 in `~/.unolia/cli/config.json` is migrated on
first run and the old file is left alone.

## The command grammar

Commands read as a noun and a verb, the way `gh` does. The colon spelling from v1 keeps working.

```bash
unolia status                       # who you are, which team, what this directory maps to
unolia auth refresh --scopes x      # sign in again with one more scope
unolia init                         # link this directory to a project and website
unolia deploy --wait                # deploy the linked website and follow it
unolia issue list --fixable         # what is broken and what can be fixed
unolia domain records acme.com      # the DNS records of a zone
```

Run `unolia` for the full tree, `unolia <namespace>` for one group, and `unolia <command> --help` for
one command. The generated reference lives in [docs/cli](docs/cli).

## Context

`unolia init` writes `.unolia/config.json`, which is committed:

```json
{
  "team": "acme",
  "project": 12,
  "website": 118,
  "environments": { "production": 118, "staging": 121 }
}
```

Context is resolved once per invocation, most specific first: the `--team`, `--project` and
`--website` flags, then `UNOLIA_TEAM`, `UNOLIA_PROJECT` and `UNOLIA_WEBSITE`, then
`.unolia/config.json` searched from the current directory up to the git root, then the git remote
matched by the API, then the default team from `~/.config/unolia/config.json`, then a prompt on a
terminal. `unolia status` prints which source answered.

`.unolia/local.json` is gitignored and holds only what a bare `unolia watch` needs.

## Environment variables

| Variable | Meaning |
| --- | --- |
| `UNOLIA_TOKEN` | Personal or team token. Wins over `hosts.json`. `UNOLIA_API_TOKEN` still works with a warning. |
| `UNOLIA_HOST` | Host, `app.unolia.com` by default. Always https. A `.test` or `localhost` host skips the certificate check. |
| `UNOLIA_INSECURE` | Talk plain http to the host. Only for a server that has no TLS at all. |
| `UNOLIA_TEAM`, `UNOLIA_PROJECT`, `UNOLIA_WEBSITE` | Context without a config file. |
| `UNOLIA_FORMAT` | `table`, `json`, `ndjson`, `csv` or `yaml`. |
| `UNOLIA_DEBUG` | Print one line per request on stderr. Never a token. |
| `NO_COLOR`, `CI` | Turn colors off and force the pipe face. |

## Exit codes

| Code | Name | When |
| --- | --- | --- |
| 0 | ok | Success, a `--dry-run` that printed a plan, a watch that ended well. |
| 1 | remote failure | The remote thing failed, or `compare local` found a mismatch. |
| 2 | usage | Bad arguments, missing input without a terminal, a refused confirmation, a 422. |
| 3 | auth | No token, or a token the API rejected. |
| 4 | not found | Nothing matched. |
| 5 | forbidden | Not allowed, or your plan does not include it. |
| 6 | timeout | `--timeout` elapsed. The remote thing is still running. |
| 7 | awaiting input | An automation run is waiting for an answer. Resume it, do not retry. |
| 130 | interrupted | Ctrl+C. The remote thing keeps going. |

## For agents and scripts

```
- Detects a pipe. Never prompts. Missing input exits 2 and lists candidates.
- Add --json for structured output, --format ndjson for progress streams, --jq to filter.
- Add --dry-run to any mutating command to see the plan without changing anything.
- Add --yes to skip confirmations. Add --wait to block until the remote work finishes.
- Context: .unolia/config.json in the repo, or --team/--project/--website, or UNOLIA_* env vars.
- Auth: UNOLIA_TOKEN env var, or unolia login --with-token < token.txt. A person runs unolia login, which opens the browser.
- Scopes: a 403 with insufficient_scope names the scope. unolia auth refresh --scopes <scope> adds it. unolia auth token prints the token in use.
- Errors with --json are one JSON object on stderr: {"error":{"code","message","hint","exit_code"}}.
```

`unolia help agents` prints the same block. `unolia api` reaches any endpoint the way `gh api` does:

```bash
unolia api v1/websites --jq '.data[].domain'
unolia api v1/websites/118/deployments -X POST -F dry_run=true
```

## Connect your AI agents

Unolia exposes a remote MCP server, so an AI agent can manage your infrastructure with you. Put it into
the config of the agents on your machine:

```bash
unolia mcp setup                                   # pick the agents and the scope at the terminal
unolia mcp setup --global --agent claude,cursor    # every project, no prompt
unolia mcp setup --local --agent vscode --yes      # this directory only, from a script or an agent
unolia mcp setup --print                           # the JSON snippet for any other client
```

Supported agents: Claude Code, Cursor, VS Code (Copilot), Codex, Gemini CLI, Junie (JetBrains), Kiro,
OpenCode and Amp. No token is stored: the first connection opens your browser to sign in. `--dry-run`
shows which files would be written. `--url` or `UNOLIA_MCP_URL` point the connector at another host.

## Shell completion

```bash
eval "$(unolia completion zsh)"     # or bash, fish
```

## Upgrade

```bash
unolia upgrade            # the phar updates itself
unolia upgrade --check    # exits 1 when a newer version exists
```

Installed with Composer: `composer global update unolia/unolia-cli`. With cpx there is nothing to do,
it always fetches the latest.

## Contributing

```bash
composer qa    # pint, phpstan level 6, pest
```

The phar is built with [Box](https://github.com/box-project/box), downloaded from its release rather
than required, so it never touches `composer.lock`:

```bash
curl -sSL -o box.phar https://github.com/box-project/box/releases/download/4.7.0/box.phar
php -d phar.readonly=0 box.phar compile
```

`docs/cli` is generated from the same metadata the help renderer uses. Run `php bin/unolia
docs:generate` after changing a command, and CI fails when the two drift apart.

## Credits

**unolia-cli** was created by Eser DENIZ.

## License

**unolia-cli** is licensed under the MIT License. See LICENSE for more information.
