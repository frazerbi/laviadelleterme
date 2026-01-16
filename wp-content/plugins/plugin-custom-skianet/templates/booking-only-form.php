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
$user_codes = Booking_Only_Handler::get_user_codes();

?>

<div class="booking-only-form-wrapper">
    <form id="booking-only-form" class="booking-only-form" method="post" action="">
        
        <input type="hidden" name="action" value="submit_booking_only_ajax">
        <?php wp_nonce_field('booking_only_form_action', 'booking_only_form_nonce'); ?>

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
                pattern="[A-Z0-9]{16}"
                maxlength="16"
                minlength="16"
            >
        </div>

        <?php if (!empty($user_codes)): ?>
        <!-- Dropdown codici salvati -->
        <div class="saved-codes-wrapper">
            <label for="saved_codes" class="saved-codes-label">
                Oppure seleziona un codice dai tuoi ordini:
            </label>
            <select id="saved_codes" class="saved-codes-select">
                <option value="">-- Seleziona un codice --</option>
                <?php foreach ($user_codes as $order_data): ?>
                    <optgroup label="Ordine #<?php echo esc_attr($order_data['order_number']); ?> - <?php echo esc_html($order_data['order_date']); ?>">
                        <?php foreach ($order_data['codes'] as $code_data): ?>
                            <option value="<?php echo esc_attr($code_data['code']); ?>">
                                <?php echo esc_html($code_data['code']); ?>
                                <?php if (!empty($code_data['product_name'])): ?>
                                    - <?php echo esc_html($code_data['product_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Location -->
        <div class="form-group visualradio-group">
            <legend for="location">Seleziona Location:</legend>
                <?php foreach ($locations as $value => $label): ?>
                    <label class="visualradio-item disabled" for="location_<?php echo esc_attr($value); ?>">
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
            <label for="booking-only_date">
                Data Prenotazione:
                <span class="label-hint">Seleziona la data desiderata</span>
            </label>
            <input 
                type="date" 
                name="booking-only_date" 
                id="booking-only_date" 
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
            <div class="gender-options">
                <label class="gender-item disabled" for="gender_male">
                    <input 
                        type="radio" 
                        name="gender" 
                        id="gender_male" 
                        value="male" 
                        required 
                        disabled
                        class="gender-input"
                    >
                    <span class="gender-label">Uomo</span>
                </label>
                
                <label class="gender-item disabled" for="gender_female">
                    <input 
                        type="radio" 
                        name="gender" 
                        id="gender_female" 
                        value="female" 
                        required 
                        disabled
                        class="gender-input"
                    >
                    <span class="gender-label">Donna</span>
                </label>
            </div>
        </div>

        <!-- Messaggio di risposta -->
        <div id="booking-only-response" class="booking-only-response"></div>

        <!-- Submit -->
        <div class="form-group">
            <button type="submit" name="submit_booking_only" class="btn-submit">
                Prosegui con la prenotazione
            </button>
        </div>
    </form>
</div>