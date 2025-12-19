document.addEventListener('DOMContentLoaded', function() {
    // Leggi variation_id dall'URL
    const urlParams = new URLSearchParams(window.location.search);
    const variationId = urlParams.get('variation_id');
    
    if (!variationId) {
        console.log('Nessuna variazione da pre-selezionare');
        return;
    }
    
    console.log('Pre-selezione variazione ID:', variationId);
    
    // Aspetta che WooCommerce carichi le variazioni
    const waitForVariations = setInterval(function() {
        const variationForm = document.querySelector('.variations_form');
        
        if (!variationForm) {
            console.log('Form variazioni non trovato');
            return;
        }
        
        // Trova il select delle variazioni (potrebbe variare in base al tema)
        const selectFields = variationForm.querySelectorAll('select[name^="attribute_"]');
        
        if (selectFields.length === 0) {
            console.log('Nessun campo variazione trovato');
            return;
        }
        
        console.log('Trovati', selectFields.length, 'campi variazione');
        clearInterval(waitForVariations);
        
        // Cicla tutti i select delle variazioni
        selectFields.forEach(function(select) {
            // Trova l'option con data-variation_id corrispondente
            const options = select.querySelectorAll('option');
            
            options.forEach(function(option) {
                // Controlla se l'option corrisponde alla variazione
                const optionValue = option.value;
                
                // Seleziona in base al ticket_type dall'URL
                const ticketType = urlParams.get('ticket_type');
                
                if (ticketType === '4h' && optionValue.toLowerCase().includes('4')) {
                    option.selected = true;
                    console.log('Selezionato 4 ore:', optionValue);
                } else if (ticketType === 'giornaliero' && optionValue.toLowerCase().includes('giornaliero')) {
                    option.selected = true;
                    console.log('Selezionato giornaliero:', optionValue);
                }
            });
            
            // Trigger change event per aggiornare il prezzo
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        
        // Se WooCommerce usa jQuery, triggera anche quello
        if (typeof jQuery !== 'undefined') {
            jQuery(variationForm).trigger('check_variations');
            jQuery(variationForm).trigger('woocommerce_variation_select_change');
        }
        
    }, 500); // Controlla ogni 500ms finché non trova il form
    
    // Stop dopo 10 secondi
    setTimeout(function() {
        clearInterval(waitForVariations);
    }, 10000);
});