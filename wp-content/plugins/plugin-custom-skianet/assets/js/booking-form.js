(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        
        const form = document.getElementById('booking-form');
        if (!form) return;
        
        const submitBtn = form.querySelector('.btn-submit');
        const responseDiv = document.getElementById('booking-response');
        
        // Gestione submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disabilita bottone
            submitBtn.disabled = true;
            submitBtn.textContent = 'Invio in corso...';
            
            // Nascondi messaggi precedenti
            responseDiv.style.display = 'none';
            responseDiv.className = 'booking-response';
            
            // Invia dati
            fetch(bookingFormData.ajaxurl, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    responseDiv.className = 'booking-response success';
                    responseDiv.textContent = data.data.message;
                    form.reset();
                } else {
                    responseDiv.className = 'booking-response error';
                    responseDiv.textContent = data.data.message || 'Errore durante la prenotazione.';
                }
                responseDiv.style.display = 'block';
            })
            .catch(() => {
                responseDiv.className = 'booking-response error';
                responseDiv.textContent = 'Errore di connessione.';
                responseDiv.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Prenota Ora';
            });
        });
        
    });

})();