# PHPds

A barebones implementation of an atproto PDS in PHP and Slim Framework 4.

This is a work-in-progress project done for fun! Don't expect it to be production-ready or fully compliant with atproto specs!

## Features

The following XRPC endpoints are implemented:

- `com.atproto.admin.getInviteCodes`
- `com.atproto.identity.resolveHandle`
- `com.atproto.server.createInviteCode`
- `com.atproto.server.createSession`
- `com.atproto.server.describeServer`
- `com.atproto.sync.getLatestCommit`
- `com.atproto.sync.getRepoStatus`
- `com.atproto.sync.listRepos`

## Installation

To serve the application, clone the repository and run `composer install` in the application directory.

Then:

* Point your virtual host document root to `phpds`'s `public/` directory.
* Ensure `logs/` is web writable.

### Docker

You can also run the application with `docker`:

```bash
docker build -t phpds .
docker run -p 8080:8080 phpds
```

## Development

To run the application in development, you can run these commands

```bash
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:

```bash
docker-compose up -d
```

After that, open `http://localhost:8080` in your browser.

Run this command in the application directory to run the test suite

```bash
composer test
```

To run PHPCS manually:

```bash
composer phpcs
```

To auto-fix what PHPCBF can fix:

```bash
composer phpcbf
```

To run PHPCS automatically in VS Code, install the recommended PHPCS extension from `.vscode/extensions.json` and use the workspace settings in `.vscode/settings.json`.

To run PHPCS before every commit, enable the tracked Git hook with:

```bash
git config core.hooksPath .githooks
```

## Repo mirror

This repository is automatically synced one-way from [GitHub (aitorres/phpds)](https://github.com/aitorres/phpds) to [tangled.sh (andresitorresm.com/phpds)](https://tangled.sh/andresitorresm.com/phpds).

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
