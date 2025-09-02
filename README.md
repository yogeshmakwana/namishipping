# \# Namicargo Shipping Module for Magento 2

# 

# Provide all details of your extension’s features and functionality. Include any updates.

# 

# Transform your Adobe Commerce store with Namicargo's revolutionary same-city shipping integration, enabling ultra-fast deliveries across 9 Latin American countries. This powerful extension for Magento seamlessly connects your store to Namicargo's extensive logistics network, allowing you to offer guaranteed 90-minute express delivery or cost-effective standard shipping options. By integrating directly with your existing Adobe Commerce workflow, this extension for Magento enhances your checkout process with real-time shipping calculations, automated multi-warehouse coordination, and comprehensive order tracking capabilities.

# 

# This extension for Magento modifies your store's checkout interface to display dynamic shipping options and delivery time estimates, while adding a new admin configuration section for complete control over shipping rules, warehouse management, and delivery zones. Merchants can customize delivery windows, preparation times, and shipping rates to match their business needs, all through an intuitive interface under Stores > Configuration > Sales > Delivery Methods >  Namicargo Shipping Module.

# 

# ---

# 

# \## 🔧 Features

# 

# \### Seamless Checkout Integration

# \- Adds new shipping options directly to your checkout page: "Nami one-shipment Nami Cargo | Deliveries: 1 | Distance: XX | ETA: XX"

# \- Displays real-time rate calculations based on delivery speed, distance, and warehouse proximity

# \- Shows estimated delivery times and allows customers to select preferred delivery windows

# \- Maintains your existing checkout flow while adding powerful delivery options

# \### Advanced Multi-Warehouse Management

# \- Automatically routes orders to the nearest warehouse in the customer's city

# \- Synchronizes inventory across multiple warehouses in real-time

# \- Optimizes delivery routes for cost-efficiency and speed

# \- Supports unlimited warehouses per city

# \### Real-Time Tracking \& Updates

# \- Provides customers with instant tracking links via email

# \- Displays delivery status in customer account dashboard

# \- Offers detailed delivery monitoring for admin users

# \- Sends automated delivery notifications

# \### Comprehensive Admin Controls

# \- Configure delivery zones and shipping rules

# \- Set warehouse-specific preparation times

# \- Define custom delivery windows and blackout dates

# \- Monitor all shipments through an integrated dashboard

# \- Access detailed shipping analytics and reports

# \### Latin America Coverage

# \- Supports same-city delivery across 9 countries: 

# &nbsp;  Mexico

# &nbsp;  Brazil

# &nbsp;  Colombia

# &nbsp;  Argentina

# &nbsp;  Chile

# &nbsp;  Peru

# &nbsp;  Ecuador

# &nbsp;  Costa Rica

# &nbsp;  Uruguay

# 

# ---

# 

# \## 🧩 Compatibility

# 

# | Magento Version      | PHP Version     |

# |----------------------|-----------------|

# | 2.4.6 or higher      | 7.4 or higher   |

# 

# ---

# 

# \## 🚀 Installation

# 

# \*\*Via Composer (recommended):\*\*

# 

# 1\. Add the repository (if using VCS):

# &nbsp;  ```bash

# &nbsp;  composer require namicargo/module-shipping:1.0.0

# &nbsp;  ```

# 

# 3\. Enable the module:

# ```bash

# &nbsp;  bin/magento module:enable Namicargo\_Shipping

# &nbsp;  bin/magento setup:upgrade

# &nbsp;  bin/magento setup:di:compile

# &nbsp;  bin/magento setup:static-content:deploy

# &nbsp;  bin/magento cache:flush

# ````

