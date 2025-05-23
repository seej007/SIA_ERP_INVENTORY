/**
 * Supply Chain Management System
 * Frontend JavaScript for handling purchase orders, suppliers, and products
 */

// Global variables to store current data
let currentSuppliers = [];
let currentProducts = [];
let currentOrderItems = [];
let orderTotal = 0;

// Store modal instances
let productModal = null;
let supplierModal = null;
let orderDetailsModal = null;

// Document ready function
$(document).ready(function() {
    console.log("Supply Chain Management System Initialized");
    
    // Initialize Bootstrap modals properly
    productModal = new bootstrap.Modal(document.getElementById('addProductModal'), {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    
    supplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'), {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    
    orderDetailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'), {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    
    // Initialize sections visibility
    $('#suppliers-section, #products-section').addClass('hidden');
    $('#orders-section').removeClass('hidden');
    
    // Initialize tab content visibility
    $('#order-history-tab').hide();
    $('#new-order-tab').show();
    
    // Navigation functionality
    $('#orders-link').click(function(e) {
        e.preventDefault();
        $('.nav-item').removeClass('active');
        $(this).parent().addClass('active');
        $('.content-section').addClass('hidden');
        $('#orders-section').removeClass('hidden');
    });
    
    $('#suppliers-link').click(function(e) {
        e.preventDefault();
        $('.nav-item').removeClass('active');
        $(this).parent().addClass('active');
        $('.content-section').addClass('hidden');
        $('#suppliers-section').removeClass('hidden');
        // Load suppliers data
        loadSuppliers();
    });
    
    $('#products-link').click(function(e) {
        e.preventDefault();
        $('.nav-item').removeClass('active');
        $(this).parent().addClass('active');
        $('.content-section').addClass('hidden');
        $('#products-section').removeClass('hidden');
        // Load products data
        loadProducts();
    });
    
    // Toggle sidebar
    $('.sidebar-toggle-btn').click(function() {
        $('body').toggleClass('sidebar-collapsed');
    });
    
    // Tab navigation
    $('.tab-btn').click(function() {
        const tabId = $(this).data('tab');
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').hide();
        $(`#${tabId}-tab`).show();
        
        // Load data if needed
        if (tabId === 'order-history') {
            loadPurchaseOrders();
        }
    });
    
    // Initialize data on page load
    loadSuppliers();
    loadProducts();
    
    // Add product to order
    $('#add-product-btn').click(function() {
        // Populate product dropdown
        populateProductDropdown();
        // Show the modal using stored instance
        productModal.show();
    });
    
    // Add product to order from modal
    $('#addProductToOrder').click(function() {
        const productId = $('#productSelect').val();
        const quantity = parseInt($('#productQuantity').val());
        const price = parseFloat($('#productPrice').val());
        
        if (productId && quantity && price) {
            addProductToOrder(productId, quantity, price);
            // Hide modal using stored instance
            productModal.hide();
        } else {
            alert('Please fill in all fields');
        }
    });
    
    // Remove product from order
    $(document).on('click', '.remove-item-btn', function() {
        const index = $(this).data('index');
        removeProductFromOrder(index);
    });
    
    // Submit purchase order
    $('#purchaseOrderForm').submit(function(e) {
        e.preventDefault();
        submitPurchaseOrder();
    });
    
    // Add new supplier
    $('#saveSupplier').click(function() {
        saveSupplier();
    });
    
    // Refresh buttons
    $('#refreshSuppliersBtn').click(function() {
        loadSuppliers();
    });
    
    $('#refreshProductsBtn').click(function() {
        loadProducts();
    });
    
    $('#refreshOrdersBtn').click(function() {
        loadPurchaseOrders();
    });
    
    // Search functionality
    $('#supplierSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterSuppliers(searchTerm);
    });
    
    $('#productSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterProducts(searchTerm);
    });
    
    // Initialize Product Modal
    const addProductModalForm = new bootstrap.Modal(document.getElementById('addProductModalForm'), {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    
    // Add new product
    $('#saveProduct').click(function() {
        saveProduct(addProductModalForm);
    });
    
    // Load categories for product form
    $('#addProductModalForm').on('show.bs.modal', function() {
        loadCategories();
    });
});

/**
 * Load suppliers from the API
 */
function loadSuppliers() {
    $('#suppliersGrid').html('<div class="loader">Loading suppliers...</div>');
      $.ajax({
        url: 'api/purchase.php',
        type: 'GET',
        data: {
            action: 'getSuppliers'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                currentSuppliers = response.data;
                displaySuppliers(response.data);
                populateSupplierDropdown(response.data);
            } else {
                $('#suppliersGrid').html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#suppliersGrid').html('<div class="alert alert-danger">Error loading suppliers: ' + error + '</div>');
        }
    });
}

/**
 * Display suppliers in the grid
 * 
 * @param {Array} suppliers Array of supplier objects
 */
function displaySuppliers(suppliers) {
    if (suppliers.length === 0) {
        $('#suppliersGrid').html('<div class="text-center">No suppliers found</div>');
        return;
    }
    
    let html = '';
    suppliers.forEach(supplier => {
        html += `
            <div class="supplier-card">
                <div class="card-img">
                    <i class="fas fa-building"></i>
                </div>
                <div class="card-body">
                    <h4 class="card-title">${supplier.name}</h4>
                    <p class="card-text">${supplier.email || ''}</p>
                    <p class="card-text">${supplier.phone || ''}</p>
                    <p class="card-text">${supplier.address || ''}</p>
                </div>
            </div>
        `;
    });
    
    $('#suppliersGrid').html(html);
}

/**
 * Populate supplier dropdown for purchase order creation
 * 
 * @param {Array} suppliers Array of supplier objects
 */
function populateSupplierDropdown(suppliers) {
    let options = '<option value="">-- Select Supplier --</option>';
    
    suppliers.forEach(supplier => {
        options += `<option value="${supplier.id}">${supplier.name}</option>`;
    });
    
    $('#supplier').html(options);
}

/**
 * Load products from the API
 */
function loadProducts() {
    $('#productsGrid').html('<div class="loader">Loading products...</div>');
      
    $.ajax({
        url: 'api/purchase.php',
        type: 'GET',
        data: {
            action: 'getProducts'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                currentProducts = response.data;
                displayProducts(response.data);
            } else {
                $('#productsGrid').html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
                console.error('API Error:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response Text:', xhr.responseText);
            
            let errorMsg = 'Failed to connect to the server';
            if (xhr.responseText) {
                try {
                    // Try to parse as JSON first
                    const errorObj = JSON.parse(xhr.responseText);
                    if (errorObj.message) {
                        errorMsg = errorObj.message;
                    }
                } catch (e) {
                    // If not JSON, use the first 100 chars of response
                    if (xhr.responseText.includes('Fatal error')) {
                        errorMsg = 'PHP Fatal Error - Check server logs';
                    } else {
                        errorMsg = 'Server error: ' + xhr.responseText.substring(0, 100);
                        if (xhr.responseText.length > 100) errorMsg += '...';
                    }
                }
            }
            
            $('#productsGrid').html('<div class="alert alert-danger">Error loading products: ' + errorMsg + '</div>');
        }
    });
}

/**
 * Display products in the grid
 * 
 * @param {Array} products Array of product objects
 */
function displayProducts(products) {
    if (products.length === 0) {
        $('#productsGrid').html('<div class="text-center">No products found</div>');
        return;
    }
    
    let html = '';
    products.forEach(product => {
        html += `
            <div class="product-card">
                <div class="card-img">
                    <i class="fas fa-box"></i>
                </div>
                <div class="card-body">
                    <h4 class="card-title">${product.name}</h4>
                    <p class="card-text">${product.description || ''}</p>
                    <p class="card-text">Price: $${product.list_price || '0.00'}</p>
                    <p class="card-text">On Hand: ${product.qty_available || '0'}</p>
                </div>
            </div>
        `;
    });
    
    $('#productsGrid').html(html);
}

/**
 * Populate product dropdown for adding to purchase order
 */
function populateProductDropdown() {
    let options = '<option value="">-- Select Product --</option>';
    
    currentProducts.forEach(product => {
        options += `<option value="${product.id}" data-price="${product.list_price || 0}">${product.name}</option>`;
    });
    
    $('#productSelect').html(options);
    
    // Set price when product is selected
    $('#productSelect').change(function() {
        const price = $(this).find(':selected').data('price');
        $('#productPrice').val(price);
    });
}

/**
 * Add product to current purchase order
 * 
 * @param {number} productId Product ID
 * @param {number} quantity Quantity to order
 * @param {number} price Unit price
 */
function addProductToOrder(productId, quantity, price) {
    // Find product
    const product = currentProducts.find(p => p.id == productId);
    
    if (!product) {
        alert('Product not found');
        return;
    }
    
    // Calculate subtotal
    const subtotal = quantity * price;
    
    // Add to order items array
    currentOrderItems.push({
        product_id: productId,
        name: product.name,
        quantity: quantity,
        price_unit: price,
        subtotal: subtotal
    });
    
    // Recalculate order total
    updateOrderTotal();
    
    // Update UI
    updateOrderItemsTable();
}

/**
 * Remove product from current purchase order
 * 
 * @param {number} index Index of the item to remove
 */
function removeProductFromOrder(index) {
    // Remove from array
    currentOrderItems.splice(index, 1);
    
    // Recalculate order total
    updateOrderTotal();
    
    // Update UI
    updateOrderItemsTable();
}

/**
 * Update order total calculation
 */
function updateOrderTotal() {
    orderTotal = currentOrderItems.reduce((total, item) => total + item.subtotal, 0);
    $('#orderTotal').text('$' + orderTotal.toFixed(2));
}

/**
 * Update the order items table UI
 */
function updateOrderItemsTable() {
    if (currentOrderItems.length === 0) {
        $('#orderItemsBody').html('<tr class="empty-row"><td colspan="5" class="text-center">No items added yet</td></tr>');
        return;
    }
    
    let html = '';
    currentOrderItems.forEach((item, index) => {
        html += `
            <tr>
                <td>${item.name}</td>
                <td>${item.quantity}</td>
                <td>$${item.price_unit.toFixed(2)}</td>
                <td>$${item.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-item-btn" data-index="${index}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    $('#orderItemsBody').html(html);
}

/**
 * Submit purchase order to API
 */
function submitPurchaseOrder() {
    // Validate form
    const supplierId = $('#supplier').val();
    const orderDate = $('#orderDate').val();
    
    if (!supplierId) {
        alert('Please select a supplier');
        return;
    }
    
    if (!orderDate) {
        alert('Please select an order date');
        return;
    }
    
    if (currentOrderItems.length === 0) {
        alert('Please add at least one product to the order');
        return;
    }
      // Prepare data
    const orderData = {
        partner_id: supplierId,
        date_order: orderDate,
        order_line: currentOrderItems.map(item => ({
            product_id: item.product_id,
            product_qty: item.quantity,
            price_unit: item.price_unit
        }))
    };
    
    // Submit to API
    console.log('Submitting invoice data:', orderData);
    
    // Show loading message
    const submitBtn = $('#submitPurchaseOrder');
    const originalBtnText = submitBtn.text();
    submitBtn.prop('disabled', true).text('Creating Invoice...');
    
    $.ajax({
        url: 'api/purchase.php',
        type: 'POST',
        data: {
            action: 'createPurchaseOrder',
            orderData: JSON.stringify(orderData)
        },
        dataType: 'json',
        success: function(response) {
            submitBtn.prop('disabled', false).text(originalBtnText);
              if (response.success) {
                let message = 'Customer invoice created successfully!';
                if (response.data.mock) {
                    message += ' (Note: This is a simulated invoice as the Odoo server does not have invoice models)';
                }
                
                alert(message);
                
                // Reset form
                $('#purchaseOrderForm')[0].reset();
                currentOrderItems = [];
                updateOrderItemsTable();
                updateOrderTotal();
                
                // Switch to history tab
                $('.tab-btn[data-tab="order-history"]').click();
                
                // Reload order history
                loadPurchaseOrders();
            } else {
                alert('Error: ' + response.message);
                console.error('API Error:', response);
            }
        },
        error: function(xhr, status, error) {
            submitBtn.prop('disabled', false).text(originalBtnText);
            
            console.error('AJAX Error Status:', status);
            console.error('AJAX Error:', error);
            console.error('AJAX Response Text:', xhr.responseText);
            
            let errorMsg = error;
            try {
                // Try to parse error response as JSON
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse && errorResponse.message) {
                    errorMsg = errorResponse.message;
                }
            } catch (e) {
                // If parsing fails, use the raw response text
                if (xhr.responseText) {
                    errorMsg = xhr.responseText.substring(0, 200) + (xhr.responseText.length > 200 ? '...' : '');
                }
            }
            
            alert('Error creating purchase order: ' + errorMsg + '\n\nPlease check the browser console for more details.');
        }
    });
}

/**
 * Load purchase orders from API
 */
function loadPurchaseOrders() {
    $('#ordersList').html('<div class="loader">Loading purchase orders...</div>');
    
    $.ajax({
        url: 'api/purchase.php',
        type: 'GET',
        data: {
            action: 'getPurchaseOrders'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayPurchaseOrders(response.data);
            } else {
                $('#ordersList').html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#ordersList').html('<div class="alert alert-danger">Error loading purchase orders: ' + error + '</div>');
        }
    });
}

/**
 * Display customer invoices
 * 
 * @param {Array} orders Array of invoice objects
 */
function displayPurchaseOrders(orders) {
    if (orders.length === 0) {
        $('#ordersList').html('<div class="text-center">No invoices found</div>');
        return;
    }
    
    // Check if we're using mock orders
    const usingMockData = orders.some(order => order.mock === true);
    
    let html = '';
    
    // Add a notice if using mock data
    if (usingMockData) {
        html += `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                You are viewing simulated invoice data because the Odoo server doesn't have invoice models.
                Invoices you create will be stored in your browser session.
            </div>
        `;
    }
    
    orders.forEach(order => {
        // Format date
        const orderDate = new Date(order.date_order);
        const formattedDate = orderDate.toLocaleDateString();
        
        // Determine status class
        let statusClass = 'status-draft';
        if (order.state === 'purchase') {
            statusClass = 'status-confirmed';
        } else if (order.state === 'sent') {
            statusClass = 'status-sent';
        } else if (order.state === 'cancel') {
            statusClass = 'status-cancelled';
        }
          html += `
            <div class="order-item">
                <div class="order-header">
                    <div class="order-id">Invoice #${order.name}</div>
                    <div class="order-date">${formattedDate}</div>
                </div>
                <div class="order-body">
                    <div class="order-supplier">
                        <span>Customer: </span>
                        <span class="order-supplier-name">${order.partner_id[1]}</span>
                    </div>
                    <div class="order-total">Total: $${parseFloat(order.amount_total).toFixed(2)}</div>
                </div>
                <div class="order-footer">
                    <span class="order-status ${statusClass}">${order.state.toUpperCase()}</span>
                    <button class="btn btn-sm btn-primary view-order-btn" data-id="${order.id}">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        `;
    });
    
    $('#ordersList').html(html);
    
    // Add event listener for view details button
    $('.view-order-btn').click(function() {
        const orderId = $(this).data('id');
        viewOrderDetails(orderId);
    });
}

/**
 * View purchase order details
 * 
 * @param {number} orderId Purchase order ID
 */
function viewOrderDetails(orderId) {
    $('#orderDetailsContent').html('<div class="loader">Loading order details...</div>');
    
    // Show modal using stored instance
    orderDetailsModal.show();
    
    // Load order details
    $.ajax({
        url: 'api/purchase.php',
        type: 'GET',
        data: {
            action: 'getPurchaseOrderDetails',
            orderId: orderId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayOrderDetails(response.data);
            } else {
                $('#orderDetailsContent').html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#orderDetailsContent').html('<div class="alert alert-danger">Error loading order details: ' + error + '</div>');
        }
    });
}

/**
 * Display invoice details
 * 
 * @param {Object} order Invoice object
 */
function displayOrderDetails(order) {
    // Format date
    const orderDate = new Date(order.date_order);
    const formattedDate = orderDate.toLocaleDateString();
    
    let html = `
        <div class="order-details">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Invoice Number:</strong> ${order.name}
                </div>
                <div class="col-md-6">
                    <strong>Date:</strong> ${formattedDate}
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Customer:</strong> ${order.partner_id[1]}
                </div>
                <div class="col-md-6">
                    <strong>Status:</strong> ${order.state.toUpperCase()}
                </div>
            </div>
            
            <h5 class="mt-4">Invoice Items</h5>
            <table class="table">
                <thead>
                    <tr>                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th>Tax</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    order.order_line.forEach(line => {        const product = line.product_id[1];
        const quantity = line.product_qty;
        const price = line.price_unit;
        const subtotal = quantity * price;

        // Tax display
        const taxNames = line.tax_ids ? line.tax_ids.map(tax => tax[1]).join(', ') : line.invoice_line_tax_ids ? line.invoice_line_tax_ids.map(tax => tax[1]).join(', ') : 'No tax';
        
        html += `
            <tr>
                <td>${product}</td>
                <td>${quantity}</td>
                <td>$${price.toFixed(2)}</td>
                <td>$${subtotal.toFixed(2)}</td>
                <td>${taxNames}</td>
            </tr>
        `;
    });
      // Calculate totals
    const subtotal = order.order_line.reduce((total, line) => total + (line.product_qty * line.price_unit), 0);
    const total = parseFloat(order.amount_total);
    const tax = total - subtotal;

    html += `
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td>$${subtotal.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Tax:</strong></td>
                        <td>$${tax.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td>$${total.toFixed(2)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
    
    $('#orderDetailsContent').html(html);
}

/**
 * Save new customer
 */
function saveSupplier() {
    // Get form data
    const name = $('#supplierName').val();
    const email = $('#supplierEmail').val();
    const phone = $('#supplierPhone').val();
    const address = $('#supplierAddress').val();
    
    if (!name || !email) {
        alert('Please enter customer name and email');
        return;
    }
    
    // Prepare data
    const customerData = {
        name: name,
        email: email,
        phone: phone,
        street: address,
        customer: true  // Set as customer instead of supplier
    };
    
    // Submit to API
    $.ajax({
        url: 'api/purchase.php',
        type: 'POST',
        data: {
            action: 'createSupplier',// Keep the action name for compatibility
            supplierData: JSON.stringify(customerData)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Customer added successfully!');
                // Reset form
                $('#addSupplierForm')[0].reset();
                // Hide modal
                supplierModal.hide();
                // Reload suppliers
                loadSuppliers();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error adding customer: ' + error);
        }
    });
}

/**
 * Save new product
 * 
 * @param {bootstrap.Modal} modal Modal instance to hide after success
 */
function saveProduct(modal) {
    // Get form data and remove any spaces
    const name = $('#productName').val().trim();
    const categoryId = $('#productCategory').val() || 1; // Default to category 1 if not selected
    
    // Make sure we handle the numeric fields properly
    let priceVal = $('#productPrice').val();
    let costVal = $('#productCost').val();
    
    // Default to 0 if empty
    if (priceVal === '') priceVal = '0';
    if (costVal === '') costVal = '0';
    
    // Debug what's being submitted
    console.log('Form values:', {
        name: name,
        categoryId: categoryId,
        priceVal: priceVal,
        costVal: costVal,
        priceType: typeof priceVal,
        costType: typeof costVal
    });
    
    // Try to convert to numbers, handling potential formatting issues
    // Replace comma with period for international number formats
    priceVal = priceVal.toString().replace(',', '.');
    costVal = costVal.toString().replace(',', '.');
    
    const price = parseFloat(priceVal);
    const cost = parseFloat(costVal);
    
    // Log the parsed values
    console.log('Parsed values:', {
        price: price,
        cost: cost,
        isNaNPrice: isNaN(price),
        isNaNCost: isNaN(cost)
    });
    
    const description = $('#productDescription').val().trim();
    const initialStock = parseInt($('#productQuantity').val()) || 0;
    const trackInventory = $('#trackInventory').is(':checked');
    
    // Validate form fields
    let errors = [];
    
    if (!name) {
        errors.push("Product name is required");
    }
    
    // Check if price is a valid number greater than or equal to 0
    if (isNaN(price)) {
        errors.push("Please enter a valid sale price");
    }
    
    // Check if cost is a valid number greater than or equal to 0
    if (isNaN(cost)) {
        errors.push("Please enter a valid cost price");
    }
    
    // Show errors if any
    if (errors.length > 0) {
        alert(errors.join("\n"));
        return;
    }
    
    // Prepare data
    const productData = {
        name: name,
        category_id: categoryId,
        price: price,
        cost: cost,
        description: description,
        initial_stock: initialStock,
        track_inventory: trackInventory
    };
    
    console.log('Submitting product data:', productData);
    
    // Show loading
    $('#saveProduct').prop('disabled', true).text('Saving...');
    
    // Submit to API
    $.ajax({
        url: 'api/products.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(productData),
        dataType: 'json',
        success: function(response) {
            $('#saveProduct').prop('disabled', false).text('Save Product');
            
            if (response.success) {
                alert('Product created successfully!');
                
                // Reset form
                $('#addProductForm')[0].reset();
                
                // Hide modal
                modal.hide();
                
                // Reload products
                loadProducts();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            $('#saveProduct').prop('disabled', false).text('Save Product');
            
            console.error('Error creating product:', error);
            console.error('Status:', status);
            console.error('Response Text:', xhr.responseText);
            
            let errorMsg = 'Failed to create product. Please try again.';
            
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse && errorResponse.message) {
                    errorMsg = errorResponse.message;
                }
            } catch (e) {
                // If not JSON, use raw text
                if (xhr.responseText) {
                    errorMsg = xhr.responseText.substring(0, 200);
                    if (xhr.responseText.length > 200) errorMsg += '...';
                }
            }
            
            alert('Error creating product: ' + errorMsg);
        }
    });
}

/**
 * Load product categories from the API
 */
function loadCategories() {
    $.ajax({
        url: 'api/categories.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateCategoryDropdown(response.data.categories);
            } else {
                // If can't load categories, create a default option
                $('#productCategory').html('<option value="1">All Products</option>');
                console.error('Error loading categories: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            // If API fails, create a default option
            $('#productCategory').html('<option value="1">All Products</option>');
            console.error('Error loading categories:', error);
            console.error('Response:', xhr.responseText);
        }
    });
}

/**
 * Populate category dropdown for product form
 * 
 * @param {Array} categories Array of category objects
 */
function populateCategoryDropdown(categories) {
    let options = '<option value="">-- Select Category --</option>';
    
    categories.forEach(category => {
        options += `<option value="${category.id}">${category.name}</option>`;
    });
    
    $('#productCategory').html(options);
}

/**
 * Filter suppliers by search term
 * 
 * @param {string} term Search term
 */
function filterSuppliers(term) {
    if (!term) {
        displaySuppliers(currentSuppliers);
        return;
    }
    
    const filtered = currentSuppliers.filter(supplier => 
        supplier.name.toLowerCase().includes(term) || 
        (supplier.email && supplier.email.toLowerCase().includes(term))
    );
    
    displaySuppliers(filtered);
}

/**
 * Filter products by search term
 * 
 * @param {string} term Search term
 */
function filterProducts(term) {
    if (!term) {
        displayProducts(currentProducts);
        return;
    }
    
    const filtered = currentProducts.filter(product => 
        product.name.toLowerCase().includes(term) || 
        (product.description && product.description.toLowerCase().includes(term))
    );
    
    displayProducts(filtered);
}
