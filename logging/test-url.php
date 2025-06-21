<?php
// Simple test to verify URL construction
?>
<!DOCTYPE html>
<html>
<head>
    <title>URL Test</title>
</head>
<body>
    <h1>URL Construction Test</h1>
    <p>Current URL: <span id="current-url"></span></p>
    <p>Constructed URL: <span id="constructed-url"></span></p>
    
    <script>
        const pathname = window.location.pathname;
        const basePath = pathname.endsWith('index.php') ? pathname.replace('index.php', '') : pathname;
        const url = window.location.origin + basePath + 'get-changes.php?log_id=6453&debug=1';
        
        document.getElementById('current-url').textContent = window.location.href;
        document.getElementById('constructed-url').textContent = url;
        
        console.log('Pathname:', pathname);
        console.log('Base path:', basePath);
        console.log('Constructed URL:', url);
    </script>
</body>
</html> 