(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        
        const form = document.getElementById('booking-form');
        if (!form) return;
        
        const submitBtn = form.querySelector('.btn-submit');
        const responseDiv = document.getElementById('booking-response');
        
        // Campi del form
        const locationField = document.getElementById('location');
        const dateField = document.getElementById('booking_date');
        const ticketTypeField = document.getElementById('ticket_type');
        const timeSlotField = document.getElementById('time_slot');
        const numMaleField = document.getElementById('num_male');
        const numFemaleField = document.getElementById('num_female');
        
        // Gestione abilitazione progressiva dei campi
        locationField.addEventListener('change', function() {
            if (this.value) {
                dateField.disabled = false;
            } else {
                dateField.disabled = true;
                dateField.value = '';
                resetFollowingFields('date');
            }
        });
        
        dateField.addEventListener('change', function() {
            if (this.value) {
                ticketTypeField.disabled = false;
            } else {
                ticketTypeField.disabled = true;
                ticketTypeField.value = '';
                resetFollowingFields('ticket');
            }
        });
        
        ticketTypeField.addEventListener('change', function() {
            if (this.value) {
                timeSlotField.disabled = false;
            } else {
                timeSlotField.disabled = true;
                timeSlotField.value = '';
                resetFollowingFields('time');
            }
        });
        
        timeSlotField.addEventListener('change', function() {
            if (this.value) {
                numMaleField.disabled = false;
                numFemaleField.disabled = false;
                checkSubmitButton();
            } else {
                numMaleField.disabled = true;
                numFemaleField.disabled = true;
                numMaleField.value = '0';
                numFemaleField.value = '0';
                submitBtn.disabled = true;
            }
        });
        
        // Controlla se abilitare il bottone submit
        numMaleField.addEventListener('input', checkSubmitButton);
        numFemaleField.addEventListener('input', checkSubmitButton);
        
        function checkSubmitButton() {
            const male = parseInt(numMaleField.value) || 0;
            const female = parseInt(numFemaleField.value) || 0;
            const total = male + female;
            
            if (total > 0 && total <= 20 && timeSlotField.value) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }
        
        function resetFollowingFields(from) {
            if (from === 'date') {
                ticketTypeField.disabled = true;
                ticketTypeField.value = '';
                timeSlotField.disabled = true;
                timeSlotField.value = '';
                numMaleField.disabled = true;
                numMaleField.value = '0';
                numFemaleField.disabled = true;
                numFemaleField.value = '0';
                submitBtn.disabled = true;
            } else if (from === 'ticket') {
                timeSlotField.disabled = true;
                timeSlotField.value = '';
                numMaleField.disabled = true;
                numMaleField.value = '0';
                numFemaleField.disabled = true;
                numFemaleField.value = '0';
                submitBtn.disabled = true;
            } else if (from === 'time') {
                numMaleField.disabled = true;
                numMaleField.value = '0';
                numFemaleField.disabled = true;
                numFemaleField.value = '0';
                submitBtn.disabled = true;
            }
        }
        
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
                    
                    // Resetta lo stato dei campi
                    dateField.disabled = true;
                    ticketTypeField.disabled = true;
                    timeSlotField.disabled = true;
                    numMaleField.disabled = true;
                    numFemaleField.disabled = true;
                    submitBtn.disabled = true;
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
                if (!submitBtn.disabled) {
                    submitBtn.disabled = false;
                }
                submitBtn.textContent = 'Prenota Ora';
            });
        });
        
    });

})();