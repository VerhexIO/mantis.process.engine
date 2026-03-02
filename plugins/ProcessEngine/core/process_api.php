<?php
/**
 * ProcessEngine - Core Process API
 *
 * Provides functions for status change logging, process queries,
 * and flow-to-bug matching.
 */

/**
 * Log a status change for a bug in the process log.
 * Finds the active flow and matching step for the new status.
 *
 * @param int $p_bug_id   Bug ID
 * @param int $p_old_status Previous status
 * @param int $p_new_status New status
 * @param string $p_note  Optional note
 */
function process_log_status_change( $p_bug_id, $p_old_status, $p_new_status, $p_note = '' ) {
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_flow = process_get_active_flow_for_project( $t_project_id );

    if( $t_flow === null ) {
        return;
    }

    $t_step = process_find_step_by_status( $t_flow['id'], $p_new_status );
    $t_step_id = ( $t_step !== null ) ? $t_step['id'] : 0;

    $t_log_table = plugin_table( 'log' );
    db_param_push();
    $t_query = "INSERT INTO $t_log_table
        ( bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at )
        VALUES
        ( " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . " )";

    db_query( $t_query, array(
        (int) $p_bug_id,
        (int) $t_flow['id'],
        (int) $t_step_id,
        (int) $p_old_status,
        (int) $p_new_status,
        (int) auth_get_current_user_id(),
        $p_note,
        time(),
    ) );

    // Trigger custom event
    event_signal( 'EVENT_PROCESSENGINE_STATUS_CHANGED', array(
        'bug_id'      => $p_bug_id,
        'flow_id'     => $t_flow['id'],
        'step_id'     => $t_step_id,
        'from_status' => $p_old_status,
        'to_status'   => $p_new_status,
    ) );
}

/**
 * Get the active flow for a project.
 * Falls back to flows with project_id = 0 (global).
 *
 * @param int $p_project_id Project ID
 * @return array|null Flow row or null
 */
function process_get_active_flow_for_project( $p_project_id ) {
    $t_table = plugin_table( 'flow_definition' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE status = 2
        AND ( project_id = " . db_param() . " OR project_id = 0 )
        ORDER BY project_id DESC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_project_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Find the step in a flow that matches a given MantisBT status.
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_mantis_status MantisBT status value
 * @return array|null Step row or null
 */
function process_find_step_by_status( $p_flow_id, $p_mantis_status ) {
    $t_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE flow_id = " . db_param() . "
        AND mantis_status = " . db_param() . "
        ORDER BY step_order ASC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_mantis_status ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Get all process logs for a bug, with step name joined.
 *
 * @param int $p_bug_id Bug ID
 * @return array Array of log rows
 */
function process_get_logs_for_bug( $p_bug_id ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT l.*, COALESCE(s.name, '') AS step_name
        FROM $t_log_table l
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE l.bug_id = " . db_param() . "
        ORDER BY l.created_at ASC";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );

    $t_logs = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_logs[] = $t_row;
    }
    return $t_logs;
}

/**
 * Get the latest log entry for a bug (current step info).
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Latest log row or null
 */
function process_get_current_step_for_bug( $p_bug_id ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT l.*, COALESCE(s.name, '') AS step_name, COALESCE(s.department, '') AS department
        FROM $t_log_table l
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE l.bug_id = " . db_param() . "
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Find the start step of a flow (step with no incoming transitions).
 *
 * @param int $p_flow_id Flow ID
 * @return array|null Step row or null
 */
function process_find_start_step( $p_flow_id ) {
    $t_step_table = plugin_table( 'step' );
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT s.* FROM $t_step_table s
        WHERE s.flow_id = " . db_param() . "
        AND s.id NOT IN (SELECT to_step_id FROM $t_trans_table WHERE flow_id = " . db_param() . ")
        ORDER BY s.step_order ASC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_flow_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Log initial process entry when a bug is created.
 *
 * @param int $p_bug_id Bug ID
 * @param int $p_flow_id Flow ID
 * @param array $p_step Start step data
 */
function process_log_initial( $p_bug_id, $p_flow_id, $p_step ) {
    $t_log_table = plugin_table( 'log' );
    db_param_push();
    $t_query = "INSERT INTO $t_log_table
        ( bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at )
        VALUES ( " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . " )";
    db_query( $t_query, array(
        (int) $p_bug_id,
        (int) $p_flow_id,
        (int) $p_step['id'],
        0,
        (int) $p_step['mantis_status'],
        (int) auth_get_current_user_id(),
        plugin_lang_get( 'process_started' ),
        time(),
    ) );
}

/**
 * Check if a transition exists between two steps (by MantisBT status values).
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_from_status MantisBT from status
 * @param int $p_to_status MantisBT to status
 * @return bool True if transition exists
 */
function process_transition_exists( $p_flow_id, $p_from_status, $p_to_status ) {
    $t_step_table = plugin_table( 'step' );
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT t.id FROM $t_trans_table t
        INNER JOIN $t_step_table sf ON t.from_step_id = sf.id AND sf.flow_id = " . db_param() . "
        INNER JOIN $t_step_table st ON t.to_step_id = st.id AND st.flow_id = " . db_param() . "
        WHERE t.flow_id = " . db_param() . "
        AND sf.mantis_status = " . db_param() . "
        AND st.mantis_status = " . db_param() . "
        LIMIT 1";
    $t_result = db_query( $t_query, array(
        (int) $p_flow_id, (int) $p_flow_id, (int) $p_flow_id,
        (int) $p_from_status, (int) $p_to_status
    ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false );
}

/**
 * Get flow progress information for a bug.
 * Returns all steps with their completion status.
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Progress data or null if no process
 */
function process_get_flow_progress( $p_bug_id ) {
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_flow = process_get_active_flow_for_project( $t_project_id );
    if( $t_flow === null ) {
        return null;
    }

    require_once( dirname( __FILE__ ) . '/flow_api.php' );
    $t_steps = flow_get_steps( (int) $t_flow['id'] );
    if( empty( $t_steps ) ) {
        return null;
    }

    // Sürecin geçtiği tüm durum loglarını al
    $t_logs = process_get_logs_for_bug( $p_bug_id );
    $t_visited_step_ids = array();
    foreach( $t_logs as $t_log ) {
        if( (int) $t_log['step_id'] > 0 ) {
            $t_visited_step_ids[(int) $t_log['step_id']] = true;
        }
    }

    // Mevcut durumu al
    $t_current = process_get_current_step_for_bug( $p_bug_id );
    $t_current_step_id = $t_current !== null ? (int) $t_current['step_id'] : 0;

    // SLA bilgisi al
    $t_sla_table = plugin_table( 'sla_tracking' );
    db_param_push();
    $t_sla_query = "SELECT * FROM $t_sla_table
        WHERE bug_id = " . db_param() . "
        AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1";
    $t_sla_result = db_query( $t_sla_query, array( (int) $p_bug_id ) );
    $t_sla_row = db_fetch_array( $t_sla_result );

    // Her adımın durumunu belirle
    $t_step_list = array();
    $t_current_index = -1;
    foreach( $t_steps as $i => $t_step ) {
        $t_step_id = (int) $t_step['id'];
        if( $t_step_id === $t_current_step_id ) {
            $t_status = 'current';
            $t_current_index = $i;
        } else if( isset( $t_visited_step_ids[$t_step_id] ) && $t_step_id !== $t_current_step_id ) {
            $t_status = 'completed';
        } else {
            $t_status = 'pending';
        }
        $t_step_list[] = array(
            'id'            => $t_step_id,
            'name'          => $t_step['name'],
            'department'    => $t_step['department'],
            'handler_id'    => isset( $t_step['handler_id'] ) ? (int) $t_step['handler_id'] : 0,
            'sla_hours'     => (int) $t_step['sla_hours'],
            'status'        => $t_status,
        );
    }

    $t_current_sla = null;
    if( $t_sla_row !== false ) {
        $t_now = time();
        $t_deadline = (int) $t_sla_row['deadline_at'];
        $t_remaining_sec = $t_deadline - $t_now;
        $t_current_sla = array(
            'sla_status'    => $t_sla_row['sla_status'],
            'deadline_at'   => $t_deadline,
            'remaining_sec' => $t_remaining_sec,
            'remaining_hrs' => round( $t_remaining_sec / 3600, 1 ),
        );
    }

    return array(
        'flow'               => $t_flow,
        'steps'              => $t_step_list,
        'current_step_index' => $t_current_index,
        'total_steps'        => count( $t_step_list ),
        'current_sla'        => $t_current_sla,
    );
}

/**
 * Get all unique bug IDs that have process log entries.
 *
 * @return array Array of bug IDs
 */
function process_get_tracked_bug_ids() {
    $t_log_table = plugin_table( 'log' );
    $t_query = "SELECT DISTINCT bug_id FROM $t_log_table ORDER BY bug_id DESC";
    $t_result = db_query( $t_query );

    $t_ids = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_ids[] = (int) $t_row['bug_id'];
    }
    return $t_ids;
}

/**
 * Get dashboard summary statistics.
 *
 * @return array Associative array with dashboard counts
 */
function process_get_dashboard_stats() {
    $t_log_table = plugin_table( 'log' );
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_today_start = mktime( 0, 0, 0 );

    // Total unique bugs with process logs
    $t_result = db_query( "SELECT COUNT(DISTINCT bug_id) AS cnt FROM $t_log_table" );
    $t_row = db_fetch_array( $t_result );
    $t_total = (int) $t_row['cnt'];

    // Active SLA trackings (not completed)
    db_param_push();
    $t_result = db_query( "SELECT COUNT(DISTINCT bug_id) AS cnt FROM $t_sla_table WHERE completed_at IS NULL" );
    $t_row = db_fetch_array( $t_result );
    $t_active = (int) $t_row['cnt'];

    // SLA exceeded
    db_param_push();
    $t_result = db_query( "SELECT COUNT(*) AS cnt FROM $t_sla_table WHERE sla_status = 'EXCEEDED' AND completed_at IS NULL" );
    $t_row = db_fetch_array( $t_result );
    $t_sla_exceeded = (int) $t_row['cnt'];

    // Average resolution time (completed SLA entries)
    db_param_push();
    $t_result = db_query( "SELECT AVG(completed_at - started_at) AS avg_time FROM $t_sla_table WHERE completed_at IS NOT NULL AND completed_at > 0" );
    $t_row = db_fetch_array( $t_result );
    $t_avg_time = $t_row['avg_time'] ? round( (float) $t_row['avg_time'] / 3600, 1 ) : 0;

    // Updated today
    db_param_push();
    $t_result = db_query( "SELECT COUNT(DISTINCT bug_id) AS cnt FROM $t_log_table WHERE created_at >= " . db_param(), array( $t_today_start ) );
    $t_row = db_fetch_array( $t_result );
    $t_today = (int) $t_row['cnt'];

    // Pending (bugs at a step that has transitions but hasn't moved forward yet)
    // Simple approach: bugs with latest log where status is not resolved/closed
    $t_pending = 0;
    $t_bug_ids = process_get_tracked_bug_ids();
    foreach( $t_bug_ids as $t_bug_id ) {
        if( bug_exists( $t_bug_id ) ) {
            $t_status = bug_get_field( $t_bug_id, 'status' );
            // Status < 80 (resolved) means still pending
            if( $t_status < 80 ) {
                $t_pending++;
            }
        }
    }

    return array(
        'total'        => $t_total,
        'active'       => $t_active,
        'sla_exceeded' => $t_sla_exceeded,
        'avg_time'     => $t_avg_time,
        'today'        => $t_today,
        'pending'      => $t_pending,
    );
}

/**
 * Get the list of departments from config + existing step_table entries.
 *
 * @return array Sorted array of department names
 */
function process_get_departments() {
    // 1. Yapılandırmadan tanımlı departmanları al
    $t_config = plugin_config_get( 'departments', '' );
    $t_depts = array();
    if( $t_config !== '' ) {
        $t_depts = array_map( 'trim', explode( ',', $t_config ) );
        $t_depts = array_filter( $t_depts, function( $v ) { return $v !== ''; } );
    }
    // 2. step_table'daki mevcut departmanları da ekle
    $t_step_table = plugin_table( 'step' );
    $t_result = db_query( "SELECT DISTINCT department FROM $t_step_table WHERE department != '' ORDER BY department" );
    while( $t_row = db_fetch_array( $t_result ) ) {
        if( !in_array( $t_row['department'], $t_depts ) ) {
            $t_depts[] = $t_row['department'];
        }
    }
    sort( $t_depts );
    return $t_depts;
}

/**
 * Get all tracked bugs with their current step info for the dashboard table.
 *
 * @param string $p_filter Filter type: 'all', 'active', 'sla_exceeded', 'completed'
 * @return array Array of bug process data
 */
function process_get_dashboard_bugs( $p_filter = 'all', $p_department = '' ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    $t_sla_table = plugin_table( 'sla_tracking' );

    // Get latest log entry per bug
    $t_query = "SELECT l.bug_id, l.flow_id, l.step_id, l.to_status, l.created_at,
            COALESCE(s.name, '') AS step_name,
            COALESCE(s.department, '') AS department
        FROM $t_log_table l
        INNER JOIN (
            SELECT bug_id, MAX(id) AS max_id FROM $t_log_table GROUP BY bug_id
        ) latest ON l.id = latest.max_id
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        ORDER BY l.created_at DESC";

    $t_result = db_query( $t_query );
    $t_bugs = array();

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_bug_id = (int) $t_row['bug_id'];
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }

        $t_bug = bug_get( $t_bug_id );
        $t_status = $t_bug->status;

        // Get SLA status for this bug
        db_param_push();
        $t_sla_query = "SELECT sla_status FROM $t_sla_table
            WHERE bug_id = " . db_param() . "
            AND completed_at IS NULL
            ORDER BY id DESC LIMIT 1";
        $t_sla_result = db_query( $t_sla_query, array( $t_bug_id ) );
        $t_sla_row = db_fetch_array( $t_sla_result );
        $t_sla_status = $t_sla_row ? $t_sla_row['sla_status'] : 'NORMAL';

        $t_is_active = ( $t_status < 80 );
        $t_is_completed = ( $t_status >= 80 );
        $t_is_sla_exceeded = ( $t_sla_status === 'EXCEEDED' );

        // Apply filter
        if( $p_filter === 'active' && !$t_is_active ) continue;
        if( $p_filter === 'completed' && !$t_is_completed ) continue;
        if( $p_filter === 'sla_exceeded' && !$t_is_sla_exceeded ) continue;

        // Apply department filter
        if( $p_department !== '' && $t_row['department'] !== $p_department ) continue;

        // İlerleme bilgisi
        $t_progress_data = process_get_flow_progress( $t_bug_id );
        $t_progress_pct = 0;
        if( $t_progress_data !== null && $t_progress_data['total_steps'] > 0 ) {
            $t_completed_count = 0;
            foreach( $t_progress_data['steps'] as $t_ps ) {
                if( $t_ps['status'] === 'completed' ) $t_completed_count++;
            }
            if( $t_progress_data['current_step_index'] >= 0 ) {
                $t_progress_pct = round( ( $t_progress_data['current_step_index'] + 1 ) / $t_progress_data['total_steps'] * 100 );
            } else {
                $t_progress_pct = round( $t_completed_count / $t_progress_data['total_steps'] * 100 );
            }
        }

        // Sorumlu kişi
        $t_handler = (int) $t_bug->handler_id;
        $t_handler_name = ( $t_handler > 0 ) ? user_get_name( $t_handler ) : '-';

        $t_bugs[] = array(
            'bug_id'       => $t_bug_id,
            'summary'      => $t_bug->summary,
            'step_name'    => $t_row['step_name'],
            'department'   => $t_row['department'],
            'sla_status'   => $t_sla_status,
            'updated_at'   => $t_row['created_at'],
            'bug_status'   => $t_status,
            'progress_pct' => $t_progress_pct,
            'handler_name' => $t_handler_name,
        );
    }

    return $t_bugs;
}
