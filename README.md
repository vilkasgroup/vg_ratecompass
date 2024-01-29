# RateCompass integration

[RateCompass](https://ratecompass.eu/) integration for PrestaShop.

## Developing

```
composer dump-autoload --optimize --no-dev --classmap-authoritative
```

## Creating a new release
Remember to:
- Up the version number in the main module file
- Update CHANGELOG

Releases are triggered by tags matching vx.x.x being pushed, for example:
```
git tag v1.0.0
git push --tags
```

## Running tests

Tests require apikey to be defined.

```
RATECOMPASS_APIKEY=asdf composer run-script test
```

You can also define `RATECOMPASS_HOST` if left out it will default to `localhost:8000`

get your apikey from: https://ratecompass.eu

