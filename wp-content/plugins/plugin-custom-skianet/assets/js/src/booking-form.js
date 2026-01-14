import { Calendar } from 'vanilla-calendar-pro';
import 'vanilla-calendar-pro/styles/index.css';
import 'vanilla-calendar-pro/styles/layout.css';
import 'vanilla-calendar-pro/styles/themes/light.css';

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('booking-form');
    if (!form) return;

    // Campi del form
    const locationRadios = document.querySelectorAll('input[name="location"]');
    const dateField = document.getElementById('booking_date');
    const ticketTypeField = document.getElementById('ticket_type');
    const timeSlotField = document.getElementById('time_slot');
    const numMaleField = document.getElementById('num_male');
    const numFemaleField = document.getElementById('num_female');
    const submitBtn = form.querySelector('.btn-submit');
    const responseDiv = document.getElementById('booking-response');

    // Helper per ottenere la location selezionata
    function getSelectedLocation() {
        const selectedRadio = document.querySelector('input[name="location"]:checked');
        return selectedRadio ? selectedRadio.value : '';
    }

    // Array delle date natalizie dal backend
    const christmasDates = bookingFormData.christmas_dates || [];

    // === VANILLA CALENDAR INTEGRATION ===
    let calendar = null;
    let availabilityData = null;
    let calendarWrapper = null;

    // Funzione per creare/ricreare il wrapper del calendario
    function createCalendarWrapper() {
        // Rimuovi il wrapper esistente se presente
        if (calendarWrapper && calendarWrapper.parentNode) {
            calendarWrapper.parentNode.removeChild(calendarWrapper);
        }

        // Crea un nuovo wrapper
        calendarWrapper = document.createElement('div');
        calendarWrapper.className = 'vanilla-calendar-wrapper';
        dateField.parentNode.insertBefore(calendarWrapper, dateField.nextSibling);

        return calendarWrapper;
    }

    // Crea il wrapper iniziale
    createCalendarWrapper();

    // add bottoni + e - per i campi numerici
    handleNumbersInput(numMaleField);
    handleNumbersInput(numFemaleField);
    verifyNumberFieldsState();

    // enable/ disable + / - buttons based on number input disabled check
    function verifyNumberFieldsState() {
        [numMaleField, numFemaleField].forEach(field => {
            const wrap = field.parentElement;
            const btnUp = wrap.querySelector('.btn-up');
            const btnDown = wrap.querySelector('.btn-down');
            if (field.disabled) {
                btnUp.disabled = true;
                btnDown.disabled = true;
            } else {
                btnUp.disabled = false;
                btnDown.disabled = false;
            }
        });
    }

    // Mappa le location dai valori del form ai nomi dei file JSON
    function mapLocationToFileName(location) {
        const locationMap = {
            'terme-genova': 'genova',
            'monterosa-spa': 'monterosa',
            'terme-saint-vincent': 'saint-vincent'
        };

        return locationMap[location] || location;
    }

    /**
     * Verifica se una data è nel periodo natalizio
     */
    function isChristmasPeriod(dateString) {
        return christmasDates.includes(dateString);
    }

    // Funzione per recuperare il JSON delle disponibilità per location
    async function fetchAvailabilityJSON(location) {
    try {
        const fileName = mapLocationToFileName(location);
        const jsonPath = `/wp-content/plugins/plugin-custom-skianet/assets/data/availability-${fileName}.json`;

        const response = await fetch(jsonPath);

        if (!response.ok) {
            console.error(`File non trovato: ${jsonPath}`);
            // ✅ Mostra messaggio all'utente
            showMessage('error', 'Impossibile caricare il calendario per questa location.');
            return null;
        }

        const data = await response.json();
        //console.log(`JSON caricato per ${location}:`, data);
        return data;
    } catch (error) {
        console.error('Errore nel recupero del JSON availability:', error);
        showMessage('error', 'Errore nel caricamento del calendario.');
        return null;
    }
}

    // Funzione per costruire l'array di date disabilitate dal JSON
    function buildDisabledDatesArray(availabilityData) {
        if (!availabilityData || !availabilityData.availability) {
            return [];
        }

        // Filtra le date con availability = false e restituiscile come array
        const disabledDates = Object.entries(availabilityData.availability)
            .filter(([, isAvailable]) => !isAvailable)
            .map(([date]) => date);

        return disabledDates;
    }

    async function initCalendar(location) {
        if (calendar) return;

        // Range di date: oggi + 60 giorni
        const today = new Date();
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 60);

        const firstDayCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDayNextMonth = new Date(today.getFullYear(), today.getMonth() + 2, 0);

        /*console.log('Calendario range:', {
            min: firstDayCurrentMonth.toISOString().split('T')[0],
            max: lastDayNextMonth.toISOString().split('T')[0]
        });*/

        // Recupera i dati di disponibilità dal JSON
        availabilityData = await fetchAvailabilityJSON(location);

        // Costruisci l'array delle date disabilitate
        const disabledDates = buildDisabledDatesArray(availabilityData);

        const options = {
            locale: 'it',
            selectedTheme: 'light',
            selectionDatesMode: 'single',
            dateMin: firstDayCurrentMonth.toISOString().split('T')[0], 
            dateMax: lastDayNextMonth.toISOString().split('T')[0], 
            disableDates: disabledDates,
            disableDatesPast: true,
            selectedDates: dateField.value ? [dateField.value] : [],
            onShow() {
                // Assicura che il calendario sia visibile con display: block
                calendarWrapper.style.display = 'block';
            },
            onClickDate(self, event) {
                // Ottieni la data cliccata dal data attribute (formato YYYY-MM-DD)
                const clickedDate = self.context.selectedDates[0];
                if (clickedDate && !disabledDates.includes(clickedDate)) {
                    const [year, month, day] = clickedDate.split('-');

                    dateField.value = clickedDate;
                    // Trigger dell'evento change per il form
                    const changeEvent = new Event('change', { bubbles: true });
                    
                    dateField.dispatchEvent(changeEvent);
                    console.log('Data selezionata:', clickedDate);
                    // Chiudi il calendario - il metodo hide non funziona con questa configurazione
                    calendarWrapper.style.display = 'none';
                } else {
                    showMessage('error', 'Data non disponibile. Seleziona un\'altra data.');
                    self.update(); // Reset selezione
                }
            },
        };

        calendar = new Calendar(calendarWrapper, options);
        calendar.init();
        //console.log('Calendario inizializzato per location:', location);
        //console.log(calendar);
    }

    // Mostra calendario al click sull'input
    dateField.addEventListener('click', async function(e) {
        if (!this.disabled) {
            e.preventDefault();

            if (!calendar) {
                const selectedLocation = getSelectedLocation();
                if (selectedLocation) {
                    await initCalendar(selectedLocation);
                }
            }
            if (calendar) {
                calendar.show();
            }
        }
    });

    // Dati API
    let apiData = null;

    // === GESTIONE PROGRESSIVA DEI CAMPI ===
    locationRadios.forEach(radio => {
        radio.addEventListener('change', async function() {
            // Reset campi successivi quando cambia la location
            // Reset campi successivi quando cambia la location
            dateField.value = '';
            ticketTypeField.value = '';
            ticketTypeField.selectedIndex = 0;
            timeSlotField.value = '';
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            numMaleField.value = '0';
            numFemaleField.value = '0';
            if (calendar) {
                calendar.destroy();
                calendar = null;
                availabilityData = null;
            }

            hideMessage();

            apiData = null;

            if (this.value) {
                // Ricrea completamente il wrapper del calendario
                createCalendarWrapper();
                // Inizializza nuovo calendario per la nuova location
                await initCalendar(this.value);
                dateField.disabled = false;
            } else {
                dateField.disabled = true;
                disableFieldsFrom('ticket');
            }

        });
    });

    dateField.addEventListener('change', function() {
        // Reset campi successivi quando cambia la data
        ticketTypeField.value = '';
        ticketTypeField.selectedIndex = 0;
        timeSlotField.value = '';
        timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';

        const selectedLocation = getSelectedLocation();
        if (this.value && selectedLocation) {

            handleTicketTypeOptions(this.value);

            callAvailabilityAPI(selectedLocation, this.value);
        } else {
            disableFieldsFrom('ticket');
        }
    });

    ticketTypeField.addEventListener('change', function() {
        timeSlotField.disabled = !this.value;
        timeSlotField.value = '';
        if (!this.value) {
            disableFieldsFrom('time');
        }
    });

    timeSlotField.addEventListener('change', function() {
        const isEnabled = !!this.value;
        numMaleField.disabled = !isEnabled;
        numFemaleField.disabled = !isEnabled;
        verifyNumberFieldsState();
        if (!isEnabled) {
            numMaleField.value = '0';
            numFemaleField.value = '0';
            submitBtn.disabled = true;
        } else {
            const selectedOption = this.options[this.selectedIndex];
            const categorie = selectedOption.dataset.categorie || '';
            const disponibilita = selectedOption.dataset.disponibilita || '0';

            //console.log('Categorie selezionate:', categorie);
            //console.log('Disponibilità fascia:', disponibilita);
        
            // Aggiungi campo hidden
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

            checkSubmitButton();
        }
    });

    numMaleField.addEventListener('input', checkSubmitButton);
    numFemaleField.addEventListener('input', checkSubmitButton);

    // === FUNZIONI HELPER ===
    /**
     * Gestisci visibilità opzione "giornaliero" in base alla data
     */
    function handleTicketTypeOptions(selectedDate) {
        if (!ticketTypeField || !selectedDate) {
            return;
        }
        
        const isChristmas = isChristmasPeriod(selectedDate);
        const giornalieroOption = ticketTypeField.querySelector('option[value="giornaliero"]');
        
        if (giornalieroOption) {
            if (isChristmas) {
                // ✅ PERIODO NATALIZIO - Blocca "giornaliero"
                giornalieroOption.disabled = true;
                giornalieroOption.textContent = 'Giornaliero (non disponibile nel periodo natalizio)'; 

                // Se era selezionato, resettalo a 4h
                if (ticketTypeField.value === 'giornaliero') {
                    ticketTypeField.value = '4h';
                }
                
                //console.log('Periodo natalizio - Solo 4 ore disponibile');
            } else {
                // ✅ PERIODO NORMALE - Abilita "giornaliero"
                giornalieroOption.disabled = false;
                giornalieroOption.textContent = 'Giornaliero';
            }
        }
    }
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
                field.value = field.type === 'tel' ? '0' : '';
            });
        }

        submitBtn.disabled = true;
        verifyNumberFieldsState();
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

    function handleNumbersInput(field) {
        const wrap = document.createElement('div');
        wrap.className = 'number-input-wrapper';
        field.parentNode.insertBefore(wrap, field);
        wrap.appendChild(field);
        const btnUp = document.createElement('button');
        btnUp.type = 'button';
        btnUp.className = 'btn-number btn-up';
        btnUp.textContent = '+';    
        const btnDown = document.createElement('button');
        btnDown.type = 'button';
        btnDown.className = 'btn-number btn-down';
        btnDown.textContent = '−';    
        wrap.appendChild(btnDown);
        wrap.appendChild(btnUp);
        btnUp.addEventListener('click', function() {
            let currentValue = parseInt(field.value) || 0; 
            if (currentValue < field.max) {
                field.value = currentValue + 1;
                field.dispatchEvent(new Event('input'));
            }
        });
        btnDown.addEventListener('click', function() {
            let currentValue = parseInt(field.value) || 0; 
            if (currentValue > field.min) {
                field.value = currentValue - 1;
                field.dispatchEvent(new Event('input'));
            }
        });
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

                const availableSlots = apiData.available_slots.filter(slot => slot.disponibilita > 0);

                if (availableSlots.length === 0) {
                    showMessage('error', 'Nessuna fascia oraria disponibile per questa data.');
                    disableFieldsFrom('ticket');
                    return;
                }

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
                //console.log('Slot ID:', slot.id, 'Time:', slot.time, 'Categorie:', slot.categorie); 
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

        const numMale = parseInt(numMaleField.value) || 0;
        const numFemale = parseInt(numFemaleField.value) || 0;
        const total = numMale + numFemale;

        if (total === 0) {
            showMessage('error', 'Seleziona almeno un ingresso.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Invio in corso...';
        hideMessage();

        const formData = new FormData(form);

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

                // Focus sul primo radio button della location
                const firstLocationRadio = document.querySelector('input[name="location"]');
                if (firstLocationRadio) {
                    firstLocationRadio.focus();
                }

                if (data.data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect_url;
                    }, 1500); // Aspetta 1.5s per mostrare messaggio
                } else {
                    // Reset form se non c'è redirect
                    form.reset();
                    disableFieldsFrom('date');
                    dateField.disabled = true;
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
            submitBtn.textContent = 'Prenota Ora';
            if (submitBtn.disabled) {
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 2000);
            }
        });
    });
});
