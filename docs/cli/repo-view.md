# unolia repo view

Show one repository

```
USAGE
  unolia repo view [<repository>] [flags]

ARGUMENTS
  repository  Repository id or full name

FLAGS
      --repo <value>  Repository id or full name

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
  # The repository of this directory
  $ unolia repo view
  # Another one
  $ unolia repo view acme/marketing

LEARN MORE
  https://unolia.com/docs/cli/repo-view
```
