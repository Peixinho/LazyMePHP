<style>
.welcome-container {
    text-align: center;
    padding: 50px 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf3 100%);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    margin-top: 2rem;
}
.welcome-title {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 10px;
    color: #1a202c;
    letter-spacing: -1.5px;
}
.welcome-subtitle {
    font-size: 1.25rem;
    margin-bottom: 40px;
    color: #4a5568;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.welcome-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-top: 40px;
    text-align: left;
}
.welcome-card {
    background: #ffffff;
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.welcome-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 45px rgba(0,0,0,0.1);
}
.welcome-card h3 {
    font-size: 1.5rem;
    margin-bottom: 15px;
    color: #2d3748;
}
.welcome-card p {
    font-size: 1rem;
    line-height: 1.6;
    color: #4a5568;
}
.welcome-card code {
    background: #edf2f7;
    color: #4a5568;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
}
.welcome-card a {
    display: inline-block;
    margin-top: 20px;
    text-decoration: none;
    color: #4299e1;
    font-weight: 600;
    transition: color 0.2s ease;
}
.welcome-card a:hover {
    color: #2b6cb0;
    text-decoration: underline;
}
</style>

<div class="welcome-container">
    <div class="welcome-grid">
        <div class="welcome-card">
            <h3>Your Home Page</h3>
            <p>This is your default landing page. You can customize it by editing the file at:<br><code>App/Views/_Index/index.blade.php</code></p>
            <a href="https://github.com/DuartePeixinho/LazyMePHP/wiki" target="_blank">Read the Docs &rarr;</a>
        </div>
        <div class="welcome-card">
            <h3>Routing</h3>
            <p>Define your application's URLs in the <code>App/Routes/</code> directory. The default routes are located in <code>Routes.php</code>.</p>
            <a href="https://github.com/DuartePeixinho/LazyMePHP/wiki" target="_blank">Learn about Routing &rarr;</a>
        </div>
        <div class="welcome-card">
            <h3>Models & Database</h3>
            <p>Use the built-in generator to create your models and interact with your database effortlessly. See the wiki for details.</p>
            <a href="https://github.com/DuartePeixinho/LazyMePHP/wiki" target="_blank">Database Guide &rarr;</a>
        </div>
        <div class="welcome-card">
            <h3>Create an API</h3>
            <p>Build powerful APIs by creating handler files in <code>App/Api/</code>. Your endpoints will become available at <code>/api/...</code>.</p>
            <a href="https://github.com/DuartePeixinho/LazyMePHP/wiki" target="_blank">API Development &rarr;</a>
        </div>
    </div>
</div> 