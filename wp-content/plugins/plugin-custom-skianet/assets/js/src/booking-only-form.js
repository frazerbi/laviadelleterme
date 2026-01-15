document.addEventListener('DOMContentLoaded', function() {

    console.log('Booking Only Form JS loaded');


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

    // === GESTIONE CODICE ACQUISTO ===
    // Trasforma il codice in maiuscolo mentre digita
    purchaseCodeInput.addEventListener('input', function(e) {
        const code = e.target.value.toUpperCase();
        e.target.value = code;
        
        // Abilita le location quando il codice è completo (16 caratteri)
        if (code.length === 16) {
            enableLocations();
        } else {
            disableLocations();
        }
    });

    function enableLocations() {
        locationRadios.forEach(radio => {
            radio.disabled = false;
        });
        showMessage('success', 'Codice valido! Seleziona una location.');
    }

    function disableLocations() {
        locationRadios.forEach(radio => {
            radio.disabled = true;
            radio.checked = false;
        });
        // Reset tutto se il codice viene modificato
        resetFromLocation();
    }

    function resetFromLocation() {
        dateField.value = '';
        dateField.disabled = true;
        timeSlotField.value = '';
        timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
        timeSlotField.disabled = true;
        genderRadios.forEach(radio => {
            radio.disabled = true;
            radio.checked = false;
        });
        submitBtn.disabled = true;
        hideMessage();
    }

    // === GESTIONE LOCATION ===
    locationRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                // Abilita il campo data
                dateField.disabled = false;
                
                // Reset campi successivi
                dateField.value = '';
                timeSlotField.value = '';
                timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
                timeSlotField.disabled = true;
                genderRadios.forEach(radio => {
                    radio.disabled = true;
                    radio.checked = false;
                });
                submitBtn.disabled = true;
                
                hideMessage();
                apiData = null;
            }
        });
    });

    // === GESTIONE DATA ===
    dateField.addEventListener('change', function() {
        const selectedLocation = getSelectedLocation();
        
        if (this.value && selectedLocation) {
            // Reset campi successivi
            timeSlotField.value = '';
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            genderRadios.forEach(radio => {
                radio.disabled = true;
                radio.checked = false;
            });
            submitBtn.disabled = true;
            
            // Chiama API disponibilità
            callAvailabilityAPI(selectedLocation, this.value);
        } else {
            timeSlotField.disabled = true;
            genderRadios.forEach(radio => radio.disabled = true);
            submitBtn.disabled = true;
        }
    });

    // === GESTIONE FASCIA ORARIA ===
    timeSlotField.addEventListener('change', function() {
        if (this.value) {
            // Abilita il campo gender
            genderRadios.forEach(radio => {
                radio.disabled = false;
            });
            
            // Aggiungi dati nascosti (se necessario)
            const selectedOption = this.options[this.selectedIndex];
            const categorie = selectedOption.dataset.categorie || '';
            const disponibilita = selectedOption.dataset.disponibilita || '0';
            
            let categorieInput = document.getElementById('selected_categorie');
            if (!categorieInput) {
                categorieInput = document.createElement('input');
                categorieInput.type = 'hidden';
                categorieInput.id = 'selected_categorie';
                categorieInput.name = 'categorie';
                form.appendChild(categorieInput);
            }
            categorieInput.value = categorie;
            
            let disponibilitaInput = document.getElementById('selected_disponibilita');
            if (!disponibilitaInput) {
                disponibilitaInput = document.createElement('input');
                disponibilitaInput.type = 'hidden';
                disponibilitaInput.id = 'selected_disponibilita';
                disponibilitaInput.name = 'disponibilita';
                form.appendChild(disponibilitaInput);
            }
            disponibilitaInput.value = disponibilita;
        } else {
            genderRadios.forEach(radio => {
                radio.disabled = true;
                radio.checked = false;
            });
            submitBtn.disabled = true;
        }
        
        checkSubmitButton();
    });

    // === GESTIONE GENDER ===
    genderRadios.forEach(radio => {
        radio.addEventListener('change', checkSubmitButton);
    });

    // === FUNZIONI HELPER ===
    function checkSubmitButton() {
        const hasCode = purchaseCodeInput.value.trim().length === 16;
        const hasLocation = getSelectedLocation();
        const hasDate = dateField.value;
        const hasTimeSlot = timeSlotField.value;
        const hasGender = getSelectedGender();
        
        submitBtn.disabled = !(hasCode && hasLocation && hasDate && hasTimeSlot && hasGender);
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
        timeSlotField.disabled = true;
        timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';

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
                    timeSlotField.disabled = true;
                    return;
                }

                showMessage('success', data.data.message || 'Disponibilità verificata!');
                updateTimeSlots(apiData.available_slots);
                timeSlotField.disabled = false;

            } else {
                showMessage('error', data.data.message || 'Nessuna disponibilità per questa data.');
                timeSlotField.disabled = true;
            }
        })
        .catch(error => {
            console.error('Errore API:', error);
            showMessage('error', 'Errore di connessione durante la verifica disponibilità.');
            timeSlotField.disabled = true;
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
                    disableLocations();
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