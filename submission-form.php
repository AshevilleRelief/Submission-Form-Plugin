<?php
/*
Plugin Name: Submission Form Plugin
Description: A plugin to create a submission form and send data to Discord and Google Sheets without refreshing the page.
Version: 1.0
Author: Chris White
*/

// Enqueue jQuery and localize ajaxurl
function enqueue_submission_form_scripts() {
    wp_enqueue_script('jquery');
    
    // Localize ajaxurl for use in our AJAX requests
    wp_localize_script('jquery', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'enqueue_submission_form_scripts');

// [submit-info] shortcode to display the form
function create_submission_form() {
    ob_start();
    ?>
<form id="submissionForm" method="post" action="">
    <label for="organization">Site of Resource or Organization Name:</label><br>
    <input type="text" name="organization" required><br>

    <label for="category">Category:</label><br>
    <select name="category" required>
        <option value="Other">Other</option>
        <option value="Grocery Distribution">Grocery Distribution</option>
        <option value="Water and Supply Stations">Water and Supply Stations</option>
        <option value="Harm Reduction">Harm Reduction</option>
        <option value="Donation Drop-off">Donation Drop-off</option>
        <option value="Emergency Shelter">Emergency Shelter</option>
        <option value="Supply Distribution">Supply Distribution</option>
        <option value="Water Distribution">Water Distribution</option>
        <option value="Ice Distribution">Ice Distribution</option>
        <option value="Food and Water Distribution">Food and Water Distribution</option>
        <option value="Food Distribution">Food Distribution</option>
    </select><br>

    <label for="contact_info">Contact Info for Organization:</label><br>
    <textarea name="contact_info" required></textarea><br>

    <label for="address">Address of Resource:</label><br>
    <input type="text" name="address" required><br>

    <label for="cross_streets">Cross Streets, Landmarks, or Long/Lat:</label><br>
    <input type="text" name="cross_streets"><br>

    <label for="retrieved_datetime">Date & Time This Info Was Retrieved:</label><br>
    <input type="text" name="retrieved_datetime" required><br>

    <label for="available_datetime">Date & Time This Resource is Available:</label><br>
    <input type="text" name="available_datetime" required><br>

    <label for="good_until">This Resource is Good Until:</label><br>
    <input type="text" name="good_until"><br>

    <label for="payment_required">Payment Required?:</label><br>
    <select name="payment_required" required>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
        <option value="Free">Free</option>
    </select><br>

    <label for="hours">Daily Hours Of Operation:</label><br>
    <input type="text" name="hours"><br>

    <label for="details">Details/Comments:</label><br>
    <textarea name="details" required></textarea><br>

    <label for="link">Link / Website:</label><br>
    <input type="text" name="link"><br>

    <label for="source">Source of Information:</label><br>
    <input type="text" name="source"><br>

    <input type="submit" id="submitBtn" value="Submit">

    <!-- Loading spinner -->
    <div id="loadingSpinner" style="display:none; margin-top: 10px;width: 30px;">
        <img src="https://ashevillerelief.com/wp-content/uploads/2024/10/loading.gif" alt="Loading..." />
    </div>
</form>

<div id="form-response"></div> <!-- This will display the success or error message -->
   
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#submissionForm').on('submit', function(e) {
        e.preventDefault(); // Prevent the default form submission
        
        // Disable the submit button and show the spinner
        $('#submitBtn').attr('disabled', true);
        $('#loadingSpinner').show();

        var formData = {
            action: 'handle_form_submission', // Hook for the AJAX action
            organization: $('input[name="organization"]').val(),
            category: $('select[name="category"]').val(),
            contact_info: $('textarea[name="contact_info"]').val(),
            address: $('input[name="address"]').val(),
            cross_streets: $('input[name="cross_streets"]').val(),
            retrieved_datetime: $('input[name="retrieved_datetime"]').val(),
            available_datetime: $('input[name="available_datetime"]').val(),
            good_until: $('input[name="good_until"]').val(),
            payment_required: $('select[name="payment_required"]').val(),
            hours: $('input[name="hours"]').val(),
            details: $('textarea[name="details"]').val(),
            link: $('input[name="link"]').val(),
            source: $('input[name="source"]').val()
        };

        $.post(ajax_object.ajaxurl, formData, function(response) {
            $('#form-response').html('<p>Your submission has been received and will be added by our team!</p>');
            // Hide spinner and re-enable the button
            $('#loadingSpinner').hide();
            $('#submitBtn').attr('disabled', false);
        }).fail(function() {
            $('#form-response').html('<p>There was an error submitting your form. Please try again later.</p>');
            // Hide spinner and re-enable the button
            $('#loadingSpinner').hide();
            $('#submitBtn').attr('disabled', false);
        });
    });
});
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('submit-info', 'create_submission_form');

// Handle form submission with AJAX
function handle_form_submission_ajax() {
    if (isset($_POST['organization'])) {
        $organization = sanitize_text_field($_POST['organization']);
        $category = sanitize_text_field($_POST['category']);
        $contact_info = sanitize_textarea_field($_POST['contact_info']);
        $address = sanitize_text_field($_POST['address']);
        $cross_streets = sanitize_text_field($_POST['cross_streets']);
        $retrieved_datetime = sanitize_text_field($_POST['retrieved_datetime']);
        $available_datetime = sanitize_text_field($_POST['available_datetime']);
        $good_until = sanitize_text_field($_POST['good_until']);
        $payment_required = sanitize_text_field($_POST['payment_required']);
        $hours = sanitize_text_field($_POST['hours']);
        $details = sanitize_textarea_field($_POST['details']);
        $link = esc_url_raw($_POST['link']);
        $source = sanitize_text_field($_POST['source']);

        // Send notification to Discord
        send_discord_notification($organization, $category, $contact_info, $address, $cross_streets, $retrieved_datetime, $available_datetime, $good_until, $payment_required, $hours, $details, $link, $source);

        // Send data to Google Sheets
        send_data_to_google_sheets($organization, $category, $contact_info, $address, $cross_streets, $retrieved_datetime, $available_datetime, $good_until, $payment_required, $hours, $details, $link, $source);

        wp_send_json_success('Form submission successful!');
    } else {
        wp_send_json_error('Invalid form submission');
    }
}
add_action('wp_ajax_handle_form_submission', 'handle_form_submission_ajax');
add_action('wp_ajax_nopriv_handle_form_submission', 'handle_form_submission_ajax');

// Send data to Discord via Webhook
function send_discord_notification($organization, $category, $contact_info, $address, $cross_streets, $retrieved_datetime, $available_datetime, $good_until, $payment_required, $hours, $details, $link, $source) {
    $webhook_url = 'DISCORD_WEBHOOK_URL_HERE'; // Add your webhook URL here

    $message = "**New Submission:**\n";
    $message .= "Organization: $organization\n";
    $message .= "Category: $category\n";
    $message .= "Contact Info: $contact_info\n";
    $message .= "Address: $address\n";
    $message .= "Cross Streets: $cross_streets\n";
    $message .= "Retrieved Date/Time: $retrieved_datetime\n";
    $message .= "Available Date/Time: $available_datetime\n";
    $message .= "Good Until: $good_until\n";
    $message .= "Payment Required: $payment_required\n";
    $message .= "Hours: $hours\n";
    $message .= "Details: $details\n";
    if (!empty($link)) {
        $message .= "Link: < $link >\n";
    }
    $message .= "Source: $source\n";
	$message .= "__________________________________________\n";

    $data = json_encode(array('content' => $message));

    $args = array(
        'body'        => $data,
        'headers'     => array('Content-Type' => 'application/json'),
    );

    wp_remote_post($webhook_url, $args);
}

// Send data to Google Sheets
function send_data_to_google_sheets($organization, $category, $contact_info, $address, $cross_streets, $retrieved_datetime, $available_datetime, $good_until, $payment_required, $hours, $details, $link, $source) {
    $script_url = 'SCRIPT_URL_GOES_HERE'; // Add your Google Apps Script URL

    $data = array(
        'organization' => $organization,
        'category' => $category,
        'contact_info' => $contact_info,
        'address' => $address,
        'cross_streets' => $cross_streets,
        'retrieved_datetime' => $retrieved_datetime,
        'available_datetime' => $available_datetime,
        'good_until' => $good_until,
        'payment_required' => $payment_required,
        'hours' => $hours,
        'details' => $details,
        'link' => $link,
        'source' => $source,
    );

    $args = array(
        'body'        => http_build_query($data),
        'headers'     => array('Content-Type' => 'application/x-www-form-urlencoded'),
    );

    wp_remote_post($script_url, $args);
}
