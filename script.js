document.addEventListener("DOMContentLoaded", function () {
    // Check if user is logged in and sync cart if they are
    fetch("/api/check_auth.php")
        .then(res => res.json())
        .then(data => {
            if (data.authenticated) {
                // If user is logged in, sync cart with server
                syncCartFromServerAndMerge();
            } else {
                // If not logged in, just use local cart
                updateCartUI();
            }
        })
        .catch(err => {
            console.error("Error checking authentication:", err);
            // Use local cart as fallback
            updateCartUI();
        });

    // Add event listener to all "Add to Cart" buttons
    document.querySelectorAll(".add-to-cart-btn").forEach(button => {
        button.addEventListener("click", function () {
            const productId = this.getAttribute("data-id");
            const productName = this.getAttribute("data-name");
            const productPrice = parseFloat(this.getAttribute("data-price"));
            const productImage = this.getAttribute("data-image");
            
            // Get quantity from input field if it exists
            const quantityInput = document.getElementById("quantity");
            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

            if (!productId || !productName || !productPrice || !productImage) {
                console.error("Missing product data:", { productId, productName, productPrice, productImage });
                alert("Error: Product data is incomplete");
                return;
            }

            addToCart(productId, productName, productPrice, productImage, quantity);
        });
    });

    // Logout form listener
    const logoutForm = document.getElementById('logout-form');
    if (logoutForm) {
       // Using the onclick="return handleLogoutClick();" approach now
    }

    // Checkout Page Specific Initializations
    const checkoutItemsContainer = document.getElementById('checkout-items');
    if (checkoutItemsContainer) {
        // Initial attachment of listeners for checkout items
        attachCheckoutItemListeners();
        // Initial UI update for checkout summary
        updateCheckoutUI();
    }

    // Handle same address checkbox logic (copied from checkout.php)
    const sameAddressCheckbox = document.getElementById('same-address');
    const shippingAddressContainer = document.getElementById('shipping-address-container');
    const billingAddressField = document.getElementById('billing-address');
    const shippingAddressField = document.getElementById('shipping-address');
    
    if (sameAddressCheckbox && shippingAddressContainer) {
        sameAddressCheckbox.addEventListener('change', function() {
            shippingAddressContainer.style.display = this.checked ? 'none' : 'block';
            if (this.checked && billingAddressField && shippingAddressField) {
                shippingAddressField.value = billingAddressField.value;
            }
        });
        
        if (billingAddressField) {
            billingAddressField.addEventListener('input', function() {
                if (sameAddressCheckbox.checked && shippingAddressField) {
                    shippingAddressField.value = this.value;
                }
            });
        }
        // Trigger change event on load to set initial state
        sameAddressCheckbox.dispatchEvent(new Event('change'));
    }

    // Card input formatting (copied from checkout.php)
    const cardNumberInput = document.getElementById('card-number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            e.target.value = formattedValue.trim();
        });
    }
    
    const expiryInput = document.getElementById('expiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            } else if (value.length === 2 && e.inputType !== 'deleteContentBackward') {
                 // Auto-add slash after MM, unless deleting
                 // This part might need refinement for better UX
                 // value += '/'; 
            }
            e.target.value = value;
        });
    }
    
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });
    }

    // Add listener for the main checkout form submission
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        // Attach confirmCheckout to the form's submit event
        checkoutForm.addEventListener('submit', confirmCheckout);
    }
});

// Function to add items to the cart
function addToCart(productId, productName, productPrice, productImage, quantity) {
    // First add to local storage
    let localCart = JSON.parse(localStorage.getItem("cart")) || [];
    
    // Check if item exists in cart (convert both to numbers for comparison)
    const existingItemIndex = localCart.findIndex(item => Number(item.id) === Number(productId));
    
    if (existingItemIndex !== -1) {
        // Update existing item quantity
        localCart[existingItemIndex].quantity += Number(quantity);
    } else {
        // Add new item with all details
        localCart.push({
            id: Number(productId),
            name: productName,
            price: Number(productPrice),
            image_url: productImage,
            quantity: Number(quantity)
        });
    }
    
    localStorage.setItem("cart", JSON.stringify(localCart));
    updateCartUI(); // Update UI immediately for better UX

    // Then check if user is logged in
    fetch("/api/check_auth.php")
        .then(res => res.json())
        .then(data => {
            if (data.authenticated) {
                // If logged in, sync with backend
                fetch("/api/cart.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `product_id=${productId}&quantity=${quantity}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== "success") {
                        console.warn("Backend sync failed:", data.message);
                    }
                })
                .catch(err => {
                    console.error("Error syncing with backend:", err);
                });
            } else {
                // If not logged in, automatically open the cart to show the item was added
                document.body.classList.add("cart-open");
            }
        })
        .catch(err => {
            console.error("Error checking auth status:", err);
        });
}

// Function to update the cart UI with optimistic updates
function updateCartUI() {
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    
    // Update cart count badge
    const cartCount = document.getElementById("cart-count");
    const cartCountHeader = document.getElementById("cart-count-header");
    const cartItems = document.getElementById("cart-items");
    const cartSubtotal = document.getElementById("cart-subtotal");
    const checkoutBtn = document.getElementById("checkout-btn");
    
    // Toggle checkout button visibility based on cart contents
    if (checkoutBtn) {
        checkoutBtn.style.display = cart.length === 0 ? 'none' : 'flex';
    }
    
    // Get total quantity and update count badges
    let totalQuantity = cart.reduce((total, item) => total + Number(item.quantity), 0);
    if (cartCount) cartCount.textContent = totalQuantity;
    if (cartCountHeader) cartCountHeader.textContent = totalQuantity;
    
    // Update cart content
    if (cartItems) {
        if (cart.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart-message text-center my-4">
                    <p class="mb-3">Your cart is empty</p>
                    <a href="allProducts.php" class="btn btn-outline-dark">Start Shopping</a>
                </div>`;
            if (cartSubtotal) cartSubtotal.textContent = "$0.00";
            return;
        }
        
        let total = 0;
        let html = '';
        
        cart.forEach(item => {
            const itemTotal = Number(item.price) * Number(item.quantity);
            total += itemTotal;
            
            html += `
                <div class="cart-item" data-product-id="${item.id}">
                    <img src="${item.image_url}" alt="${item.name}">
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.name}</div>
                        <div class="cart-item-quantity">
                            <button class="quantity-btn minus-btn" 
                                   data-product-id="${item.id}"
                                   aria-label="Decrease quantity of ${item.name}">-</button>
                            <label for="cart-quantity-${item.id}" class="visually-hidden">Quantity for ${item.name}</label>
                            <input type="number" 
                                   id="cart-quantity-${item.id}"
                                   class="quantity-input" 
                                   value="${item.quantity}" 
                                   min="1" 
                                   max="10"
                                   data-product-id="${item.id}"
                                   aria-label="Quantity for ${item.name}">
                            <button class="quantity-btn plus-btn" 
                                   data-product-id="${item.id}"
                                   aria-label="Increase quantity of ${item.name}">+</button>
                        </div>
                        <span class="stock-message text-danger small d-block" style="min-height: 1.2em;"></span>
                    </div>
                    <div class="cart-item-actions">
                        <div class="cart-item-price">$${itemTotal.toFixed(2)}</div>
                        <button class="remove-item" 
                               data-product-id="${item.id}" 
                               onclick="removeFromCart(${item.id})"
                               aria-label="Remove ${item.name} from cart">
                            <i class="bi bi-trash text-danger" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>`;
        });
        
        cartItems.innerHTML = html;
        
        // Update subtotal
        if (cartSubtotal) cartSubtotal.textContent = `$${total.toFixed(2)}`;
        
        // Add event listeners for the quantity buttons and inputs
        document.querySelectorAll('.minus-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                updateQuantity(productId, -1);
            });
        });

        document.querySelectorAll('.plus-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.disabled) {
                    return;
                }
                const productId = this.getAttribute('data-product-id');
                updateQuantity(productId, 1);
            });
        });

        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const productId = this.getAttribute('data-product-id');
                handleQuantityInput(productId, this.value);
            });
            
            input.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    const productId = this.getAttribute('data-product-id');
                    handleQuantityInput(productId, this.value);
                }
            });
        });
    }
}

// Handler function for remove button clicks
function handleRemoveClick() {
    const productId = this.getAttribute('data-product-id');
    console.log("Removing product:", productId); // Debug logging
    removeFromCart(productId);
}

// Function to handle quantity input changes
function handleQuantityInput(productId, value) {
    productId = Number(productId);
    const quantity = parseInt(value);
    
    // --- Handling for invalid input (<= 0 or NaN) ---
    if (isNaN(quantity) || quantity < 1) {
        const input = document.querySelector(`.cart-item[data-product-id="${productId}"] .quantity-input, .checkout-quantity-input[data-product-id="${productId}"]`);
        const currentQuantityInCart = getCurrentCartQuantity(productId);
        const resetQuantity = currentQuantityInCart > 0 ? currentQuantityInCart : 1; // Reset to current or 1

        if (input) input.value = resetQuantity; // Reset input visually
        
        // If quantity was invalidly low (e.g., 0 or negative), treat as removal intent *if* it was typed
        // But if it was just invalid text, reset to 1 or current value. Let's reset to 1 for simplicity unless it needs removal.
        // For now, just resetting input value. If user wanted to remove, they should use the remove button.
         alert("Please enter a quantity of 1 or more."); // Give feedback
        
        // No API call needed if input was invalid and didn't change the actual cart state meaningfully yet.
        // We just reset the input field visually.
        return; 
    }

    // --- Handling for valid input ---
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    const itemIndex = cart.findIndex(item => Number(item.id) === productId);

    if (itemIndex === -1) {
         console.warn(`[handleQuantityInput] Product ${productId} not found in cart.`);
         return; // Item not found
    }

    // Only proceed if the new quantity is different from the current one
    if (quantity !== Number(cart[itemIndex].quantity)) {
        // Update local storage first (optimistic update)
        cart[itemIndex].quantity = quantity;
        localStorage.setItem("cart", JSON.stringify(cart));
        
        // Update UI immediately
        updateCartUI();
        if (document.getElementById('checkout-items')) updateCheckoutUI(); // Update checkout page too if present
            
        // Send update to server
        console.log(`[handleQuantityInput] Sending update for product ${productId}: quantity ${quantity}`);
        fetch('api/update_quantity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            // Use the helper function to handle the response
            handleQuantityUpdateResponse(productId, data);
        })
        .catch(err => {
            console.error(`[handleQuantityInput] Error updating quantity for product ${productId}:`, err);
             // Optional: Revert UI changes on network error
        });
    }
}

// Utility function to get current quantity from cart (avoids code repetition)
function getCurrentCartQuantity(productId) {
     productId = Number(productId);
     const cart = JSON.parse(localStorage.getItem("cart")) || [];
     const item = cart.find(item => Number(item.id) === productId);
     return item ? Number(item.quantity) : 0;
}

// Function to update quantity with optimistic updates using +/- buttons
function updateQuantity(productId, change) {
    productId = Number(productId);
    
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    const itemIndex = cart.findIndex(item => Number(item.id) === productId);
    
    if (itemIndex === -1) {
         console.warn(`[updateQuantity] Product ${productId} not found in cart.`);
         return; // Item not found
    } 
    
    const currentQuantity = Number(cart[itemIndex].quantity);
    const potentialNewQuantity = currentQuantity + Number(change);
    
    // If decreasing and quantity reaches 0 or less, remove the item
    if (potentialNewQuantity <= 0) {
        removeFromCart(productId);
        return; // Stop further processing
    }
    
    // If increasing, proceed (stock check will happen server-side)
    const newQuantity = potentialNewQuantity;
    
    // Update the quantity in local storage (optimistic update)
    cart[itemIndex].quantity = newQuantity;
    localStorage.setItem("cart", JSON.stringify(cart));
    
    // Update the UI immediately
    updateCartUI();
    if (document.getElementById('checkout-items')) updateCheckoutUI(); // Update checkout page too if present
    
    // Send update to server
    console.log(`[updateQuantity] Sending update for product ${productId}: quantity ${newQuantity}`);
    fetch('api/update_quantity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}&quantity=${newQuantity}`
    })
    .then(response => response.json())
    .then(data => {
        // Use the helper function to handle the response
        handleQuantityUpdateResponse(productId, data);
    })
    .catch(err => {
        console.error(`[updateQuantity] Error updating quantity for product ${productId}:`, err);
        // Optional: Revert UI changes on network error
    });
}

// Function to handle direct quantity input (potentially redundant now? Review if needed)
// This seems largely replaced by handleQuantityInput. Keeping for now, but might be removable.
function updateQuantityDirect(productId, value) {
    productId = Number(productId);
    let quantity = parseInt(value);
    
    // Validate quantity
    if (isNaN(quantity) || quantity <= 0) {
        // If invalid, treat as removal for now (could refine this)
        console.warn(`[updateQuantityDirect] Invalid quantity ${value} for product ${productId}. Removing item.`);
        removeFromCart(productId);
        return;
    }
    
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    const itemIndex = cart.findIndex(item => Number(item.id) === productId);
    
    if (itemIndex === -1) {
         console.warn(`[updateQuantityDirect] Product ${productId} not found in cart.`);
         return; // Item not found
    }
    
    // Only update if quantity actually changed
    if (quantity !== Number(cart[itemIndex].quantity)) {
        cart[itemIndex].quantity = quantity;
        localStorage.setItem("cart", JSON.stringify(cart));
        
        updateCartUI();
         if (document.getElementById('checkout-items')) updateCheckoutUI(); // Update checkout page too if present
        
        // Send update to server
        console.log(`[updateQuantityDirect] Sending update for product ${productId}: quantity ${quantity}`);
        fetch('api/update_quantity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            // Use the helper function to handle the response
            handleQuantityUpdateResponse(productId, data);
        })
        .catch(err => {
            console.error(`[updateQuantityDirect] Error updating quantity for product ${productId}:`, err);
             // Optional: Revert UI changes on network error
        });
    }
}

// Function to remove items from cart
function removeFromCart(productId) {
    productId = Number(productId);
    
    // Remove from local storage
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    cart = cart.filter(item => Number(item.id) !== productId);
    localStorage.setItem("cart", JSON.stringify(cart));
    
    // Update UI immediately
    updateCartUI();
    
    // Directly send remove request to server without authentication check
    fetch("api/remove_from_cart.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== "success") {
            console.warn("Failed to remove item from database:", data.message);
        } else {
            console.log(`Successfully removed product ${productId} from cart`);
        }
    })
    .catch(err => {
        console.error("Error removing item from database:", err);
    });
}

// Toggle Cart Sidebar
function toggleCart() {
    // Don't allow toggling cart on restricted pages
    if (isCartRestrictedPage()) {
        return;
    }
    
    const body = document.body;
    const isCartOpen = body.classList.contains("cart-open");
    
    if (!isCartOpen) {
        // Opening the cart
        // Store the current scroll position
        const scrollY = window.scrollY;
        
        // Apply styles before adding class to prevent visual jump
        body.style.position = 'fixed';
        body.style.width = '100%';
        body.style.top = `-${scrollY}px`;
        
        // Add the cart-open class
        body.classList.add("cart-open");
        
        // Store the scroll position as a data attribute for easy access
        body.dataset.scrollY = scrollY;
    } else {
        // Closing the cart
        // Get the scroll position from the data attribute
        const scrollY = parseInt(body.dataset.scrollY || '0');
        
        // Remove the cart-open class
        body.classList.remove("cart-open");
        
        // Reset body styles
        body.style.position = '';
        body.style.width = '';
        body.style.top = '';
        
        // Restore scroll position immediately
        window.scrollTo({
            top: scrollY,
            behavior: 'instant' // Use instant for seamless restoration
        });
    }
}

// Function to check if cart should be hidden on the current page
function isCartRestrictedPage() {
    const currentPath = window.location.pathname;
    // Restrict cart only on login, checkout, and register pages
    return currentPath.includes('login.php') || 
           currentPath.includes('checkout.php') ||
           currentPath.includes('process_login.php') ||
           currentPath.includes('register.php');
    
    // Explicitly NOT restricting on profile.php and other pages
}

function validatePayment() {
    let cardNumber = document.getElementById("card-number").value;
    let expiry = document.getElementById("expiry").value;
    let cvv = document.getElementById("cvv").value;

    // Basic Validation
    if (!/^\d{16}$/.test(cardNumber)) {
        alert("Invalid card number. It must be 16 digits.");
        return false;
    }
    if (!/^\d{2}\/\d{2}$/.test(expiry)) {
        alert("Invalid expiry date. Use MM/YY format.");
        return false;
    }
    if (!/^\d{3}$/.test(cvv)) {
        alert("Invalid CVV. It must be 3 digits.");
        return false;
    }

    // Simulated Payment Processing
    alert("Payment successful! Thank you for your order.");
    document.cookie = "cart=[]; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    window.location.href = "order_success.php"; // Redirect to confirmation page
    return false;
}


function redirectToCheckout() {
    // First check if cart is empty
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }
    
    // Save cart data to localStorage (it will be synced in checkout.php)
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Add timestamp to URL to prevent caching issues
    window.location.href = 'checkout.php?ts=' + new Date().getTime();
}

// Helper function to handle the response from quantity update API calls
function handleQuantityUpdateResponse(productId, data) {
    productId = Number(productId); // Ensure consistency

    // Clear any previous stock message for this item in both cart and checkout view
    const stockMessageSpans = document.querySelectorAll(`[data-product-id="${productId}"] .stock-message`);
    stockMessageSpans.forEach(span => span.textContent = '');

    // Select plus buttons in both cart and checkout view
    const plusButtons = document.querySelectorAll(`[data-product-id="${productId}"] .plus-btn, [data-product-id="${productId}"] .checkout-plus-btn`);

    if (data.status === 'error' && data.message === 'Not enough stock available') {
        const availableStock = data.available_stock;
        console.warn(`Stock limit reached for product ${productId}. Available: ${availableStock}`);

        // Correct local storage FIRST
        let cart = JSON.parse(localStorage.getItem("cart")) || [];
        const itemIndex = cart.findIndex(item => Number(item.id) === productId);
        if (itemIndex !== -1 && cart[itemIndex].quantity !== availableStock) { // Only update if different
            cart[itemIndex].quantity = availableStock;
            localStorage.setItem("cart", JSON.stringify(cart));
            
             // Refresh UI with corrected quantity (cart and checkout if present)
            updateCartUI(); 
            if (document.getElementById('checkout-items')) updateCheckoutUI();
        } else if (itemIndex === -1) {
             console.error(`Item ${productId} not found in cart for stock correction.`);
        }


        // NOW, find the potentially re-rendered elements and apply message/disable
        // Use setTimeout to ensure the DOM has updated after UI refreshes
        setTimeout(() => {
            const updatedStockMessageSpans = document.querySelectorAll(`[data-product-id="${productId}"] .stock-message`);
            updatedStockMessageSpans.forEach(span => {
                if (span) span.textContent = `Only ${availableStock} left`;
            });
            const updatedPlusButtons = document.querySelectorAll(`[data-product-id="${productId}"] .plus-btn, [data-product-id="${productId}"] .checkout-plus-btn`);
            updatedPlusButtons.forEach(button => {
                 if (button) button.disabled = true;
            });
        }, 0); // Execute after current stack clears

    } else if (data.status !== 'success') {
        // Handle other errors
        console.warn(`Failed to update quantity for product ${productId} in database:`, data.message);
        // Optional: Revert UI change or show a generic error message
        // updateCartUI(); // Revert to previous state
        // if (document.getElementById('checkout-items')) updateCheckoutUI(); 
    } else {
        // Success: Ensure plus button is enabled and message is clear
        console.log(`Successfully updated quantity for product ${productId} to ${data.new_quantity || 'specified value'}`); // Assuming API returns new quantity on success
        plusButtons.forEach(button => {
            if (button) button.disabled = false; 
        });
        // Ensure messages are cleared again in case of rapid clicks leading to success after error
         stockMessageSpans.forEach(span => span.textContent = '');
    }
}

function syncCartFromServerAndMerge() {
    const localCart = JSON.parse(localStorage.getItem("cart")) || [];
    console.log("Starting cart sync. Local cart items:", localCart.length);

    fetch("api/sync_cart.php")
        .then(res => {
            console.log("Sync cart response status:", res.status);
            return res.json();
        })
        .then(data => {
            console.log("Sync cart response data:", data);
            if (data.status !== "success") {
                console.warn("Failed to sync with server:", data.message);
                return;
            }

            const serverCart = data.cart || [];
            console.log("Merging server cart:", serverCart, "with local cart:", localCart);

            // Merge carts (by product ID)
            const mergedMap = new Map();

            // Step 1: Add local cart items first so we don't lose user's offline selections
            localCart.forEach(localItem => {
                const id = Number(localItem.id);
                mergedMap.set(id, {
                    id: Number(localItem.id),
                    name: localItem.name,
                    price: Number(localItem.price),
                    image_url: localItem.image_url,
                    quantity: Number(localItem.quantity)
                });
            });

            // Step 2: Merge with server cart, combining quantities
            serverCart.forEach(item => {
                const id = Number(item.id);
                if (mergedMap.has(id)) {
                    // If item exists in both carts, take the higher quantity
                    const existing = mergedMap.get(id);
                    existing.quantity = Math.max(Number(existing.quantity), Number(item.quantity));
                } else {
                    // If item only exists in server cart, add it
                    mergedMap.set(id, {
                        id: Number(item.id),
                        name: item.name,
                        price: Number(item.price),
                        image_url: item.image_url,
                        quantity: Number(item.quantity)
                    });
                }
            });

            // Final merged cart
            const mergedCart = Array.from(mergedMap.values());
            console.log("Merged cart:", mergedCart);

            // Save back to localStorage
            localStorage.setItem("cart", JSON.stringify(mergedCart));

            // Sync the merged cart back to server
            return fetch("api/save_cart.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ cart: mergedCart })
            });
        })
        .then(res => {
            if (!res) return; // Skip if previous step failed
            console.log("Save cart response status:", res.status);
            return res.json();
        })
        .then(data => {
            if (!data) return; // Skip if previous step failed
            console.log("Save cart response data:", data);
            if (data.status === "success") {
                console.log("Cart synced successfully");
                updateCartUI();
            } else {
                console.warn("Failed to save merged cart to server:", data.message);
                updateCartUI(); // Still update UI even if server save fails
            }
        })
        .catch(err => {
            console.error("Error syncing with server:", err);
            // If server sync fails, just use local cart
            updateCartUI();
        });
}

// Add debouncing to prevent too many API calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Debounce the updateCartUI function
const debouncedUpdateCartUI = debounce(updateCartUI, 300);

// Format credit card number with spaces
function initCheckoutPage() {
    const cardNumberInput = document.getElementById('card-number');
    const expiryInput = document.getElementById('expiry');
    const cvvInput = document.getElementById('cvv');

    if (cardNumberInput) {
        // Format card number with spaces
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue.substring(0, 19); // 16 digits + 3 spaces
        });
    }

    if (expiryInput) {
        // Format expiry date as MM/YY
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/gi, '');
            
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            e.target.value = value;
        });
    }

    if (cvvInput) {
        // Restrict CVV to 3 digits
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/gi, '').substring(0, 3);
        });
    }
}

// Debug function to help test cart synchronization
function testSyncCart() {
    console.log("Manually triggering cart sync...");
    syncCartFromServerAndMerge();
}

// Function called by the logout button's onclick
function handleLogoutClick() {
    console.log("Logout button clicked. Clearing local cart...");
    try {
        localStorage.removeItem('cart');
        console.log("Local cart cleared.");
    } catch (e) {
        console.error("Error clearing local cart:", e);
    }
    // Return true to allow the default form submission to proceed
    return true; 
}

// --- Checkout Page Specific Functions ---

// Function to update the checkout page UI (order summary)
function updateCheckoutUI() {
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    
    // Get references to display elements
    const sidebarSubtotal = document.getElementById('sidebar-subtotal');
    const sidebarShipping = document.getElementById('sidebar-shipping');
    const sidebarTotal = document.getElementById('sidebar-total');
    const checkoutItemsContainer = document.getElementById('checkout-items');
    
    // Checkout page specific elements
    const checkoutSubtotal = document.getElementById('checkout-subtotal');
    const checkoutShipping = document.getElementById('checkout-shipping');
    const checkoutTotal = document.getElementById('checkout-total');

    // Calculate subtotal
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += Number(item.price) * Number(item.quantity);
    });

    // Handle empty cart scenario FIRST
    if (cart.length === 0) {
        if (sidebarSubtotal) sidebarSubtotal.textContent = '$0.00';
        if (sidebarShipping) sidebarShipping.textContent = '$0.00'; // Set shipping to 0
        if (sidebarTotal) sidebarTotal.textContent = '$0.00';    // Set total to 0
        
        // Update checkout page summary elements
        if (checkoutSubtotal) checkoutSubtotal.textContent = '$0.00';
        if (checkoutShipping) checkoutShipping.textContent = '$0.00';
        if (checkoutTotal) checkoutTotal.textContent = '$0.00';

        if (checkoutItemsContainer) {
            checkoutItemsContainer.innerHTML = '<li class="list-group-item">Your cart is empty. <a href="allProducts.php">Continue shopping</a>.</li>';
        }
        // Potentially disable payment form/button here
        const payButton = document.querySelector('#checkout-form button[type="submit"]');
        if (payButton) payButton.disabled = true;
        return; // Stop further processing for empty cart
    }

    // --- Cart is NOT empty --- 
    
    // Define shipping fee
    const shippingFee = 5.00;
    const total = subtotal + shippingFee;
    
    // Update cart sidebar elements
    if (sidebarSubtotal) sidebarSubtotal.textContent = `$${subtotal.toFixed(2)}`;
    if (sidebarShipping) sidebarShipping.textContent = `$${shippingFee.toFixed(2)}`; // Set shipping to 5.00
    if (sidebarTotal) sidebarTotal.textContent = `$${total.toFixed(2)}`;
    
    // Update checkout page summary elements
    if (checkoutSubtotal) checkoutSubtotal.textContent = `$${subtotal.toFixed(2)}`;
    if (checkoutShipping) checkoutShipping.textContent = `$${shippingFee.toFixed(2)}`;
    if (checkoutTotal) checkoutTotal.textContent = `$${total.toFixed(2)}`;
    
    // Re-enable payment button if needed
    const payButton = document.querySelector('#checkout-form button[type="submit"]');
    if (payButton) payButton.disabled = false;
    
    // Update items in the order summary section (ensure container exists)
    if (checkoutItemsContainer) {
        let itemsHtml = '';
        cart.forEach(item => {
            itemsHtml += `
                <li class="list-group-item d-flex justify-content-between align-items-center" data-product-id="${item.id}">
                    <div class="d-flex align-items-center">
                        <img src="${item.image_url}" alt="${item.name}" class="checkout-product-img me-2">
                        <div>
                            <span>${item.name}</span>
                            <div class="quantity-controls d-flex align-items-center mt-1">
                                <button class="btn btn-sm btn-outline-secondary quantity-btn checkout-minus-btn" 
                                       data-product-id="${item.id}"
                                       aria-label="Decrease quantity of ${item.name}">-</button>
                                <label for="checkout-quantity-${item.id}" class="visually-hidden">Quantity for ${item.name}</label>
                                <input type="number" 
                                       id="checkout-quantity-${item.id}"
                                       class="form-control form-control-sm quantity-input checkout-quantity-input mx-1" 
                                       style="width: 40px;" 
                                       value="${item.quantity}" 
                                       min="1" 
                                       max="10" 
                                       data-product-id="${item.id}"
                                       aria-label="Quantity for ${item.name}">
                                <button class="btn btn-sm btn-outline-secondary quantity-btn checkout-plus-btn" 
                                       data-product-id="${item.id}"
                                       aria-label="Increase quantity of ${item.name}">+</button>
                            </div>
                            <span class="stock-message text-danger small d-block" style="min-height: 1.2em;"></span> 
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end">
                        <span class="fw-bold item-price" data-price="${item.price}">$${(Number(item.price) * Number(item.quantity)).toFixed(2)}</span>
                        <button class="btn btn-sm btn-link text-danger checkout-remove-item p-0 mt-2" 
                               data-product-id="${item.id}"
                               aria-label="Remove ${item.name} from cart">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </li>`;
        });
        checkoutItemsContainer.innerHTML = itemsHtml;
        
        // Reattach event listeners for checkout items specifically
        attachCheckoutItemListeners(); 
    }
}

// Function to attach listeners to items within the #checkout-items list
function attachCheckoutItemListeners() {
    const checkoutItems = document.getElementById('checkout-items');
    if (!checkoutItems) return;

    // Minus button click
    checkoutItems.querySelectorAll('.checkout-minus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            console.log(`[Checkout Listener] Minus button clicked for product: ${productId}`);
            updateQuantity(productId, -1); // Handles storage, fetch, and subsequent UI updates
            updateCheckoutUI(); // Add back immediate UI update for checkout page itself
        });
    });

    // Plus button click
    checkoutItems.querySelectorAll('.checkout-plus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
             if (this.disabled) return; // Check if disabled (stock limit)
            const productId = this.getAttribute('data-product-id');
            console.log(`[Checkout Listener] Plus button clicked for product: ${productId}`);
            updateQuantity(productId, 1); // Handles storage, fetch, and subsequent UI updates
            updateCheckoutUI(); // Add back immediate UI update for checkout page itself
        });
    });

    // Input change
    checkoutItems.querySelectorAll('.checkout-quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            console.log(`[Checkout Listener] Quantity input changed for product: ${productId} to ${this.value}`);
            handleQuantityInput(productId, this.value); // Handles storage, fetch, and subsequent UI updates
            updateCheckoutUI(); // Add back immediate UI update for checkout page itself
        });
    });

    // Remove button
    checkoutItems.querySelectorAll('.checkout-remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            console.log(`[Checkout Listener] Remove button clicked for product: ${productId}`);
            removeFromCart(productId); // Handles storage, fetch, and cart UI update
            // Explicitly update checkout UI after removal
            if (document.getElementById('checkout-items')) updateCheckoutUI();
        });
    });
}

// Function to sync cart with database (likely redundant, keep for now?)
function syncCartWithDatabase(cart) {
    fetch('/api/save_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart: cart })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'success') {
            console.warn('Failed to sync cart with database:', data.message);
        }
    })
    .catch(error => {
        console.error('Error syncing cart with database:', error);
    });
}

// Function to handle checkout confirmation and validation
function confirmCheckout(event) {
    event.preventDefault(); // Prevent default form submission
    
    // Get user details
    const fullName = document.getElementById('full-name')?.value.trim();
    const email = document.getElementById('email')?.value.trim();
    const phone = document.getElementById('phone')?.value.trim();
    const billingAddress = document.getElementById('billing-address')?.value.trim();
    const sameAddressCheckbox = document.getElementById('same-address');
    const shippingAddress = sameAddressCheckbox && sameAddressCheckbox.checked 
        ? billingAddress 
        : document.getElementById('shipping-address')?.value.trim();
    
    // Get validation error elements (or create them if needed)
    function showError(inputId, message) {
        let input = document.getElementById(inputId);
        if (!input) return false;
        
        // Add error class to input
        input.classList.add('is-invalid');
        
        // Create or update error message
        let errorEl = document.getElementById(`${inputId}-error`);
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.id = `${inputId}-error`;
            errorEl.className = 'invalid-feedback';
            input.parentNode.appendChild(errorEl);
        }
        errorEl.textContent = message;
        
        // Focus the first invalid input
        if (!document.querySelector('.is-invalid:focus')) {
            input.focus();
        }
        
        return false;
    }
    
    // Clear previous validation errors
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Basic Form Validation 
    let isValid = true;
    
    // Full Name validation - required, max length 100 chars
    if (!fullName) {
        isValid = showError('full-name', 'Please enter your full name');
    } else if (fullName.length > 100) {
        isValid = showError('full-name', 'Name is too long (max 100 characters)');
    }
    
    // Email validation - required, proper format
    if (!email) {
        isValid = showError('email', 'Please enter your email address');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        isValid = showError('email', 'Please enter a valid email address');
    } else if (email.length > 100) {
        isValid = showError('email', 'Email is too long (max 100 characters)');
    }
    
    // Phone validation - optional but validate format if provided
    if (phone && !/^[\d\s\+\(\)-]{7,20}$/.test(phone)) {
        isValid = showError('phone', 'Please enter a valid phone number');
    }
    
    // Billing Address validation - required, reasonable length
    if (!billingAddress) {
        isValid = showError('billing-address', 'Please enter your billing address');
    } else if (billingAddress.length < 10) {
        isValid = showError('billing-address', 'Please enter a complete billing address');
    } else if (billingAddress.length > 255) {
        isValid = showError('billing-address', 'Address is too long (max 255 characters)');
    }
    
    // Shipping Address validation - required if checkbox unchecked
    if (!sameAddressCheckbox?.checked) {
        if (!shippingAddress) {
            isValid = showError('shipping-address', 'Please enter your shipping address');
        } else if (shippingAddress.length < 10) {
            isValid = showError('shipping-address', 'Please enter a complete shipping address');
        } else if (shippingAddress.length > 255) {
            isValid = showError('shipping-address', 'Address is too long (max 255 characters)');
        }
    }
    
    // Card validation
    const cardNumberInput = document.getElementById('card-number');
    const expiryInput = document.getElementById('expiry');
    const cvvInput = document.getElementById('cvv');

    if (!cardNumberInput || !expiryInput || !cvvInput) {
        alert("Payment details form elements not found.");
        return false;
    }

    const cardNumber = cardNumberInput.value.replace(/\s/g, '');
    const expiry = expiryInput.value;
    const cvv = cvvInput.value;
    
    // Card number validation - must be 16 digits
    if (!/^\d{16}$/.test(cardNumber)) {
        isValid = showError('card-number', 'Please enter a valid 16-digit card number');
    }
    
    // Expiry date validation - MM/YY format and not expired
    if (!/^\d{2}\/\d{2}$/.test(expiry)) {
        isValid = showError('expiry', 'Please enter a valid expiry date in MM/YY format');
    } else {
        // Validate expiry date logic (not expired)
        const [expMonth, expYear] = expiry.split('/');
        const now = new Date();
        const currentYear = now.getFullYear() % 100; // Get last 2 digits
        const currentMonth = now.getMonth() + 1; // JS months are 0-indexed
        
        if (parseInt(expYear) < currentYear || (parseInt(expYear) === currentYear && parseInt(expMonth) < currentMonth)) {
            isValid = showError('expiry', 'Your card has expired');
        }
    }
    
    // CVV validation - must be 3 digits
    if (!/^\d{3}$/.test(cvv)) {
        isValid = showError('cvv', 'Please enter a valid 3-digit CVV');
    }
    
    // Exit if any validation failed
    if (!isValid) {
        return false;
    }
    
    // Get order details
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    if (cart.length === 0) {
        alert("Your cart is empty");
        return false;
    }
    
    // Calculate totals again for safety
    let subtotal = cart.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
    const shippingFee = 5.00;
    const total = subtotal + shippingFee;
    
    // Create order details object
    const orderDetails = {
        customer: { name: fullName, email: email, phone: phone, billingAddress: billingAddress, shippingAddress: shippingAddress },
        payment: { cardNumber: cardNumber.slice(-4), expiry: expiry }, // Only store last 4 digits for display
        items: cart,
        subtotal: subtotal,
        shipping: { cost: shippingFee, method: "Standard Shipping" },
        total: total,
        date: new Date().toISOString(),
        orderId: 'ORD-' + Math.floor(100000 + Math.random() * 900000) // Generate simple order ID
    };
    
    // Show loading indicator on button
    const payButton = document.querySelector('#checkout-form button[type="submit"]');
    let originalButtonHTML = ''; // Store original HTML
    if (payButton) {
        originalButtonHTML = payButton.innerHTML;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        payButton.disabled = true;
    }
    
    // Save order details to session storage for success page
    sessionStorage.setItem('order_details', JSON.stringify(orderDetails));
    
    // Clean up card number input - remove spaces before submission
    if (cardNumberInput) {
        cardNumberInput.value = cardNumberInput.value.replace(/\s/g, '');
    }

    // Submit the actual form to the backend for processing
    // The form that triggered the event is event.target
    if (event.target && event.target.tagName === 'FORM') { 
        // We are submitting to process_checkout.php (or similar)
        // The form should already have method="POST" and action="process_checkout.php"
        event.target.submit(); 
    } else {
        console.error("Checkout form could not be submitted via event.target!");
        alert("Error submitting order. Form could not be referenced.");
        if (payButton) { // Re-enable button on error
            payButton.innerHTML = originalButtonHTML; // Restore original HTML
            payButton.disabled = false;
        }
    }
}

// --- End Checkout Page Specific Functions ---


// Function to load and show order details
function loadOrderDetails(orderId) {
    // Show the modal with loading indicator
    const orderModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const contentDiv = document.getElementById('order-details-content');
    
    // Show loading indicator
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    orderModal.show();
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'get_order_details');
    formData.append('order_id', orderId);
    
    // Send POST request
    fetch('profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data || !data.order) {
            contentDiv.innerHTML = '<div class="alert alert-danger">Order not found or access denied.</div>';
            return;
        }
        
        // Build HTML for order details
        let html = `
            <div class="order-details">
                <div class="order-header mb-4">
                    <h4>Order #${data.order.id}</h4>
                    <div class="text-muted">${data.order.formatted_date}</div>
                    <div class="mt-2">
                        <span class="badge bg-${data.order.status === 'completed' ? 'success' : 'primary'}">
                            ${data.order.status}
                        </span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="mb-3">Order Items</h5>
                        <div class="order-items">`;
        
        // Add items
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                html += `
                    <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                        <img src="${item.image_url}" alt="${item.name}" class="me-3" style="width: 60px; height: 60px; object-fit: contain;">
                        <div class="flex-grow-1">
                            <h6 class="mb-0">${item.name}</h6>
                            <div class="text-muted small">
                                Quantity: ${item.quantity} × $${parseFloat(item.unit_price).toFixed(2)} = $${(item.quantity * item.unit_price).toFixed(2)}
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            html += '<p>No items found for this order.</p>';
        }
        
        // Add order summary
        html += `
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Order Summary</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>$${(parseFloat(data.order.total_amount) - 5.00).toFixed(2)}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <span>$5.00</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total:</span>
                                    <span>$${parseFloat(data.order.total_amount).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        
        // Update modal content
        contentDiv.innerHTML = html;
    })
    .catch(error => {
        contentDiv.innerHTML = `<div class="alert alert-danger">Error loading order details: ${error.message}</div>`;
        console.error('Error:', error);
    });
}

// Add this to your existing DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    // ... your existing code ...
    
    // Add event listeners to all "View Details" buttons
    document.querySelectorAll('.view-order-details').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            loadOrderDetails(orderId);
        });
    });
});
