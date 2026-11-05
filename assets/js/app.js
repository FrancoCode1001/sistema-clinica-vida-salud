/**
 * Scripts Generales - Clínica Vida Salud
 * Manejo de alertas visuales (Toasts), validaciones y confirmaciones
 */

document.addEventListener('DOMContentLoaded', function () {
    // Inicializar tooltips y popovers de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar Toasts de Bootstrap
    const toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function (toastEl) {
        const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
        toast.show();
        return toast;
    });

    // Validación de formularios cliente HTML5/Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Control de Sidebar Colapsable (Desktop + Mobile) con persistencia
    initSidebarToggle();
});

/**
 * Inicializa el comportamiento de alternar (toggle) el menú lateral
 */
function initSidebarToggle() {
    const wrapper = document.querySelector('.app-wrapper');
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (!wrapper || !sidebarToggleBtn) return;

    // Restaurar estado guardado en desktop
    if (window.innerWidth >= 768) {
        const isCollapsed = localStorage.getItem('clinica_sidebar_collapsed') === 'true';
        if (isCollapsed) {
            wrapper.classList.add('sidebar-collapsed');
        }
    }

    // Evento botón hamburguesa (Navbar)
    sidebarToggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (window.innerWidth >= 768) {
            // Modo escritorio: colapsar / expandir
            const collapsed = wrapper.classList.toggle('sidebar-collapsed');
            localStorage.setItem('clinica_sidebar_collapsed', collapsed ? 'true' : 'false');
        } else {
            // Modo móvil: abrir / cerrar offcanvas con telón
            const mobileOpen = wrapper.classList.toggle('sidebar-mobile-open');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('show', mobileOpen);
            }
        }
    });

    // Evento cerrar en móvil (botón X del sidebar)
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', function () {
            wrapper.classList.remove('sidebar-mobile-open');
            if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
        });
    }

    // Evento clic en el fondo oscuro móvil (cerrar al tocar afuera)
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function () {
            wrapper.classList.remove('sidebar-mobile-open');
            sidebarBackdrop.classList.remove('show');
        });
    }

    // Ajustar si se cambia el tamaño de la ventana
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) {
            wrapper.classList.remove('sidebar-mobile-open');
            if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
        }
    });
}

/**
 * Muestra un Toast de notificación dinámico
 */
function showNotification(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const bgClass = type === 'success' ? 'bg-success text-white' :
                    type === 'error' || type === 'danger' ? 'bg-danger text-white' :
                    type === 'warning' ? 'bg-warning text-dark' : 'bg-info text-white';

    const icon = type === 'success' ? 'bi-check-circle-fill' :
                 type === 'error' || type === 'danger' ? 'bi-exclamation-triangle-fill' :
                 type === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill';

    const toastId = 'toast_' + Date.now();
    const html = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi ${icon} fs-5"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
    const toastElement = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastElement, { delay: 4500 });
    bsToast.show();

    toastElement.addEventListener('hidden.bs.toast', function () {
        toastElement.remove();
    });
}

/**
 * Confirmación modal reutilizable
 */
function confirmAction(message, onConfirmCallback) {
    if (confirm(message)) {
        if (typeof onConfirmCallback === 'function') {
            onConfirmCallback();
        }
        return true;
    }
    return false;
}

/**
 * ============================================================================
 * SISTEMA DE NOTIFICACIONES INTERNAS EN TIEMPO REAL (FETCH & POLLING)
 * ============================================================================
 */

function cargarNotificaciones() {
    const container = document.getElementById('notifItemsContainer');
    const counterBadge = document.getElementById('notifBadgeCounter');
    const bellIcon = document.getElementById('notifBellIcon');

    if (!container) return;

    fetch('/clinicaVidaSalud/api/ajax.php?action=notificaciones_obtener')
        .then(response => response.json())
        .then(res => {
            if (!res.status) return;

            // 1. Actualizar contador no leídas
            const noLeidas = parseInt(res.no_leidas || 0);
            if (counterBadge) {
                if (noLeidas > 0) {
                    counterBadge.textContent = noLeidas > 99 ? '99+' : noLeidas;
                    counterBadge.classList.remove('d-none');
                    if (bellIcon) {
                        bellIcon.classList.remove('text-secondary');
                        bellIcon.classList.add('text-primary');
                    }
                } else {
                    counterBadge.classList.add('d-none');
                    if (bellIcon) {
                        bellIcon.classList.remove('text-primary');
                        bellIcon.classList.add('text-secondary');
                    }
                }
            }

            // 2. Renderizar lista de notificaciones
            const notifs = res.notificaciones || [];
            if (notifs.length === 0) {
                container.innerHTML = `
                    <div class="p-4 text-center text-muted small">
                        <i class="bi bi-bell-slash fs-3 d-block mb-2 text-secondary"></i>
                        <div>No tiene notificaciones pendientes</div>
                    </div>
                `;
                return;
            }

            let html = '';
            notifs.forEach(n => {
                let iconClass = 'bi-info-circle-fill text-primary';
                let bgBadge = 'bg-primary-subtle text-primary';

                switch (n.tipo) {
                    case 'CITA_NUEVA':
                        iconClass = 'bi-calendar-plus-fill text-primary';
                        bgBadge = 'bg-primary-subtle text-primary';
                        break;
                    case 'CITA_CONFIRMADA':
                        iconClass = 'bi-check-circle-fill text-success';
                        bgBadge = 'bg-success-subtle text-success';
                        break;
                    case 'CITA_REPROGRAMADA':
                        iconClass = 'bi-arrow-repeat text-warning';
                        bgBadge = 'bg-warning-subtle text-dark';
                        break;
                    case 'CITA_CANCELADA':
                        iconClass = 'bi-x-circle-fill text-danger';
                        bgBadge = 'bg-danger-subtle text-danger';
                        break;
                    case 'CITA_RECORDATORIO_MEDICO':
                        iconClass = 'bi-alarm-fill text-info';
                        bgBadge = 'bg-info-subtle text-info';
                        break;
                    case 'ASISTENTE_VOZ':
                        iconClass = 'bi-mic-fill text-info';
                        bgBadge = 'bg-info-subtle text-info';
                        break;
                }

                const unreadClass = !n.leida ? 'bg-light border-start border-3 border-primary fw-semibold' : 'text-muted';
                const unreadDot = !n.leida ? '<span class="badge bg-primary rounded-pill p-1" style="width: 8px; height: 8px;" title="No leída"></span>' : '';
                const linkAttr = n.enlace ? `style="cursor: pointer;" onclick="marcarNotificacionLeida(${n.id_notificacion}, '${n.enlace}')"` : `style="cursor: default;"`;

                html += `
                    <div class="p-3 border-bottom d-flex align-items-start gap-2 notif-item ${unreadClass}" ${linkAttr}>
                        <div class="mt-1 fs-5">
                            <i class="bi ${iconClass}"></i>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge ${bgBadge} text-truncate" style="font-size: 0.68rem;">${n.titulo}</span>
                                <div class="d-flex align-items-center gap-1">
                                    <small class="text-muted" style="font-size: 0.7rem;">${n.fecha_formateada}</small>
                                    ${unreadDot}
                                </div>
                            </div>
                            <div class="small text-dark mb-0 text-break" style="font-size: 0.82rem; line-height: 1.35;">
                                ${n.mensaje}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        })
        .catch(() => {});
}

function marcarNotificacionLeida(idNotif, enlace) {
    const formData = new FormData();
    formData.append('id_notificacion', idNotif);

    fetch('/clinicaVidaSalud/api/ajax.php?action=notificaciones_marcar_leida', {
        method: 'POST',
        body: formData
    }).finally(() => {
        if (enlace && enlace.trim() !== '') {
            window.location.href = enlace;
        } else {
            cargarNotificaciones();
        }
    });
}

function marcarTodasNotificacionesLeidas(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    fetch('/clinicaVidaSalud/api/ajax.php?action=notificaciones_marcar_todas', {
        method: 'POST'
    })
    .then(r => r.json())
    .then(() => {
        cargarNotificaciones();
    });
}

// Iniciar sondeo y eventos de notificaciones al cargar el documento
document.addEventListener('DOMContentLoaded', function () {
    const notifBtn = document.getElementById('notifBellBtn');
    if (notifBtn) {
        cargarNotificaciones();
        // Polling cada 20 segundos sin recargar la página
        setInterval(cargarNotificaciones, 20000);

        notifBtn.addEventListener('show.bs.dropdown', function () {
            cargarNotificaciones();
        });
    }
});

// ==============================================================================
// Impresión y Exportación de Documentos PDF con Dompdf y Print.js
// ==============================================================================

/**
 * Imprime un documento PDF de manera limpia y directa usando Print.js
 * @param {string} url Ruta del archivo o endpoint que devuelve el PDF
 * @param {string} titulo Título opcional para la ventana de impresión
 */
function imprimirDocumentoPdf(url, titulo = 'Documento Clínico') {
    if (!url) return;
    if (typeof printJS === 'function') {
        printJS({
            printable: url,
            type: 'pdf',
            showModal: true,
            modalMessage: 'Generando y preparando documento para impresión...',
            documentTitle: titulo,
            onError: function (error) {
                console.error('Error en Print.js:', error);
                window.open(url, '_blank');
            }
        });
    } else {
        const win = window.open(url, '_blank');
        if (win) win.focus();
    }
}

/**
 * Imprime la Ficha Médica de un paciente en PDF
 * @param {number|string} idPaciente ID del paciente a imprimir
 */
function imprimirFichaMedica(idPaciente) {
    if (!idPaciente) return;
    const url = `/clinicaVidaSalud/views/pacientes/ficha_medica_pdf.php?id_paciente=${encodeURIComponent(idPaciente)}`;
    imprimirDocumentoPdf(url, 'Ficha Médica del Paciente - Vida Salud');
}


