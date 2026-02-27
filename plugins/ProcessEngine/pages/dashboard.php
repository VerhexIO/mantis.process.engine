<?php
/**
 * ProcessEngine - Dashboard Page
 *
 * Shows summary cards, filterable request table, and process overview.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'view_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/process_api.php' );

layout_page_header( plugin_lang_get( 'dashboard_title' ) );
layout_page_begin();

$t_stats = process_get_dashboard_stats();
$t_filter = gpc_get_string( 'filter', 'all' );
$t_bugs = process_get_dashboard_bugs( $t_filter );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card">
                        <div class="pe-card-value"><?php echo $t_stats['total']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'total_requests' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-blue">
                        <div class="pe-card-value"><?php echo $t_stats['active']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'active_processes' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-red">
                        <div class="pe-card-value"><?php echo $t_stats['sla_exceeded']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'sla_exceeded' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-purple">
                        <div class="pe-card-value"><?php echo $t_stats['avg_time']; ?>h</div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'avg_resolution_time' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-green">
                        <div class="pe-card-value"><?php echo $t_stats['today']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'updated_today' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-orange">
                        <div class="pe-card-value"><?php echo $t_stats['pending']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'pending_approvals' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-10"></div>

    <!-- Filter Buttons + Request Table -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-list"></i>
                <?php echo plugin_lang_get( 'dashboard_title' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-toolbox padding-8">
                <div class="btn-group">
                    <?php
                    $t_filters = array( 'all', 'active', 'sla_exceeded', 'completed' );
                    foreach( $t_filters as $t_f ) {
                        $t_active_class = ( $t_filter === $t_f ) ? 'btn-primary' : 'btn-white';
                        $t_label = plugin_lang_get( 'filter_' . $t_f );
                        $t_url = plugin_page( 'dashboard' ) . '&filter=' . $t_f;
                        echo '<a href="' . $t_url . '" class="btn btn-sm ' . $t_active_class . '">' . $t_label . '</a> ';
                    }
                    ?>
                </div>
            </div>
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'col_bug_id' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_summary' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_current_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_department' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_sla_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_updated' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_bugs ) ) { ?>
                            <tr>
                                <td colspan="6" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td>
                            </tr>
                            <?php } else {
                                foreach( $t_bugs as $t_bug_row ) {
                                    $t_sla_class = 'pe-sla-normal';
                                    if( $t_bug_row['sla_status'] === 'WARNING' ) {
                                        $t_sla_class = 'pe-sla-warning';
                                    } else if( $t_bug_row['sla_status'] === 'EXCEEDED' ) {
                                        $t_sla_class = 'pe-sla-exceeded';
                                    }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo string_get_bug_view_url( $t_bug_row['bug_id'] ); ?>">
                                        <?php echo bug_format_id( $t_bug_row['bug_id'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo string_display_line( $t_bug_row['summary'] ); ?></td>
                                <td><?php echo string_display_line( $t_bug_row['step_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_bug_row['department'] ); ?></td>
                                <td>
                                    <span class="pe-sla-badge <?php echo $t_sla_class; ?>">
                                        <?php echo string_display_line( $t_bug_row['sla_status'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo date( 'Y-m-d H:i', $t_bug_row['updated_at'] ); ?></td>
                            </tr>
                            <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo plugin_file( 'process_panel.js' ); ?>"></script>

<?php
layout_page_end();
