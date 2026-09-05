# unolia issue fix

Apply the fix an issue carries

```
USAGE
  unolia issue fix <issue> [flags]

ARGUMENTS
  issue  Issue id or a prefix of it

FLAGS
      --wait  Recheck the issue and wait for the new state

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

EXAMPLES
  # See what it would change
  $ unolia issue fix 01J9P7 --dry-run
  # Fix it and recheck
  $ unolia issue fix 01J9P7 --yes --wait

LEARN MORE
  https://unolia.com/docs/cli/issue-fix
```
