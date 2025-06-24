<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        html, body { 
            height: 100%; 
            width: 100%; 
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #333;
            height: 100vh;
            width: 100vw;
        }
        
        .error-center-wrapper {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            z-index: 999999; /* Error modal - below debug toolbar (9999999) */
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: auto;
        }
        
        .error-icon { 
            font-size: 80px; 
            color: #e74c3c; 
            margin-bottom: 20px; 
        }
        
        .error-code { 
            font-size: 48px; 
            color: #e74c3c; 
            font-weight: bold; 
            margin-bottom: 10px; 
        }
        
        .error-title { 
            font-size: 24px; 
            color: #2c3e50; 
            margin-bottom: 20px; 
            font-weight: 600; 
        }
        
        .error-description { 
            color: #7f8c8d; 
            line-height: 1.6; 
            margin-bottom: 30px; 
            font-size: 16px; 
        }
        
        .error-id { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 10px; 
            margin: 20px 0; 
            font-family: monospace; 
            font-size: 14px; 
            border: 1px solid #e1e8ed; 
        }
        
        .error-actions { 
            margin-top: 30px; 
        }
        
        .btn { 
            display: inline-block; 
            padding: 12px 24px; 
            background: #e74c3c; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            margin: 0 10px; 
            transition: all 0.3s ease; 
            font-weight: 500; 
        }
        
        .btn:hover { 
            background: #c0392b; 
            transform: translateY(-2px); 
        }
        
        .btn-secondary { 
            background: #7f8c8d; 
        }
        
        .btn-secondary:hover { 
            background: #6a757a; 
        }
        
        /* Customizable CSS variables */
        :root {
            --error-primary-color: #e74c3c;
            --error-secondary-color: #7f8c8d;
            --error-bg-color: rgba(255, 255, 255, 0.95);
            --error-border-radius: 20px;
            --error-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="error-center-wrapper">
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1 class="error-code">{{ $errorCode }}</h1>
            <h2 class="error-title">{{ $title }}</h2>
            <p class="error-description">
                {{ $message }}
            </p>
            
            @if($errorId)
            <div class="error-id">
                <strong>Error ID:</strong> {{ $errorId }}
            </div>
            @endif

            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                <a href="/" class="btn">Go Home</a>
            </div>
        </div>
    </div>
</body>
</html> 