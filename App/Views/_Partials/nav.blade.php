<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/">
                <img src="/img/logo.png" alt="LazyMePHP" class="nav-logo">
            </a>
        </div>
        
        <ul class="nav-menu">
            <?php 
            $routesPath = __DIR__ . '/../../Routes/';
            foreach (glob($routesPath . '/*.php') as $routeFile) { 
                $routeName = basename($routeFile, '.php');
                if ($routeName !== 'Routes') { 
                    echo "<li class=\"nav-item\"><a href=\"/{$routeName}\" class=\"nav-link\">{$routeName}</a></li>";
                } 
            } 
            ?>
            <li class="nav-item">
                <a href="/api" class="nav-link">API</a>
            </li>
        </ul>
    </div>
</nav> 