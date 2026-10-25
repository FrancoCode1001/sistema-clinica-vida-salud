/**
 * Manejador de Disponibilidad y Selección Dinámica de Citas Médicas
 * Soporte para CU-08, CU-09 y CU-10
 */

function consultarDisponibilidadSlots(idMedico, fechaCita, containerId, inputInicioId, inputFinId, onSelectCallback) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!idMedico || !fechaCita) {
        container.innerHTML = `
            <div class="alert alert-light border text-muted small p-3">
                <i class="bi bi-info-circle me-1"></i>Seleccione un médico y una fecha válida para cargar los intervalos de atención.
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="text-center py-4 text-primary">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <span class="small fw-semibold">Consultando disponibilidad en tiempo real...</span>
        </div>
    `;

    fetch(`/clinicaVidaSalud/api/ajax.php?action=disponibilidad&id_medico=${idMedico}&fecha_cita=${fechaCita}`)
        .then(response => response.json())
        .then(res => {
            if (!res.status) {
                container.innerHTML = `
                    <div class="alert alert-warning border d-flex align-items-center gap-2 p-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
                        <div class="small fw-semibold">${res.message}</div>
                    </div>
                `;
                return;
            }

            if (!res.slots || res.slots.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info border d-flex align-items-center gap-2 p-3">
                        <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                        <div class="small">No se encontraron bloques configurados para la fecha seleccionada.</div>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-dark">Intervalos de Atención Calculados (30 min):</span>
                    <span class="badge bg-light text-muted border">Dr(a). ${res.medico}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
            `;

            let libresCount = 0;

            res.slots.forEach(slot => {
                if (slot.disponible) {
                    libresCount++;
                    html += `
                        <button type="button" class="slot-btn" 
                                onclick="seleccionarSlot(this, '${slot.hora_inicio_sql}', '${slot.hora_fin_sql}', '${inputInicioId}', '${inputFinId}')">
                            <i class="bi bi-clock"></i>
                            <span>${slot.hora_inicio} - ${slot.hora_fin}</span>
                        </button>
                    `;
                } else {
                    html += `
                        <button type="button" class="slot-btn occupied" disabled title="Horario Ocupado (${slot.estado_cita})">
                            <i class="bi bi-lock-fill"></i>
                            <span>${slot.hora_inicio} - ${slot.hora_fin}</span>
                        </button>
                    `;
                }
            });

            html += `</div>`;

            if (libresCount === 0) {
                html += `
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <i class="bi bi-calendar-x me-1"></i>Todos los turnos de este médico se encuentran ocupados para la fecha seleccionada.
                    </div>
                `;
            }

            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = `
                <div class="alert alert-danger p-3 small">
                    <i class="bi bi-exclamation-octagon me-1"></i>Error al consultar el servicio de disponibilidad.
                </div>
            `;
        });
}

function seleccionarSlot(btn, inicioSql, finSql, inputInicioId, inputFinId) {
    // Deseleccionar otros botones en el contenedor
    const parent = btn.closest('.card-body, .modal-body, div');
    parent.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));

    btn.classList.add('selected');

    if (inputInicioId && document.getElementById(inputInicioId)) {
        document.getElementById(inputInicioId).value = inicioSql;
    }
    if (inputFinId && document.getElementById(inputFinId)) {
        document.getElementById(inputFinId).value = finSql;
    }

    if (window.showNotification) {
        showNotification(`Horario seleccionado: ${inicioSql.substring(0, 5)} a ${finSql.substring(0, 5)}`, 'info');
    }
}

function cargarMedicosPorEspecialidad(idEsp, selectMedicoId, idMedicoActual = 0) {
    const select = document.getElementById(selectMedicoId);
    if (!select) return;

    select.innerHTML = '<option value="">Cargando especialistas...</option>';

    fetch(`/clinicaVidaSalud/api/ajax.php?action=medicos_por_especialidad&id_especialidad=${idEsp}`)
        .then(response => response.json())
        .then(res => {
            select.innerHTML = '<option value="">-- Seleccionar Médico Especialista --</option>';
            if (res.status && res.medicos) {
                res.medicos.forEach(m => {
                    const sel = (parseInt(m.id_medico) === parseInt(idMedicoActual)) ? 'selected' : '';
                    select.innerHTML += `<option value="${m.id_medico}" ${sel}>Dr(a). ${m.nombres} ${m.apellidos} (${m.codigo_medico})</option>`;
                });
            }
        })
        .catch(() => {
            select.innerHTML = '<option value="">Error al cargar médicos</option>';
        });
}
