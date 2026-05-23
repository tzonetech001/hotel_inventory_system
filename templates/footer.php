    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p class="footer-version">Version 1.20 | Powered by TZONETECH</p>
        </div>
    </footer>
    
    <style>
        .footer {
            background: white;
            border-top: 1px solid #E5E7EB;
            padding: 16px 24px;
            margin-left: 260px;
            margin-top: 30px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6B7280;
        }
        
        .footer-version {
            color: #9CA3AF;
        }
        
        @media (max-width: 768px) {
            .footer {
                margin-left: 0;
                padding: 12px 16px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }
        }
    </style>
    
    <script>
        // Auto-hide any alert messages after 5 seconds
        document.querySelectorAll('.alert-success, .alert-error, .alert-info').forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>

<style>
    @keyframes fadeOut {
        to {
            opacity: 0;
            visibility: hidden;
        }
    }
    
    /* Scrollbar Styling */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    
    ::-webkit-scrollbar-track {
        background: #F3F4F6;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: #1E3A8A;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: #2563EB;
    }
    
    /* Loading Animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #E5E7EB;
        border-top-color: #1E3A8A;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
    
    /* Button Hover Effects */
    .btn-primary, .btn-secondary, .action-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .btn-primary:active, .btn-secondary:active, .action-btn:active {
        transform: scale(0.96);
    }
    
    /* Card Hover Effect */
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
</style>