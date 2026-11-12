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
    const ticketTypeRadios = document.querySelectorAll('input[name="ticket_type"]');
    const ticketTypeGroup = document.querySelector('.form-group-ticket-type');
    const timeSlotField = document.getElementById('time_slot');
    const numMaleField = document.getElementById('num_male');
    const numFemaleField = document.getElementById('num_female');
    const submitBtn = form.querySelector('.btn-submit');
    const responseDiv = document.getElementById('booking-response');

    // Reset form al caricamento per evitare stato residuo dal browser back button
    form.reset();

    // Gestisce il caso back/forward navigation (bfcache o ricaricamento con form restore del browser)
    window.addEventListener('pageshow', function(event) {
        const navEntries = performance.getEntriesByType('navigation');
        const isBackForward = event.persisted ||
            (navEntries.length > 0 && navEntries[0].type === 'back_forward');

        if (isBackForward) {
            form.reset();
            // Esplicitamente deseleziona i radio (form.reset può ripristinare il default HTML)
            locationRadios.forEach(r => r.checked = false);
            resetAfter('start');
            createCalendarWrapper();
            hideMessage();
        }
    });

    // Helper per ottenere la location selezionata
    function getSelectedLocation() {
        const selectedRadio = document.querySelector('input[name="location"]:checked');
        return selectedRadio ? selectedRadio.value : '';
    }

    // Helper per ticket type radio buttons
    function getTicketTypeValue() {
        const checked = document.querySelector('input[name="ticket_type"]:checked');
        return checked ? checked.value : '';
    }

    function resetTicketType() {
        ticketTypeRadios.forEach(r => { r.checked = false; });
    }

    function setTicketTypeDisabled(disabled) {
        ticketTypeRadios.forEach(r => {
            if (!disabled && r.closest('.visualradio-card')?.classList.contains('ticket-option-disabled')) {
                return; // mantieni disabilitate le opzioni individualmente non disponibili
            }
            r.disabled = disabled;
        });
        ticketTypeGroup.classList.toggle('is-disabled', disabled);
    }

    function setTicketTypeOptionState(value, disabled, label) {
        const radio = document.querySelector(`input[name="ticket_type"][value="${value}"]`);
        if (!radio) return;
        const card = radio.closest('.visualradio-card');
        if (!card) return;
        const labelEl = card.querySelector('.visualradio-card-label');

        radio.disabled = disabled || ticketTypeGroup.classList.contains('is-disabled');
        card.classList.toggle('ticket-option-disabled', disabled);
        if (labelEl && label !== undefined) labelEl.textContent = label;

        if (disabled && radio.checked) {
            radio.checked = false;
            const radio4h = document.querySelector('input[name="ticket_type"][value="4h"]');
            if (radio4h && !radio4h.disabled) {
                radio4h.checked = true;
                radio4h.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
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

    // Pre-controlla le location chiuse stagionalmente
    preCheckLocationsAvailability();

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
    async function fetchAvailabilityJSON(location, silent = false) {
        try {
            const fileName = mapLocationToFileName(location);
            const jsonPath = `/wp-content/plugins/plugin-custom-skianet/assets/data/availability-${fileName}.json`;

            const response = await fetch(jsonPath);

            if (!response.ok) {
                console.error(`File non trovato: ${jsonPath}`);
                if (!silent) showMessage('error', 'Impossibile caricare il calendario per questa location.');
                return null;
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Errore nel recupero del JSON availability:', error);
            if (!silent) showMessage('error', 'Errore nel caricamento del calendario.');
            return null;
        }
    }

    function isLocationClosed(data) {
        if (!data || !data.availability) return false;
        const values = Object.values(data.availability);
        return values.length > 0 && values.every(v => v === false);
    }

    function markLocationAsClosed(radio) {
        const item = radio.closest('.visualradio-item');
        if (!item || item.classList.contains('location-closed')) return;
        item.classList.add('location-closed');
        radio.disabled = true;
        const thumb = item.querySelector('.visualradio-thumb');
        if (thumb) {
            const badge = document.createElement('span');
            badge.className = 'location-closed-badge';
            badge.textContent = 'Chiusura stagionale';
            thumb.appendChild(badge);
        }
    }

    async function preCheckLocationsAvailability() {
        await Promise.all(Array.from(locationRadios).map(async radio => {
            const data = await fetchAvailabilityJSON(radio.value, true);
            if (data && isLocationClosed(data)) {
                markLocationAsClosed(radio);
            }
        }));
    }

    function formatLocalDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    // Costruisce le date disabilitate: disabilita tutto ciò che non è esplicitamente true nel JSON,
    // incluse le date fuori dal range del JSON (trattate come non disponibili)
    function buildDisabledDatesArray(availabilityData, dateMin, dateMax) {
        if (!availabilityData || !availabilityData.availability) {
            return [];
        }

        const availability = availabilityData.availability;
        const disabledDates = [];

        // Itera tutte le date nel range del calendario, non solo quelle nel JSON
        const current = new Date(dateMin + 'T12:00:00');
        const end = new Date(dateMax + 'T12:00:00');

        while (current <= end) {
            const dateStr = formatLocalDate(current);
            if (!availability[dateStr]) {
                disabledDates.push(dateStr);
            }
            current.setDate(current.getDate() + 1);
        }

        return disabledDates;
    }

    /**
     * Crea il calendario per la location. Restituisce true se il calendario è pronto,
     * false se la location è chiusa o nel frattempo ne è stata scelta un'altra.
     */
    async function initCalendar(location) {
        if (calendar) return true;

        // Range: primo giorno del mese corrente → ultimo giorno del mese successivo
        const today = new Date();
        const firstDayCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDayNextMonth = new Date(today.getFullYear(), today.getMonth() + 2, 0);
        const dateMinStr = formatLocalDate(firstDayCurrentMonth);
        const dateMaxStr = formatLocalDate(lastDayNextMonth);

        // Recupera i dati di disponibilità dal JSON
        const data = await fetchAvailabilityJSON(location);

        // Nel frattempo l'utente ha scelto un'altra location: questo calendario non serve più
        if (getSelectedLocation() !== location) return false;
        if (calendar) return true;

        availabilityData = data;

        if (isLocationClosed(availabilityData)) {
            const closedRadio = document.querySelector(`input[name="location"][value="${location}"]`);
            if (closedRadio) markLocationAsClosed(closedRadio);
            showMessage('error', 'Questa struttura è temporaneamente chiusa.');
            return false;
        }

        // Costruisci l'array delle date disabilitate
        const disabledDates = buildDisabledDatesArray(availabilityData, dateMinStr, dateMaxStr);

        const options = {
            locale: 'it',
            selectedTheme: 'light',
            selectionDatesMode: 'single',
            dateMin: dateMinStr,
            dateMax: dateMaxStr,
            disableDates: disabledDates,
            disableDatesPast: true,
            // Un secondo clic sulla data scelta non la deseleziona (dava "Data non disponibile")
            enableDateToggle: false,
            selectedDates: dateField.value ? [dateField.value] : [],
            onShow() {
                // Assicura che il calendario sia visibile con display: block
                calendarWrapper.style.display = 'block';
            },
            onClickDate(self, event) {
                // Ottieni la data cliccata dal data attribute (formato YYYY-MM-DD)
                const clickedDate = self.context.selectedDates[0];
                if (clickedDate && clickedDate === dateField.value) {
                    // Stessa data di prima: niente da azzerare, si chiude e basta
                    calendarWrapper.style.display = 'none';
                } else if (clickedDate && !disabledDates.includes(clickedDate)) {
                    const [year, month, day] = clickedDate.split('-');

                    dateField.value = clickedDate;
                    // Trigger dell'evento change per il form
                    const changeEvent = new Event('change', { bubbles: true });
                    
                    dateField.dispatchEvent(changeEvent);

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

        return true;
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
    //
    // Regola unica: quando un passo cambia, tutti i passi DOPO tornano vuoti e bloccati
    // (resetAfter), e si riaprono uno alla volta man mano che l'utente va avanti.

    // Numero dell'ultima richiesta di disponibilità: una risposta che arriva dopo un cambio
    // di data o location porta un numero vecchio e viene scartata, altrimenti le fasce di
    // un giorno finirebbero sotto un altro giorno.
    let availabilityRequest = 0;

    const STEPS = ['start', 'location', 'date', 'ticket', 'time'];

    /**
     * Svuota e blocca tutti i campi che vengono dopo lo step indicato:
     * 'start' → tutto; 'location' → dalla data in poi; 'date' → dal tipo di ingresso;
     * 'ticket' → dalla fascia oraria; 'time' → numero ingressi e pulsante.
     */
    function resetAfter(step) {
        const from = STEPS.indexOf(step);

        if (from < STEPS.indexOf('date')) {
            // Data (il calendario si ricrea quando si sceglie la location)
            dateField.value = '';
            dateField.disabled = true;
            if (calendar) {
                calendar.destroy();
                calendar = null;
            }
            availabilityData = null;
        }

        if (from < STEPS.indexOf('ticket')) {
            // Tipo di ingresso: deselezionato, bloccato, etichette di default
            availabilityRequest++;
            apiData = null;
            resetTicketType();
            setTicketTypeOptionState('giornaliero', false, 'Giornaliero');
            setTicketTypeOptionState('serale', false, 'Serale');
            setTicketTypeDisabled(true);
        }

        if (from < STEPS.indexOf('time')) {
            // Fascia oraria
            timeSlotField.innerHTML = '<option value="">-- Seleziona una fascia oraria --</option>';
            timeSlotField.disabled = true;
            const seraleNote = document.getElementById('serale-note');
            if (seraleNote) seraleNote.style.display = 'none';
        }

        // Numero ingressi, dati della fascia scelta e pulsante: sempre
        numMaleField.value = '0';
        numFemaleField.value = '0';
        numMaleField.disabled = true;
        numFemaleField.disabled = true;
        verifyNumberFieldsState();
        setHiddenField('selected_categorie', 'categorie', '');
        setHiddenField('selected_disponibilita', 'disponibilita', '');
        submitBtn.disabled = true;
    }

    /**
     * Campo hidden inviato col form (creato al primo uso)
     */
    function setHiddenField(id, name, value) {
        let input = document.getElementById(id);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.id = id;
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    locationRadios.forEach(radio => {
        radio.addEventListener('change', async function() {
            resetAfter('location');
            hideMessage();

            if (!this.value) return;

            // Ricrea completamente il wrapper del calendario per la nuova location
            createCalendarWrapper();
            if (await initCalendar(this.value)) {
                dateField.disabled = false;
                scrollToNext(dateField.closest('.form-group'));
            }
        });
    });

    dateField.addEventListener('change', function() {
        resetAfter('date');

        const selectedLocation = getSelectedLocation();
        if (this.value && selectedLocation) {
            handleTicketTypeOptions(this.value);
            callAvailabilityAPI(selectedLocation, this.value);
        }
    });

    ticketTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            resetAfter('ticket');

            const value = this.value;
            if (!value) return;

            const seraleNote = document.getElementById('serale-note');
            if (seraleNote) {
                seraleNote.style.display = (value === 'serale') ? '' : 'none';
            }

            if (filterTimeSlotsByTicketType(value)) {
                timeSlotField.disabled = false;
                scrollToNext(timeSlotField.closest('.form-group'));
            }
        });
    });

    timeSlotField.addEventListener('change', function() {
        resetAfter('time');

        if (!this.value) return;

        const selectedOption = this.options[this.selectedIndex];
        setHiddenField('selected_categorie', 'categorie', selectedOption.dataset.categorie || '');
        setHiddenField('selected_disponibilita', 'disponibilita', selectedOption.dataset.disponibilita || '0');

        numMaleField.disabled = false;
        numFemaleField.disabled = false;
        verifyNumberFieldsState();
        scrollToNext(numMaleField.closest('.form-group'));
        checkSubmitButton();
    });

    numMaleField.addEventListener('input', checkSubmitButton);
    numFemaleField.addEventListener('input', checkSubmitButton);

    // === FUNZIONI HELPER ===
    /**
     * Gestisci visibilità opzione "giornaliero" in base alla data
     */
    function handleTicketTypeOptions(selectedDate) {
        if (!selectedDate) return;

        const isChristmas = isChristmasPeriod(selectedDate);
        if (isChristmas) {
            setTicketTypeOptionState('giornaliero', true, 'Giornaliero (non disponibile nel periodo natalizio)');
        } else {
            setTicketTypeOptionState('giornaliero', false, 'Giornaliero');
        }
    }

    /**
     * Categorie di una fascia. TermeGest le manda con le virgole ai bordi (",pm,pn,al,po,",
     * ",v4,"): gli elementi vuoti vanno scartati, o every()/some() li contano.
     */
    function getSlotCategorie(slot) {
        return (slot.categorie || '').toLowerCase().split(',').map(c => c.trim()).filter(Boolean);
    }

    // Categorie delle sole fasce serali, in qualunque periodo
    const SERALE_CATEGORIES = ['v3', 'v4'];

    /**
     * Categoria serale valida per la data: v4 nel periodo natalizio (serale natalizio),
     * v3 il resto dell'anno. Stessa regola di Booking_Handler::validate_category().
     */
    function seraleCategory(dateString) {
        return isChristmasPeriod(dateString) ? 'v4' : 'v3';
    }

    function isSeraleSlot(slot, dateString) {
        const hour = parseInt((slot.time || '00:00').split(':')[0], 10);
        return getSlotCategorie(slot).includes(seraleCategory(dateString)) && hour >= 18;
    }

    /**
     * Disabilita "giornaliero" se nessuno slot disponibile ha le categorie p3 o p4,
     * disabilita "serale" se nessuno slot disponibile ha la categoria serale della data
     * (v3, o v4 nel periodo natalizio) con ora >= 18.
     */
    function handleTicketTypeByCategories(slots, dateString) {
        const hasGiornaliero = slots.some(slot => {
            const categorie = getSlotCategorie(slot);
            return categorie.includes('p3') || categorie.includes('p4');
        });
        if (!hasGiornaliero) {
            setTicketTypeOptionState('giornaliero', true, 'Giornaliero (non disponibile per questa data)');
        } else {
            setTicketTypeOptionState('giornaliero', false, 'Giornaliero');
        }

        const hasSerale = slots.some(slot => isSeraleSlot(slot, dateString));
        if (!hasSerale) {
            setTicketTypeOptionState('serale', true, 'Serale (non disponibile per questa data)');
        } else {
            setTicketTypeOptionState('serale', false, 'Serale');
        }
    }

    /**
     * Filtra le fasce orarie in base al tipo di ingresso selezionato.
     * Serale: solo slot con la categoria serale della data e ora >= 18.
     * Altri: solo slot con almeno una categoria che non sia serale.
     */
    function filterTimeSlotsByTicketType(ticketType) {
        if (!apiData || !apiData.available_slots) return false;

        const allSlots = apiData.available_slots;
        let filteredSlots;

        if (ticketType === 'serale') {
            filteredSlots = allSlots.filter(slot => isSeraleSlot(slot, dateField.value));
        } else {
            filteredSlots = allSlots.filter(slot =>
                getSlotCategorie(slot).some(c => !SERALE_CATEGORIES.includes(c))
            );
        }

        updateTimeSlots(filteredSlots);

        // La prima option e' il segnaposto "-- Seleziona --"
        return timeSlotField.options.length > 1;
    }

    function checkSubmitButton() {
        // Massimo per genere = attributo max dei campi (10, come Booking_Handler::validate_booking_data()):
        // su type="tel" il browser non lo applica, e un numero scritto a mano lo supererebbe
        const male = parseInt(numMaleField.value) || 0;
        const female = parseInt(numFemaleField.value) || 0;
        const withinMax = male <= Number(numMaleField.max) && female <= Number(numFemaleField.max);
        submitBtn.disabled = !(male + female > 0 && withinMax && timeSlotField.value);
    }

    function showMessage(type, text) {
        responseDiv.className = `booking-response ${type}`;
        responseDiv.textContent = text;
        responseDiv.style.display = 'block';
    }

    function hideMessage() {
        responseDiv.style.display = 'none';
    }

    // Auto-scroll al campo successivo su mobile
    function scrollToNext(element) {
        if (window.innerWidth > 768 || !element) return;
        setTimeout(() => {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
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
        // resetAfter('date') ha gia' svuotato e bloccato i passi successivi
        const request = ++availabilityRequest;

        showMessage('', 'Verifica disponibilità in corso...');

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
            // Nel frattempo e' cambiata la data o la location: risposta superata
            if (request !== availabilityRequest) return;

            if (!data.success) {
                showMessage('error', data.data.message || 'Nessuna disponibilità per questa data.');
                return;
            }

            const availableSlots = data.data.available_slots.filter(slot => slot.disponibilita > 0);

            if (availableSlots.length === 0) {
                showMessage('error', 'Nessuna fascia oraria disponibile per questa data.');
                return;
            }

            apiData = data.data;
            showMessage('success', data.data.message || 'Disponibilità verificata!');

            // Le fasce si riempiono solo dopo la scelta del tipo di ingresso, gia' filtrate
            handleTicketTypeByCategories(apiData.available_slots, date);
            setTicketTypeDisabled(false);
            scrollToNext(ticketTypeGroup);
        })
        .catch(error => {
            if (request !== availabilityRequest) return;
            showMessage('error', 'Errore di connessione durante la verifica disponibilità.');
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

        const numMale = parseInt(numMaleField.value) || 0;
        const numFemale = parseInt(numFemaleField.value) || 0;
        const total = numMale + numFemale;

        if (total === 0) {
            showMessage('error', 'Seleziona almeno un ingresso.');
            return;
        }

        const submitLabel = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Invio in corso...';
        hideMessage();

        const formData = new FormData(form);

        // Aggiungi testo orario dalla option selezionata
        const selectedTimeOpt = timeSlotField.options[timeSlotField.selectedIndex];
        if (selectedTimeOpt && selectedTimeOpt.dataset.time) {
            formData.append('time_slot_label', selectedTimeOpt.dataset.time);
        }

        fetch(bookingFormData.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Tutto resta bloccato fino al redirect: niente doppio invio
                form.reset();
                locationRadios.forEach(r => r.checked = false);
                resetAfter('start');
                showMessage('success', 'Dati prenotazione completati, verrai indirizzato per completare l\'acquisto.');

                if (data.data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect_url;
                    }, 500); // Mezzo secondo per leggere il messaggio
                }
            } else {
                showMessage('error', data.data.message || 'Errore durante la prenotazione.');
                submitBtn.textContent = submitLabel;
                checkSubmitButton();
            }
        })
        .catch(error => {
            console.error('Errore submit:', error);
            showMessage('error', 'Errore di connessione.');
            submitBtn.textContent = submitLabel;
            checkSubmitButton();
        });
    });
});
