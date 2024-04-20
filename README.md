# generator-phpstan-rules
PHPStan rules bringing added Generator inspections, primarily targeting [SOF3/await-generator](https://github.com/SOF3/await-generator) projects.
This extension introduces the following checks:
- Require generators be consumed (by a `foreach`, `yield from $g`, `...$g`, a function that accepts a `Generator` parameter, etc.) (finds unused generators).

### Installation
This library is compatible with PHPStan's extension installer. Install it by running:
```sh
composer require --dev phpstan/extension-installer && \
composer require --dev muqsit/generator-phpstan-rules
```