<?php
/**
 * Template per il form di prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="booking-form-wrapper">
    <form id="booking-form" class="booking-form" method="post" action="">
        
        <?php wp_nonce_field('booking_form_action', 'booking_form_nonce'); ?>
        
        <!-- Location -->
        <div class="form-group">
            <label for="location">Seleziona Location:</label>
            <select name="location" id="location" required>
                <option value="">-- Scegli una location --</option>
                <option value="location1">Location 1</option>
                <option value="location2">Location 2</option>
                <option value="location3">Location 3</option>
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
            <label>Tipo di Ingresso:</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="ticket_type" value="4h" required>
                    4 Ore
                </label>
                <label>
                    <input type="radio" name="ticket_type" value="giornaliero" required>
                    Giornaliero
                </label>
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