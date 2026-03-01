<?php
/**
 * ProcessEngine - Flow Designer Page
 *
 * No-code visual flow editor with SVG canvas, drag-drop nodes,
 * and transition drawing.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

$t_flow_id = gpc_get_int( 'flow_id', 0 );
$t_action = gpc_get_string( 'action', 'list' );

// Handle new flow creation
if( $t_action === 'new' ) {
    $t_flow_id = flow_create( 'Yeni Akış' );
    print_header_redirect( plugin_page( 'flow_designer', true ) . '&flow_id=' . $t_flow_id );
}

// Handle delete
if( $t_action === 'delete' && $t_flow_id > 0 ) {
    form_security_validate( 'ProcessEngine_flow_delete' );
    flow_delete( $t_flow_id );
    form_security_purge( 'ProcessEngine_flow_delete' );
    print_header_redirect( plugin_page( 'flow_designer', true ) );
}

layout_page_header( plugin_lang_get( 'flow_designer_title' ) );
layout_page_begin();

// If no flow selected, show flow list
if( $t_flow_id === 0 ) {
    $t_flows = flow_get_all();
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'flow_list_title' ); ?>
            </h4>
            <div class="widget-toolbar">
                <a href="<?php echo plugin_page( 'flow_designer' ) . '&action=new'; ?>" class="btn btn-primary btn-white btn-sm">
                    <i class="fa fa-plus"></i> <?php echo plugin_lang_get( 'new_flow' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo plugin_lang_get( 'col_flow_name' ); ?></th>
                                <th><?php echo plugin_lang_get( 'flow_project' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_updated' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_actions' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_flows ) ) { ?>
                            <tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td></tr>
                            <?php } else {
                                foreach( $t_flows as $t_flow ) {
                                    $t_status_labels = array(
                                        0 => plugin_lang_get( 'flow_status_draft' ),
                                        1 => plugin_lang_get( 'flow_status_pending' ),
                                        2 => plugin_lang_get( 'flow_status_active' ),
                                    );
                                    $t_status_label = isset( $t_status_labels[(int)$t_flow['status']] )
                                        ? $t_status_labels[(int)$t_flow['status']]
                                        : $t_flow['status'];
                                    $t_status_class = '';
                                    if( (int)$t_flow['status'] === 2 ) $t_status_class = 'label-success';
                                    elseif( (int)$t_flow['status'] === 1 ) $t_status_class = 'label-warning';
                                    else $t_status_class = 'label-default';
                            ?>
                            <tr>
                                <td><?php echo (int)$t_flow['id']; ?></td>
                                <td>
                                    <a href="<?php echo plugin_page( 'flow_designer' ) . '&flow_id=' . (int)$t_flow['id']; ?>">
                                        <?php echo string_display_line( $t_flow['name'] ); ?>
                                    </a>
                                </td>
                                <td><?php
                                    $t_proj_id = (int) $t_flow['project_id'];
                                    if( $t_proj_id > 0 && project_exists( $t_proj_id ) ) {
                                        echo string_display_line( project_get_name( $t_proj_id ) );
                                    } else {
                                        echo plugin_lang_get( 'all_projects' );
                                    }
                                ?></td>
                                <td><span class="label <?php echo $t_status_class; ?>"><?php echo $t_status_label; ?></span></td>
                                <td><?php echo $t_flow['updated_at'] ? date( 'Y-m-d H:i', $t_flow['updated_at'] ) : '-'; ?></td>
                                <td>
                                    <a href="<?php echo plugin_page( 'flow_designer' ) . '&flow_id=' . (int)$t_flow['id']; ?>" class="btn btn-xs btn-primary">
                                        <i class="fa fa-pencil"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
                                    </a>
                                    <form method="post" action="<?php echo plugin_page( 'flow_designer' ) . '&action=delete&flow_id=' . (int)$t_flow['id']; ?>" style="display:inline;">
                                        <?php echo form_security_field( 'ProcessEngine_flow_delete' ); ?>
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?php echo plugin_lang_get( 'confirm_delete' ); ?>');">
                                            <i class="fa fa-trash"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
                                        </button>
                                    </form>
                                </td>
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
<?php
} else {
    // Flow Designer Canvas
    $t_flow = flow_get( $t_flow_id );
    if( $t_flow === null ) {
        echo '<div class="alert alert-danger">Flow not found.</div>';
        layout_page_end();
        return;
    }
    $t_steps = flow_get_steps( $t_flow_id );
    $t_transitions = flow_get_transitions( $t_flow_id );

    // MantisBT status enum for dropdown
    $t_status_enum = MantisEnum::getAssocArrayIndexedByValues( config_get( 'status_enum_string' ) );
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Flow Header -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'flow_designer_title' ); ?>:
                <span id="pe-flow-name-display"><?php echo string_display_line( $t_flow['name'] ); ?></span>
            </h4>
            <div class="widget-toolbar">
                <a href="<?php echo plugin_page( 'flow_designer' ); ?>" class="btn btn-sm btn-white">
                    <i class="fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'btn_back' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <!-- Flow metadata -->
                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-4">
                        <label><?php echo plugin_lang_get( 'flow_name' ); ?></label>
                        <input type="text" id="pe-flow-name" class="form-control input-sm" value="<?php echo string_attribute( $t_flow['name'] ); ?>" />
                    </div>
                    <div class="col-md-4">
                        <label><?php echo plugin_lang_get( 'flow_description' ); ?></label>
                        <input type="text" id="pe-flow-desc" class="form-control input-sm" value="<?php echo string_attribute( $t_flow['description'] ); ?>" />
                    </div>
                    <div class="col-md-2">
                        <label><?php echo plugin_lang_get( 'flow_project' ); ?></label>
                        <select id="pe-flow-project" class="form-control input-sm">
                            <option value="0"><?php echo plugin_lang_get( 'all_projects' ); ?></option>
                            <?php
                            $t_projects = project_get_all_rows();
                            foreach( $t_projects as $t_proj ) { ?>
                            <option value="<?php echo (int) $t_proj['id']; ?>"
                                <?php echo ( (int) $t_flow['project_id'] === (int) $t_proj['id'] ) ? 'selected' : ''; ?>>
                                <?php echo string_display_line( $t_proj['name'] ); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2" style="padding-top: 22px;">
                        <span class="label <?php
                            if((int)$t_flow['status'] === 2) echo 'label-success';
                            elseif((int)$t_flow['status'] === 1) echo 'label-warning';
                            else echo 'label-default';
                        ?>"><?php
                            $t_sl = array(0=>'TASLAK',1=>'ONAY BEKLİYOR',2=>'AKTİF');
                            echo isset($t_sl[(int)$t_flow['status']]) ? $t_sl[(int)$t_flow['status']] : $t_flow['status'];
                        ?></span>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="btn-toolbar" style="margin-bottom: 10px;">
                    <button id="pe-btn-add-step" class="btn btn-sm btn-success">
                        <i class="fa fa-plus"></i> <?php echo plugin_lang_get( 'btn_add_step' ); ?>
                    </button>
                    <button id="pe-btn-save" class="btn btn-sm btn-primary">
                        <i class="fa fa-save"></i> <?php echo plugin_lang_get( 'btn_save' ); ?>
                    </button>
                    <button id="pe-btn-validate" class="btn btn-sm btn-warning">
                        <i class="fa fa-check-circle"></i> <?php echo plugin_lang_get( 'btn_validate' ); ?>
                    </button>
                    <button id="pe-btn-publish" class="btn btn-sm btn-danger">
                        <i class="fa fa-rocket"></i> <?php echo plugin_lang_get( 'btn_publish' ); ?>
                    </button>
                    <span id="pe-status-msg" class="label" style="margin-left: 10px; display: none;"></span>
                </div>

                <!-- SVG Canvas -->
                <div id="pe-canvas-wrapper" style="border: 1px solid #ddd; background: #f9f9f9; overflow: auto; position: relative; height: 500px;">
                    <svg id="pe-canvas" width="2000" height="1000" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#666" />
                            </marker>
                        </defs>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step Edit Modal -->
<div id="pe-step-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo plugin_lang_get( 'btn_edit' ); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pe-modal-step-id" />
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_name' ); ?></label>
                    <input type="text" id="pe-modal-name" class="form-control input-sm" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_department' ); ?></label>
                    <select id="pe-modal-department" class="form-control input-sm">
                        <option value="">--</option>
                        <option value="Satış"><?php echo plugin_lang_get( 'dept_sales' ); ?></option>
                        <option value="Fiyatlandırma"><?php echo plugin_lang_get( 'dept_pricing' ); ?></option>
                        <option value="Satış Operasyon"><?php echo plugin_lang_get( 'dept_sales_ops' ); ?></option>
                        <option value="Satınalma"><?php echo plugin_lang_get( 'dept_procurement' ); ?></option>
                        <option value="ArGe"><?php echo plugin_lang_get( 'dept_rnd' ); ?></option>
                        <option value="Yönetim"><?php echo plugin_lang_get( 'dept_management' ); ?></option>
                        <option value="Kalite"><?php echo plugin_lang_get( 'dept_quality' ); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_sla_hours' ); ?></label>
                    <input type="number" id="pe-modal-sla" class="form-control input-sm" min="0" value="0" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_role' ); ?></label>
                    <select id="pe-modal-role" class="form-control input-sm">
                        <option value="">--</option>
                        <option value="reporter">Reporter</option>
                        <option value="updater">Updater</option>
                        <option value="developer">Developer</option>
                        <option value="manager">Manager</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_mantis_status' ); ?></label>
                    <select id="pe-modal-mantis-status" class="form-control input-sm">
                        <?php foreach( $t_status_enum as $t_val => $t_label ) { ?>
                        <option value="<?php echo $t_val; ?>"><?php echo string_display_line( $t_label ); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_handler' ); ?></label>
                    <select id="pe-modal-handler" class="form-control input-sm">
                        <option value="0"><?php echo plugin_lang_get( 'no_auto_assign' ); ?></option>
                        <?php
                        $t_project_id_for_users = (int) $t_flow['project_id'];
                        if( $t_project_id_for_users > 0 ) {
                            $t_users = project_get_all_user_rows( $t_project_id_for_users );
                        } else {
                            $t_users = project_get_all_user_rows( ALL_PROJECTS );
                        }
                        foreach( $t_users as $t_user ) {
                            $t_uid = (int) $t_user['id'];
                            $t_uname = user_get_name( $t_uid );
                        ?>
                        <option value="<?php echo $t_uid; ?>"><?php echo string_display_line( $t_uname ); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">İptal</button>
                <button type="button" id="pe-modal-save" class="btn btn-sm btn-primary"><?php echo plugin_lang_get( 'btn_save' ); ?></button>
                <button type="button" id="pe-modal-delete" class="btn btn-sm btn-danger"><?php echo plugin_lang_get( 'btn_delete' ); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Pass data to JS via data attributes (CSP-safe, no inline script) -->
<div id="pe-config"
     data-flow-id="<?php echo (int) $t_flow_id; ?>"
     data-save-url="<?php echo string_attribute( plugin_page( 'flow_save' ) ); ?>"
     data-validate-url="<?php echo string_attribute( plugin_page( 'flow_validate' ) ); ?>"
     data-publish-url="<?php echo string_attribute( plugin_page( 'flow_publish' ) ); ?>"
     data-project-id="<?php echo (int) $t_flow['project_id']; ?>"
     data-steps="<?php echo string_attribute( json_encode( $t_steps ) ); ?>"
     data-transitions="<?php echo string_attribute( json_encode( $t_transitions ) ); ?>"
     style="display:none;"></div>
<link rel="stylesheet" href="<?php echo plugin_file( 'flow_designer.css' ); ?>" />
<script src="<?php echo plugin_file( 'flow_designer.js' ); ?>"></script>

<?php
}
layout_page_end();
