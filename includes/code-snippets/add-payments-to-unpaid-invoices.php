<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Modified function to use invoice number and process all invoices
function add_payments_to_unpaid_invoices() {

    // Query all invoices
    $args = array(
        'post_type'      => 'sliced_invoice',
        'post_status'    => 'publish',
        'posts_per_page' => -1 // Get all invoices
    );

    $invoices = get_posts( $args );

    foreach ( $invoices as $invoice ) {
        $invoice_id = $invoice->ID;
        $invoice_number = get_post_meta( $invoice_id, '_sliced_number', true );

        // Step 1: Remove all existing payments for the invoice
        delete_post_meta( $invoice_id, '_sliced_payment' );

        // Step 2: Get the current status taxonomy
        $current_terms = wp_get_object_terms( $invoice_id, 'invoice_status', array( 'fields' => 'names' ) );
        $current_status = ! is_wp_error( $current_terms ) && ! empty( $current_terms ) ? implode( ', ', $current_terms ) : 'None';

        // Step 3: Get the accurate Total Due
        $totals = Sliced_Shared::get_totals( $invoice_id );
        $total_due = isset( $totals['total_due'] ) ? $totals['total_due'] : 0;

        // Step 4: Add a payment for the accurate Total Due amount
        if ( $total_due > 0 ) {
            $payment = array(
                'gateway'    => 'manual',
                'date'       => current_time( 'Y-m-d' ),
                'amount'     => $total_due,
                'currency'   => get_option( 'sliced_currency', 'USD' ),
                'payment_id' => uniqid( 'payment_' ),
                'status'     => 'success',
                'extra_data' => json_encode( array( 'memo' => 'Auto payment added for total due.' ) )
            );

            update_post_meta( $invoice_id, '_sliced_payment', array( $payment ) );
        }

        // Step 5: Trigger a save action to update the status
        wp_update_post( array( 'ID' => $invoice_id ) );

        // Step 6: Get the latest status taxonomy
        $updated_terms = wp_get_object_terms( $invoice_id, 'invoice_status', array( 'fields' => 'names' ) );
        $updated_status = ! is_wp_error( $updated_terms ) && ! empty( $updated_terms ) ? implode( ', ', $updated_terms ) : 'None';
    }
}

// Add a manual execution button in the WordPress admin area
add_action( 'admin_menu', function() {
    add_management_page(
        'Add Payments to Invoices',
        'Add Payments to Invoices',
        'manage_options',
        'add-payments-to-invoices',
        function() {
            if ( isset( $_POST['run_add_payments'] ) ) {
                // Combine functionality: Add payments and enable Bank
                add_payments_to_unpaid_invoices();

                // Query all invoices
                $args = array(
                    'post_type'      => 'sliced_invoice',
                    'posts_per_page' => -1, // Get all invoices
                );
                $invoices = get_posts( $args );

                foreach ( $invoices as $invoice ) {
                    $invoice_id = $invoice->ID;

                    // Retrieve the payment methods for the invoice
                    $payment_methods = get_post_meta( $invoice_id, '_sliced_payment_methods', true );

                    // Check if 'Bank' is already enabled
                    if ( ! is_array( $payment_methods ) || ! in_array( 'bank', $payment_methods ) ) {
                        // Add 'Bank' to the payment methods
                        if ( ! is_array( $payment_methods ) ) {
                            $payment_methods = array();
                        }
                        $payment_methods[] = 'bank';
                        update_post_meta( $invoice_id, '_sliced_payment_methods', $payment_methods );
                    }
                }

                echo '<div class="updated"><p>Payments have been processed and Bank payment method enabled for all invoices.</p></div>';
            }

            echo '<div class="wrap">';
            echo '<h1>Add Payments to Invoices</h1>';
            echo '<form method="post">';
            echo '<input type="hidden" name="run_add_payments" value="1">';
            echo '<p><input type="submit" class="button-primary" value="Run Add Payments"></p>';
            echo '</form>';
            echo '</div>';
        }
    );
});