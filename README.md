# InvoiceEase - Inventory Management System

## Project: SIA (Systems Integration and Architecture)

### Team Members
- Christian Javidil A. Sy
- Jerome Tom D. Deiparine
- Jude Ralph Nabalan
- Rhobert C. Patena

## Overview

InvoiceEase is a web-based inventory management system that integrates with Odoo ERP through its RPC API. The application provides a user-friendly interface for viewing inventory data and managing products, while leveraging the powerful backend capabilities of Odoo.

![image](https://github.com/user-attachments/assets/273ca677-7a19-4f81-8897-5b3ab8ee01d4)
![image](https://github.com/user-attachments/assets/49794fd7-0550-4070-bbc1-e6bbb1e88bbc)
![image](https://github.com/user-attachments/assets/bc544538-8f91-4396-b08e-384ea0815313)
![image](https://github.com/user-attachments/assets/b90fd9f0-e822-4618-91b8-6674e3df3187)




## Features

- **Product Management**: View and add products to the inventory
- **Customer Management**: Add and manage customers
- **Invoice Creation**: Create new invoices with multiple product lines
- **Real-time API Integration**: Direct integration with Odoo ERP
- **Error Handling**: Comprehensive error logging and display
- **Responsive Design**: Works on desktop and mobile devices

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5, jQuery
- **Backend**: PHP
- **API**: Odoo XML-RPC API
- **Server**: Apache (XAMPP)

## Installation

1. **Prerequisites**:
   - XAMPP (or equivalent with PHP 7.4+)
   - Odoo account with API access
   - Web browser (Chrome, Firefox, Edge recommended)

2. **Setup**:
   ```bash
   # Clone the repository to your XAMPP htdocs directory
   git clone https://github.com/yourusername/invoiceease.git

   # Navigate to the project directory
   cd invoiceease

   # Configure the Odoo API credentials in api/config.php
   # (See Configuration section below)
   ```

3. **Configuration**:
   - Edit the `api/config.php` file with your Odoo credentials:
     ```php
     return [
         'odoo' => [
             'url' => 'https://your-instance.odoo.com',
             'db' => 'your-database',
             'username' => 'your-email@example.com',
             'apikey' => 'your-api-key'
         ]
     ];
     ```

4. **Run the Application**:
   - Start your XAMPP Apache server
   - Navigate to `http://localhost/invoiceease/InvoiceEase.html` in your browser

## API Integration

The application integrates with Odoo's RPC API through several endpoints:

### 1. Authentication
- **Endpoint**: `/xmlrpc/2/common`
- **Purpose**: Authenticates user credentials and establishes a session
- **Implementation**: `OdooAPI.php`

### 2. Product Management
- **Endpoint**: `/xmlrpc/2/object` (model: `product.product`)
- **Purpose**: Retrieves and creates product data
- **Implementation**: `products.php`, `products_new.php`

### 3. Category Management
- **Endpoint**: `/xmlrpc/2/object` (model: `product.category`)
- **Purpose**: Retrieves product categories
- **Implementation**: `categories.php`

### 4. Customer Management
- **Endpoint**: `/xmlrpc/2/object` (model: `res.partner`)
- **Purpose**: Manages customer data
- **Implementation**: API calls in `supply.js`

## Project Structure

```
├── InvoiceEase.html     # Main application page
├── api_error.log        # Error log file
├── api/                 # Backend API files
│   ├── api_utils.php    # Utility functions
│   ├── categories.php   # Category API endpoint
│   ├── config.php       # Configuration settings
│   ├── dashboard.php    # Dashboard data
│   ├── OdooAPI.php      # Main API class
│   ├── products.php     # Product retrieval
│   ├── products_new.php # Product creation
│   └── stock.php        # Stock management
└── assets/              # Frontend assets
    ├── script/
    │   └── supply.js    # Main JavaScript file
    └── styles/
        └── supply.css   # Main CSS file
```

## Usage

1. **View Products**:
   - Navigate to the Products tab to view all products in inventory
   - Each product displays its name, price, and stock level

2. **Add a Product**:
   - Click "Add Product" button in the Products section
   - Fill in the required fields: Name, Category, Sale Price, Cost Price
   - Optionally add a description and initial stock quantity
   - Click "Save Product" to create the product in Odoo

3. **Add a Customer**:
   - Navigate to the Customers tab
   - Click "Add Customer" button
   - Fill in the required information
   - Click "Save Customer" to add the customer to the system

4. **Create an Invoice**:
   - Navigate to the Customer Invoices tab
   - Select a customer and enter invoice date
   - Add products to the invoice by clicking "Add Item"
   - Submit the invoice when complete

## Error Handling

The application includes comprehensive error handling:

- **API Connection Errors**: Logged and displayed when connection to Odoo fails
- **Data Validation**: Input validation before sending data to the API
- **User Feedback**: Clear error messages displayed to users
- **Logging**: All errors are logged to `api_error.log` for troubleshooting

## Development

### Adding New Features

1. **Backend Integration**:
   - Add new endpoint files in the `api/` directory
   - Follow the pattern in existing files like `products.php`
   - Use the `OdooAPI` class for Odoo communication

2. **Frontend Development**:
   - Add new UI components to `InvoiceEase.html`
   - Add JavaScript functions in `supply.js`
   - Style with Bootstrap classes and custom CSS in `supply.css`

### Debugging

- Check `api_error.log` for backend errors
- Use browser developer tools for frontend debugging
- Enable PHP error display in development by setting `ini_set('display_errors', 1)` in API files

## Acknowledgments

- Odoo Community for the comprehensive API documentation
- Bootstrap team for the responsive design framework
- All our team members contributing to this development
