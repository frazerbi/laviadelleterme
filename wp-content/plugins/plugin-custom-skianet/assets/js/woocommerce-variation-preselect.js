function getVars() {
    const TICKET_TYPE_LABELS = {
        '4h': '4 ore',
        'giornaliero': 'Giornaliero'
    };
    const LOCATION_LABELS = {
        'terme-saint-vincent': 'Terme di Saint-Vincent',
        'terme-genova': 'Terme di Genova',
        'monterosa-spa': 'Monterosa SPA'
    };

    const urlParams = new URLSearchParams(window.location.search);
    const locationId = urlParams.get('location');
    const dateParam = urlParams.get('date');
    const ticketType = urlParams.get('ticket_type');
    const totalGuests = parseInt(urlParams.get('total_guests')) || 1;
    const timeSlotLabel = urlParams.get('time_slot_label');

    const niceDate = dateParam ? dateParam.split('-').reverse().join('/') : null;
    const locationLabel = LOCATION_LABELS[locationId] || null;
    const ticketTypeLabel = TICKET_TYPE_LABELS[ticketType] || null;

    const vars = {
        'location': locationLabel,
        'date': niceDate,
        'ticket': ticketTypeLabel,
        'guests': totalGuests
    };

    if (timeSlotLabel) {
        vars.time = timeSlotLabel;
    }

    return vars;
}

function selectVariation(ticketType) {
    return new Promise((resolve, reject) => {
        if (!ticketType) {
            reject(new Error('ticketType non specificato'));
            return;
        }

        // jQuery necessario per compatibilità WooCommerce
        if (typeof jQuery === 'undefined') {
            reject(new Error('jQuery non disponibile'));
            return;
        }

        jQuery(document).ready(function($) {
            const checkForm = setInterval(function() {
            const $select = $('[name="attribute_pa_tipologia-ingressi"]');

            if ($select.length === 0) {
                return;
            }

            clearInterval(checkForm);

            let valueToSelect = null;

            $select.find('option').each(function() {
                const optionValue = $(this).val();
                const optionText = $(this).text().toLowerCase();

                if (ticketType === '4h' && (optionValue.includes('mezza-giornata') )) {
                    valueToSelect = optionValue;
                } else if (ticketType === 'giornaliero' && (optionValue.includes('giornalier') || optionText.includes('giornalier'))) {
                    valueToSelect = optionValue;
                }
            });

            if (!valueToSelect) {
                reject(new Error(`Nessuna opzione trovata per ticketType: ${ticketType}`));
                return;
            }

            $select.val(valueToSelect).trigger('change');
            resolve(true);

            }, 100);

            setTimeout(function() {
                clearInterval(checkForm);
                reject(new Error('Timeout: select variazioni non trovata'));
            }, 10000);
        });
    });
}

// jQuery necessario per intercettare eventi custom WooCommerce (found_variation, show_variation)
// che non sono eventi DOM nativi e non possono essere ascoltati con addEventListener()
function waitForVariationPrice() {
    return new Promise((resolve, reject) => {
        if (typeof jQuery === 'undefined') {
            reject(new Error('jQuery non disponibile'));
            return;
        }

        jQuery(document).ready(function($) {
            const $variationForm = $('.variations_form');
            if ($variationForm.length === 0) {
                reject(new Error('Form variazioni non trovato'));
                return;
            }

            let stabilizeTimer = null;

            // show_variation può triggerare più volte, aspetta che il prezzo si stabilizzi
            $variationForm.on('show_variation', function(event, variation) {
                if (stabilizeTimer) {
                    clearTimeout(stabilizeTimer);
                }

                stabilizeTimer = setTimeout(function() {
                    const priceContainer = document.querySelector('.woocommerce-variation-price');

                    if (priceContainer && priceContainer.innerHTML.trim()) {
                        resolve({
                            html: priceContainer.innerHTML,
                            element: priceContainer,
                            price: variation.display_price,
                            priceHtml: variation.price_html,
                            variation: variation
                        });
                    } else {
                        reject(new Error('Container prezzo non trovato nel DOM'));
                    }
                }, 500);
            });

            $variationForm.trigger('check_variations');

            setTimeout(function() {
                if (stabilizeTimer) {
                    clearTimeout(stabilizeTimer);
                }
                reject(new Error('Timeout: nessun evento show_variation ricevuto'));
            }, 10000);
        });
    });
}

function setQuantity(guests) {
    const qtyInput = document.querySelector('.product input[name="quantity"]');

    if (qtyInput && guests) {
        qtyInput.value = guests;
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function setLocationImage(locationId) {
    const imageColumn = document.getElementById('colonna-img-prodotto');

    if (imageColumn && locationId) {
        const imagePath = `/wp-content/plugins/plugin-custom-skianet/assets/img/strutture/fig_${locationId}.jpg`;
        imageColumn.style.backgroundImage = `url('${imagePath}')`;
    }
}

function buildDataUI(data) {
    const DATA_LABELS = {
        'location': 'Struttura',
        'date': 'Data',
        'time': 'Orario',
        'ticket': 'Tipologia',
        'guests': 'Ospiti',
        'unitPrice': 'Prezzo unitario',
        'totalPrice': 'Totale',
        'priceHtml': 'Prezzo'
    };

    console.log('Dati da mostrare nella UI:', data);

    const titleWrapper = document.querySelector('.product .product_title').parentElement;
    const titleEl = document.querySelector('.product .product_title');
    const titleText = titleEl.textContent

    const timeInfo = data.time ? ` alle ore <b>${data.time}</b>` : '';
    const newTitle = `Prenota a <b>${data.location}</b> per il giorno <b>${data.date}</b>${timeInfo} per ${data.guests} ${data.guests === 1 ? 'ospite' : 'ospiti'} (${data.ticket}).`;
    titleEl.innerHTML = newTitle;

    const secondaryInfo = document.createElement('div');
    secondaryInfo.className = 'secondary-info';
    const priceEl = document.createElement('span');
    priceEl.className = 'price';
    priceEl.innerHTML = data.guests === 1 ? data.totalPrice + '€' : `<span>${data.totalPrice}€</span><span class="note">(${data.unitPrice}€ x ${data.guests})</span>`;

    const noteEl = document.createElement('span');
    noteEl.className = 'note';
    noteEl.textContent = titleText;

    secondaryInfo.appendChild(priceEl);
    secondaryInfo.appendChild(noteEl);
    titleWrapper.appendChild(secondaryInfo);



    const container = document.createElement('div');
    container.className = 'custom-data-ui';

    const list = document.createElement('ul');

    const HIDDEN_KEYS = ['unitPrice', 'totalPrice'];
    for (const [key, value] of Object.entries(data)) {
        if (HIDDEN_KEYS.includes(key)) continue;
        const listItem = document.createElement('li');
        const label = DATA_LABELS[key] || key;
        listItem.textContent = `${label}: ${value}`;
        list.appendChild(listItem);
    }

    container.appendChild(list);

    // append container after .woocommerce-variation-description
    const variationDesc = document.querySelector('.woocommerce-variation-description');
    if (variationDesc) {
        variationDesc.parentNode.insertBefore(container, variationDesc.nextSibling);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const productElement = document.querySelector('.product');
    if (!productElement) {
        console.error('Elemento prodotto non trovato');
        return;
    }
    
    productElement.classList.add('loadingData');
    const data = getVars();

    const urlParams = new URLSearchParams(window.location.search);
    const locationId = urlParams.get('location');
    const ticketType = urlParams.get('ticket_type');

    setQuantity(data.guests);
    setLocationImage(locationId);
    if (!ticketType) {
        console.error('Parametro ticket_type non specificato nell\'URL');
        productElement.classList.remove('loadingData');
        return;
    }

    try {
        await selectVariation(ticketType);
        const priceData = await waitForVariationPrice();
        const unitPrice = priceData.price;
        const totalPrice = unitPrice * data.guests;
        data.unitPrice = unitPrice;
        data.totalPrice = totalPrice;
        data.priceHtml = data.guests === 1 ? `${unitPrice}€` : `${unitPrice}€ x ${data.guests} = ${totalPrice}€`;

        const hasNullOrUndefined = Object.values(data).some(value => value === null || value === undefined);

        if (hasNullOrUndefined) {
            // c'è qualche problema con i dati
            console.error('Dati incompleti:', data);
            // TODO gestire l'errore: nascondere il bottone di carrello e inserire un bottone back
        }

        productElement.classList.remove('loadingData');
        productElement.classList.add('customDataLoaded');
        // Ora sistemo la UI
        buildDataUI(data);

    } catch (error) {
        console.error('Errore:', error.message);
        productElement.classList.remove('loadingData');
    }
});