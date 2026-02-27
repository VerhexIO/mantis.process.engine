<?php
/**
 * ProcessEngine - Flow Publish (AJAX endpoint)
 *
 * Validates and publishes a flow, deactivating previous active flow.
 */

// AJAX endpoint: auth_ensure ile kontrol (auth_reauthenticate HTML form döndürür)
auth_ensure_user_authenticated();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

header( 'Content-Type: application/json; charset=utf-8' );

$t_input = json_decode( file_get_contents( 'php://input' ), true );
$t_flow_id = isset( $t_input['flow_id'] ) ? (int) $t_input['flow_id'] : 0;

if( $t_flow_id === 0 ) {
    echo json_encode( array( 'success' => false, 'error' => 'Invalid flow ID' ) );
    exit;
}

// Validate first
$t_validation = flow_validate( $t_flow_id );
if( !$t_validation['valid'] ) {
    echo json_encode( array(
        'success' => false,
        'error'   => 'Validation failed',
        'errors'  => $t_validation['errors'],
    ) );
    exit;
}

// Publish
$t_result = flow_publish( $t_flow_id );

if( $t_result ) {
    echo json_encode( array( 'success' => true ) );
} else {
    echo json_encode( array( 'success' => false, 'error' => 'Publish failed' ) );
}
