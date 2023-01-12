# Magento 2: qbo/module-paypal-commerce-platform payment module (MX)
Magento 2 PayPal Commerce Platform (México)

# Installation via composer (recommend)

From the root folder of your project:
```
composer require qbo/module-paypal-commerce-platform
php bin/magento setup:upgrade
```
If something goes wrong with dependencies, and youre OK to ignore it,  add the following line to your composer.json under "require" section:
```
"qbo/module-paypal-commerce-platform": "1.0"
```
Then update your dependencies
```
composer update [--ignore-platform-reqs]
```

# Installation manual

If you don't want to install via composer, you can use this way. 

- Download [the latest version here](https://github.com/qbo-tech/magento2-paypal-commerce-platform/archive/master.zip) 
- Extract `master.zip` file to `app/code/PayPal/CommercePlatform` ; You should create a folder path `app/code/PayPal/CommercePlatform` if not exist.
- Go to Magento root folder and run upgrade command line to install `PayPal_CommercePlatform`:

```
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

# Configuration

- Select "Mexico" for Merchant Location under Payment Methods Configuration
- Get your REST APP Credentiasl from https://developer.paypal.com
- Enter your PayPal API credentials under PayPal Checkout Mexico Module configuration.
- Set Sandbox/Live Mode
- Save and clean the cache.

# Upgrading

From the root folder of your project:
```
composer update [--ignore-platform-reqs]
php bin/magento setup:upgrade
rm -rf var/generation var/di var/view_preprocessed pub/static
php bin/magento setup:static-content:deploy
```
# Debugging

- The module has a configuration "Debug Mode" which logs everything going out to PAYPAL APIs, error responses and details
