
# Unolia CLI
Unolia CLI is a command line interface for Unolia API.

Check out [unolia.com](https://unolia.com) for more information.

## Basic usage
```bash
unolia login 

unolia me 
unolia teams 

unolia dig unolia.com TXT
unolia dig unolia.com A

unolia domain:list

unolia domain:records unolia.com

unolia domain:add unolia.com mx mg.unolia.com "10 mxa.eu.mailgun.org"
unolia domain:add unolia.com mx mg.unolia.com "10 mxb.eu.mailgun.org"

unolia domain:update {ID} 
unolia domain:remove {ID}

unolia domain:watch {ID}

unolia logout
```
