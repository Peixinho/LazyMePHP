<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'LazyMePHP')</title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        /* Layout styles for sticky footer */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        main.container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
    </style>
    @yield('head')
</head>
<body>
    @include('_Partials.nav')
    
    <main class="container">
        @include('_Notifications.notifications')
        {!! $pageContent ?? '' !!}
    </main>
    
    @include('_Partials.footer')
    
    <script src="/js/LazyMePHP.js"></script>
    @yield('scripts')
    
    <script>
      // Initialize LazyMePHP and handle notifications when DOM is ready
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof LazyMePHP !== 'undefined' && typeof LazyMePHP.Init === 'function') {
          LazyMePHP.Init();
          
          // Process any session notifications that might be available
          // This will be handled by the notifications component, but we ensure LazyMePHP is ready
          console.log('LazyMePHP initialized successfully');
        } else {
          console.error('LazyMePHP not available for initialization');
        }
      });
    </script>
</body>
</html> 