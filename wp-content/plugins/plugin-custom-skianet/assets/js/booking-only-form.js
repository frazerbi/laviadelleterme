document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('booking-form-code');
    if (!form) return;

    // Campi del form
    const purchaseCodeInput = document.getElementById('purchase_code');
    const locationRadios = document.querySelectorAll('input[name="location"]');
    const dateField = document.getElementById('booking_date');
    const timeSlotField = document.getElementById('time_slot');
    const genderRadios = document.querySelectorAll('input[name="gender"]');
    const submitBtn = form.querySelector('.btn-submit');
    const responseDiv = document.getElementById('booking-response');

    // Dati API
    let apiData = null;

    // Helper per ottenere la location selezionata
    function getSelectedLocation() {
        const selectedRadio = document.querySelector('input[name="location"]:checked');
        return selectedRadio ? selectedRadio.value : '';
    }

    // Helper per ottenere il genere selezionato
    function getSelectedGender() {
        const selectedRadio = document.querySelector('input[name="gender"]:checked');
        return selectedRadio ? selectedRadio.value : '';
    }

    // Trasforma il codice in maiuscolo mentre digita
    purchaseCodeInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });

    // Abilita le location quando il codice è completo (16 caratteri)
    purchaseCodeInput.addEventListener('input', function() {
        const code = this.value.trim();
        const isValidLength = code.length === 16;
        
        locationRadios.forEach(radio => {
            radio.disabled = !isValidLength;
        });
        
        if (!isValidLength && getSelectedLocation()) {
            // Reset se il codice viene modificato
            locationRadios.forEach(radio => radio.checked = false);
            dateField.value = '';
            dateField.disabled = true;
            disableFieldsFrom('date');
        }
    });

    // === GESTIONE PROGRESSIVA DEI CAMPI ===
    locationRadios.forEach(radio => {
        radio.addEventListener('change', async function() {
            // Reset campi successivi quando cambia la location
            dateField.value = '';
            timeSlotField.value = '';
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            
            hideMessage();
            apiData = null;

            if (this.value) {
                dateField.disabled = false;
            } else {
                dateField.disabled = true;
                disableFieldsFrom('date');
            }
        });
    });

    dateField.addEventListener('change', function() {
        // Reset campi successivi quando cambia la data
        timeSlotField.value = '';
        timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';

        const selectedLocation = getSelectedLocation();
        if (this.value && selectedLocation) {
            callAvailabilityAPI(selectedLocation, this.value);
        } else {
            disableFieldsFrom('time');
        }
    });

    timeSlotField.addEventListener('change', function() {
        checkSubmitButton();
    });

    genderRadios.forEach(radio => {
        radio.addEventListener('change', checkSubmitButton);
    });

    // === FUNZIONI HELPER ===
    function disableFieldsFrom(from) {
        apiData = null;

        const fields = {
            'date': [timeSlotField, genderRadios],
            'time': [genderRadios]
        };

        if (fields[from]) {
            fields[from].forEach(field => {
                if (NodeList.prototype.isPrototypeOf(field)) {
                    field.forEach(radio => radio.disabled = true);
                } else {
                    field.disabled = true;
                    field.value = '';
                }
            });
        }

        submitBtn.disabled = true;
    }

    function checkSubmitButton() {
        const hasCode = purchaseCodeInput.value.trim().length === 16;
        const hasLocation = getSelectedLocation();
        const hasDate = dateField.value;
        const hasTimeSlot = timeSlotField.value;
        const hasGender = getSelectedGender();
        
        submitBtn.disabled = !(hasCode && hasLocation && hasDate && hasTimeSlot && hasGender);
        
        // Abilita il campo gender quando c'è una fascia oraria
        if (hasTimeSlot) {
            genderRadios.forEach(radio => radio.disabled = false);
        }
    }

    function showMessage(type, text) {
        responseDiv.className = `booking-response ${type}`;
        responseDiv.textContent = text;
        responseDiv.style.display = 'block';
        
        // Scroll verso il messaggio
        responseDiv.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
        });
    }

    function hideMessage() {
        responseDiv.style.display = 'none';
    }

    // === CHIAMATA API DISPONIBILITÀ ===
    function callAvailabilityAPI(location, date) {
        showMessage('', 'Verifica disponibilità in corso...');
        disableFieldsFrom('time');

        const formData = new FormData();
        formData.append('action', 'check_availability_api');
        formData.append('nonce', bookingCodeAjax.nonce);
        formData.append('location', location);
        formData.append('booking_date', date);

        fetch(bookingCodeAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                apiData = data.data;

                const availableSlots = apiData.available_slots.filter(slot => slot.disponibilita > 0);

                if (availableSlots.length === 0) {
                    showMessage('error', 'Nessuna fascia oraria disponibile per questa data.');
                    disableFieldsFrom('time');
                    return;
                }

                showMessage('success', data.data.message || 'Disponibilità verificata!');
                updateTimeSlots(apiData.available_slots);

                timeSlotField.disabled = false;
            } else {
                showMessage('error', data.data.message || 'Nessuna disponibilità per questa data.');
                disableFieldsFrom('time');
            }
        })
        .catch(error => {
            console.error('Errore API:', error);
            showMessage('error', 'Errore di connessione durante la verifica disponibilità.');
            disableFieldsFrom('time');
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
                option.value = slot.id; 
                option.textContent = `${slot.time} - ${slot.disponibilita} posti disponibili`;
                option.dataset.time = slot.time;
                option.dataset.categorie = slot.categorie || '';
                option.dataset.disponibilita = slot.disponibilita;
                timeSlotField.appendChild(option);
            }
        });
    }

    // === SUBMIT FORM ===
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const code = purchaseCodeInput.value.trim();
        const selectedLocation = getSelectedLocation();
        const selectedGender = getSelectedGender();

        // Validazione
        if (code.length !== 16) {
            showMessage('error', 'Il codice deve essere di 16 caratteri.');
            return;
        }

        if (!selectedLocation || !dateField.value || !timeSlotField.value || !selectedGender) {
            showMessage('error', 'Compila tutti i campi obbligatori.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Invio in corso...';
        hideMessage();

        const formData = new FormData(form);

        fetch(bookingCodeAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.data.message);

                if (data.data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect_url;
                    }, 1500);
                } else {
                    // Reset form
                    form.reset();
                    disableFieldsFrom('date');
                    dateField.disabled = true;
                    locationRadios.forEach(radio => radio.disabled = true);
                    genderRadios.forEach(radio => radio.disabled = true);
                }

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
            submitBtn.textContent = 'Conferma Prenotazione';
            if (submitBtn.disabled) {
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 2000);
            }
        });
    });
});