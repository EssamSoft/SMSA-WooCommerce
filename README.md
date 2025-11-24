# SMSA Express Shipping for WooCommerce

A WordPress/WooCommerce plugin that integrates SMSA Express shipping services with your WooCommerce store.

## Features

- **Weight-based Shipping Rates** - Configure shipping costs based on package weight
- **Multi-zone Support** - Set up unlimited shipping zones by country
- **Shipment Management** - Create, track, and cancel shipments from the WooCommerce admin
- **PDF Labels** - Generate shipping labels and Air Waybills (AWB)
- **COD Support** - Handle Cash-on-Delivery and prepaid orders
- **Bilingual** - Arabic and English language support
- **Tracking** - Real-time shipment tracking via SMSA API

## Requirements

- WordPress 4.0+
- WooCommerce 3.0+
- PHP 5.3+
- SMSA Express API credentials (passkey)

## Installation

1. Download or clone this repository
2. Upload the `SMSA-WooCommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel
4. Navigate to **WooCommerce > Settings > SMSA Settings** to configure

## Configuration

### API Settings

Go to **WooCommerce > Settings > SMSA Settings** and enter:

- **Passkey** - Your SMSA Express API passkey
- **Store Name** - Your business name
- **Store Address** - Shipping origin address
- **Phone Number** - Contact number
- **City** - Origin city
- **Country** - Origin country
- **Postal Code** - Origin postal code

### Shipping Zones

Go to **WooCommerce > Settings > Shipping > SMSA Express** to configure:

- Add shipping zones for different countries
- Set weight-based rates for each zone
- Configure shipping conditions

## Usage

### Creating Shipments

1. Open an order in WooCommerce
2. Use the SMSA Express meta box to create a shipment
3. The system will generate an AWB number and PDF label

### Tracking Shipments

Shipment status can be tracked using the AWB number through the SMSA tracking functionality.

### Canceling Shipments

Shipments can be canceled from the order admin page with a reason for cancellation.

## File Structure

```
SMSA-WooCommerce/
├── woocommerce-samsa-express-shipping.php    # Main plugin file
├── woocommerce-samsa-express-shipment.php    # Shipment operations
├── wc-samsa-settings-tab.php                 # Admin settings
├── samsa_track.php                           # Tracking functionality
├── samsa_express_shipping_model.php          # Database model
├── woocommerce-samsa-express-plugin-functions.php  # API utilities
└── assets/
    └── css/
        └── custom.css                        # Plugin styles
```

## API Integration

This plugin communicates with the SMSA Express SOAP API for:

- Creating shipments (`addShip`)
- Canceling shipments (`cancelShipment`)
- Tracking shipments (`getTracking`)
- Generating PDF labels (`getPDF`)

## Version

Current version: **2.0.8**

## Author

Krishna Mishra - [JEM Products](http://www.jem-products.com)

## License

This plugin is licensed for use with WooCommerce stores.
