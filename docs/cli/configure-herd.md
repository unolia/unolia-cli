# unolia configure herd

Write herd.yml from production and run herd init

```
USAGE
  unolia configure herd [flags]

FLAGS
      --print         Print the YAML and do nothing else
      --no-services   Leave the services block out
      --no-yml        Run herd directly and write nothing
      --no-init       Write herd.yml but do not run herd init
      --site <value>  Herd site name, the directory name by default

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
  # Match production
  $ unolia configure herd
  # See the plan first
  $ unolia configure herd --dry-run
  # Only the YAML
  $ unolia configure herd --print

LEARN MORE
  https://unolia.com/docs/cli/configure-herd
```
