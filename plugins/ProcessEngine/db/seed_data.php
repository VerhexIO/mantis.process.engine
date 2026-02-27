<?php
/**
 * ProcessEngine - Seed Data
 *
 * Inserts 2 default flows:
 * 1. Fiyat Talebi (Price Request) - 5 steps
 * 2. Urun Gelistirme (Product Development) - 4 steps
 *
 * Called from seed_data page endpoint.
 */

/**
 * Load seed data into the database.
 * Safe to call multiple times - checks for existing data.
 */
function process_seed_load() {
    $t_flow_table = plugin_table( 'flow_definition' );
    $t_step_table = plugin_table( 'step' );
    $t_transition_table = plugin_table( 'transition' );

    // Check if data already exists
    $t_result = db_query( "SELECT COUNT(*) AS cnt FROM $t_flow_table" );
    $t_row = db_fetch_array( $t_result );
    if( (int) $t_row['cnt'] > 0 ) {
        return false; // Data already seeded
    }

    $t_now = time();
    $t_user_id = auth_get_current_user_id();

    // ---- Flow 1: Fiyat Talebi (Price Request) ----
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Fiyat Talebi',
            'Satış departmanından gelen fiyat taleplerinin fiyatlandırma, satınalma ve onay sürecinden geçirilmesi.',
            2, // AKTIF
            0, // Global
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow1_id = db_insert_id( $t_flow_table );

    // Flow 1 Steps
    $t_flow1_steps = array(
        array( 'Talep Oluşturma',    'Satış',            10, 8,  1, 'reporter',  100, 100 ),
        array( 'Fiyat Analizi',      'Fiyatlandırma',    20, 16, 2, 'updater',   300, 100 ),
        array( 'Tedarikçi Kontrolü', 'Satınalma',        30, 24, 3, 'updater',   500, 100 ),
        array( 'Yönetim Onayı',     'Yönetim',          50, 8,  4, 'manager',   700, 100 ),
        array( 'Teklif Hazırlama',   'Satış Operasyon',  80, 16, 5, 'updater',   900, 100 ),
    );

    $t_flow1_step_ids = array();
    foreach( $t_flow1_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow1_id,
                $t_step[0], // name
                $t_step[1], // department
                $t_step[2], // mantis_status
                $t_step[3], // sla_hours
                $t_step[4], // step_order
                $t_step[5], // role
                $t_step[6], // position_x
                $t_step[7], // position_y
            )
        );
        $t_flow1_step_ids[] = db_insert_id( $t_step_table );
    }

    // Flow 1 Transitions (linear: 1→2→3→4→5)
    for( $i = 0; $i < count( $t_flow1_step_ids ) - 1; $i++ ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow1_id,
                $t_flow1_step_ids[$i],
                $t_flow1_step_ids[$i + 1],
                '',
                '',
            )
        );
    }

    // ---- Flow 2: Ürün Geliştirme (Product Development) ----
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Ürün Geliştirme',
            'Yeni ürün geliştirme sürecinin ArGe, kalite ve üretim aşamalarından geçirilmesi.',
            0, // TASLAK
            0,
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow2_id = db_insert_id( $t_flow_table );

    // Flow 2 Steps
    $t_flow2_steps = array(
        array( 'Talep ve Analiz',   'Satış',   10, 24, 1, 'reporter',  100, 100 ),
        array( 'ArGe Tasarım',      'ArGe',    30, 40, 2, 'developer', 300, 100 ),
        array( 'Kalite Kontrolü',   'Kalite',  50, 16, 3, 'updater',   500, 100 ),
        array( 'Üretim Onayı',      'Yönetim', 80, 8,  4, 'manager',   700, 100 ),
    );

    $t_flow2_step_ids = array();
    foreach( $t_flow2_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow2_id,
                $t_step[0],
                $t_step[1],
                $t_step[2],
                $t_step[3],
                $t_step[4],
                $t_step[5],
                $t_step[6],
                $t_step[7],
            )
        );
        $t_flow2_step_ids[] = db_insert_id( $t_step_table );
    }

    // Flow 2 Transitions (linear: 1→2→3→4)
    for( $i = 0; $i < count( $t_flow2_step_ids ) - 1; $i++ ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow2_id,
                $t_flow2_step_ids[$i],
                $t_flow2_step_ids[$i + 1],
                '',
                '',
            )
        );
    }

    return true;
}
