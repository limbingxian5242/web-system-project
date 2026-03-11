
    <nav class="navbar navbar-expand-xl">
        <div class="nav-overlay"></div>
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Green Echo</a>

            <button class="navbar-toggler" 
                type="button" data-bs-toggle="collapse" 
                data-bs-target="#navbarSupportedContent" 
                aria-controls="navbarSupportedContent" 
                aria-expanded="false" 
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="allProducts.php">Plants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php" title="Profile">
                            <i class="bi bi-person  d-none d-xl-inline"></i>
                            <span class="d-xl-none">Profile</span>
                        </a>
                    </li>
                    <li class = "nav-item">
                        <?php
                        // Check if we should display the cart icon
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $restricted_pages = ['login.php', 'checkout.php', 'process_login.php', 'register.php'];
                        
                        // Make sure profile.php is not in the restricted pages list
                        if (!in_array($current_page, $restricted_pages)):
                        ?>
                        <a class="nav-link" title="Cart">
                        <!-- Cart Icon in Navbar -->
                            <div class="cart-icon" onclick="toggleCart()">
                                🛒 <span id="cart-count">0</span>
                            </div>
                        </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
