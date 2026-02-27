<?php
/**
 * ProcessEngine - Flow Save (AJAX endpoint)
 *
 * Accepts JSON POST with flow data and saves to database.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

header( 'Content-Type: application/json; charset=utf-8' );

$t_input = json_decode( file_get_contents( 'php://input' ), true );

if( !$t_input || !isset( $t_input['flow_id'] ) ) {
    echo json_encode( array( 'success' => false, 'error' => 'Invalid input' ) );
    exit;
}

$t_flow_id = (int) $t_input['flow_id'];
$t_flow = flow_get( $t_flow_id );

if( $t_flow === null ) {
    echo json_encode( array( 'success' => false, 'error' => 'Flow not found' ) );
    exit;
}

// Update flow metadata
if( isset( $t_input['name'] ) ) {
    flow_update( $t_flow_id, $t_input['name'], isset( $t_input['description'] ) ? $t_input['description'] : '' );
}

// Save steps and transitions
$t_steps = isset( $t_input['steps'] ) ? $t_input['steps'] : array();
$t_transitions = isset( $t_input['transitions'] ) ? $t_input['transitions'] : array();

$t_id_map = flow_save_complete( $t_flow_id, $t_steps, $t_transitions );

// Return updated data
$t_new_steps = flow_get_steps( $t_flow_id );
$t_new_transitions = flow_get_transitions( $t_flow_id );

echo json_encode( array(
    'success'     => true,
    'id_map'      => $t_id_map,
    'steps'       => $t_new_steps,
    'transitions' => $t_new_transitions,
) );
