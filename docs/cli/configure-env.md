# unolia configure env

Add the production environment keys to .env.example

```
USAGE
  unolia configure env [flags]

FLAGS
      --from <value>    Which environment to read, production by default
      --output <value>  File to write, .env.example by default

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
  # Sync the example file
  $ unolia configure env
  # See what is missing
  $ unolia configure env --dry-run

LEARN MORE
  https://unolia.com/docs/cli/configure-env
```
