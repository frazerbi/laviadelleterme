(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        
        const form = document.getElementById('booking-form');
        if (!form) return;
        
        // Campi del form
        const locationField = document.getElementById('location');
        const dateField = document.getElementById('booking_date');
        const ticketTypeField = document.getElementById('ticket_type');
        const timeSlotField = document.getElementById('time_slot');
        const numMaleField = document.getElementById('num_male');
        const numFemaleField = document.getElementById('num_female');
        const submitBtn = form.querySelector('.btn-submit');
        const responseDiv = document.getElementById('booking-response');
        
        // Apri calendario al click
        dateField.addEventListener('click', function() {
            if (!this.disabled) {
                try {
                    this.showPicker();
                } catch (e) {
                    // Fallback per browser che non supportano showPicker()
                    this.focus();
                }
            }
        });

        // Opzionale: apri calendario al focus
        dateField.addEventListener('focus', function() {
            if (!this.disabled) {
                try {
                    this.showPicker();
                } catch (e) {
                    // Silenzioso se non supportato
                }
            }
        });

        // Dati API
        let apiData = null;
        
        // === GESTIONE PROGRESSIVA DEI CAMPI ===
        locationField.addEventListener('change', function() {
            dateField.disabled = !this.value;
            if (!this.value) {
                dateField.value = '';
                disableFieldsFrom('date');
            }
        });
        
        dateField.addEventListener('change', function() {
            // Reset campi successivi quando cambia la data
            ticketTypeField.value = '';
            ticketTypeField.selectedIndex = 0; // Torna alla prima opzione (placeholder)
            timeSlotField.value = '';
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            
            if (this.value && locationField.value) {
                callAvailabilityAPI(locationField.value, this.value);
            } else {
                disableFieldsFrom('ticket');
            }
        });
        
        ticketTypeField.addEventListener('change', function() {
            timeSlotField.disabled = !this.value;
            if (!this.value) {
                timeSlotField.value = '';
                disableFieldsFrom('time');
            }
        });
        
        timeSlotField.addEventListener('change', function() {
            const isEnabled = !!this.value;
            numMaleField.disabled = !isEnabled;
            numFemaleField.disabled = !isEnabled;
            
            if (!isEnabled) {
                numMaleField.value = '0';
                numFemaleField.value = '0';
                submitBtn.disabled = true;
            } else {
                checkSubmitButton();
            }
        });
        
        numMaleField.addEventListener('input', checkSubmitButton);
        numFemaleField.addEventListener('input', checkSubmitButton);
        
        // === FUNZIONI HELPER ===
        
        function disableFieldsFrom(from) {
            apiData = null;
            
            const fields = {
                'date': [ticketTypeField, timeSlotField, numMaleField, numFemaleField],
                'ticket': [timeSlotField, numMaleField, numFemaleField],
                'time': [numMaleField, numFemaleField]
            };
            
            if (fields[from]) {
                fields[from].forEach(field => {
                    field.disabled = true;
                    field.value = field.type === 'number' ? '0' : '';
                });
            }
            
            submitBtn.disabled = true;
        }
        
        function checkSubmitButton() {
            const total = (parseInt(numMaleField.value) || 0) + (parseInt(numFemaleField.value) || 0);
            submitBtn.disabled = !(total > 0 && total <= 20 && timeSlotField.value);
        }
        
        function showMessage(type, text) {
            responseDiv.className = `booking-response ${type}`;
            responseDiv.textContent = text;
            responseDiv.style.display = 'block';
        }
        
        function hideMessage() {
            responseDiv.style.display = 'none';
        }
        
        // === CHIAMATA API DISPONIBILITÀ ===
        function callAvailabilityAPI(location, date) {
            showMessage('', 'Verifica disponibilità in corso...');
            disableFieldsFrom('ticket');
            
            const formData = new FormData();
            formData.append('action', 'check_availability_api');
            formData.append('nonce', bookingFormData.nonce);
            formData.append('location', location);
            formData.append('booking_date', date);
            
            fetch(bookingFormData.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    apiData = data.data;
                    
                    console.log('Available slots:', apiData.available_slots);
                    console.log('Numero fasce disponibili:', apiData.available_slots ? apiData.available_slots.length : 0);

                    const availableSlots = apiData.available_slots.filter(slot => slot.disponibilita > 0);

                    if (availableSlots.length === 0) {
                        // Nessuno slot disponibile
                        showMessage('error', 'Nessuna fascia oraria disponibile per questa data.');
                        disableFieldsFrom('ticket');
                        return;
                    }

                    // Mostra messaggio di successo
                    showMessage('success', data.data.message || 'Disponibilità verificata!');
                    updateTimeSlots(apiData.available_slots);
                    
                    ticketTypeField.disabled = false;
                } else {
                    showMessage('error', data.data.message || 'Nessuna disponibilità per questa data.');
                    disableFieldsFrom('ticket');
                }
            })
            .catch(error => {
                console.error('Errore API:', error);
                showMessage('error', 'Errore di connessione durante la verifica disponibilità.');
                disableFieldsFrom('ticket');
            });
        }
        
        function updateTimeSlots(slots) {
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            
            if (!slots || slots.length === 0) {
                showMessage('error', 'Nessuna fascia oraria disponibile per questa data.');
                return;
            }
            
            slots.forEach(slot => {
                if (slot.disponibilita > 0) {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    option.textContent = `${slot.time} - ${slot.disponibilita} posti disponibili`;
                    option.dataset.fasciaId = slot.id;
                    timeSlotField.appendChild(option);
                }
            });
        }
        
        // === SUBMIT FORM ===
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Invio in corso...';
            hideMessage();
            
            const formData = new FormData(form);
            if (apiData) {
                formData.append('api_data', JSON.stringify(apiData));
            }
            
            fetch(bookingFormData.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage('success', data.data.message);
                    form.reset();
                    disableFieldsFrom('date');
                    dateField.disabled = true;
                } else {
                    showMessage('error', data.data.message || 'Errore durante la prenotazione.');
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Errore submit:', error);
                showMessage('error', 'Errore di connessione.');
                submitBtn.disabled = false;
            })
            .finally(() => {
                submitBtn.textContent = 'Prenota Ora';
            });
        });
        
    });

})();