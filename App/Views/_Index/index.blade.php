@php
    $dbOk = false;
    $dbType = strtoupper(\Core\LazyMePHP::DB_TYPE() ?? '');
    $dbName = \Core\LazyMePHP::DB_NAME() ?? '';
    try {
        $conn = \Core\LazyMePHP::DB_CONNECTION();
        $dbOk = $conn !== null;
    } catch (\Throwable) {}
    $phpVersion = PHP_VERSION;
    $env = $_ENV['APP_ENV'] ?? 'production';
@endphp

<style>
.lm-welcome {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 960px;
    margin: 2rem auto;
    padding: 0 1rem;
    color: #1a202c;
}
.lm-hero {
    text-align: center;
    padding: 3rem 1rem 2rem;
    background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
    border-radius: 16px;
    margin-bottom: 2rem;
}
.lm-hero h1 { font-size: 2.8rem; font-weight: 800; margin: 0 0 .5rem; letter-spacing: -1px; }
.lm-hero p  { font-size: 1.1rem; color: #4a5568; margin: 0; }
.lm-status {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 1.5rem;
}
.lm-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .85rem;
    border-radius: 999px;
    font-size: .8rem;
    font-weight: 600;
    border: 1.5px solid;
}
.lm-badge-ok   { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
.lm-badge-warn { background:#fffbeb; color:#92400e; border-color:#fde68a; }
.lm-badge-info { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
.lm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.lm-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    transition: transform .2s, box-shadow .2s;
}
.lm-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.lm-card h3 { font-size: 1.1rem; margin: 0 0 .6rem; color: #2d3748; }
.lm-card p  { font-size: .9rem; color: #4a5568; line-height: 1.6; margin: 0 0 1rem; }
.lm-card a  { font-size: .875rem; font-weight: 600; color: #4299e1; text-decoration: none; }
.lm-card a:hover { text-decoration: underline; color: #2b6cb0; }
.lm-quickstart {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.75rem;
    margin-bottom: 2rem;
}
.lm-quickstart h2 { margin: 0 0 1.25rem; font-size: 1.3rem; color: #2d3748; }
.lm-tabs { display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
.lm-tab {
    padding:.35rem .85rem;
    border-radius:6px;
    font-size:.8rem;
    font-weight:600;
    cursor:pointer;
    border:1.5px solid #e2e8f0;
    background:#f8fafc;
    color:#4a5568;
    transition:all .15s;
}
.lm-tab.active, .lm-tab:hover { background:#4299e1; color:#fff; border-color:#4299e1; }
.lm-snippet { display:none; }
.lm-snippet.active { display:block; }
.lm-snippet pre {
    background:#1e2433;
    color:#e2e8f0;
    padding:1.25rem;
    border-radius:8px;
    overflow-x:auto;
    font-size:.82rem;
    line-height:1.6;
    margin:0;
}
.lm-snippet pre .cm { color:#6b7280; }
.lm-snippet pre .kw { color:#93c5fd; }
.lm-snippet pre .fn { color:#86efac; }
.lm-snippet pre .st { color:#fca5a5; }
.lm-snippet pre .nu { color:#fbbf24; }
</style>

<div class="lm-welcome">
    <div class="lm-hero">
        <h1>LazyMePHP</h1>
        <p>DB-first PHP framework. Zero code generation. Introspect, query, ship.</p>
        <div class="lm-status">
            <span class="lm-badge lm-badge-info">PHP {{ $phpVersion }}</span>
            @if($dbOk)
                <span class="lm-badge lm-badge-ok">&#10003; {{ $dbType }} connected</span>
            @else
                <span class="lm-badge lm-badge-warn">&#9888; No DB connection</span>
            @endif
            <span class="lm-badge lm-badge-info">ENV: {{ $env }}</span>
        </div>
    </div>

    <div class="lm-quickstart">
        <h2>Quick Start</h2>
        <div class="lm-tabs">
            <span class="lm-tab active" onclick="lmTab(this,'query')">Query</span>
            <span class="lm-tab" onclick="lmTab(this,'api')">API Resource</span>
            <span class="lm-tab" onclick="lmTab(this,'paginate')">Pagination</span>
            <span class="lm-tab" onclick="lmTab(this,'notify')">Notifications</span>
        </div>

        <div id="lm-query" class="lm-snippet active">
<pre><span class="cm">// No model class needed — point at any table and go</span>
<span class="kw">use</span> Core\Model;

$users = Model::<span class="fn">query</span>(<span class="st">'users'</span>)
    -><span class="fn">where</span>(<span class="st">'active'</span>, <span class="nu">1</span>)
    -><span class="fn">with</span>(<span class="st">'posts'</span>)       <span class="cm">// eager-load relationship</span>
    -><span class="fn">orderBy</span>(<span class="st">'name'</span>)
    -><span class="fn">get</span>();

<span class="cm">// Create / update / delete</span>
$user = <span class="kw">new</span> Model(<span class="st">'users'</span>);
$user->name  = <span class="st">'Alice'</span>;
$user->email = <span class="st">'alice@example.com'</span>;
$user-><span class="fn">save</span>();</pre>
        </div>

        <div id="lm-api" class="lm-snippet">
<pre><span class="cm">// App/Resources/UserResource.php</span>
<span class="kw">class</span> UserResource <span class="kw">extends</span> \Core\Http\ApiResource
{
    <span class="kw">public function</span> <span class="fn">toArray</span>(): <span class="kw">array</span>
    {
        <span class="kw">return</span> [
            <span class="st">'id'</span>    => $this->model->id,
            <span class="st">'name'</span>  => $this->model->name,
            <span class="cm">// 'password' omitted</span>
        ];
    }
}

<span class="cm">// In a route handler:</span>
$page = Model::<span class="fn">query</span>(<span class="st">'users'</span>)-><span class="fn">paginate</span>(<span class="nu">15</span>, (int)($_GET[<span class="st">'page'</span>] ?? <span class="nu">1</span>));
UserResource::<span class="fn">fromPaginator</span>($page)-><span class="fn">respond</span>();</pre>
        </div>

        <div id="lm-paginate" class="lm-snippet">
@verbatim
<pre><span class="cm">// In your controller</span>
$page = Model::<span class="fn">query</span>(<span class="st">'products'</span>)-><span class="fn">paginate</span>(<span class="nu">20</span>, (int)($_GET[<span class="st">'page'</span>] ?? <span class="nu">1</span>));

<span class="cm">// Pass to view</span>
<span class="kw">echo</span> $blade-><span class="fn">run</span>(<span class="st">'products.index'</span>, [<span class="st">'page'</span> => $page]);

<span class="cm">// In products/index.blade.php</span>
@foreach($page[<span class="st">'data'</span>] <span class="kw">as</span> $product)
    &lt;p&gt;{{ $product->name }}&lt;/p&gt;
@endforeach

@pagination($page)   <span class="cm">// renders prev/next/numbered page links</span></pre>
@endverbatim
        </div>

        <div id="lm-notify" class="lm-snippet">
<pre><span class="cm">// Flash a message that survives a redirect (POST → Redirect → GET)</span>
<span class="kw">use</span> Messages\Messages;

Messages::<span class="fn">Success</span>(<span class="st">'Record saved successfully.'</span>);
Messages::<span class="fn">RecordCreated</span>(<span class="st">'User'</span>);   <span class="cm">// → "User created successfully."</span>
Messages::<span class="fn">Error</span>(<span class="st">'Something went wrong.'</span>);
Messages::<span class="fn">ValidationErrors</span>($formRequest-><span class="fn">errors</span>());

<span class="cm">// Shown automatically on the next page via @include('_Notifications.notifications')</span>
header(<span class="st">'Location: /users'</span>);
exit;</pre>
        </div>
    </div>

    <div class="lm-grid">
        <div class="lm-card">
            <h3>Documentation</h3>
            <p>Full guides for the ORM, authentication, routing, API resources, migrations, and more.</p>
            <a href="/docs/intro">Read the Docs &rarr;</a>
        </div>
        <div class="lm-card">
            <h3>Routing</h3>
            <p>Define routes in <code>App/Routes/</code>. Call <code>LazyMePHP::boot($blade)</code> to auto-wire CRUD routes for every DB table.</p>
            <a href="/docs/routing">Routing Guide &rarr;</a>
        </div>
        <div class="lm-card">
            <h3>Models &amp; ORM</h3>
            <p>Point at any table and query it — no model class required. Relationships, eager loading, scopes, and pagination built in.</p>
            <a href="/docs/orm/basic-crud">ORM Guide &rarr;</a>
        </div>
        <div class="lm-card">
            <h3>API Development</h3>
            <p>Shape responses with <code>ApiResource</code>, validate with <code>FormRequest</code>, and protect with JWT and RBAC.</p>
            <a href="/docs/http/api-resources">API Guide &rarr;</a>
        </div>
    </div>
</div>

<script>
function lmTab(el, id) {
    document.querySelectorAll('.lm-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.lm-snippet').forEach(s => s.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('lm-' + id).classList.add('active');
}
</script>
