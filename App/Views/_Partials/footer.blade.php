<style>
/* Footer Styling */
.footer {
    background: linear-gradient(135deg, #212529 0%, #343a40 100%);
    color: #ffffff;
    padding: 2rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto; /* This pushes the footer to the bottom */
    flex-shrink: 0; /* Prevents footer from shrinking */
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-content {
    flex: 1;
}

.footer-content p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.footer-links ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.footer-links li {
    font-size: 0.9rem;
    opacity: 0.9;
}

.footer-links a {
    color: #ffffff;
    text-decoration: none;
    transition: color 0.3s ease-in-out;
}

.footer-links a:hover {
    color: #007bff;
}

/* Responsive footer */
@media (max-width: 768px) {
    .footer-container {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .footer-links ul {
        justify-content: center;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .footer {
        padding: 1.5rem 0;
    }
    
    .footer-links ul {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <p>&copy; {{ date('Y') }} LazyMePHP. All rights reserved.</p>
        </div>
        <div class="footer-links">
            <ul>
                <li>LazyMePHP v1.0</li>
                <li><a href="/api" target="_blank">API Docs</a></li>
                <li><a href="/batman" target="_blank">Batman Debugger</a></li>
            </ul>
        </div>
    </div>
</footer> 