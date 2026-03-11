function toggleFilter() {
    const content = document.querySelector('.filter-content');
    const icon = document.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '−';
    } else {
        content.style.display = 'none';
        icon.textContent = '+';
    }
}

function toggleAvailability() {
    const content = document.querySelector('.availability-content');
    const icon = document.querySelector('.availability-toggle');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '−';
    } else {
        content.style.display = 'none';
        icon.textContent = '+';
    }
}

function filterByStock(type) {
    const options = document.querySelectorAll('.stock-option');
    const clickedOption = event.target;
    
    if (clickedOption.classList.contains('active')) {
        clickedOption.classList.remove('active');
        // Reset to show all products
        loadAllProducts();
    } else {
        options.forEach(option => option.classList.remove('active'));
        clickedOption.classList.add('active');
        
        fetch(`inc/searchhandler.inc.php?ajax=1&stock=${type}`)
            .then(response => response.json())
            .then(data => updateProducts(data));
    }
}

let searchTimeout;

function searchProducts(query) {
    clearTimeout(searchTimeout);
    
    if (query.trim() === '') {
        loadAllProducts();
        return;
    }
    if (query.length > 40) {
        const productsContainer = document.querySelector('.row.row-cols-3');
        productsContainer.innerHTML = `
            <div class="col-12">
                <div class="message-container">
                    <h3 class="stock-message">Search query is too long. Maximum 40 characters allowed.</h3>
                </div>
            </div>`;
        return;
    }
    searchTimeout = setTimeout(() => {
        const productsContainer = document.querySelector('.row.row-cols-3');
        productsContainer.innerHTML = '<div class="col-12 text-center"><h3>Searching...</h3></div>';

        fetch(`inc/searchhandler.inc.php?ajax=1&q=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.message || 'Search failed');
                }
                updateProducts(data);
            })
            .catch(error => {
                console.error('Search error:', error);
                productsContainer.innerHTML = `
                    <div class="col-12">
                        <div class="message-container">
                            <h3 class="stock-message">Unable to search products. Please try again.</h3>
                        </div>
                    </div>`;
            });
    }, 300);
}

function loadAllProducts() {
    fetch('allProducts.php')
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const productsContainer = doc.querySelector('.row.row-cols-3');
            document.querySelector('.row.row-cols-3').innerHTML = productsContainer.innerHTML;
        });
}

function updateProducts(data) {
    const productsContainer = document.querySelector('.row.row-cols-3');
    let html = '';
    
    if (data.message) {
        // Display message when no products found or there's an error message
        html = `
            <div class="col-12">
                <div class="message-container">
                    <h3 class="stock-message">${data.message}</h3>
                </div>
            </div>`;
    } else if (data.products && data.products.length > 0) {
        // Display products when found
        data.products.forEach(row => {
            html += `
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="product.php?id=${row.id}" class="product-card-link">
                        <div class="product-card position-relative text-center">
                            <img src="${row.image}" alt="${row.name}" style="width: 100%; height: auto; max-width: 300px; object-fit: contain;">
                            <div class="product-info text-center" style="margin-top: 60px; min-height: 120px;">
                                <h2 class="product-name" style="white-space: nowrap; height: 1.5em; margin-bottom: 8px;">${row.name}</h2>
                                <p class="product-price" style="margin-bottom: 0;">from SGD${row.price}</p>
                                ${row.stock <= 0 ? '<p style="margin-top: 4px; color: black; font-size: 1em; letter-spacing: 0.1em; font-weight: 500;">Sold Out</p>' : ''}
                            </div>
                        </div>
                    </a>
                </div>`;
        });
    } else {
        html = `
            <div class="col-12">
                <div class="message-container">
                    <h3 class="stock-message">No products found</h3>
                </div>
            </div>`;
    }
    
    productsContainer.innerHTML = html;
}