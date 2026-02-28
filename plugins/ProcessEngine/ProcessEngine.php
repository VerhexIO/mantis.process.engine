<?php
/**
 * ProcessEngine Plugin for MantisBT
 *
 * Automates workflow management for inter-departmental processes
 * (price requests, product development, etc.)
 *
 * Compatible with MantisBT 2.24.2 (Schema 210)
 */

class ProcessEnginePlugin extends MantisPlugin {

    /**
     * Plugin registration
     */
    public function register() {
        $this->name        = plugin_lang_get( 'plugin_title' );
        $this->description = plugin_lang_get( 'plugin_description' );
        $this->page        = 'config_page';

        $this->version     = '1.0.0';
        $this->requires    = array(
            'MantisCore' => '2.24.0',
        );

        $this->author  = 'VerhexIO';
        $this->contact = 'info@verhex.io';
        $this->url     = 'https://github.com/VerhexIO/mantis.process.engine';
    }

    /**
     * Default configuration
     */
    public function config() {
        return array(
            'manage_threshold'     => MANAGER,
            'view_threshold'       => REPORTER,
            'sla_warning_percent'  => 80,
            'business_hours_start' => 9,
            'business_hours_end'   => 18,
            'working_days'         => '1,2,3,4,5',
        );
    }

    /**
     * Register custom events
     */
    public function events() {
        return array(
            'EVENT_PROCESSENGINE_STATUS_CHANGED' => EVENT_TYPE_EXECUTE,
            'EVENT_PROCESSENGINE_ESCALATION'     => EVENT_TYPE_EXECUTE,
        );
    }

    /**
     * Register event hooks
     */
    public function hooks() {
        return array(
            'EVENT_REPORT_BUG'        => 'on_bug_report',
            'EVENT_UPDATE_BUG'        => 'on_bug_update',
            'EVENT_MENU_MAIN'         => 'on_menu_main',
            'EVENT_LAYOUT_RESOURCES'  => 'on_layout_resources',
            'EVENT_VIEW_BUG_EXTRA'    => 'on_view_bug_extra',
            'EVENT_MENU_MANAGE'       => 'on_menu_manage',
        );
    }

    /**
     * Database schema - creates 5 tables
     */
    public function schema() {
        $t_schema = array();

        // 0: flow_definition table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'flow_definition' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                name            C(128)  NOTNULL DEFAULT '',
                description     XL,
                status          I2      NOTNULL DEFAULT '0',
                project_id      I       NOTNULL UNSIGNED DEFAULT '0',
                created_by      I       NOTNULL UNSIGNED DEFAULT '0',
                created_at      I       NOTNULL UNSIGNED DEFAULT '0',
                updated_at      I       NOTNULL UNSIGNED DEFAULT '0'
            " )
        );

        // 1: step table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'step' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                name            C(128)  NOTNULL DEFAULT '',
                department      C(64)   DEFAULT '',
                mantis_status   I2      NOTNULL DEFAULT '10',
                sla_hours       I       NOTNULL UNSIGNED DEFAULT '0',
                step_order      I       NOTNULL UNSIGNED DEFAULT '0',
                role            C(64)   DEFAULT '',
                position_x      I       NOTNULL DEFAULT '0',
                position_y      I       NOTNULL DEFAULT '0'
            " )
        );

        // 2: transition table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'transition' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                from_step_id    I       NOTNULL UNSIGNED DEFAULT '0',
                to_step_id      I       NOTNULL UNSIGNED DEFAULT '0',
                condition_field C(128)  DEFAULT '',
                condition_value C(255)  DEFAULT ''
            " )
        );

        // 3: log table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'log' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                bug_id          I       NOTNULL UNSIGNED DEFAULT '0',
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                step_id         I       NOTNULL UNSIGNED DEFAULT '0',
                from_status     I2      NOTNULL DEFAULT '0',
                to_status       I2      NOTNULL DEFAULT '0',
                user_id         I       NOTNULL UNSIGNED DEFAULT '0',
                note            XL,
                created_at      I       NOTNULL UNSIGNED DEFAULT '0'
            " )
        );

        // 4: sla_tracking table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'sla_tracking' ), "
                id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                bug_id              I       NOTNULL UNSIGNED DEFAULT '0',
                step_id             I       NOTNULL UNSIGNED DEFAULT '0',
                flow_id             I       NOTNULL UNSIGNED DEFAULT '0',
                sla_hours           I       NOTNULL UNSIGNED DEFAULT '0',
                started_at          I       NOTNULL UNSIGNED DEFAULT '0',
                deadline_at         I       NOTNULL UNSIGNED DEFAULT '0',
                completed_at        I       UNSIGNED DEFAULT NULL,
                sla_status          C(16)   NOTNULL DEFAULT 'NORMAL',
                notified_warning    I2      NOTNULL DEFAULT '0',
                notified_exceeded   I2      NOTNULL DEFAULT '0',
                escalation_level    I2      NOTNULL DEFAULT '0'
            " )
        );

        // 5: Index on log.bug_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_log_bug', plugin_table( 'log' ), 'bug_id' )
        );

        // 6: Index on sla_tracking.bug_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_sla_bug', plugin_table( 'sla_tracking' ), 'bug_id' )
        );

        // 7: Index on step.flow_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_step_flow', plugin_table( 'step' ), 'flow_id' )
        );

        // 8: Index on transition.flow_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_trans_flow', plugin_table( 'transition' ), 'flow_id' )
        );

        return $t_schema;
    }

    /**
     * Plugin install - run seed data
     */
    public function install() {
        $t_seed_file = __DIR__ . '/db/seed_data.php';
        // Seed data will be loaded separately via the seed page
        return true;
    }

    /**
     * Hook: EVENT_REPORT_BUG - start process tracking when a bug is created
     */
    public function on_bug_report( $p_event, $p_bug_data, $p_bug_id ) {
        require_once( __DIR__ . '/core/process_api.php' );
        require_once( __DIR__ . '/core/sla_api.php' );

        $t_project_id = $p_bug_data->project_id;
        $t_flow = process_get_active_flow_for_project( $t_project_id );
        if( $t_flow === null ) {
            return $p_bug_data;
        }

        // İlk adımı bul (gelen geçişi olmayan adım)
        $t_step = process_find_start_step( $t_flow['id'] );
        if( $t_step === null ) {
            return $p_bug_data;
        }

        // Süreç loguna başlangıç kaydı yaz
        process_log_initial( $p_bug_id, $t_flow['id'], $t_step );

        // SLA takibini başlat
        if( (int) $t_step['sla_hours'] > 0 ) {
            sla_start_tracking( $p_bug_id, (int) $t_step['id'], (int) $t_flow['id'], (int) $t_step['sla_hours'] );
        }

        return $p_bug_data;
    }

    /**
     * Hook: EVENT_UPDATE_BUG - log status changes and trigger SLA tracking
     */
    public function on_bug_update( $p_event, $p_bug_data, $p_bug_id ) {
        require_once( __DIR__ . '/core/process_api.php' );

        $t_old_bug = bug_get( $p_bug_id );
        $t_new_status = $p_bug_data->status;
        $t_old_status = $t_old_bug->status;

        if( $t_old_status != $t_new_status ) {
            // Akış dışı geçiş kontrolü
            $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
            $t_flow = process_get_active_flow_for_project( $t_project_id );
            $t_note = '';
            if( $t_flow !== null && !process_transition_exists( $t_flow['id'], $t_old_status, $t_new_status ) ) {
                $t_note = plugin_lang_get( 'out_of_flow_transition' );
            }

            process_log_status_change( $p_bug_id, $t_old_status, $t_new_status, $t_note );

            // SLA tracking: complete old step, start new step
            require_once( __DIR__ . '/core/sla_api.php' );
            if( $t_flow !== null ) {
                sla_complete_tracking( $p_bug_id );
                $t_step = process_find_step_by_status( $t_flow['id'], $t_new_status );
                if( $t_step !== null && (int) $t_step['sla_hours'] > 0 ) {
                    sla_start_tracking( $p_bug_id, (int) $t_step['id'], (int) $t_flow['id'], (int) $t_step['sla_hours'] );
                }
            }
        }

        return $p_bug_data;
    }

    /**
     * Hook: EVENT_MENU_MAIN - add "Process Panel" to main menu
     */
    public function on_menu_main( $p_event ) {
        if( access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
            return array(
                '<a href="' . plugin_page( 'dashboard' ) . '">'
                . plugin_lang_get( 'menu_dashboard' )
                . '</a>'
            );
        }
        return array();
    }

    /**
     * Hook: EVENT_MENU_MANAGE - add config link to admin menu
     */
    public function on_menu_manage( $p_event ) {
        if( access_has_global_level( plugin_config_get( 'manage_threshold' ) ) ) {
            return array(
                '<a href="' . plugin_page( 'config_page' ) . '">'
                . plugin_lang_get( 'menu_config' )
                . '</a>'
            );
        }
        return array();
    }

    /**
     * Hook: EVENT_LAYOUT_RESOURCES - load CSS and JS assets
     */
    public function on_layout_resources( $p_event ) {
        $t_css = '<link rel="stylesheet" href="' . plugin_file( 'process_panel.css' ) . '" />' . "\n";
        return $t_css;
    }

    /**
     * Hook: EVENT_VIEW_BUG_EXTRA - show process timeline on issue detail
     */
    public function on_view_bug_extra( $p_event, $p_bug_id ) {
        if( !access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
            return;
        }

        require_once( __DIR__ . '/core/process_api.php' );

        $t_logs = process_get_logs_for_bug( $p_bug_id );
        if( empty( $t_logs ) ) {
            return;
        }

        $t_collapse_block = is_page_name( 'bug_view_page' );
?>
<tr class="spacer"><td colspan="6"></td></tr>
<tr>
    <td class="category" colspan="6">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title">
                <i class="fa fa-cogs"></i>
                <?php echo plugin_lang_get( 'process_timeline' ); ?>
            </h4>
        </div>
    </td>
</tr>
<tr>
    <td colspan="6">
        <div class="process-timeline">
            <table class="table table-bordered table-condensed table-striped">
                <thead>
                    <tr>
                        <th><?php echo plugin_lang_get( 'col_date' ); ?></th>
                        <th><?php echo plugin_lang_get( 'col_from_status' ); ?></th>
                        <th><?php echo plugin_lang_get( 'col_to_status' ); ?></th>
                        <th><?php echo plugin_lang_get( 'col_user' ); ?></th>
                        <th><?php echo plugin_lang_get( 'col_step' ); ?></th>
                        <th><?php echo plugin_lang_get( 'col_note' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach( $t_logs as $t_log ) { ?>
                    <tr>
                        <td><?php echo date( 'Y-m-d H:i', $t_log['created_at'] ); ?></td>
                        <td><span class="process-status"><?php echo get_enum_element( 'status', $t_log['from_status'] ); ?></span></td>
                        <td><span class="process-status"><?php echo get_enum_element( 'status', $t_log['to_status'] ); ?></span></td>
                        <td><?php echo user_get_name( $t_log['user_id'] ); ?></td>
                        <td><?php echo string_display_line( $t_log['step_name'] ); ?></td>
                        <td><?php echo string_display_line( $t_log['note'] ); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<?php
    }
}
