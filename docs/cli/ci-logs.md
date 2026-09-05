# unolia ci logs

Print the log of a CI run

```
USAGE
  unolia ci logs [<run>] [flags]

ARGUMENTS
  run  Action id or #run number, the newest by default

FLAGS
      --repo <value>  Repository id or full name
      --all-branches  Look at every branch when picking the newest run
      --job <value>   Only this job, by name or id
      --failed-steps  Only the jobs that failed
  -f, --follow        Keep printing until the run ends
      --raw           Keep the ANSI codes

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
  # The whole log
  $ unolia ci logs
  # One job
  $ unolia ci logs --job tests
  # What broke
  $ unolia ci logs --failed-steps

LEARN MORE
  https://unolia.com/docs/cli/ci-logs
```
