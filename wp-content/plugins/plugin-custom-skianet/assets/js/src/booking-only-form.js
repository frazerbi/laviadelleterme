/**
 * Booking Only Form - JavaScript Handler Semplificato
 */

(function() {
    'use strict';

    class BookingOnlyForm {
        constructor() {
            this.form = document.getElementById('booking-only-form');
            if (!this.form) return;

            this.purchaseCode = document.getElementById('purchase_code');
            this.savedCodes = document.getElementById('saved_codes'); 
            this.locations = document.querySelectorAll('input[name="location"]');
            this.dateInput = document.getElementById('booking-only_date');
            this.timeSlot = document.getElementById('time_slot');
            this.genderInputs = document.querySelectorAll('input[name="gender"]');
            this.response = document.getElementById('booking-only-response');
            this.submitBtn = this.form.querySelector('button[type="submit"]');

            this.currentLocation = null;

            this.init();
        }

        init() {
            // Event listeners per abilitazione progressiva
            this.purchaseCode.addEventListener('input', () => this.checkPurchaseCode());
            
            if (this.savedCodes) {
                this.savedCodes.addEventListener('change', () => this.handleSavedCodeSelect());
            }


            this.locations.forEach(location => {
                location.addEventListener('change', () => this.checkLocation());
            });

            this.dateInput.addEventListener('change', () => this.checkDate());
            this.timeSlot.addEventListener('change', () => this.checkTimeSlot());
            
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));

            // Formatta il codice in maiuscolo mentre si digita
            this.purchaseCode.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        }

        /**
         * Gestisce la selezione di un codice salvato
         */
        handleSavedCodeSelect() {
            const selectedCode = this.savedCodes.value;
            
            if (selectedCode) {
                this.purchaseCode.value = selectedCode;
                this.purchaseCode.dispatchEvent(new Event('input')); // Trigger validazione
            }
        }

        /**
         * Controlla il codice acquisto (solo lunghezza)
         */
        checkPurchaseCode() {
            const code = this.purchaseCode.value.trim();
            
            if (code.length === 16) {
                this.enableLocations();
            } else {
                this.disableLocations();
                this.disableDate();
                this.disableTimeSlot();
                this.disableGender();
                this.currentLocation = null;
            }
        }

        /**
         * Controlla la location selezionata
         */
        checkLocation() {
            const selectedLocation = this.getSelectedLocation();
            
            if (selectedLocation) {
                // Se la location è cambiata, resetta data e campi successivi
                if (this.currentLocation !== null && this.currentLocation !== selectedLocation) {
                    this.dateInput.value = '';
                    this.disableTimeSlot();
                    this.disableGender();
                    this.clearMessage();
                }
                
                this.currentLocation = selectedLocation;
                this.enableDate();
            } else {
                this.currentLocation = null;
                this.disableDate();
                this.disableTimeSlot();
                this.disableGender();
            }
        }

        /**
         * Controlla la data selezionata
         */
        checkDate() {
            const selectedDate = this.dateInput.value;
            const selectedLocation = this.getSelectedLocation();
            
            if (selectedDate && selectedLocation) {
                this.loadTimeSlots(selectedLocation, selectedDate);
            } else {
                this.disableTimeSlot();
                this.disableGender();
            }
        }

        /**
         * Controlla la fascia oraria selezionata
         */
        checkTimeSlot() {
            if (this.timeSlot.value) {
                this.enableGender();
            } else {
                this.disableGender();
            }
        }

        /**
         * Abilita locations
         */
        enableLocations() {
            this.locations.forEach(location => {
                location.disabled = false;
                const item = location.closest('.visualradio-item');
                if (item) item.classList.remove('disabled');
            });
        }

        /**
         * Disabilita locations
         */
        disableLocations() {
            this.locations.forEach(location => {
                location.disabled = true;
                location.checked = false;
                const item = location.closest('.visualradio-item');
                if (item) {
                    item.classList.add('disabled');
                } 
            });
        }

        /**
         * Abilita data
         */
        enableDate() {
            this.dateInput.disabled = false;
        }

        /**
         * Disabilita data
         */
        disableDate() {
            this.dateInput.disabled = true;
            this.dateInput.value = '';
        }

        /**
         * Abilita fascia oraria
         */
        enableTimeSlot() {
            this.timeSlot.disabled = false;
        }

        /**
         * Disabilita fascia oraria
         */
        disableTimeSlot() {
            this.timeSlot.disabled = true;
            this.timeSlot.value = '';
        }

        /**
         * Abilita sesso
         */
        enableGender() {
            this.genderInputs.forEach(input => {
                input.disabled = false;
                const item = input.closest('.gender-item');
                if (item) item.classList.remove('disabled');
            });
        }

        /**
         * Disabilita sesso
         */
        disableGender() {
            this.genderInputs.forEach(input => {
                input.disabled = true;
                input.checked = false;
                const item = input.closest('.gender-item');
                if (item) item.classList.add('disabled');
            });
        }

        /**
         * Carica le fasce orarie disponibili
         */
        loadTimeSlots(location, date) {
            this.showMessage('Caricamento fasce orarie...', 'info');
            this.timeSlot.disabled = true;
            this.timeSlot.innerHTML = '<option value="">Caricamento...</option>';

            const formData = new FormData();
            formData.append('action', 'check_availability_api');
            formData.append('nonce', bookingOnlyAjax.nonce);
            formData.append('location', location);
            formData.append('booking_date', date);

            fetch(bookingOnlyAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.json();
            })
            .then(data => {                
                // Controlla che esistano gli available_slots
                if (data.success && data.data && data.data.available_slots && data.data.available_slots.length > 0) {
                    
                    // Svuota il select e aggiungi l'opzione di default
                    this.timeSlot.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
                    
                    let hasAvailableSlots = false; // ← Dichiarato qui
                    
                    // Aggiungi le opzioni disponibili
                    data.data.available_slots.forEach(slot => {
                        if (slot.disponibilita > 0) {
                            const option = document.createElement('option');
                            option.value = slot.id; 
                            option.textContent = `${slot.time} - ${slot.disponibilita} posti disponibili`;
                            option.dataset.time = slot.time;
                            option.dataset.categorie = slot.categorie || '';
                            option.dataset.disponibilita = slot.disponibilita;
                            this.timeSlot.appendChild(option);
                            
                            hasAvailableSlots = true;
                        }
                    });
                    
                    // Abilita il campo solo se ci sono slot disponibili
                    if (hasAvailableSlots) {
                        this.enableTimeSlot();
                        this.clearMessage();
                    } else {
                        this.timeSlot.innerHTML = '<option value="">Nessuna fascia oraria disponibile</option>';
                        this.showMessage('Nessuna fascia oraria disponibile per questa data', 'warning');
                    }
                    
                } else {
                    this.timeSlot.innerHTML = '<option value="">Nessuna fascia oraria disponibile</option>';
                    this.showMessage('Nessuna disponibilità per questa data', 'warning');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                this.timeSlot.innerHTML = '<option value="">Errore caricamento</option>';
                this.showMessage('Errore nel caricamento delle fasce orarie', 'error');
            });
        }

        /**
         * Gestisce l'invio del form
         */
        handleSubmit(e) {
            e.preventDefault();

            const formData = new FormData(this.form);
            this.submitBtn.disabled = true;
            this.submitBtn.textContent = 'Invio in corso...';
            this.showMessage('Elaborazione in corso...', 'info');

            fetch(bookingOnlyAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showMessage(data.data.message, 'success');
                    
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
                this.showMessage('Errore di connessione', 'error');
                this.submitBtn.disabled = false;
                this.submitBtn.textContent = 'Prosegui con la prenotazione';
            });
        }

        /**
         * Ottiene la location selezionata
         */
        getSelectedLocation() {
            const selected = Array.from(this.locations).find(loc => loc.checked);
            return selected ? selected.value : null;
        }

        /**
         * Mostra messaggio
         */
        showMessage(message, type = 'info') {
            this.response.className = 'booking-only-response ' + type;
            this.response.innerHTML = message;
            this.response.style.display = 'block';
        }

        /**
         * Cancella messaggio
         */
        clearMessage() {
            this.response.style.display = 'none';
            this.response.innerHTML = '';
            this.response.className = 'booking-only-response';
        }
    }

    // Inizializza
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new BookingOnlyForm());
    } else {
        new BookingOnlyForm();
    }

})();