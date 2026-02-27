<?php
/**
 * ProcessEngine - Flow Validate (AJAX endpoint)
 *
 * Validates the flow graph and returns validation results.
 */

// AJAX endpoint: auth_ensure ile kontrol (auth_reauthenticate HTML form döndürür)
auth_ensure_user_authenticated();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

header( 'Content-Type: application/json; charset=utf-8' );

$t_input = json_decode( file_get_contents( 'php://input' ), true );
$t_flow_id = isset( $t_input['flow_id'] ) ? (int) $t_input['flow_id'] : 0;

if( $t_flow_id === 0 ) {
    echo json_encode( array( 'valid' => false, 'errors' => array( 'Invalid flow ID' ) ) );
    exit;
}

$t_result = flow_validate( $t_flow_id );

echo json_encode( $t_result );
