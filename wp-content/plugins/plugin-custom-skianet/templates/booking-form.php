<?php
/**
 * Template per il form di prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni i dati dalla classe
$locations = Booking_Handler::get_available_locations();
$ticket_types = Booking_Handler::get_ticket_types();
?>

<div class="booking-form-wrapper skianet-booking-wrapper">
    <form id="booking-form" class="booking-form" method="post" action="">
        
        <input type="hidden" name="action" value="submit_booking_ajax">
        <?php wp_nonce_field('booking_form_action', 'booking_form_nonce'); ?>
        
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

        <!-- Tipo di Ingresso -->
        <div class="form-group">
            <label for="ticket_type">Tipo di Ingresso: <span class="required">*</span></label>
            <select name="ticket_type" id="ticket_type" required disabled>
                <option value="">-- Seleziona tipo di ingresso --</option>
                <?php foreach ($ticket_types as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Fascia Oraria -->
        <div class="form-group">
            <label for="time_slot">A che ora ti aspettiamo? <span class="required">*</span></label>
            <select name="time_slot" id="time_slot" required disabled>
                <option value="">-- Seleziona una fascia oraria --</option>
                <!-- Le opzioni vengono popolate dinamicamente da JavaScript -->
            </select>
        </div>
        
        <!-- Numero Ingressi -->
        <div class="form-group-row">
            <div class="form-group form-group-half">
                <label for="num_male">Ingressi Uomo: <span class="required">*</span></label>
                <input type="tel" name="num_male" id="num_male" 
                       min="0" max="20" value="0" placeholder="0" required disabled>
            </div>

            <div class="form-group form-group-half">
                <label for="num_female">Ingressi Donna: <span class="required">*</span></label>
                <input type="tel" name="num_female" id="num_female" 
                       min="0" max="20" value="0" placeholder="0" required disabled>
            </div>
        </div>


        <!-- Messaggio di risposta -->
        <div id="booking-response" class="booking-response"></div>

        <!-- Submit -->
        <div class="form-group">
            <button type="submit" name="submit_booking" class="btn-submit">
                Prosegui con la prenotazione
            </button>
        </div>
    </form>
</div>