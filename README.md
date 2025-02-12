# Magento 2: qbo/module-paypal-commerce-platform payment module (MX)
Magento 2 PayPal Commerce Platform (México)

# Installation via composer (recommend)

1. Setup your composer/github credentials locally.
2. From the root folder of your project run:
```
composer require qbo/module-paypal-commerce-platform --ignore-platform-reqs
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

# Configuration

- Module configurations us found at: Stores -> Configuration -> Sales -> Payment Methods -> PayPal Checkout Mexico
- Select "Mexico" for Merchant Location under Payment Methods Configuration
- Get your REST APP Credentiasl from https://developer.paypal.com
- Enter your PayPal API credentials under PayPal Checkout Mexico Module configuration.
- Set Sandbox/Live Mode
- Save and clean the cache.

# Upgrading to specific version

From the root folder of your project:
```
composer require qbo/module-paypal-commerce-platform:$version --ignore-platform-reqs
e.g: composer require qbo/module-paypal-commerce-platform:v1.6.1 --ignore-platform-reqs

php bin/magento setup:upgrade
php bin/magento setup:di:compile
rm -rf var/generation var/di var/view_preprocessed pub/static
php bin/magento setup:static-content:deploy
```
# Debugging

- The module has a configuration "Debug Mode" which logs everything going out to PAYPAL APIs, error responses and details
