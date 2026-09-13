# unolia ci rerun

Re-run a CI run

```
USAGE
  unolia ci rerun [<run>] [flags]

ARGUMENTS
  run  Action id or #run number, the newest by default

FLAGS
      --repo <value>      Repository id or full name
      --all-branches      Look at every branch when picking the newest run
      --failed            Only the failed jobs
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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # Re-run the newest run
  $ unolia ci rerun
  # Only what failed
  $ unolia ci rerun "#1187" --failed
  # Queue it and come back later
  $ unolia ci rerun --no-progress
  # Block in a script
  $ unolia ci rerun --yes --wait

LEARN MORE
  https://unolia.com/docs/cli/ci-rerun
```
