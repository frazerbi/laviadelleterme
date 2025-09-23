'use strict';

(($) => {
    if (typeof skianet_ajax_object === 'undefined') {
        console.error('skianet_ajax_object not found');
        return;
    }

    let disponibilitaContainer = '#skn-disponibilita-page';
    let calendar = null;
    let calendarEl = disponibilitaContainer + ' #' + skianet_ajax_object?.calendar_container_id + ' #' + skianet_ajax_object?.calendar_element_id;
    let calendarMyAccEl = '.' + skianet_ajax_object?.calendar_my_account + ' #' + skianet_ajax_object?.calendar_element_id;
    let loader = disponibilitaContainer + ' #' + skianet_ajax_object?.calendar_container_id + ' .calendar-loader';
    let locationSelect = disponibilitaContainer + ' .elementor-form .elementor-field-type-select #form-field-sede';
    let qtySelect = disponibilitaContainer + ' .elementor-form .elementor-field-type-number #form-field-qty';
    let citySelect = '.elementor-field-type-select #form-field-city';
    let dialogSwiper = '.elementor-widget-loop-carousel .swiper-container';
    let variationForms = {};
    let calendarFetching = false;
    let calendarFetchingError = false;
    let locationFetching = null;
    let qtyFetching = null;

    $(document).on('ready', () => {
        const today = new Date();
        const firstDayCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDayNextMonth = new Date(today.getFullYear(), today.getMonth() + 4, 0); // Ultimo giorno del mese successivo
    
        calendarEl = $(calendarEl);
        loader = $(loader);
        locationSelect = $(locationSelect);
        qtySelect = $(qtySelect);
        calendarMyAccEl = $(calendarMyAccEl);

        if (calendarEl.length !== 1 || locationSelect.length !== 1 || qtySelect.length !== 1 || loader.length !== 1) {
            console.error('calendar element not found');
            return;
        }

        calendar = new FullCalendar.Calendar(calendarEl[0], {
            locale: 'it',
            timeZone: skianet_ajax_object.calendar_timezone ?? 'Europe/Rome',
            selectable: false,
            editable: false,
            droppable: false,
            initialDate: today.toISOString().split('T')[0], // Imposta la data iniziale su oggi
            // validRange: {
            //     start: firstDayCurrentMonth.toISOString().split('T')[0], // Inizio del mese corrente
            //     end: lastDayNextMonth.toISOString().split('T')[0], // Fine del mese successivo
            // },
            visibleRange: function(currentDate) {
                    // Calcola il primo giorno del mese corrente della data visualizzata
                    const startOfCurrentMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
                    // Calcola il primo giorno del mese dopo il prossimo (per includere 2 mesi completi)
                    const startOfTwoMonthsLater = new Date(currentDate.getFullYear(), currentDate.getMonth() + 2, 1);
                    
                    console.log('=== VISIBLE RANGE DEBUG ===');
                    console.log('Current Date:', currentDate.toISOString().split('T')[0]);
                    console.log('Current Month:', currentDate.getMonth(), '(' + currentDate.toLocaleDateString('it-IT', { month: 'long' }) + ')');
                    console.log('Start of Current Month:', startOfCurrentMonth.toISOString().split('T')[0]);
                    console.log('Start of Two Months Later:', startOfTwoMonthsLater.toISOString().split('T')[0]);
                    console.log('Range Duration (days):', Math.ceil((startOfTwoMonthsLater - startOfCurrentMonth) / (1000 * 60 * 60 * 24)));
                    
                    // Calcola anche l'ultimo giorno del mese successivo per verifica
                    const lastDayOfNextMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 2, 0);
                    console.log('Last Day of Next Month:', lastDayOfNextMonth.toISOString().split('T')[0]);
                    console.log('Should include 30 Aug:', lastDayOfNextMonth.getDate() >= 30 ? 'YES' : 'NO');
                    console.log('Should include 31 Aug:', lastDayOfNextMonth.getDate() >= 31 ? 'YES' : 'NO');
                    console.log('========================');
                    
                    return {
                        start: startOfCurrentMonth,
                        end: startOfTwoMonthsLater
                    };
                },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            initialView: getMaxWidth() > 700 ? 'dayGridMonth' : 'timeGridWeek',
            navLinks: true,
            aspectRatio: 2,
            allDaySlot: false,
            slotDuration: '01:00:00',
            slotMinTime: '08:00:00',
            slotMaxTime: '21:00:00',
            expandRows: true,
            eventShortHeight: 60,
            eventDisplay: 'block',
            listDayFormat: {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                weekday: 'long',
            },
            listDaySideFormat: false,
            events: (info, successCallback, failureCallback) => {
                if (calendarFetching) {
                    locationSelect.val(locationFetching);
                    qtySelect.val(qtyFetching);
                    calendarFetchingError = true;
                    return failureCallback(new Error('calendar is already fetching'));
                }

                loader.show();
                calendarFetching = true;
                locationFetching = locationSelect.val();
                qtyFetching = qtySelect.val();
                fetch(new Request(skianet_ajax_object?.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': skianet_ajax_object?.action_get_disp,
                        'location': locationFetching,
                        'qty': qtyFetching,
                        'start': info.startStr,
                        'end': info.endStr,
                        'timeZone': info.timeZone,
                    }),
                })).then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                    return failureCallback(response);
                }).then(json => {
                    if (calendarFetchingError && Array.isArray(json)) {
                        json.forEach(event => calendar.addEvent(event));
                        calendarFetchingError = false;
                    }
                    return successCallback(json);
                }).catch(error => {
                    return failureCallback(error);
                }).finally(() => {
                    calendarFetching = false;
                    locationSelect.val(locationFetching);
                    qtySelect.val(qtyFetching);
                    loader.hide();
                });
            },
            eventContent: (info) => {
                let str = '<div class="fc-event-main-frame">';
                str += info.timeText && ('<div class="fc-event-time">Ore: ' + info.timeText + '</div>');
                str += '<div class="fc-event-title-container">';
                str += '<div class="fc-event-title fc-sticky">';
                str += info.event.title || '&nbsp;';
                str += '</div>';
                str += '</div>';
                str += '</div>';
                return {html: str};
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false,
                hour12: false,
            },
            displayEventEnd: false,
            eventClick: info => {
                info?.jsEvent.preventDefault();
                info?.jsEvent.stopPropagation();
                loader.show();
                $.post(skianet_ajax_object?.ajax_url, {
                    'action': skianet_ajax_object?.action_get_book_modal,
                    'location': locationSelect.find('option:selected').text(),
                    'qty': qtySelect.val(),
                    'id': info?.event?.id,
                    'event': JSON.stringify(info?.event),
                    'from': calendarMyAccEl.length === 1 ? 'myaccount' : 'calendar',
                }, response => {
                    if (typeof response !== 'object' || !response.hasOwnProperty('dialog')) {
                        loader.hide();
                        console.error(response);
                        return;
                    }
                    let container = $('#' + skianet_ajax_object?.calendar_container_id);
                    container.append(response.dialog);
                    let dialogEl = container.find('.dialog');
                    if (dialogEl.length !== 1) {
                        loader.hide();
                        console.error('dialog element not found');
                        return;
                    }
                    dialogEl.dialog({
                        appendTo: '#' + skianet_ajax_object?.calendar_container_id,
                        autoOpen: false,
                        draggable: false,
                        height: 'auto',
                        modal: true,
                        resizable: false,
                        width: 'auto',
                        open: () => openDialog(dialogEl) && resizeDialog(dialogEl),
                        close: () => closeDialog(dialogEl),
                    });
                    dialogEl.dialog('open');
                    loader.hide();
                }, 'json');
            },
            windowResize: () => resizeCalendar(calendar),
        });

        calendar.render();
        calendar.setOption('aspectRatio', getCalendarAspectRatio());
        $(window).on('resize resize.dialog scroll', () => resizeCalendar(calendar));
    }).on('change', locationSelect + ', ' + qtySelect, () => {
        let locationSelectEl = $(locationSelect);
        let qtySelectEl = $(qtySelect);
        let qtySelectMin = parseInt(qtySelectEl.attr('min'));
        let qtySelectMax = parseInt(qtySelectEl.attr('max'));
        let qtySelectVal = parseInt(qtySelectEl.val());
        let errorClass = 'elementor-error';
        if (typeof calendar === 'object' && typeof calendar.refetchEvents === 'function' && locationSelectEl.length === 1 && qtySelectEl.length === 1) {
            locationSelectEl.closest('form').find('.' + errorClass).removeClass(errorClass);
            locationSelectEl.closest('form').find('.elementor-field-type-submit button').trigger('click');
            if (isNaN(qtySelectMin) || isNaN(qtySelectMax) || isNaN(qtySelectVal) || qtySelectVal < qtySelectMin || qtySelectVal > qtySelectMax) {
                qtySelectEl.closest('.elementor-field-group').addClass(errorClass);
            } else if ($.trim(locationSelectEl.val()) === '') {
                locationSelectEl.closest('.elementor-field-group').addClass(errorClass);
            } else {
                calendar.refetchEvents();
            }
        }
    }).on('reset', 'form#skndispform', (e) => {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }).on('submit', 'form.cart', (e) => {
        if (!skianet_ajax_object.hasOwnProperty('action_booking_add_cart')) {
            console.error('skianet_ajax_object.action_booking_add_cart not found');
            return;
        }

        calendarEl = $(calendarEl);
        if (calendarEl.length !== 1) {
            console.error('calendar element not found');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        let form = $(e.target);
        form.block({
            message: null,
            overlayCSS: {
                background: '#FFFFFF',
                opacity: 0.6,
            },
        });

        let $thisbutton = form.find('.single_add_to_cart_button');
        $thisbutton.removeClass('added');
        $thisbutton.addClass('loading');

        let data = {'action': skianet_ajax_object?.action_booking_add_cart};
        $.each(form.serializeArray(), (i, item) => {
            item.name = item.name.indexOf('add-to-cart') !== -1 ? 'add_to_cart' : item.name;
            data[item.name] = item.value;
        });

        if (!data.hasOwnProperty('add_to_cart')) {
            data.add_to_cart = form.find('[name^="add-to-cart"]').first().val();
        }

        if (!data.hasOwnProperty('product_id')) {
            data.product_id = form.find('[name^="add-to-cart"]').first().val();
        }

        $.ajax({
            type: 'POST',
            url: skianet_ajax_object?.ajax_url,
            data: data,
            dataType: 'json',
        }).done(response => {
            if (typeof response !== 'object' || !response.hasOwnProperty('fragments')) {
                alert(skianet_ajax_object?.calendar_dialog_error_add_to_cart);
                return;
            }
            $(document.body).trigger('wc_fragment_refresh');
            form.find('.woocommerce-error, .woocommerce-message, .woocommerce-info').remove();
            if (form.hasClass('variations_form cart')) {
                form.find('.woocommerce-variation-add-to-cart').before(response.fragments.notices_html);
            } else {
                form.find('.single_add_to_cart_button').before(response.fragments.notices_html);
            }

            if (response.fragments.notices_html.indexOf('woocommerce-error') === -1) {
                //$(document.body).trigger('added_to_cart', [
                //    response.fragments,
                //    response.cart_hash,
                //    $thisbutton,
                //]);
                //$thisbutton.addClass('added');
                window.location.href = skianet_ajax_object?.cart_url;
            } else {
                form.unblock();
                $thisbutton.removeClass('loading');
            }
        }).fail(response => {
            console.error(response);
            alert(skianet_ajax_object?.calendar_dialog_error_add_to_cart + ': ' + response.statusText);
        });
    });

    /**
     * @returns {number}
     */
    function getCalendarAspectRatio() {
        const width = getMaxWidth();
        if (width < 425) {
            return 0.75;
        } else if (width < 768) {
            return 1;
        } else if (width < 1024) {
            return 1.5;
        } else if (width < 1440) {
            return 1.75;
        } else if (width < 1920) {
            return 2;
        } else {
            return 1.5;
        }
    }

    /**
     * @param dialogEl {jQuery}
     */
    function initializeCitySelect2(dialogEl) {
        let citySelectEl = dialogEl.find(citySelect);
        if (citySelectEl.length !== 1 || !citySelectEl.is(':visible') || citySelectEl.hasClass('select2-hidden-accessible')) {
            return;
        }
        let cityPlaceholder = citySelectEl.find('option:first').text();
        citySelectEl.select2({
            allowClear: true,
            dropdownParent: $('#' + skianet_ajax_object?.calendar_container_id).find('.dialog'),
            language: 'it',
            placeholder: cityPlaceholder,
        });
    }

    /**
     * @param dialogEl {jQuery}
     */
    function initializeFormVariations(dialogEl) {
        let variationForm = dialogEl.find('.variations_form');
        if (typeof wc_add_to_cart_variation_params !== 'undefined' && variationForm.length > 0) {
            variationForm.each((i, el) => {
                if ($(el).is(':visible') && !variationForms.hasOwnProperty(i) && typeof $(el).wc_variation_form === 'function') {
                    variationForms[i] = $(el).wc_variation_form();
                }
            });
        }
    }

    /**
     * @param dialogEl {jQuery}
     * @returns {boolean}
     */
    function openDialog(dialogEl) {
        document.activeElement.blur();
        window.elementorFrontend.init();
        initializeCitySelect2(dialogEl);
        setTimeout(() => initializeFormVariations(dialogEl), 100);
        $(window).on('resize resize.dialog scroll', () => resizeDialog(dialogEl));
        return true;
    }

    /**
     * @param dialogEl {jQuery}
     * @returns {number}
     */
    function getDialogWidth(dialogEl) {
        const width = window.innerWidth;
        let form = dialogEl.find('.elementor-widget-form');
        let formVis = form.length > 0 && form.is(':visible');
        if (width <= 425) {
            return Math.ceil(width * 0.9);
        } else if (width <= 768) {
            return Math.ceil(width * 0.8);
        } else if (width <= 1024) {
            return Math.ceil(width * (formVis ? 0.6 : 0.8));
        } else if (width <= 1440) {
            return Math.ceil(width * (formVis ? 0.5 : 0.7));
        } else {
            return Math.ceil(width * (formVis ? 0.3 : 0.5));
        }
    }

    /**
     * @returns {number}
     */
    function getDialogHeight() {
        return Math.ceil(window.innerHeight * 0.9);
    }

    /**
     * @param dialogEl {jQuery}
     */
    function resizeDialog(dialogEl) {
        if (typeof dialogEl !== 'object' || typeof dialogEl.dialog !== 'function') {
            return;
        }
        dialogEl.dialog('option', 'position', {
            my: 'center',
            at: 'center',
            of: window,
        });
        dialogEl.dialog('option', 'width', getDialogWidth(dialogEl));
        dialogEl.dialog('option', 'height', getDialogHeight());
    }

    /**
     * @param dialogEl {jQuery}
     */
    function closeDialog(dialogEl) {
        let citySelectEl = dialogEl.find(citySelect);
        if (citySelectEl.length === 1 && citySelectEl.is(':visible') && citySelectEl.hasClass('select2-hidden-accessible')) {
            citySelectEl.select2('destroy');
        }

        Object.keys(variationForms).forEach(key => delete variationForms[key]);

        $(window).off('resize resize.dialog scroll');
        dialogEl.dialog('destroy');
        dialogEl.remove();
    }

    /**
     * @param calendar {FullCalendar.Calendar}
     */
    function resizeCalendar(calendar) {
        if (typeof calendar !== 'object' || typeof calendar.setOption !== 'function') {
            return;
        }
        calendar.setOption('aspectRatio', getCalendarAspectRatio());
        //if (getMaxWidth() > 700) {
        //    calendar.changeView('dayGridMonth');
        //} else {
        //    calendar.changeView('listMonth');
        //}
    }

    function getMaxWidth() {
        let maxWidth = window.innerWidth;
        if (calendarMyAccEl.length === 1) {
            maxWidth = Math.min(700, calendarMyAccEl.width());
        }
        return maxWidth;
    }
})(jQuery);
