# unolia completion

Dump the shell completion script

```
The <info>completion</> command dumps the shell completion script required
to use shell autocompletion (currently, bash, fish, zsh completion are supported).

<comment>Static installation
-------------------</>

Dump the script to a global completion file and restart your shell:

    <info>unolia completion zsh | sudo tee $fpath[1]/_unolia</>

Or dump the script to a local file and source it:

    <info>unolia completion zsh > completion.sh</>

    <comment># source the file whenever you use the project</>
    <info>source completion.sh</>

    <comment># or add this line at the end of your "~/.zshrc" file:</>
    <info>source /path/to/completion.sh</>

<comment>Dynamic installation
--------------------</>

Add this to the end of your shell configuration file (e.g. <info>"~/.zshrc"</>):

    <info>eval "$(unolia completion zsh)"</>

USAGE
  unolia completion [<shell>] [flags]

ARGUMENTS
  shell  The shell type (e.g. "bash"), the value of the "$SHELL" env var will be used if this is not given

FLAGS
      --debug  Tail the completion debug log

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
```
