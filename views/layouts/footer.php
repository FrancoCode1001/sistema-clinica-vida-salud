    </main>
    </div><!-- Fin main-content -->
    </div><!-- Fin app-wrapper -->

    <!-- Contenedor flotante para alertas Toast -->
    <div class="toast-container-custom" id="toastContainer">
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="toast align-items-center bg-success text-white border-0 shadow show mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="toast align-items-center bg-danger text-white border-0 shadow show mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_info'])): ?>
            <div class="toast align-items-center bg-info text-white border-0 shadow show mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                        <div><?= htmlspecialchars($_SESSION['flash_info']) ?></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_info']); ?>
        <?php endif; ?>
    </div>

    <?php
    require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
    if (AuthMiddleware::esPaciente()):
    ?>
        <!-- Botón Flotante Asistente de Voz con IA (Exclusivo para Pacientes) -->
        <button type="button" class="btn-voz-flotante" id="btnAsistenteVozFlotante" title="Asistente de Voz con IA - Vida Salud">
            <i class="bi bi-mic-fill fs-4"></i>
            <span class="badge-ia-voz">IA</span>
        </button>

        <!-- Modal Asistente de Voz con IA (Google Gemini 3.8 Flash) -->
        <div class="modal fade" id="modalAsistenteVoz" tabindex="-1" aria-labelledby="modalAsistenteVozLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-voz-dialog">
                <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 16px;">
                    <div class="modal-header modal-voz-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-pill p-2"><i class="bi bi-robot fs-6"></i></span>
                            <div>
                                <h6 class="modal-title fw-bold mb-0 text-white" id="modalAsistenteVozLabel">VidaSalud Voz &bull; Asistente IA</h6>
                                <small class="text-white-50" style="font-size: 0.72rem;">Control por voz inteligente y seguro</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-light rounded-circle" id="btnMuteVoz" title="Silenciar / Activar voz">
                                <i class="bi bi-volume-up-fill"></i>
                            </button>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Estado y Ondas de Audio -->
                        <div class="text-center mb-3">
                            <span id="vozEstadoBadge" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <i class="bi bi-mic me-1"></i>Presiona para hablar
                            </span>
                            <div class="voice-wave-container" id="vozWaveContainer">
                                <div class="voice-wave-bar"></div>
                                <div class="voice-wave-bar"></div>
                                <div class="voice-wave-bar"></div>
                                <div class="voice-wave-bar"></div>
                                <div class="voice-wave-bar"></div>
                            </div>
                        </div>

                        <!-- Burbuja de Transcripción del Paciente -->
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-semibold mb-1">
                                <i class="bi bi-person me-1"></i>Lo que escuché:
                            </label>
                            <div class="transcript-bubble" id="vozTranscripcion">
                                <em>"Presiona el botón del micrófono o escribe abajo para comenzar..."</em>
                            </div>
                        </div>

                        <!-- Burbuja de Respuesta del Asistente -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small text-muted fw-semibold mb-0">
                                    <i class="bi bi-robot me-1"></i>Respuesta del Asistente:
                                </label>
                                <span id="vozBadgeIntencion" class="badge bg-info-subtle text-info border border-info-subtle d-none" style="font-size: 0.7rem;"></span>
                            </div>
                            <div class="assistant-response-bubble" id="vozRespuestaTexto">
                                ¡Hola! Puedo ayudarte a consultar tu próxima cita, ver disponibilidad de médicos, o agendar y reprogramar citas.
                            </div>
                        </div>

                        <!-- Tarjeta de Confirmación de Acción (Human-in-the-Loop) -->
                        <div class="confirmation-card d-none mb-3" id="tarjetaConfirmacionVoz">
                            <div id="resumenConfirmacionVoz" class="mb-3"></div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelarAccion">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-sm btn-success fw-bold px-3" id="btnConfirmarAccion">
                                    <i class="bi bi-check-circle-fill me-1"></i>Confirmar Acción
                                </button>
                            </div>
                        </div>

                        <!-- Botón central de Micrófono -->
                        <div class="text-center my-3">
                            <button type="button" class="btn btn-primary btn-lg rounded-circle shadow p-3" id="btnMicModal" title="Hablar con el asistente">
                                <i class="bi bi-mic-fill fs-3"></i>
                            </button>
                            <div class="small text-muted mt-2">Haz clic para hablar o pausar</div>
                        </div>

                        <!-- Entrada por texto alternativa (accesibilidad) -->
                        <form id="formTextoVoz" class="mt-3 pt-3 border-top">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm" id="inputTextoVoz" placeholder="O escribe tu consulta aquí..." maxlength="400">
                                <button class="btn btn-sm btn-outline-primary" type="submit">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Print.js para impresión directa de documentos y fichas PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>

    <!-- Scripts personalizados con control de versión anti-caché -->
    <script src="/clinicaVidaSalud/assets/js/app.js?v=2.2"></script>
    <?php if (AuthMiddleware::esPaciente()): ?>
        <script src="/clinicaVidaSalud/assets/js/asistente_voz.js"></script>
    <?php endif; ?>
    </body>

    </html>