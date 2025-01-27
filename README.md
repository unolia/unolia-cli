
# Unolia CLI

![GitHub release (with filter)](https://img.shields.io/github/v/release/unolia/unolia-cli)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/unolia/unolia-cli/php)
![Packagist License (custom server)](https://img.shields.io/packagist/l/unolia/unolia-cli)

Unolia CLI is a command line interface for managing your DNS records across different providers. It uses [Unolia API](https://app.unolia.com/docs/#/) to unify your provider under the same API. Check out [unolia.com](https://unolia.com) for more information.

Currently supported providers:
- [Amazon Route 53](https://aws.amazon.com/route53/)
- [Bunnynet](https://bunny.net/)
- [Cloudflare](https://www.cloudflare.com/)
- [Digitalocean](https://www.digitalocean.com/)
- [Gandi](https://www.gandi.net/)
- [Ionos](https://www.ionos.fr/)
- [OVH](https://www.ovh.com/)
- [Porkbun](https://porkbun.com)
- Namecheap (soon)
- Godaddy

## Installation
Current installation requires you to have PHP and Composer installed on your computer. After that, it's as simple as that:
```bash
composer global require unolia/unolia-cli
```

## Connect your accounts
If it's the first time you are using Unolia, [add your first providers](https://app.unolia.com/providers).

Then you can log in for a 30-day period with this command:
```bash
unolia login
```

If you prefer a longer lifetime, [create a token](https://unolia.test/user/api-tokens) for your user account or one dedicated to your team and use the token as follows:
```bash
unolia login --token={TOKEN}
```

## Usage
List information about the current user
```bash
unolia me 
unolia teams 
```
List all domains
```bash
unolia domain:list
```
List all records for a domain
```bash
unolia domain:records example.com
```
Add, update and remove records
```bash
unolia domain:add example.com mg.example.com MX "10 mxa.eu.mailgun.org"
unolia domain:add example.com mg.example.com MX "10 mxb.eu.mailgun.org"
unolia domain:update {ID} 
unolia domain:remove {ID}
```
Check DNS records
```bash
unolia dig unolia.com TXT
unolia dig unolia.com A
```


## Upgrade
Installed with composer:
```bash
composer global update unolia/unolia-cli
```

## Credits
**unolia-cli** was created by Eser DENIZ.

## License
**unolia-cli** PHP is licensed under the MIT License. See LICENSE for more information.
