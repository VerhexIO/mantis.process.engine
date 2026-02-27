/**
 * ProcessEngine - Flow Designer JS
 *
 * SVG-based visual flow editor with:
 * - Drag & drop nodes
 * - Transition drawing (click port on source, click on target)
 * - Context menu for node editing
 * - AJAX save / validate / publish
 */
(function() {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var NODE_W = 160;
    var NODE_H = 70;
    var PORT_R = 8;

    var canvas, wrapper;
    var steps = [];       // Local step state
    var transitions = []; // Local transition state
    var nextTempId = 1;
    var selectedNodeId = null;
    var dragState = null; // { nodeId, offsetX, offsetY }
    var connectState = null; // { fromId } - drawing a transition

    // Config (read from data attributes, CSP-safe)
    var PE_FLOW_ID = 0;
    var PE_SAVE_URL = '';
    var PE_VALIDATE_URL = '';
    var PE_PUBLISH_URL = '';

    // ---- Init ----
    function init() {
        canvas = document.getElementById('pe-canvas');
        wrapper = document.getElementById('pe-canvas-wrapper');
        if (!canvas) return;

        // Load config from data attributes (CSP-safe, no inline script)
        var configEl = document.getElementById('pe-config');
        if (configEl) {
            PE_FLOW_ID = parseInt(configEl.getAttribute('data-flow-id')) || 0;
            PE_SAVE_URL = configEl.getAttribute('data-save-url') || '';
            PE_VALIDATE_URL = configEl.getAttribute('data-validate-url') || '';
            PE_PUBLISH_URL = configEl.getAttribute('data-publish-url') || '';

            try {
                var stepsData = JSON.parse(configEl.getAttribute('data-steps') || '[]');
                steps = stepsData.map(normalizeStep);
            } catch (e) { steps = []; }

            try {
                var transData = JSON.parse(configEl.getAttribute('data-transitions') || '[]');
                transitions = transData.map(normalizeTransition);
            } catch (e) { transitions = []; }
        }

        render();
        bindToolbar();
        bindCanvasEvents();
        bindModal();
    }

    function normalizeStep(s) {
        return {
            id: String(s.id),
            name: s.name || 'Step',
            department: s.department || '',
            mantis_status: parseInt(s.mantis_status) || 10,
            sla_hours: parseInt(s.sla_hours) || 0,
            step_order: parseInt(s.step_order) || 0,
            role: s.role || '',
            position_x: parseInt(s.position_x) || 100,
            position_y: parseInt(s.position_y) || 100
        };
    }

    function normalizeTransition(t) {
        return {
            id: String(t.id),
            from_step_id: String(t.from_step_id),
            to_step_id: String(t.to_step_id),
            condition_field: t.condition_field || '',
            condition_value: t.condition_value || ''
        };
    }

    // ---- Rendering ----
    function render() {
        // Clear canvas (keep defs)
        var defs = canvas.querySelector('defs');
        canvas.innerHTML = '';
        canvas.appendChild(defs);

        // Draw transitions first (behind nodes)
        transitions.forEach(function(tr) {
            renderTransition(tr);
        });

        // Draw nodes
        steps.forEach(function(step) {
            renderNode(step);
        });
    }

    function renderNode(step) {
        var g = document.createElementNS(SVG_NS, 'g');
        g.setAttribute('class', 'pe-node');
        g.setAttribute('data-id', step.id);
        g.setAttribute('transform', 'translate(' + step.position_x + ',' + step.position_y + ')');

        // Rectangle
        var rect = document.createElementNS(SVG_NS, 'rect');
        rect.setAttribute('width', NODE_W);
        rect.setAttribute('height', NODE_H);
        rect.setAttribute('rx', 6);
        rect.setAttribute('ry', 6);
        g.appendChild(rect);

        // Title text
        var title = document.createElementNS(SVG_NS, 'text');
        title.setAttribute('class', 'pe-node-title');
        title.setAttribute('x', NODE_W / 2);
        title.setAttribute('y', 22);
        title.setAttribute('text-anchor', 'middle');
        title.textContent = truncate(step.name, 18);
        g.appendChild(title);

        // Department text
        var dept = document.createElementNS(SVG_NS, 'text');
        dept.setAttribute('class', 'pe-node-dept');
        dept.setAttribute('x', NODE_W / 2);
        dept.setAttribute('y', 40);
        dept.setAttribute('text-anchor', 'middle');
        dept.textContent = step.department || '';
        g.appendChild(dept);

        // SLA text
        var sla = document.createElementNS(SVG_NS, 'text');
        sla.setAttribute('class', 'pe-node-sla');
        sla.setAttribute('x', NODE_W / 2);
        sla.setAttribute('y', 56);
        sla.setAttribute('text-anchor', 'middle');
        sla.textContent = step.sla_hours > 0 ? 'SLA: ' + step.sla_hours + 'h' : '';
        g.appendChild(sla);

        // Output port (right side)
        var portOut = document.createElementNS(SVG_NS, 'circle');
        portOut.setAttribute('class', 'pe-port pe-port-out');
        portOut.setAttribute('cx', NODE_W);
        portOut.setAttribute('cy', NODE_H / 2);
        portOut.setAttribute('r', PORT_R);
        g.appendChild(portOut);

        // Input port (left side)
        var portIn = document.createElementNS(SVG_NS, 'circle');
        portIn.setAttribute('class', 'pe-port pe-port-in');
        portIn.setAttribute('cx', 0);
        portIn.setAttribute('cy', NODE_H / 2);
        portIn.setAttribute('r', PORT_R);
        g.appendChild(portIn);

        if (step.id === selectedNodeId) {
            g.classList.add('pe-selected');
        }

        canvas.appendChild(g);
    }

    function renderTransition(tr) {
        var fromStep = findStep(tr.from_step_id);
        var toStep = findStep(tr.to_step_id);
        if (!fromStep || !toStep) return;

        var x1 = fromStep.position_x + NODE_W;
        var y1 = fromStep.position_y + NODE_H / 2;
        var x2 = toStep.position_x;
        var y2 = toStep.position_y + NODE_H / 2;

        // Bezier curve
        var cx1 = x1 + Math.abs(x2 - x1) * 0.4;
        var cx2 = x2 - Math.abs(x2 - x1) * 0.4;

        var path = document.createElementNS(SVG_NS, 'path');
        path.setAttribute('class', 'pe-transition');
        path.setAttribute('data-id', tr.id);
        path.setAttribute('d', 'M' + x1 + ',' + y1 + ' C' + cx1 + ',' + y1 + ' ' + cx2 + ',' + y2 + ' ' + x2 + ',' + y2);
        path.addEventListener('dblclick', function(e) {
            e.stopPropagation();
            if (confirm('Bu bağlantıyı silmek istiyor musunuz?')) {
                transitions = transitions.filter(function(t) { return t.id !== tr.id; });
                render();
            }
        });
        canvas.appendChild(path);
    }

    // ---- Event Handlers ----
    function bindCanvasEvents() {
        // Node drag & connect
        canvas.addEventListener('mousedown', onCanvasMouseDown);
        canvas.addEventListener('mousemove', onCanvasMouseMove);
        canvas.addEventListener('mouseup', onCanvasMouseUp);

        // Context menu
        canvas.addEventListener('contextmenu', onContextMenu);

        // Click outside to deselect
        canvas.addEventListener('click', function(e) {
            if (e.target === canvas || e.target.tagName === 'svg') {
                selectedNodeId = null;
                render();
            }
        });

        // Double-click node to edit
        canvas.addEventListener('dblclick', function(e) {
            var nodeG = getNodeGroup(e.target);
            if (nodeG) {
                var id = nodeG.getAttribute('data-id');
                openModal(id);
            }
        });
    }

    function onCanvasMouseDown(e) {
        if (e.button !== 0) return; // Left click only

        var target = e.target;

        // Port click - start connection
        if (target.classList.contains('pe-port-out')) {
            var nodeG = getNodeGroup(target);
            if (nodeG) {
                connectState = { fromId: nodeG.getAttribute('data-id') };
                e.preventDefault();
                return;
            }
        }

        // Node click - start drag
        var nodeG = getNodeGroup(target);
        if (nodeG && !target.classList.contains('pe-port')) {
            var id = nodeG.getAttribute('data-id');
            selectedNodeId = id;
            var step = findStep(id);
            if (step) {
                var pt = svgPoint(e);
                dragState = {
                    nodeId: id,
                    offsetX: pt.x - step.position_x,
                    offsetY: pt.y - step.position_y
                };
            }
            render();
            e.preventDefault();
        }
    }

    function onCanvasMouseMove(e) {
        if (dragState) {
            var pt = svgPoint(e);
            var step = findStep(dragState.nodeId);
            if (step) {
                step.position_x = Math.max(0, Math.round(pt.x - dragState.offsetX));
                step.position_y = Math.max(0, Math.round(pt.y - dragState.offsetY));
                render();
            }
        }
        if (connectState) {
            // Draw temporary line
            removeDragLine();
            var fromStep = findStep(connectState.fromId);
            if (fromStep) {
                var pt = svgPoint(e);
                var line = document.createElementNS(SVG_NS, 'line');
                line.setAttribute('class', 'pe-drag-line');
                line.setAttribute('x1', fromStep.position_x + NODE_W);
                line.setAttribute('y1', fromStep.position_y + NODE_H / 2);
                line.setAttribute('x2', pt.x);
                line.setAttribute('y2', pt.y);
                canvas.appendChild(line);
            }
        }
    }

    function onCanvasMouseUp(e) {
        if (connectState) {
            removeDragLine();
            // Check if we dropped on a target node's input port or body
            var target = e.target;
            var nodeG = getNodeGroup(target);
            if (nodeG) {
                var toId = nodeG.getAttribute('data-id');
                if (toId !== connectState.fromId) {
                    // Check if transition already exists
                    var exists = transitions.some(function(t) {
                        return t.from_step_id === connectState.fromId && t.to_step_id === toId;
                    });
                    if (!exists) {
                        transitions.push({
                            id: 'temp_t_' + (nextTempId++),
                            from_step_id: connectState.fromId,
                            to_step_id: toId,
                            condition_field: '',
                            condition_value: ''
                        });
                    }
                }
            }
            connectState = null;
            render();
        }
        dragState = null;
    }

    function onContextMenu(e) {
        e.preventDefault();
        var nodeG = getNodeGroup(e.target);
        if (!nodeG) return;

        var id = nodeG.getAttribute('data-id');
        selectedNodeId = id;
        render();

        // Show context menu
        showContextMenu(e.clientX, e.clientY, id);
    }

    // ---- Context Menu ----
    function showContextMenu(x, y, nodeId) {
        removeContextMenu();
        var menu = document.createElement('div');
        menu.className = 'pe-context-menu';
        menu.style.display = 'block';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.position = 'fixed';

        var items = [
            { label: 'Düzenle', action: function() { openModal(nodeId); } },
            { label: 'Sil', cls: 'pe-ctx-danger', action: function() {
                steps = steps.filter(function(s) { return s.id !== nodeId; });
                transitions = transitions.filter(function(t) {
                    return t.from_step_id !== nodeId && t.to_step_id !== nodeId;
                });
                selectedNodeId = null;
                render();
            }}
        ];

        items.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'pe-ctx-item' + (item.cls ? ' ' + item.cls : '');
            div.textContent = item.label;
            div.addEventListener('click', function() {
                removeContextMenu();
                item.action();
            });
            menu.appendChild(div);
        });

        document.body.appendChild(menu);

        // Close on click outside
        setTimeout(function() {
            document.addEventListener('click', removeContextMenu, { once: true });
        }, 10);
    }

    function removeContextMenu() {
        var existing = document.querySelector('.pe-context-menu');
        if (existing) existing.remove();
    }

    // ---- Modal ----
    function bindModal() {
        var saveBtn = document.getElementById('pe-modal-save');
        var deleteBtn = document.getElementById('pe-modal-delete');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                var id = document.getElementById('pe-modal-step-id').value;
                var step = findStep(id);
                if (step) {
                    step.name = document.getElementById('pe-modal-name').value;
                    step.department = document.getElementById('pe-modal-department').value;
                    step.sla_hours = parseInt(document.getElementById('pe-modal-sla').value) || 0;
                    step.role = document.getElementById('pe-modal-role').value;
                    step.mantis_status = parseInt(document.getElementById('pe-modal-mantis-status').value) || 10;
                    render();
                }
                $('#pe-step-modal').modal('hide');
            });
        }
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                var id = document.getElementById('pe-modal-step-id').value;
                steps = steps.filter(function(s) { return s.id !== id; });
                transitions = transitions.filter(function(t) {
                    return t.from_step_id !== id && t.to_step_id !== id;
                });
                selectedNodeId = null;
                render();
                $('#pe-step-modal').modal('hide');
            });
        }
    }

    function openModal(nodeId) {
        var step = findStep(nodeId);
        if (!step) return;

        document.getElementById('pe-modal-step-id').value = step.id;
        document.getElementById('pe-modal-name').value = step.name;
        document.getElementById('pe-modal-department').value = step.department;
        document.getElementById('pe-modal-sla').value = step.sla_hours;
        document.getElementById('pe-modal-role').value = step.role;
        document.getElementById('pe-modal-mantis-status').value = step.mantis_status;

        $('#pe-step-modal').modal('show');
    }

    // ---- Toolbar ----
    function bindToolbar() {
        // Add Step
        document.getElementById('pe-btn-add-step').addEventListener('click', function() {
            var id = 'temp_' + (nextTempId++);
            // Find a clear position
            var maxX = 100, maxY = 100;
            steps.forEach(function(s) {
                if (s.position_x >= maxX) maxX = s.position_x + NODE_W + 40;
            });
            steps.push({
                id: id,
                name: 'Yeni Adım',
                department: '',
                mantis_status: 10,
                sla_hours: 0,
                step_order: steps.length + 1,
                role: '',
                position_x: maxX,
                position_y: 100
            });
            selectedNodeId = id;
            render();
            openModal(id);
        });

        // Save
        document.getElementById('pe-btn-save').addEventListener('click', doSave);

        // Validate
        document.getElementById('pe-btn-validate').addEventListener('click', doValidate);

        // Publish
        document.getElementById('pe-btn-publish').addEventListener('click', doPublish);
    }

    function doSave() {
        var payload = {
            flow_id: PE_FLOW_ID,
            name: document.getElementById('pe-flow-name').value,
            description: document.getElementById('pe-flow-desc').value,
            steps: steps.map(function(s) {
                return {
                    temp_id: s.id,
                    name: s.name,
                    department: s.department,
                    mantis_status: s.mantis_status,
                    sla_hours: s.sla_hours,
                    step_order: s.step_order,
                    role: s.role,
                    position_x: s.position_x,
                    position_y: s.position_y
                };
            }),
            transitions: transitions.map(function(t) {
                return {
                    from_step_id: t.from_step_id,
                    to_step_id: t.to_step_id,
                    condition_field: t.condition_field,
                    condition_value: t.condition_value
                };
            })
        };

        ajaxPost(PE_SAVE_URL, payload, function(resp) {
            if (resp.success) {
                // Update local state with real IDs
                steps = resp.steps.map(normalizeStep);
                transitions = resp.transitions.map(normalizeTransition);
                render();
                showStatus('success', 'Kaydedildi!');
                // Update flow name display
                document.getElementById('pe-flow-name-display').textContent =
                    document.getElementById('pe-flow-name').value;
            } else {
                showStatus('danger', resp.error || 'Kaydetme hatası');
            }
        });
    }

    function doValidate() {
        // Save first, then validate
        doSaveSync(function() {
            ajaxPost(PE_VALIDATE_URL, { flow_id: PE_FLOW_ID }, function(resp) {
                if (resp.valid) {
                    showStatus('success', 'Doğrulama başarılı!');
                } else {
                    showStatus('danger', resp.errors.join(', '));
                }
            });
        });
    }

    function doPublish() {
        doSaveSync(function() {
            ajaxPost(PE_PUBLISH_URL, { flow_id: PE_FLOW_ID }, function(resp) {
                if (resp.success) {
                    showStatus('success', 'Yayınlandı!');
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    var msg = resp.error || 'Yayınlama hatası';
                    if (resp.errors) msg += ': ' + resp.errors.join(', ');
                    showStatus('danger', msg);
                }
            });
        });
    }

    function doSaveSync(callback) {
        var payload = {
            flow_id: PE_FLOW_ID,
            name: document.getElementById('pe-flow-name').value,
            description: document.getElementById('pe-flow-desc').value,
            steps: steps.map(function(s) {
                return {
                    temp_id: s.id,
                    name: s.name,
                    department: s.department,
                    mantis_status: s.mantis_status,
                    sla_hours: s.sla_hours,
                    step_order: s.step_order,
                    role: s.role,
                    position_x: s.position_x,
                    position_y: s.position_y
                };
            }),
            transitions: transitions.map(function(t) {
                return {
                    from_step_id: t.from_step_id,
                    to_step_id: t.to_step_id,
                    condition_field: t.condition_field,
                    condition_value: t.condition_value
                };
            })
        };

        ajaxPost(PE_SAVE_URL, payload, function(resp) {
            if (resp.success) {
                steps = resp.steps.map(normalizeStep);
                transitions = resp.transitions.map(normalizeTransition);
                render();
                if (callback) callback();
            } else {
                showStatus('danger', resp.error || 'Kaydetme hatası');
            }
        });
    }

    // ---- Utilities ----
    function findStep(id) {
        var sid = String(id);
        for (var i = 0; i < steps.length; i++) {
            if (steps[i].id === sid) return steps[i];
        }
        return null;
    }

    function getNodeGroup(el) {
        var current = el;
        while (current && current !== canvas) {
            if (current.classList && current.classList.contains('pe-node')) {
                return current;
            }
            current = current.parentNode;
        }
        return null;
    }

    function svgPoint(e) {
        var pt = canvas.createSVGPoint();
        pt.x = e.clientX;
        pt.y = e.clientY;
        var ctm = canvas.getScreenCTM().inverse();
        return pt.matrixTransform(ctm);
    }

    function removeDragLine() {
        var existing = canvas.querySelector('.pe-drag-line');
        if (existing) existing.remove();
    }

    function truncate(str, max) {
        return str.length > max ? str.substring(0, max - 1) + '…' : str;
    }

    function showStatus(type, msg) {
        var el = document.getElementById('pe-status-msg');
        el.className = 'label label-' + type;
        el.textContent = msg;
        el.style.display = 'inline-block';
        setTimeout(function() {
            el.style.display = 'none';
        }, 5000);
    }

    function ajaxPost(url, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    callback(resp);
                } catch (e) {
                    showStatus('danger', 'Server error');
                }
            }
        };
        xhr.send(JSON.stringify(data));
    }

    // Init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
