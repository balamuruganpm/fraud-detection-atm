</div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Session timeout countdown
        document.addEventListener('DOMContentLoaded', function() {
            let sessionTimeout = <?php echo $sessionTimeout ?? 1800; ?>; // 30 minutes in seconds
            const timerElement = document.getElementById('session-timer');
            
            if (timerElement) {
                const countdownInterval = setInterval(function() {
                    sessionTimeout--;
                    if (sessionTimeout <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = '../auth/logout.php?timeout=1';
                    } else {
                        const minutes = Math.floor(sessionTimeout / 60);
                        const seconds = sessionTimeout % 60;
                        timerElement.textContent = `Session: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                        
                        // Change color when less than 5 minutes remaining
                        if (sessionTimeout < 300) {
                            timerElement.style.color = '#dc322f';
                        }
                    }
                }, 1000);
                
                // Reset timer on user activity
                const resetTimer = function() {
                    // Send AJAX request to update session
                    fetch('update_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    }).then(response => {
                        if (response.ok) {
                            sessionTimeout = <?php echo $sessionTimeout ?? 1800; ?>;
                            timerElement.style.color = '#93a1a1';
                        }
                    });
                };
                
                // Events that reset the timer
                document.addEventListener('click', resetTimer);
                document.addEventListener('keypress', resetTimer);
                document.addEventListener('scroll', resetTimer);
                document.addEventListener('mousemove', resetTimer);
            }
        });
        
        <?php echo $customScripts ?? ''; ?>
    </script>
</body>
</html>
