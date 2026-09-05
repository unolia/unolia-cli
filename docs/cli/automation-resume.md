# unolia automation resume

Answer a run that is waiting for input

```
USAGE
  unolia automation resume <run> [flags]

ARGUMENTS
  run  Run ULID or a prefix of it

FLAGS
      --input <value>     A key=value answer
      --wait              Follow the run after answering
      --interval <value>  Seconds between checks (default 3)
      --timeout <value>   Give up waiting after this long (default 15m)
      --notify            Send a desktop notification at the end

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
  # Answer and continue
  $ unolia automation resume 01J9A2 --input reboot=true --wait
  # Answer from a terminal
  $ unolia automation resume 01J9A2

LEARN MORE
  https://unolia.com/docs/cli/automation-resume
```
