
# Unolia CLI

![GitHub release (with filter)](https://img.shields.io/github/v/release/unolia/unolia-cli)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/unolia/unolia-cli/php)
![Packagist License (custom server)](https://img.shields.io/packagist/l/unolia/unolia-cli)

Unolia CLI is a command line interface for Unolia API.

Check out [unolia.com](https://unolia.com) for more information.

## Installation
```bash
composer global require unolia/unolia-cli
```

## Configuration
```bash
unolia login
```
or
```bash
unolia login --token={TOKEN}
```

## Basic usage
```bash
# List information about the current user
unolia me 
unolia teams 

# List all domains
unolia domain:list

# List all records for a domain
unolia domain:records unolia.com

# Add, update and remove records
unolia domain:add unolia.com mx mg.unolia.com "10 mxa.eu.mailgun.org"
unolia domain:add unolia.com mx mg.unolia.com "10 mxb.eu.mailgun.org"
unolia domain:update {ID} 
unolia domain:remove {ID}

# Check DNS records
unolia dig unolia.com TXT
unolia dig unolia.com A
```
