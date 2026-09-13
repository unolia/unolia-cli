# unolia issue fix

Apply the fix an issue carries

```
USAGE
  unolia issue fix <issue> [flags]

ARGUMENTS
  issue  Issue id, or the six characters unolia issue list prints

FLAGS
      --input <value>  A field=value answer, for a fix that asks first. A choice by value or label
      --wait           Recheck the issue and wait for the new state, also in a pipe
      --no-progress    Apply the fix and return without rechecking

INHERITED FLAGS
  -t, --team <value>     Team slug or id
  -p, --project <value>  Project id or name
  -w, --website <value>  Website id or domain
      --json [<value>]   JSON output, optionally a comma list of fields
      --format <value>   table, json, ndjson, csv or yaml
      --jq <value>       Filter the JSON output through jq
  -y, --yes              Skip confirmations
      --no-input         Never prompt, fail instead
      --dry-run          Show the plan without changing anything
      --paginate         Follow every page
      --limit <value>    Page size, up to 100 (default 30)
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # See what it would change
  $ unolia issue fix 8d0e1f --dry-run
  # Fix it and watch the recheck
  $ unolia issue fix 8d0e1f
  # Answer what the fix asks, in a pipe
  $ unolia issue fix 8d0e2a --input logo_url=https://acme.dev/logo.svg --yes
  # Block in a script
  $ unolia issue fix 8d0e1f --yes --wait

LEARN MORE
  https://unolia.com/docs/cli/issue-fix
```
