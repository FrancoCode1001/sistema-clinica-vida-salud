/**
 * Asistente de Voz con IA - Clínica Vida Salud
 * Tecnologías: Web Speech API (STT), SpeechSynthesis (TTS), Google Gemini 3.8 Flash
 * Cumple con CU-08, CU-09, CU-10, CU-11, RF06, RF07, RF08, RN01, RN06, RN07, RN13
 */

(function () {
    'use strict';

    // Estados
    let recognition = null;
    let isListening = false;
    let isSpeaking = false;
    let isMuted = false;
    let esperandoConfirmacionVerbal = false;
    let modalVoz = null;

    // Inicialización del Reconocimiento de Voz nativo del navegador (STT)
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const hasSpeechRecognition = !!SpeechRecognition;
    const hasSpeechSynthesis = 'speechSynthesis' in window;

    document.addEventListener('DOMContentLoaded', function () {
        initModal();
        initSpeechRecognition();
        bindEvents();
    });

    function initModal() {
        const modalEl = document.getElementById('modalAsistenteVoz');
        if (modalEl && typeof bootstrap !== 'undefined') {
            modalVoz = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: true
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                detenerEscucha();
                detenerVoz();
                esperandoConfirmacionVerbal = false;
            });
        }
    }

    function initSpeechRecognition() {
        if (!hasSpeechRecognition) {
            console.warn('[AsistenteVoz] El navegador no soporta Web Speech API. Se habilitará modo texto.');
            return;
        }

        recognition = new SpeechRecognition();
        recognition.lang = 'es-BO';
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.onstart = function () {
            isListening = true;
            actualizarEstadoUI('escuchando');
        };

        recognition.onresult = function (event) {
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                } else {
                    interimTranscript += event.results[i][0].transcript;
                }
            }

            const textoMostrado = finalTranscript || interimTranscript;
            const transcripcionEl = document.getElementById('vozTranscripcion');
            if (transcripcionEl && textoMostrado) {
                transcripcionEl.innerText = `"${textoMostrado}"`;
            }

            if (finalTranscript) {
                detenerEscucha();
                procesarTranscripcionFinal(finalTranscript.trim());
            }
        };

        recognition.onerror = function (event) {
            console.warn('[AsistenteVoz] Error de reconocimiento:', event.error);
            isListening = false;
            actualizarEstadoUI('esperando');

            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                mostrarMensajeAsistente(
                    'No se concedió permiso para usar el micrófono. Puedes escribir tu consulta en el recuadro inferior.',
                    'Permiso de micrófono denegado.'
                );
            } else if (event.error === 'no-speech') {
                // Silencio detectado, no alertar críticamente
            }
        };

        recognition.onend = function () {
            isListening = false;
            if (!isSpeaking && !esperandoConfirmacionVerbal) {
                actualizarEstadoUI('esperando');
            }
        };
    }

    function bindEvents() {
        // Botón flotante
        const btnFlotante = document.getElementById('btnAsistenteVozFlotante');
        if (btnFlotante) {
            btnFlotante.addEventListener('click', function () {
                abrirAsistente();
            });
        }

        // Botón Micrófono dentro del modal
        const btnMicModal = document.getElementById('btnMicModal');
        if (btnMicModal) {
            btnMicModal.addEventListener('click', function () {
                toggleEscucha();
            });
        }

        // Botón Silenciar/Activar audio TTS
        const btnMuteVoz = document.getElementById('btnMuteVoz');
        if (btnMuteVoz) {
            btnMuteVoz.addEventListener('click', function () {
                isMuted = !isMuted;
                if (isMuted) {
                    detenerVoz();
                    btnMuteVoz.innerHTML = '<i class="bi bi-volume-mute-fill"></i>';
                    btnMuteVoz.classList.replace('btn-outline-light', 'btn-outline-danger');
                    btnMuteVoz.title = 'Audio desactivado';
                } else {
                    btnMuteVoz.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
                    btnMuteVoz.classList.replace('btn-outline-danger', 'btn-outline-light');
                    btnMuteVoz.title = 'Audio activado';
                }
            });
        }

        // Envío por texto fallback
        const formTextoVoz = document.getElementById('formTextoVoz');
        const inputTextoVoz = document.getElementById('inputTextoVoz');
        if (formTextoVoz && inputTextoVoz) {
            formTextoVoz.addEventListener('submit', function (e) {
                e.preventDefault();
                const texto = inputTextoVoz.value.trim();
                if (texto) {
                    inputTextoVoz.value = '';
                    const transcripcionEl = document.getElementById('vozTranscripcion');
                    if (transcripcionEl) {
                        transcripcionEl.innerText = `"${texto}"`;
                    }
                    procesarTranscripcionFinal(texto);
                }
            });
        }

        // Botón Confirmar Acción en tarjeta
        const btnConfirmarAccion = document.getElementById('btnConfirmarAccion');
        if (btnConfirmarAccion) {
            btnConfirmarAccion.addEventListener('click', function () {
                ejecutarConfirmacionBackend();
            });
        }

        // Botón Cancelar Acción en tarjeta
        const btnCancelarAccion = document.getElementById('btnCancelarAccion');
        if (btnCancelarAccion) {
            btnCancelarAccion.addEventListener('click', function () {
                ejecutarCancelacionBackend();
            });
        }
    }

    function abrirAsistente() {
        if (modalVoz) {
            modalVoz.show();
            // Saludo inicial y empezar a escuchar
            setTimeout(() => {
                hablarTexto("Hola, soy el asistente inteligente de Clínica Vida Salud. ¿En qué puedo ayudarte hoy?");
                setTimeout(() => {
                    iniciarEscucha();
                }, 4000);
            }, 300);
        }
    }

    function toggleEscucha() {
        if (isListening) {
            detenerEscucha();
        } else {
            detenerVoz();
            iniciarEscucha();
        }
    }

    function iniciarEscucha() {
        if (!hasSpeechRecognition) {
            mostrarMensajeAsistente('Tu navegador no cuenta con soporte para micrófono. Por favor usa el campo de texto inferior.', 'Sin soporte STT');
            return;
        }

        try {
            detenerVoz();
            recognition.start();
        } catch (e) {
            console.warn('[AsistenteVoz] Recognition start error:', e);
        }
    }

    function detenerEscucha() {
        if (recognition && isListening) {
            try {
                recognition.stop();
            } catch (e) { }
        }
        isListening = false;
        actualizarEstadoUI('esperando');
    }

    function detenerVoz() {
        if (hasSpeechSynthesis) {
            window.speechSynthesis.cancel();
        }
        isSpeaking = false;
    }

    function hablarTexto(texto, callbackAlTerminar) {
        if (isMuted || !hasSpeechSynthesis || !texto) {
            if (typeof callbackAlTerminar === 'function') callbackAlTerminar();
            return;
        }

        detenerVoz();
        detenerEscucha();

        const utterance = new SpeechSynthesisUtterance(texto);
        utterance.lang = 'es-BO';
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        // Intentar seleccionar voz natural en español
        const voces = window.speechSynthesis.getVoices();
        const vozEspanol = voces.find(v => v.lang.startsWith('es-') || v.lang === 'es');
        if (vozEspanol) {
            utterance.voice = vozEspanol;
        }

        utterance.onstart = function () {
            isSpeaking = true;
            actualizarEstadoUI('hablando');
        };

        utterance.onend = function () {
            isSpeaking = false;
            actualizarEstadoUI('esperando');
            if (typeof callbackAlTerminar === 'function') {
                callbackAlTerminar();
            }
        };

        utterance.onerror = function () {
            isSpeaking = false;
            actualizarEstadoUI('esperando');
            if (typeof callbackAlTerminar === 'function') {
                callbackAlTerminar();
            }
        };

        window.speechSynthesis.speak(utterance);
    }

    function procesarTranscripcionFinal(comando) {
        // Verificar si estábamos esperando confirmación verbal
        if (esperandoConfirmacionVerbal) {
            const normalizado = comando.toLowerCase().replace(/[^a-záéíóúñ]/g, '');
            if (['confirmar', 'confirmo', 'si', 'sí', 'acepto', 'dale', 'deacuerdo', 'afirmativo', 'correcto'].some(w => normalizado.includes(w))) {
                ejecutarConfirmacionBackend();
                return;
            } else if (['cancelar', 'cancela', 'no', 'rechazar', 'olvidalo'].some(w => normalizado.includes(w))) {
                ejecutarCancelacionBackend();
                return;
            }
        }

        actualizarEstadoUI('procesando');
        ocultarTarjetaConfirmacion();

        const formData = new FormData();
        formData.append('action', 'procesar_comando');
        formData.append('comando', comando);

        fetch('/clinicaVidaSalud/api/asistente_voz.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (!data.status) {
                    mostrarMensajeAsistente(data.mensaje_texto || data.message, data.mensaje_voz || 'Ocurrió un error.');
                    hablarTexto(data.mensaje_voz || data.message);
                    return;
                }

                mostrarMensajeAsistente(data.mensaje_texto, data.mensaje_voz, data.intencion);

                if (data.requiere_confirmacion && data.detalles) {
                    mostrarTarjetaConfirmacion(data.detalles, data.accion_pendiente);
                    esperandoConfirmacionVerbal = true;

                    hablarTexto(data.mensaje_voz, function () {
                        // Al terminar de hablar la solicitud de confirmación, escuchar automáticamente
                        setTimeout(() => {
                            iniciarEscucha();
                        }, 500);
                    });
                } else {
                    esperandoConfirmacionVerbal = false;
                    hablarTexto(data.mensaje_voz);
                }
            })
            .catch(err => {
                console.error('[AsistenteVoz] Error de red:', err);
                mostrarMensajeAsistente('Hubo un problema de conexión con el servidor.', 'Error de conexión.');
                hablarTexto('Hubo un problema al contactar al servidor. Por favor intenta de nuevo.');
                actualizarEstadoUI('esperando');
            });
    }

    function ejecutarConfirmacionBackend() {
        actualizarEstadoUI('procesando');
        esperandoConfirmacionVerbal = false;

        const formData = new FormData();
        formData.append('action', 'confirmar_accion');

        fetch('/clinicaVidaSalud/api/asistente_voz.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                ocultarTarjetaConfirmacion();
                if (data.status) {
                    mostrarMensajeAsistente(data.message, data.mensaje_voz, 'EXITO');
                    hablarTexto(data.mensaje_voz);

                    // Actualizar sistema de notificaciones en tiempo real
                    if (typeof window.cargarNotificaciones === 'function') {
                        window.cargarNotificaciones();
                    }

                    // Si se confirmó exitosamente una cita, actualizar tabla si la vista actual es citas
                    setTimeout(() => {
                        if (typeof window.recargarCitas === 'function') {
                            window.recargarCitas();
                        }
                    }, 1500);
                } else {
                    mostrarMensajeAsistente(data.message, data.mensaje_voz, 'ERROR');
                    hablarTexto(data.mensaje_voz || data.message);
                }
            })
            .catch(err => {
                console.error('[AsistenteVoz] Error al confirmar:', err);
                mostrarMensajeAsistente('Error al confirmar la acción.', 'Error del servidor.');
                hablarTexto('Ocurrió un error al confirmar. Por favor intenta manualmente.');
                actualizarEstadoUI('esperando');
            });
    }

    function ejecutarCancelacionBackend() {
        esperandoConfirmacionVerbal = false;
        ocultarTarjetaConfirmacion();

        const formData = new FormData();
        formData.append('action', 'cancelar_accion');

        fetch('/clinicaVidaSalud/api/asistente_voz.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                mostrarMensajeAsistente(data.message || 'Acción cancelada.', data.mensaje_voz);
                hablarTexto(data.mensaje_voz);
            });
    }

    function mostrarMensajeAsistente(texto, voz, intencion) {
        const respuestaEl = document.getElementById('vozRespuestaTexto');
        const badgeIntencion = document.getElementById('vozBadgeIntencion');

        if (respuestaEl) {
            respuestaEl.innerHTML = htmlEncode(texto);
        }

        if (badgeIntencion) {
            if (intencion) {
                badgeIntencion.classList.remove('d-none');
                badgeIntencion.innerText = intencion.replace('_', ' ');
            } else {
                badgeIntencion.classList.add('d-none');
            }
        }
    }

    function mostrarTarjetaConfirmacion(detalles, tipoAccion) {
        const card = document.getElementById('tarjetaConfirmacionVoz');
        const resumenEl = document.getElementById('resumenConfirmacionVoz');

        if (!card || !resumenEl) return;

        let html = '';
        if (detalles.accion === 'RESERVAR') {
            html = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary"><i class="bi bi-calendar-plus me-1"></i>Nueva Cita</span>
                    <strong class="text-dark">${htmlEncode(detalles.especialidad || 'Especialidad')}</strong>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-person-fill text-primary me-1"></i>Médico: <strong>${htmlEncode(detalles.medico)}</strong></div>
                <div class="small text-muted mb-1"><i class="bi bi-calendar-event text-primary me-1"></i>Fecha: <strong>${htmlEncode(detalles.fecha)}</strong></div>
                <div class="small text-muted"><i class="bi bi-clock-fill text-primary me-1"></i>Horario: <strong>${htmlEncode(detalles.hora)}</strong></div>
            `;
        } else if (detalles.accion === 'REPROGRAMAR') {
            html = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark"><i class="bi bi-arrow-repeat me-1"></i>Reprogramar</span>
                    <strong class="text-dark">Cita ${htmlEncode(detalles.codigo)}</strong>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-person-fill text-primary me-1"></i>Médico: <strong>${htmlEncode(detalles.medico)}</strong></div>
                <div class="small text-muted mb-1"><i class="bi bi-calendar-check text-success me-1"></i>Nueva Fecha: <strong>${htmlEncode(detalles.fecha_nueva)}</strong></div>
                <div class="small text-muted"><i class="bi bi-clock-history text-success me-1"></i>Nuevo Horario: <strong>${htmlEncode(detalles.hora_nueva)}</strong></div>
            `;
        } else if (detalles.accion === 'CANCELAR') {
            html = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger"><i class="bi bi-calendar-x me-1"></i>Cancelar Cita</span>
                    <strong class="text-danger">Cita ${htmlEncode(detalles.codigo)}</strong>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-person-fill text-primary me-1"></i>Médico: <strong>${htmlEncode(detalles.medico)}</strong></div>
                <div class="small text-muted mb-1"><i class="bi bi-calendar-event text-primary me-1"></i>Fecha: <strong>${htmlEncode(detalles.fecha)}</strong> a las <strong>${htmlEncode(detalles.hora)}</strong></div>
                <div class="small text-danger fw-semibold mt-1">¿Confirmas la anulación de esta cita?</div>
            `;
        }

        resumenEl.innerHTML = html;
        card.classList.remove('d-none');
    }

    function ocultarTarjetaConfirmacion() {
        const card = document.getElementById('tarjetaConfirmacionVoz');
        if (card) {
            card.classList.add('d-none');
        }
    }

    function actualizarEstadoUI(estado) {
        const badge = document.getElementById('vozEstadoBadge');
        const wave = document.getElementById('vozWaveContainer');
        const btnMic = document.getElementById('btnMicModal');
        const btnFlotante = document.getElementById('btnAsistenteVozFlotante');

        if (!badge) return;

        if (estado === 'escuchando') {
            badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
            badge.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1" role="status"></span>Escuchando tu voz...';
            if (wave) wave.classList.add('animando');
            if (btnMic) {
                btnMic.className = 'btn btn-danger btn-lg rounded-circle shadow p-3';
                btnMic.innerHTML = '<i class="bi bi-mic-fill fs-3"></i>';
            }
            if (btnFlotante) btnFlotante.classList.add('escuchando');
        } else if (estado === 'procesando') {
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle';
            badge.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Procesando con IA...';
            if (wave) wave.classList.remove('animando');
            if (btnMic) {
                btnMic.className = 'btn btn-secondary btn-lg rounded-circle shadow p-3 disabled';
                btnMic.innerHTML = '<i class="bi bi-cpu fs-3"></i>';
            }
            if (btnFlotante) btnFlotante.classList.remove('escuchando');
        } else if (estado === 'hablando') {
            badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
            badge.innerHTML = '<i class="bi bi-soundwave me-1"></i>Asistente hablando...';
            if (wave) wave.classList.add('animando');
            if (btnMic) {
                btnMic.className = 'btn btn-outline-success btn-lg rounded-circle shadow p-3';
                btnMic.innerHTML = '<i class="bi bi-volume-up-fill fs-3"></i>';
            }
            if (btnFlotante) btnFlotante.classList.remove('escuchando');
        } else {
            // esperando
            badge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle';
            badge.innerHTML = '<i class="bi bi-mic me-1"></i>Presiona para hablar';
            if (wave) wave.classList.remove('animando');
            if (btnMic) {
                btnMic.className = 'btn btn-primary btn-lg rounded-circle shadow p-3';
                btnMic.innerHTML = '<i class="bi bi-mic-fill fs-3"></i>';
            }
            if (btnFlotante) btnFlotante.classList.remove('escuchando');
        }
    }

    function htmlEncode(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
})();
