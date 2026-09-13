# unolia automation resume

Answer a run that is waiting for input

```
USAGE
  unolia automation resume <run> [flags]

ARGUMENTS
  run  Run ULID or its short id, the last six characters

FLAGS
      --input <value>     A field=value answer. A choice by value or label, several separated by commas
      --wait              Block until it finishes, also in a pipe
      --no-progress       Return as soon as it is started instead of following it
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
  # Answer from a terminal
  $ unolia automation resume PC0XCA
  # Answer yes or no
  $ unolia automation resume PC0XCA --input reboot=yes
  # Pick several
  $ unolia automation resume PC0XCA --input selected_server_ids=web-01,db-01 --wait

LEARN MORE
  https://unolia.com/docs/cli/automation-resume
```
