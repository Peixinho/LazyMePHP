<?php
// Standalone Batman API client - no framework dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    header('Location: login.php');
    exit;
}

$apiError = null;
$baseUrl = $_SESSION['api_base_url'] ?? 'http://localhost:8080';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batman Dashboard - API Client</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header h1 i {
            color: #667eea;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .nav-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            gap: 5px;
        }

        .nav-tab {
            padding: 12px 20px;
            text-decoration: none;
            color: #7f8c8d;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .nav-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(149, 165, 166, 0.3);
        }

        .api-client-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .api-client-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .api-client-section h2 i {
            color: #667eea;
        }

        .base-url-section {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .base-url-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 150px 100px;
            gap: 15px;
            margin-bottom: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: inherit;
            background: rgba(255,255,255,0.7);
            box-shadow: 0 2px 8px rgba(102,126,234,0.05);
            color: #2c3e50;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .response-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .response-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .response-section h2 i {
            color: #667eea;
        }

        .response-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .info-card h4 {
            margin: 0 0 8px 0;
            font-size: 0.9em;
            color: #7f8c8d;
            font-weight: 500;
        }

        .info-card .value {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
        }

        .response-content {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
        }

        .error-message {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }

        .success-message {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        /* Custom dropdown arrow */
        .form-group select {
            background: linear-gradient(135deg, #f8fafc 60%, #e9eafc 100%);
            border: 2px solid #e1e8ed;
            color: #2c3e50;
            padding-right: 40px;
            position: relative;
            cursor: pointer;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23667eea" height="20" viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7.293 8.293a1 1 0 011.414 0L10 9.586l1.293-1.293a1 1 0 111.414 1.414l-2 2a1 1 0 01-1.414 0l-2-2a1 1 0 010-1.414z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px 20px;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px #667eea33;
        }

        .form-group select:hover {
            border-color: #667eea;
        }

        .form-group select::-ms-expand {
            display: none;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px #667eea33;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .response-info {
                grid-template-columns: 1fr;
            }
            
            .template-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Discovery Section */
        .discovery-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .discovery-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .discovery-section h2 i {
            color: #667eea;
        }

        .discovery-info {
            margin-top: 15px;
        }

        .routes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .route-card {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .route-card:hover {
            background: rgba(51, 65, 85, 0.95);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .route-method {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .method-query { background: #3b82f6; color: white; }
        .method-mutation { background: #ef4444; color: white; }

        .section-hint {
            color: #7f8c8d;
            font-size: 0.95em;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .section-hint code {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .route-path {
            font-family: 'Courier New', monospace;
            font-size: 1em;
            color: #ffffff;
            margin-bottom: 8px;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.4;
        }

        .route-description {
            font-size: 0.9em;
            color: #d1d5db;
            line-height: 1.5;
            font-weight: 500;
        }

        /* JSON Pretty Print */
        .json-pretty {
            color: #e5e7eb;
            line-height: 1.5;
        }

        .json-response {
            background: #1e293b;
            color: #e5e7eb;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.5;
            overflow-x: auto;
            border: 1px solid #374151;
        }

        .json-response pre {
            margin: 0;
            color: #e5e7eb;
            background: transparent !important;
        }

        .json-response code {
            background: transparent !important;
            color: #e5e7eb !important;
            padding: 0 !important;
            font-family: 'Courier New', monospace !important;
        }

        /* Override Prism.js theme for better integration */
        .json-response .token.property {
            color: #60a5fa !important;
        }

        .json-response .token.string {
            color: #34d399 !important;
        }

        .json-response .token.number {
            color: #fbbf24 !important;
        }

        .json-response .token.boolean {
            color: #f87171 !important;
        }

        .json-response .token.null {
            color: #9ca3af !important;
        }

        .json-response .token.punctuation {
            color: #e5e7eb !important;
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification System */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification i {
            margin-right: 10px;
        }

        .notification-success {
            border-left: 4px solid #10b981;
        }

        .notification-error {
            border-left: 4px solid #ef4444;
        }

        .notification-info {
            border-left: 4px solid #3b82f6;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>
                        <i class="fas fa-bat"></i>
                        Batman Dashboard
                    </h1>
                    <p>API Client - Test and debug your APIs with a comprehensive HTTP client</p>
                    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e8ed;">
                            <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></strong>
                            <?php if (isset($_SESSION['user_email'])): ?>
                                <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                    <div style="display: flex; align-items: center;">
                        <a href="logout.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">
                <i class="fas fa-activity"></i> Activity Logs
            </a>
            <a href="errors.php" class="nav-tab">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="debug.php" class="nav-tab">
                <i class="fas fa-bug"></i> Debug Dashboard
            </a>
            <a href="test.php" class="nav-tab">
                <i class="fas fa-vial"></i> Testing Tools
            </a>
            <a href="api-client.php" class="nav-tab active">
                <i class="fas fa-code"></i> API Client
            </a>
        </div>

        <?php if ($apiError): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($apiError); ?>
            </div>
        <?php endif; ?>

        <!-- Base URL (shared by both the GraphQL and REST explorers below) -->
        <div class="api-client-section">
            <div class="base-url-section">
                <h3><i class="fas fa-link"></i> Base URL</h3>
                <div class="form-group">
                    <label for="base-url">API Base URL</label>
                    <input type="url" id="base-url" name="base_url" value="<?php echo htmlspecialchars($baseUrl); ?>" placeholder="http://localhost:8080" required>
                </div>
            </div>
        </div>

        <!-- GraphQL Explorer -->
        <div class="api-client-section">
            <h2><i class="fas fa-project-diagram"></i> GraphQL API</h2>
            <p class="section-hint">The data API is a single <code>POST /graphql</code> endpoint built at runtime from the DB schema. Click <strong>Discover Schema</strong> to list every query &amp; mutation it currently exposes, or write your own below.</p>

            <div class="discovery-info">
                <button type="button" class="btn btn-secondary" onclick="discoverGraphqlSchema()">
                    <i class="fas fa-search"></i> Discover Schema
                </button>
            </div>

            <div id="discovered-graphql" style="display: none;">
                <h3>Available Operations</h3>
                <div class="routes-grid" id="graphql-operations-list"></div>
            </div>

            <div class="base-url-section">
                <h3><i class="fas fa-key"></i> Log In (only needed if AUTH_TABLE is configured on the target app)</h3>
                <p class="section-hint" style="margin-bottom: 15px;">Posts to <code>POST {base URL}/auth/login</code> and drops the returned <code>access_token</code> straight into the Bearer Token field below.</p>
                <form id="graphql-login-form">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 150px;">
                        <div class="form-group">
                            <label for="graphql-login-email">Email / Username</label>
                            <input type="text" id="graphql-login-email" name="email" placeholder="admin@example.com">
                        </div>
                        <div class="form-group">
                            <label for="graphql-login-password">Password</label>
                            <input type="password" id="graphql-login-password" name="password">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary" id="graphql-login-btn" style="height: 47px;">
                                <i class="fas fa-sign-in-alt"></i> Log In
                            </button>
                        </div>
                    </div>
                </form>
                <div id="graphql-login-message"></div>
            </div>

            <form id="graphql-form">
                <div class="form-group full-width">
                    <label for="graphql-token">Bearer Token</label>
                    <input type="text" id="graphql-token" name="token" placeholder="eyJhbGciOi...">
                </div>

                <div class="form-group full-width">
                    <label for="graphql-query">Query / Mutation</label>
                    <textarea id="graphql-query" name="query" placeholder="{ usersList { id name } }" style="min-height: 140px; font-family: 'Courier New', monospace;"></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="graphql-variables">Variables (JSON)</label>
                    <textarea id="graphql-variables" name="variables" placeholder='{"id": 1}'></textarea>
                </div>

                <button type="submit" class="btn btn-primary" id="graphql-submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Query
                </button>
            </form>
        </div>

        <!-- API Response -->
        <div id="response-container" style="display: none;">
            <div class="response-section">
                <h2><i class="fas fa-reply"></i> Response</h2>

                <div class="response-info">
                    <div class="info-card">
                        <h4>Status</h4>
                        <div class="value" id="response-status">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Duration</h4>
                        <div class="value" id="response-duration">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Size</h4>
                        <div class="value" id="response-size">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Method</h4>
                        <div class="value" id="response-method">-</div>
                    </div>
                </div>
                
                <h3>Response Headers</h3>
                <div class="response-content" id="response-headers">-</div>
                
                <h3>Response Body</h3>
                <div class="response-content" id="response-body">-</div>
            </div>
        </div>

    </div>

    <script>
        // Discover the GraphQL schema (introspected server-side, not over HTTP)
        function discoverGraphqlSchema() {
            fetch('discover-graphql.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('discovered-graphql');
                    const list = document.getElementById('graphql-operations-list');

                    container.style.display = 'block';
                    list.innerHTML = '';

                    if (!data.success) {
                        list.innerHTML = `<p>${data.message || 'Failed to introspect schema'}</p>`;
                        return;
                    }

                    if (!data.operations || data.operations.length === 0) {
                        list.innerHTML = '<p>No tables are registered with the schema yet.</p>';
                        return;
                    }

                    data.operations.forEach(op => {
                        const card = document.createElement('div');
                        card.className = 'route-card';
                        card.innerHTML = `
                            <div class="route-method method-${op.operationType}">${op.operationType}</div>
                            <div class="route-path">${op.name}(${op.args.map(a => a.name + ': ' + a.type).join(', ')}): ${op.returnType}</div>
                            <div class="route-description">Click to load a sample query into the editor below</div>
                        `;
                        card.onclick = () => fillGraphqlFromOperation(op);
                        list.appendChild(card);
                    });
                })
                .catch(error => {
                    const container = document.getElementById('discovered-graphql');
                    const list = document.getElementById('graphql-operations-list');
                    container.style.display = 'block';
                    list.innerHTML = '<p>Error discovering schema: ' + error.message + '</p>';
                });
        }

        // Fill the GraphQL editor from a discovered operation
        function fillGraphqlFromOperation(op) {
            document.getElementById('graphql-query').value = op.sampleQuery;
            document.getElementById('graphql-variables').value = JSON.stringify(op.sampleVariables, null, 2);
        }

        // Remember the base URL + token across page loads (this page never round-trips
        // through PHP anymore — everything is a browser fetch() — so this is client-only).
        function saveGraphqlSession() {
            localStorage.setItem('batmanGraphqlSession', JSON.stringify({
                baseUrl: document.getElementById('base-url').value,
                token: document.getElementById('graphql-token').value,
            }));
        }

        function restoreGraphqlSession() {
            const saved = localStorage.getItem('batmanGraphqlSession');
            if (!saved) return;
            try {
                const session = JSON.parse(saved);
                if (session.baseUrl) document.getElementById('base-url').value = session.baseUrl;
                if (session.token) document.getElementById('graphql-token').value = session.token;
            } catch (e) { /* ignore corrupt storage */ }
        }

        // Log in via Batman's own server-side proxy (proxy.php) — Batman's PHP
        // backend calls {baseUrl}/auth/login itself (server-to-server, like
        // Postman), so this never needs the target app's CORS configured. The
        // browser only ever talks to this same-origin proxy.php.
        function submitGraphqlLogin(event) {
            event.preventDefault();

            const baseUrl = document.getElementById('base-url').value.trim();
            const email = document.getElementById('graphql-login-email').value.trim();
            const password = document.getElementById('graphql-login-password').value;
            const messageEl = document.getElementById('graphql-login-message');
            const loginBtn = document.getElementById('graphql-login-btn');

            if (!baseUrl) {
                alert('Please enter a base URL');
                return;
            }
            if (!email || !password) {
                alert('Please enter both email/username and password');
                return;
            }

            const originalText = loginBtn.innerHTML;
            loginBtn.disabled = true;
            loginBtn.innerHTML = 'Logging in...';
            messageEl.innerHTML = '';

            fetch('proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'login', baseUrl, email, password }),
            })
                .then(response => response.json())
                .then(proxied => {
                    if (!proxied.success) {
                        messageEl.innerHTML = `<div class="error-message" style="margin-top: 15px;"><i class="fas fa-exclamation-triangle"></i> ${proxied.message}</div>`;
                        return;
                    }

                    const data = JSON.parse(proxied.body || '{}');
                    if (proxied.status >= 200 && proxied.status < 300 && data.access_token) {
                        document.getElementById('graphql-token').value = data.access_token;
                        saveGraphqlSession();
                        messageEl.innerHTML = '<div class="success-message" style="margin-top: 15px;"><i class="fas fa-check-circle"></i> Logged in — token filled in below.</div>';
                    } else {
                        const reason = data.error || data.message || `Login failed (HTTP ${proxied.status})`;
                        messageEl.innerHTML = `<div class="error-message" style="margin-top: 15px;"><i class="fas fa-exclamation-triangle"></i> ${reason}</div>`;
                    }
                })
                .catch(error => {
                    messageEl.innerHTML = `<div class="error-message" style="margin-top: 15px;"><i class="fas fa-exclamation-triangle"></i> ${error.message}</div>`;
                })
                .finally(() => {
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = originalText;
                });
        }

        // Send a GraphQL query/mutation via Batman's server-side proxy (proxy.php)
        // rather than fetching {baseUrl}/graphql directly from the browser — see
        // submitGraphqlLogin's comment for why.
        function submitGraphqlRequest(event) {
            event.preventDefault();

            const baseUrl = document.getElementById('base-url').value.trim();
            const token = document.getElementById('graphql-token').value.trim();
            const query = document.getElementById('graphql-query').value.trim();
            const variablesRaw = document.getElementById('graphql-variables').value.trim();

            if (!baseUrl) {
                alert('Please enter a base URL');
                return;
            }
            if (!query) {
                alert('Please enter a query or mutation');
                return;
            }

            let variables = null;
            if (variablesRaw) {
                try {
                    variables = JSON.parse(variablesRaw);
                } catch (e) {
                    alert('Variables must be valid JSON: ' + e.message);
                    return;
                }
            }

            saveGraphqlSession();

            const submitBtn = document.getElementById('graphql-submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;

            fetch('proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'query', baseUrl, token, query, variables }),
            })
                .then(response => response.json())
                .then(proxied => {
                    if (!proxied.success) {
                        displayApiResponse({
                            status: 0,
                            statusText: 'Error',
                            headers: {},
                            content_type: 'text/plain',
                            body: proxied.message,
                        });
                        return;
                    }
                    displayApiResponse({
                        status: proxied.status,
                        statusText: '',
                        headers: proxied.headers || {},
                        content_type: (proxied.headers && (proxied.headers['Content-Type'] || proxied.headers['content-type'])) || 'application/json',
                        body: proxied.body,
                    });
                })
                .catch(error => {
                    displayApiResponse({
                        status: 0,
                        statusText: 'Error',
                        headers: {},
                        content_type: 'text/plain',
                        body: error.message,
                    });
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
        }

        // Display API response
        function displayApiResponse(response) {
            const container = document.getElementById('response-container');
            const statusElement = document.getElementById('response-status');
            const headersElement = document.getElementById('response-headers');
            const bodyElement = document.getElementById('response-body');

            if (!container || !statusElement || !headersElement || !bodyElement) {
                console.error('Response container elements not found');
                return;
            }

            // Show container
            container.style.display = 'block';

            // Update status
            statusElement.textContent = `${response.status} ${response.statusText}`;
            statusElement.className = `status-badge status-${response.status}`;

            // Update headers
            const headersText = Object.entries(response.headers)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\n');
            headersElement.textContent = headersText || 'No headers';

            // Update body
            const bodyContent = response.body || '';
            const contentType = response.content_type || '';

            // Detect if content is JSON
            let isJson = false;
            if (contentType.includes('application/json')) {
                isJson = true;
            } else if (bodyContent.trim().startsWith('{') || bodyContent.trim().startsWith('[')) {
                try {
                    JSON.parse(bodyContent);
                    isJson = true;
                } catch (e) {
                    // Not valid JSON
                }
            }

            if (isJson) {
                try {
                    const parsed = JSON.parse(bodyContent);
                    const formatted = JSON.stringify(parsed, null, 2);
                    
                    // Apply syntax highlighting with Prism.js
                    bodyElement.className = 'json-response';
                    bodyElement.innerHTML = `<pre><code class="language-json">${formatted}</code></pre>`;
                    
                    // Apply Prism.js highlighting
                    if (window.Prism) {
                        Prism.highlightElement(bodyElement.querySelector('code'));
                    }
                } catch (e) {
                    bodyElement.className = 'json-response';
                    bodyElement.innerHTML = `<pre><code class="language-json">${bodyContent}</code></pre>`;
                    if (window.Prism) {
                        Prism.highlightElement(bodyElement.querySelector('code'));
                    }
                }
            } else {
                bodyElement.className = '';
                bodyElement.textContent = bodyContent;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            restoreGraphqlSession();

            document.getElementById('graphql-form').addEventListener('submit', submitGraphqlRequest);
            document.getElementById('graphql-login-form').addEventListener('submit', submitGraphqlLogin);
            document.getElementById('base-url').addEventListener('change', saveGraphqlSession);
            document.getElementById('graphql-token').addEventListener('change', saveGraphqlSession);

            // Add textarea auto-resize
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
    </script>
</body>
</html> 