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

    // Crea un container per il calendario (nascosto inizialmente)
    const calendarContainer = document.createElement('div');
    calendarContainer.id = 'vanilla-calendar';
    calendarContainer.className = 'vanilla-calendar-light'; // Classe per il tema light
    calendarContainer.style.display = 'none';
    calendarContainer.style.position = 'absolute';
    calendarContainer.style.zIndex = '1000';
    calendarContainer.style.backgroundColor = '#fff';
    calendarContainer.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    calendarContainer.style.borderRadius = '8px';
    dateField.parentNode.appendChild(calendarContainer);

    function initCalendar() {
        if (calendar) return;

        // Range di date: oggi + 60 giorni
        const today = new Date();
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 60);

        // Date da escludere (esempio: festività)
        const disabledDates = [
            '2025-12-25', // Natale
            '2026-01-01', // Capodanno
            '2026-01-06', // Epifania
            // Aggiungi altre date da escludere qui
        ];

        console.log(disabledDates);

        const options = {
            selectedTheme: 'light',
            disableDates: disabledDates, // Date disabilitate
            dateMin: today.toISOString().split('T')[0],
            dateMax: maxDate.toISOString().split('T')[0],
            /* settings: {
                range: {
                },
                selection: {
                    day: 'single'
                },
                visibility: {
                    theme: 'light'
                }
            }, */
            /* settings: {
                lang: 'it',
                iso8601: false,
                range: {
                    min: today.toISOString().split('T')[0],
                    max: maxDate.toISOString().split('T')[0],
                },
                selection: {
                    day: 'single'
                },
                visibility: {
                    theme: 'light'
                }
            }, */
            /* actions: {
                clickDay(_e, self) {
                    const selectedDate = self.selectedDates[0];
                    if (selectedDate) {
                        dateField.value = selectedDate;
                        calendarContainer.style.display = 'none';

                        // Trigger change event
                        const changeEvent = new Event('change', { bubbles: true });
                        dateField.dispatchEvent(changeEvent);
                    }
                }
            } */
        };
        console.log(options);
        calendar = new Calendar(calendarContainer, options);
        calendar.init();
    }

    // Mostra/nascondi calendario al click sul campo data
    dateField.addEventListener('click', function(e) {
        if (!this.disabled) {
            e.preventDefault();
            if (calendarContainer.style.display === 'none') {
                const rect = dateField.getBoundingClientRect();
                calendarContainer.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                calendarContainer.style.left = rect.left + 'px';
                calendarContainer.style.display = 'block';

                if (!calendar) {
                    initCalendar();
                }
            } else {
                calendarContainer.style.display = 'none';
            }
        }
    });

    // Nascondi calendario se si clicca fuori
    document.addEventListener('click', function(e) {
        if (!calendarContainer.contains(e.target) && e.target !== dateField) {
            calendarContainer.style.display = 'none';
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
