document.addEventListener('DOMContentLoaded', function() {
    
    const verifyButton = document.getElementById('verify-code');
    const purchaseCodeInput = document.getElementById('purchase_code');
    const responseDiv = document.getElementById('booking-response');
    
    // Gestione verifica codice
    if (verifyButton) {
        verifyButton.addEventListener('click', function() {
            const code = purchaseCodeInput.value.trim().toUpperCase();
            
            if (!code) {
                showMessage('Inserisci un codice valido', 'error');
                return;
            }
            
            // Disabilita il pulsante durante la verifica
            verifyButton.disabled = true;
            verifyButton.textContent = 'Verifica in corso...';
            
            // Chiamata AJAX per verificare il codice
            fetch(bookingCodeAjax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'verify_purchase_code',
                    code: code,
                    nonce: bookingCodeAjax.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.data.message, 'success');
                    verifyButton.textContent = '✓ Verificato';
                    verifyButton.classList.add('verified');
                    purchaseCodeInput.readOnly = true;
                    
                    // Abilita i campi successivi se necessario
                    // enableFormFields();
                    
                } else {
                    showMessage(data.data.message || 'Codice non valido', 'error');
                    verifyButton.disabled = false;
                    verifyButton.textContent = 'Verifica Codice';
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showMessage('Errore di connessione. Riprova.', 'error');
                verifyButton.disabled = false;
                verifyButton.textContent = 'Verifica Codice';
            });
        });
    }
    
    // Trasforma in maiuscolo mentre digita
    if (purchaseCodeInput) {
        purchaseCodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    /**
     * Funzione per mostrare messaggi
     */
    function showMessage(message, type) {
        if (!responseDiv) return;
        
        responseDiv.className = 'booking-response ' + type;
        responseDiv.innerHTML = message;
        responseDiv.style.display = 'block';
        
        // Nascondi dopo 5 secondi se è un messaggio di successo
        if (type === 'success') {
            setTimeout(function() {
                responseDiv.style.display = 'none';
            }, 5000);
        }
    }
    
    /**
     * Funzione per abilitare i campi del form (da implementare)
     */
    function enableFormFields() {
        // Questa funzione verrà espansa quando aggiungeremo gli altri campi
        console.log('Abilitazione campi form...');
    }
    
});