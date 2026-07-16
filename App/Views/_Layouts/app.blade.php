<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', '')">
    @csrfMeta
    <title>@yield('title', 'LazyMePHP')</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/css/css.css">
    <style>
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
        {{-- $pageContent is PHP-rendered HTML from the router — raw output is intentional --}}
        {!! $pageContent ?? '' !!}
    </main>

    @include('_Partials.footer')

    <script src="/js/LazyMePHP.js"></script>
    @yield('scripts')

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof LazyMePHP !== 'undefined' && typeof LazyMePHP.Init === 'function') {
          LazyMePHP.Init();
        }
      });
    </script>
</body>
</html>
