<header class="site-header">
    <div class="header-bg">
        <img src="images/placeholder.jpg" alt="Header background" class="header-bg-img">
    </div>


    <nav class="navbar navbar-expand-xl">
        <div class="nav-overlay"></div>
        <div class="container-fluid ">
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
                        <a class="nav-link" title="Cart">
                        <!-- Cart Icon in Navbar -->
                            <div class="cart-icon" onclick="toggleCart()">
                                🛒 <span id="cart-count">0</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="header-content">
        <h2 class="header-quote">Build your sanctuary</h2>
        <a href="allProducts.php" class="btn btn-outline-light rounded-pill shop-now-btn">Shop Now</a>
        </div>
</header>
