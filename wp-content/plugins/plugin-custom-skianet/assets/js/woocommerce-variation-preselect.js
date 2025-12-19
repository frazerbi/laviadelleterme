document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const variationId = urlParams.get('variation_id');
    const ticketType = urlParams.get('ticket_type');
    const totalGuests = parseInt(urlParams.get('total_guests')) || 1;
    
    if (!variationId && !ticketType) {
        console.log('Nessuna variazione da pre-selezionare');
        return;
    }
    
    console.log('Pre-selezione:', { variationId, ticketType, totalGuests });
    
    const waitForVariations = setInterval(function() {
        const variationForm = document.querySelector('.variations_form');
        
        if (!variationForm) {
            return;
        }
        
        const selectFields = variationForm.querySelectorAll('select[name^="attribute_"]');
        
        if (selectFields.length === 0) {
            return;
        }
        
        console.log('Trovati', selectFields.length, 'campi variazione');
        clearInterval(waitForVariations);
        
        // STEP 1: Seleziona la variazione
        selectFields.forEach(function(select) {
            const options = select.querySelectorAll('option');
            
            options.forEach(function(option) {
                const optionValue = option.value;
                
                if (ticketType === '4h' && optionValue.toLowerCase().includes('4')) {
                    option.selected = true;
                    console.log('Selezionato 4 ore:', optionValue);
                } else if (ticketType === 'giornaliero' && (optionValue.toLowerCase().includes('giornaliero') || optionValue.toLowerCase().includes('giorno'))) {
                    option.selected = true;
                    console.log('Selezionato giornaliero:', optionValue);
                }
            });
            
            select.dispatchEvent(new Event('change', { bubbles: true }));
            
            // ✅ BLOCCA il campo variazione
            select.disabled = true;
            select.style.pointerEvents = 'none';
            select.style.opacity = '0.6';
            select.style.cursor = 'not-allowed';
        });
        
        // STEP 2: Imposta e blocca la quantità
        setTimeout(function() {
            const qtyInput = variationForm.querySelector('input[name="quantity"]') || 
                           variationForm.querySelector('.qty') ||
                           document.querySelector('input.qty');
            
            if (qtyInput) {
                qtyInput.value = totalGuests;
                console.log('Quantità impostata:', totalGuests);
                
                qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // ✅ BLOCCA il campo quantità
                qtyInput.readOnly = true;
                qtyInput.disabled = true;
                qtyInput.style.pointerEvents = 'none';
                qtyInput.style.opacity = '0.6';
                qtyInput.style.cursor = 'not-allowed';
                qtyInput.style.backgroundColor = '#f5f5f5';
                
                // ✅ BLOCCA anche i pulsanti +/- se esistono
                const qtyButtons = document.querySelectorAll('.quantity .plus, .quantity .minus');
                qtyButtons.forEach(function(btn) {
                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.4';
                    btn.style.cursor = 'not-allowed';
                });
            } else {
                console.warn('Campo quantità non trovato');
            }
        }, 1000);
        
        // Trigger WooCommerce events
        if (typeof jQuery !== 'undefined') {
            setTimeout(function() {
                jQuery(variationForm).trigger('check_variations');
                jQuery(variationForm).trigger('woocommerce_variation_select_change');
            }, 1000);
        }
        
    }, 500);
    
    setTimeout(function() {
        clearInterval(waitForVariations);
    }, 10000);
});