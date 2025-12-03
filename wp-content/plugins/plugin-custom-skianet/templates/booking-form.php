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
$time_slots = Booking_Handler::get_time_slots();

?>

<div class="booking-form-wrapper">
    <form id="booking-form" class="booking-form" method="post" action="">
        
        <input type="hidden" name="action" value="submit_booking_ajax">
        <?php wp_nonce_field('booking_form_action', 'booking_form_nonce'); ?>
        
        <!-- Location -->
        <div class="form-group">
            <label for="location">Seleziona Location:</label>
            <select name="location" id="location" required>
                <option value="">-- Scegli una location --</option>
                <?php foreach ($locations as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Data -->
        <div class="form-group">
            <label for="booking_date">Data:</label>
            <input type="date" name="booking_date" id="booking_date" required 
                   min="<?php echo date('Y-m-d'); ?>">
        </div>

        <!-- Tipo di Ingresso -->
        <div class="form-group">
            <label for="ticket_type">Tipo di Ingresso: <span class="required">*</span></label>
            <select name="ticket_type" id="ticket_type" required>
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
            <select name="time_slot" id="time_slot" required>
                <option value="">-- Seleziona una fascia oraria --</option>
                <?php foreach ($time_slots as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Numero Ingressi -->
        <div class="form-group-row">
            <div class="form-group form-group-half">
                <label for="num_male">Ingressi Uomo: <span class="required">*</span></label>
                <input type="number" name="num_male" id="num_male" 
                       min="0" max="20" value="0" required>
            </div>

            <div class="form-group form-group-half">
                <label for="num_female">Ingressi Donna: <span class="required">*</span></label>
                <input type="number" name="num_female" id="num_female" 
                       min="0" max="20" value="0" required>
            </div>
        </div>

        <!-- Submit -->
        <div class="form-group">
            <button type="submit" name="submit_booking" class="btn-submit">
                Prenota Ora
            </button>
        </div>

        <!-- Messaggio di risposta -->
        <div id="booking-response" class="booking-response"></div>
    </form>
</div>