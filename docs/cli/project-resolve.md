# unolia project resolve

Show what this git remote maps to

```
USAGE
  unolia project resolve [flags]

FLAGS
      --remote <value>  A remote URL, the origin of this directory by default

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
  # What would init pick
  $ unolia project resolve
  # For another remote
  $ unolia project resolve --remote git@github.com:acme/marketing.git

LEARN MORE
  https://unolia.com/docs/cli/project-resolve
```
