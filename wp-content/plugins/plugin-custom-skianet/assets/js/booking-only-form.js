document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('booking-form-code');
    if (!form) return;

    // Campi del form
    const purchaseCodeInput = document.getElementById('purchase_code');
    const verifyButton = document.getElementById('verify-code');
    const locationRadios = document.querySelectorAll('input[name="location"]');
    const dateField = document.getElementById('booking_date');
    const timeSlotField = document.getElementById('time_slot');
    const genderRadios = document.querySelectorAll('input[name="gender"]');
    const submitBtn = form.querySelector('.btn-submit');
    const responseDiv = document.getElementById('booking-response');

    // Stato del codice verificato
    let codeVerified = false;
    let orderData = null;

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

    // Dati API
    let apiData = null;

    // === VERIFICA CODICE ===
    verifyButton.addEventListener('click', handleCodeVerification);

    purchaseCodeInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });

    purchaseCodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyButton.click();
        }
    });

    function handleCodeVerification() {
        const code = purchaseCodeInput.value.trim().toUpperCase();
        
        if (!validateCode(code)) {
            showMessage('error', 'Inserisci un codice valido (16 caratteri alfanumerici)');
            return;
        }
        
        verifyCode(code);
    }

    function validateCode(code) {
        const regex = /^[A-Z0-9]{16}$/;
        return regex.test(code);
    }

    function verifyCode(code) {
        setButtonState('loading');
        
        const formData = new FormData();
        formData.append('action', 'verify_purchase_code');
        formData.append('code', code);
        formData.append('nonce', bookingCodeAjax.nonce);
        
        fetch(bookingCodeAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Errore di rete');
            }
            return response.json();
        })
        .then(handleVerificationResponse)
        .catch(handleVerificationError);
    }

    function handleVerificationResponse(data) {
        if (data.success) {
            showMessage('success', data.data.message);
            setButtonState('verified');
            purchaseCodeInput.readOnly = true;
            codeVerified = true;
            
            // Salva i dati dell'ordine se presenti
            if (data.data.order_data) {
                orderData = data.data.order_data;
            }
            
            // Abilita i campi del form
            enableFormFields();
            
        } else {
            showMessage('error', data.data.message || 'Codice non valido');
            setButtonState('default');
        }
    }

    function handleVerificationError(error) {
        console.error('Errore verifica:', error);
        showMessage('error', 'Errore di connessione. Riprova.');
        setButtonState('default');
    }

    function setButtonState(state) {
        switch(state) {
            case 'loading':
                verifyButton.disabled = true;
                verifyButton.textContent = 'Verifica in corso...';
                verifyButton.classList.remove('verified');
                break;
                
            case 'verified':
                verifyButton.disabled = true;
                verifyButton.textContent = '✓ Verificato';
                verifyButton.classList.add('verified');
                break;
                
            case 'default':
            default:
                verifyButton.disabled = false;
                verifyButton.textContent = 'Verifica Codice';
                verifyButton.classList.remove('verified');
                break;
        }
    }

    function enableFormFields() {
        // Abilita location
        locationRadios.forEach(radio => {
            radio.disabled = false;
        });
        
        // Abilita gender
        genderRadios.forEach(radio => {
            radio.disabled = false;
        });
        
        // Data rimane disabilitata finché non si seleziona una location
        console.log('Campi abilitati - seleziona una location per continuare');
    }

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
            'date': [timeSlotField],
            'time': []
        };

        if (fields[from]) {
            fields[from].forEach(field => {
                field.disabled = true;
                field.value = '';
            });
        }

        submitBtn.disabled = true;
    }

    function checkSubmitButton() {
        const hasLocation = getSelectedLocation();
        const hasDate = dateField.value;
        const hasTimeSlot = timeSlotField.value;
        const hasGender = getSelectedGender();
        
        submitBtn.disabled = !(hasLocation && hasDate && hasTimeSlot && hasGender);
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

        if (!codeVerified) {
            showMessage('error', 'Verifica prima il codice acquisto.');
            return;
        }

        const selectedLocation = getSelectedLocation();
        const selectedGender = getSelectedGender();

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
                    codeVerified = false;
                    orderData = null;
                    purchaseCodeInput.readOnly = false;
                    setButtonState('default');
                    disableFieldsFrom('date');
                    dateField.disabled = true;
                    
                    // Disabilita tutti i radio
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