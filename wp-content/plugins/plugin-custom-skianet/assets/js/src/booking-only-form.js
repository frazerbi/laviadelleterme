/**
 * Booking Only Form - JavaScript Handler
 * Gestisce l'abilitazione progressiva dei campi del form
 */

(function() {
    'use strict';

    class BookingOnlyForm {
        constructor() {
            this.form = document.getElementById('booking-only-form');
            if (!this.form) return;

            this.purchaseCode = document.getElementById('purchase_code');
            this.locations = document.querySelectorAll('input[name="location"]');
            this.dateInput = document.getElementById('booking-only_date');
            this.timeSlot = document.getElementById('time_slot');
            this.genderInputs = document.querySelectorAll('input[name="gender"]');
            this.response = document.getElementById('booking-only-response');
            this.submitBtn = this.form.querySelector('button[type="submit"]');

            this.init();
        }

        init() {
            // Disabilita tutti i campi tranne il codice acquisto
            this.disableAllFields();

            // Event listeners
            this.purchaseCode.addEventListener('input', () => this.handlePurchaseCodeInput());
            
            this.locations.forEach(location => {
                location.addEventListener('change', () => this.handleLocationChange());
            });

            this.dateInput.addEventListener('change', () => this.handleDateChange());
            this.timeSlot.addEventListener('change', () => this.handleTimeSlotChange());
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        /**
         * Disabilita tutti i campi tranne il codice acquisto
         */
        disableAllFields() {
            this.disableLocations();
            this.disableDate();
            this.disableTimeSlot();
            this.disableGender();
            this.submitBtn.disabled = true;
        }

        /**
         * Gestisce l'input del codice acquisto
         */
        handlePurchaseCodeInput() {
            const code = this.purchaseCode.value.trim().toUpperCase();
            this.purchaseCode.value = code; // Forza maiuscolo
            
            // Valida il formato del codice (lettere maiuscole e numeri, minimo 10 caratteri)
            const isValidFormat = /^[A-Z0-9]{10,}$/.test(code);

            if (isValidFormat && code.length >= 10) {
                // Verifica il codice via AJAX
                this.verifyPurchaseCode(code);
            } else {
                // Se il codice non è valido, disabilita tutto
                this.disableLocations();
                this.disableDate();
                this.disableTimeSlot();
                this.disableGender();
                this.submitBtn.disabled = true;
                this.clearMessage();
            }
        }

        /**
         * Verifica il codice acquisto via AJAX
         */
        verifyPurchaseCode(code) {
            this.showMessage('Verifica del codice in corso...', 'info');

            const formData = new FormData();
            formData.append('action', 'verify_purchase_code');
            formData.append('nonce', bookingOnlyAjax.nonce);
            formData.append('purchase_code', code);

            fetch(bookingOnlyAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showMessage(data.data.message, 'success');
                    this.enableLocations();
                } else {
                    this.showMessage(data.data.message || 'Codice non valido', 'error');
                    this.disableLocations();
                }
            })
            .catch(() => {
                this.showMessage('Errore di connessione. Riprova.', 'error');
                this.disableLocations();
            });
        }

        /**
         * Abilita la selezione delle location
         */
        enableLocations() {
            this.locations.forEach(location => {
                location.disabled = false;
                const visualItem = location.closest('.visualradio-item');
                if (visualItem) {
                    visualItem.classList.remove('disabled');
                }
            });
        }

        /**
         * Disabilita la selezione delle location
         */
        disableLocations() {
            this.locations.forEach(location => {
                location.disabled = true;
                location.checked = false;
                const visualItem = location.closest('.visualradio-item');
                if (visualItem) {
                    visualItem.classList.add('disabled');
                }
            });
        }

        /**
         * Gestisce il cambio di location
         */
        handleLocationChange() {
            const selectedLocation = this.getSelectedLocation();
            
            if (selectedLocation) {
                this.enableDate();
            } else {
                this.disableDate();
                this.disableTimeSlot();
                this.disableGender();
                this.submitBtn.disabled = true;
            }
        }

        /**
         * Ottiene la location selezionata
         */
        getSelectedLocation() {
            const selected = Array.from(this.locations).find(loc => loc.checked);
            return selected ? selected.value : null;
        }

        /**
         * Abilita la selezione della data
         */
        enableDate() {
            this.dateInput.disabled = false;
        }

        /**
         * Disabilita la selezione della data
         */
        disableDate() {
            this.dateInput.disabled = true;
            this.dateInput.value = '';
        }

        /**
         * Gestisce il cambio di data
         */
        handleDateChange() {
            const selectedDate = this.dateInput.value;
            const selectedLocation = this.getSelectedLocation();
            
            if (selectedDate && selectedLocation) {
                this.loadTimeSlots(selectedLocation, selectedDate);
            } else {
                this.disableTimeSlot();
                this.disableGender();
                this.submitBtn.disabled = true;
            }
        }

        /**
         * Carica le fasce orarie disponibili via AJAX
         */
        loadTimeSlots(location, date) {
            this.showMessage('Caricamento fasce orarie...', 'info');
            this.timeSlot.disabled = true;
            this.timeSlot.innerHTML = '<option value="">Caricamento...</option>';

            const formData = new FormData();
            formData.append('action', 'get_available_time_slots');
            formData.append('nonce', bookingOnlyAjax.nonce);
            formData.append('location', location);
            formData.append('date', date);

            fetch(bookingOnlyAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.slots.length > 0) {
                    this.populateTimeSlots(data.data.slots);
                    this.enableTimeSlot();
                    this.clearMessage();
                } else {
                    this.timeSlot.innerHTML = '<option value="">Nessuna fascia oraria disponibile</option>';
                    this.showMessage('Nessuna fascia oraria disponibile per questa data', 'warning');
                    this.disableTimeSlot();
                }
            })
            .catch(() => {
                this.timeSlot.innerHTML = '<option value="">Errore caricamento</option>';
                this.showMessage('Errore nel caricamento delle fasce orarie', 'error');
                this.disableTimeSlot();
            });
        }

        /**
         * Popola il select delle fasce orarie
         */
        populateTimeSlots(slots) {
            let options = '<option value="">-- Seleziona una fascia oraria --</option>';
            
            slots.forEach(slot => {
                options += `<option value="${slot.value}">${slot.label}</option>`;
            });
            
            this.timeSlot.innerHTML = options;
        }

        /**
         * Abilita la selezione della fascia oraria
         */
        enableTimeSlot() {
            this.timeSlot.disabled = false;
        }

        /**
         * Disabilita la selezione della fascia oraria
         */
        disableTimeSlot() {
            this.timeSlot.disabled = true;
            this.timeSlot.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
        }

        /**
         * Gestisce il cambio di fascia oraria
         */
        handleTimeSlotChange() {
            const selectedTimeSlot = this.timeSlot.value;
            
            if (selectedTimeSlot) {
                this.enableGender();
            } else {
                this.disableGender();
                this.submitBtn.disabled = true;
            }
        }

        /**
         * Abilita la selezione del sesso
         */
        enableGender() {
            this.genderInputs.forEach(input => {
                input.disabled = false;
            });
            this.submitBtn.disabled = false;
        }

        /**
         * Disabilita la selezione del sesso
         */
        disableGender() {
            this.genderInputs.forEach(input => {
                input.disabled = true;
                input.checked = false;
            });
        }

        /**
         * Ottiene il sesso selezionato
         */
        getSelectedGender() {
            const selected = Array.from(this.genderInputs).find(input => input.checked);
            return selected ? selected.value : null;
        }

        /**
         * Gestisce l'invio del form
         */
        handleSubmit(e) {
            e.preventDefault();

            // Verifica che tutti i campi siano compilati
            if (!this.validateForm()) {
                this.showMessage('Compila tutti i campi richiesti', 'error');
                return;
            }

            const formData = new FormData(this.form);
            this.submitBtn.disabled = true;
            this.submitBtn.textContent = 'Invio in corso...';
            this.showMessage('Elaborazione prenotazione...', 'info');

            fetch(bookingOnlyAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showMessage(data.data.message, 'success');
                    
                    // Redirect se presente
                    if (data.data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = data.data.redirect_url;
                        }, 1500);
                    }
                } else {
                    this.showMessage(data.data.message || 'Errore durante la prenotazione', 'error');
                    this.submitBtn.disabled = false;
                    this.submitBtn.textContent = 'Prosegui con la prenotazione';
                }
            })
            .catch(() => {
                this.showMessage('Errore di connessione. Riprova.', 'error');
                this.submitBtn.disabled = false;
                this.submitBtn.textContent = 'Prosegui con la prenotazione';
            });
        }

        /**
         * Valida il form
         */
        validateForm() {
            const code = this.purchaseCode.value.trim();
            const location = this.getSelectedLocation();
            const date = this.dateInput.value;
            const timeSlot = this.timeSlot.value;
            const gender = this.getSelectedGender();

            return code && location && date && timeSlot && gender;
        }

        /**
         * Mostra un messaggio
         */
        showMessage(message, type = 'info') {
            this.response.className = 'booking-only-response ' + type;
            this.response.innerHTML = message;
            this.response.style.display = 'block';
            
            // Smooth scroll al messaggio
            this.response.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        /**
         * Cancella il messaggio
         */
        clearMessage() {
            this.response.style.display = 'none';
            this.response.innerHTML = '';
            this.response.className = 'booking-only-response';
        }
    }

    // Inizializza quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new BookingOnlyForm();
        });
    } else {
        new BookingOnlyForm();
    }

})();