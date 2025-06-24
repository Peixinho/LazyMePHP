<style>
/* Navbar Styling */
.navbar {
    background: linear-gradient(135deg, #212529 0%, #343a40 100%);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 1rem;
    min-height: 60px;
}

.nav-brand {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.nav-brand a {
    color: #ffffff;
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: bold;
    padding: 0.75rem 0;
    transition: all 0.3s ease-in-out;
    display: flex;
    align-items: center;
}

.nav-brand a:hover {
    color: #007bff;
    transform: scale(1.05);
}

.nav-logo {
    height: 40px;
    width: auto;
    display: block;
    margin-right: 0.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.nav-menu {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex: 1;
    margin-left: 1rem;
}

.nav-item {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.nav-link {
    color: #ffffff;
    text-decoration: none;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    transition: all 0.3s ease-in-out;
    font-weight: 500;
    position: relative;
    display: flex;
    align-items: center;
    text-transform: capitalize;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid transparent;
    font-size: 0.875rem;
    white-space: nowrap;
    min-width: fit-content;
}

.nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.2);
}

.nav-link:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
    background: rgba(255, 255, 255, 0.1);
}

.nav-link.active {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
    border-color: #007bff;
}

.nav-link.active:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 123, 255, 0.4);
}

/* Responsive navbar */
@media (max-width: 1200px) {
    .nav-menu {
        gap: 0.2rem;
    }
    
    .nav-link {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 1024px) {
    .nav-menu {
        gap: 0.15rem;
    }
    
    .nav-link {
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 768px) {
    .nav-container {
        flex-direction: column;
        padding: 0.5rem 1rem;
        gap: 0.75rem;
        min-height: auto;
    }
    
    .nav-menu {
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.25rem;
        margin-left: 0;
        width: 100%;
    }
    
    .nav-link {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
    }
    
    .nav-logo {
        height: 35px;
    }
}

@media (max-width: 640px) {
    .nav-menu {
        gap: 0.2rem;
    }
    
    .nav-link {
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .nav-menu {
        flex-direction: column;
        width: 100%;
        gap: 0.25rem;
    }
    
    .nav-item {
        width: 100%;
    }
    
    .nav-link {
        width: 100%;
        justify-content: center;
        text-align: center;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
}
</style>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/">
                <img src="/img/logo.png" alt="LazyMePHP" class="nav-logo">
            </a>
        </div>
        
        <ul class="nav-menu" id="nav-menu">
            <?php 
            $routesPath = __DIR__ . '/../../Routes/';
            $routeFiles = glob($routesPath . '/*.php');
            
            foreach ($routeFiles as $routeFile) { 
                $routeName = basename($routeFile, '.php');
                if ($routeName !== 'Routes') { 
                    echo "<li class=\"nav-item\"><a href=\"/{$routeName}\" class=\"nav-link\">{$routeName}</a></li>";
                } 
            } 
            ?>
        </ul>
    </div>
</nav>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById('nav-dropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('nav-dropdown');
    const toggle = document.querySelector('.nav-dropdown-toggle');
    
    if (dropdown && !dropdown.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

// Close dropdown on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('nav-dropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }
});
</script> 