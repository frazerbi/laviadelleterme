<?php
/**
 * Template per il form di prenotazione con codice acquisto
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni i dati dalla classe
$locations = Booking_Handler::get_available_locations();
?>

<div class="booking-form-wrapper skianet-booking-wrapper">
    <form id="booking-form-code" class="booking-form booking-form-code" method="post" action="">
        
        <input type="hidden" name="action" value="submit_booking_with_code_ajax">
        <?php wp_nonce_field('booking_code_form_action', 'booking_code_form_nonce'); ?>

        <!-- Codice Acquisto -->
        <div class="form-group">
            <label for="purchase_code">
                Codice Acquisto:
                <span class="label-hint">Inserisci il codice ricevuto dopo l'acquisto</span>
            </label>
            <input 
                type="text" 
                name="purchase_code" 
                id="purchase_code" 
                required 
                placeholder="XXXXXXXXXXXXXXXX"
                aria-label="Inserisci codice acquisto"
                pattern="[A-Z0-9]+"
                maxlength="16"

            >
        </div>

                <!-- Location -->
        <div class="form-group visualradio-group">
            <legend for="location">Seleziona Location:</legend>
                <?php foreach ($locations as $value => $label): ?>
                    <label class="visualradio-item" for="location_<?php echo esc_attr($value); ?>">
                        <input class="visualradio-input" type="radio" name="location" id="location_<?php echo esc_attr($value); ?>" value="<?php echo esc_attr($value); ?>" required>
                        <span class="visualradio-label"><?php echo esc_html($label); ?></span>
                        <figure class="visualradio-thumb">
                            <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__))); ?>assets/img/strutture/thumb_<?php echo esc_attr($value); ?>.jpg" alt="<?php echo esc_html($label); ?> thumb">
                        </figure>
                    </label>
                <?php endforeach; ?>
        </div>

        <!-- Data -->
        <div class="form-group">
            <label for="booking_date">
                Data Prenotazione:
                <span class="label-hint">Seleziona la data desiderata</span>
            </label>
            <input 
                type="date" 
                name="booking_date" 
                id="booking_date" 
                required 
                disabled
                min="<?php echo esc_attr(date('Y-m-d')); ?>"
                max="<?php echo esc_attr(date('Y-m-d', strtotime('+2 months'))); ?>"
                placeholder="gg/mm/aaaa"
                aria-label="Seleziona data prenotazione"
            >
        </div>
        
        <!-- Fascia Oraria -->
        <div class="form-group">
            <label for="time_slot">A che ora ti aspettiamo? <span class="required">*</span></label>
            <select name="time_slot" id="time_slot" required disabled>
                <option value="">-- Seleziona una fascia oraria --</option>
                <!-- Le opzioni vengono popolate dinamicamente da JavaScript -->
            </select>
        </div>
        
        <!-- Sesso ingresso -->
        <div class="form-group gender-group">
            <legend>Questo codice è per: <span class="required">*</span></legend>
            <div class="radio-options">
                <label class="radio-option">
                    <input 
                        type="radio" 
                        name="gender" 
                        id="gender_male" 
                        value="male" 
                        required 
                        disabled
                    >
                    <span class="radio-label">Uomo</span>
                </label>
                <label class="radio-option">
                    <input 
                        type="radio" 
                        name="gender" 
                        id="gender_female" 
                        value="female" 
                        required 
                        disabled
                    >
                    <span class="radio-label">Donna</span>
                </label>
            </div>
        </div>

        <!-- Messaggio di risposta -->
        <div id="booking-response" class="booking-response"></div>

        <!-- Submit -->
        <div class="form-group">
            <button type="submit" name="submit_booking_code" class="btn-submit" disabled>
                Conferma Prenotazione
            </button>
        </div>
    </form>
</div>
