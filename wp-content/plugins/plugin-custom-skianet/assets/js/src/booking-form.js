import { Calendar } from 'vanilla-calendar-pro';
import 'vanilla-calendar-pro/styles/index.css';
import 'vanilla-calendar-pro/styles/layout.css';
import 'vanilla-calendar-pro/styles/themes/light.css';

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

    // === VANILLA CALENDAR INTEGRATION ===
    let calendar = null;
    let availabilityData = null;

    // Crea un wrapper per il calendario vicino all'input
    const calendarWrapper = document.createElement('div');
    calendarWrapper.className = 'vanilla-calendar-wrapper';
    dateField.parentNode.insertBefore(calendarWrapper, dateField.nextSibling);

    // Mappa le location dai valori del form ai nomi dei file JSON
    function mapLocationToFileName(location) {
        const locationMap = {
            'terme-genova': 'genova',
            'terme-monterosa-spa': 'monterosa',
            'terme-saint-vincent': 'saint-vincent'
        };

        return locationMap[location] || location;
    }

    // Funzione per recuperare il JSON delle disponibilità per location
    async function fetchAvailabilityJSON(location) {
        try {
            // Normalizza il nome della location
            const fileName = mapLocationToFileName(location);

            // Costruisci il percorso del file JSON basato sulla location
            const jsonPath = `/wp-content/plugins/plugin-custom-skianet/assets/data/availability-${fileName}.json`;

            const response = await fetch(jsonPath);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Errore nel recupero del JSON availability:', error);
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

        // Recupera i dati di disponibilità dal JSON
        availabilityData = await fetchAvailabilityJSON(location);

        // Costruisci l'array delle date disabilitate
        const disabledDates = buildDisabledDatesArray(availabilityData);

        const options = {
            locale: 'it',
            selectedTheme: 'light',
            selectionDatesMode: 'single',
            dateMin: today.toISOString().split('T')[0],
            dateMax: maxDate.toISOString().split('T')[0],
            disableDates: disabledDates,
            disableDatesPast: true,
            selectedDates: dateField.value ? [dateField.value] : [],

            onClickDate(self, event) {
                // Ottieni la data cliccata dal data attribute (formato YYYY-MM-DD)
                const clickedDate = self.context.selectedDates[0];
                if (clickedDate) {
                    const [year, month, day] = clickedDate.split('-');
                    dateField.value = clickedDate;
                    // Trigger dell'evento change per il form
                    const changeEvent = new Event('change', { bubbles: true });
                    dateField.dispatchEvent(changeEvent);
                    // Chiudi il calendario
                    self.hide();
                }
            },

            onShow() {
                // Assicura che il calendario sia visibile con display: block
                calendarWrapper.style.display = 'block';
            },

            onHide() {
                // Imposta display: none per evitare che occupi spazio
                calendarWrapper.style.display = 'none';
            }
        };

        calendar = new Calendar(calendarWrapper, options);
        calendar.init();
    }

    // Mostra calendario al click sull'input
    dateField.addEventListener('click', async function(e) {
        if (!this.disabled) {
            e.preventDefault();

            if (!calendar) {
                const selectedLocation = locationField.value;
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
        ticketTypeField.selectedIndex = 0;
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
            numMaleField.value = '';
            numFemaleField.value = '';
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
                field.value = '';
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
                console.log('Slot ID:', slot.id, 'Time:', slot.time); // ✅ Debug
                const option = document.createElement('option');
                option.value = slot.id; 
                option.textContent = `${slot.time} - ${slot.disponibilita} posti disponibili`;
                option.dataset.time = slot.time;
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
                locationField.focus();
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
