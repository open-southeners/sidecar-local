Laravel Sidecar Local [![required php version](https://img.shields.io/packagist/php-v/open-southeners/sidecar-local)](https://www.php.net/supported-versions.php) [![codecov](https://codecov.io/gh/open-southeners/sidecar-local/branch/main/graph/badge.svg?token=JKIjarQfLa)](https://codecov.io/gh/open-southeners/sidecar-local) [![Edit on VSCode online](https://img.shields.io/badge/vscode-edit%20online-blue?logo=visualstudiocode)](https://vscode.dev/github/open-southeners/sidecar-local)
===

An extension of Laravel Sidecar project to be able to use it locally with Docker.

## Getting started

```bash
composer require open-southeners/sidecar-local
```

### Setting up

Publish config file with the following command:

```bash
php artisan vendor:publish --provider="OpenSoutheners\\SidecarLocal\\ServiceProvider"
```

**Then don't forget to set your `sidecar.env` config option to `local` and the `sidecar.functions` with the ones you want to test locally to make this work.**

Run the following command to publish `docker-compose.yml` file into `resources/sidecar` folder with `--run` option to run Docker with all services locally:

```bash
php artisan sidecar:local --run
```

To **stop the services** you can just run the same but with `--stop` option:

```bash
php artisan sidecar:local --stop
```

## Partners

[![skore logo](https://github.com/open-southeners/partners/raw/main/logos/skore_logo.png)](https://getskore.com)

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
