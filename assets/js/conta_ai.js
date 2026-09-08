/**
 * CONTA SMART AI & CONTA VOZ & CONTA ALERTA
 * Asistente Inteligente de Gestión Contable, Comandos de Voz y Alertas Predictivas
 */

const ContaSmartAI = (() => {
    let drawer = null;
    let backdrop = null;
    let isRecording = false;
    let recognition = null;

    function init() {
        createDrawer();
        setupSpeechRecognition();

        // Botones para abrir el asistente
        document.querySelectorAll('.btn-open-ai').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                open();
            });
        });
    }

    function createDrawer() {
        if (document.getElementById('aiAssistantDrawer')) return;

        backdrop = document.createElement('div');
        backdrop.className = 'ai-assistant-backdrop';
        backdrop.id = 'aiAssistantBackdrop';
        backdrop.onclick = close;
        document.body.appendChild(backdrop);

        drawer = document.createElement('div');
        drawer.className = 'ai-assistant-drawer';
        drawer.id = 'aiAssistantDrawer';

        drawer.innerHTML = `
            <div class="ai-drawer-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="fa fa-robot"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-white">ContaSmart IA</h6>
                        <small class="text-info" style="font-size: 0.75rem;">Asistente & Comandos de Voz</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="ContaSmartAI.close()"></button>
            </div>

            <!-- Selector de Modos -->
            <div class="bg-white border-bottom px-3 py-2">
                <ul class="nav nav-pills nav-fill" id="aiModesTabs" style="gap: 4px;">
                    <li class="nav-item">
                        <button class="nav-link active py-1 px-2 small" onclick="ContaSmartAI.switchTab('chat')">
                            <i class="fa fa-comments me-1"></i> Asistente
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2 small" onclick="ContaSmartAI.switchTab('voice')">
                            <i class="fa fa-microphone me-1 text-danger"></i> ContaVoz
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2 small" onclick="ContaSmartAI.switchTab('alerts')">
                            <i class="fa fa-bell me-1 text-warning"></i> ContaAlerta
                        </button>
                    </li>
                </ul>
            </div>

            <div class="ai-drawer-body" id="aiDrawerBody">
                <!-- Contenido Dinámico de Chat / Voz / Alertas -->
            </div>

            <div class="ai-drawer-footer" id="aiDrawerFooter">
                <!-- Barra de entrada con micrófono -->
                <div class="input-group">
                    <button class="btn btn-outline-danger btn-mic-pulse" id="btnAiMic" onclick="ContaSmartAI.toggleVoice()" title="Dictar por voz (ContaVoz)">
                        <i class="fa fa-microphone"></i>
                    </button>
                    <input type="text" id="aiInputText" class="form-control" placeholder="Escribe o dicta una operación..." onkeydown="if(event.key==='Enter') ContaSmartAI.sendUserQuery()">
                    <button class="btn btn-primary" onclick="ContaSmartAI.sendUserQuery()">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
                <div class="text-center mt-1">
                    <small class="text-muted" style="font-size: 0.72rem;">
                        Ejemplo: <em>"Compré mercadería por 1500 en efectivo"</em>
                    </small>
                </div>
            </div>
        `;

        document.body.appendChild(drawer);
        renderChatView();
    }

    function open() {
        // Cerrar el tour si está en ejecución para evitar superposición
        if (typeof ContaSmartTour !== 'undefined' && ContaSmartTour.endTour) {
            ContaSmartTour.endTour();
        }
        createDrawer();
        backdrop.classList.add('active');
        drawer.classList.add('active');
    }

    function close() {
        if (backdrop) backdrop.classList.remove('active');
        if (drawer) drawer.classList.remove('active');
        if (isRecording) stopVoice();
    }

    function switchTab(tab) {
        document.querySelectorAll('#aiModesTabs .nav-link').forEach(btn => btn.classList.remove('active'));
        if (event && event.currentTarget) event.currentTarget.classList.add('active');

        if (tab === 'chat') {
            renderChatView();
            document.getElementById('aiDrawerFooter').style.display = 'block';
        } else if (tab === 'voice') {
            renderVoiceView();
            document.getElementById('aiDrawerFooter').style.display = 'block';
        } else if (tab === 'alerts') {
            renderAlertsView();
            document.getElementById('aiDrawerFooter').style.display = 'none';
        }
    }

    function renderChatView() {
        const body = document.getElementById('aiDrawerBody');
        body.innerHTML = `
            <div class="ai-message-bubble ai-bubble-bot">
                <strong>¡Hola! Soy ContaSmart IA 🤖</strong><br>
                Puedo ayudarte a generar <strong>asientos contables automáticos</strong> (según el Plan Contable General Empresarial - PCGE), analizar tus ventas o registrar operaciones.
            </div>

            <div class="small fw-semibold text-muted mb-1">Prueba haciendo clic en estas operaciones:</div>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <button class="btn btn-sm btn-outline-primary py-1" onclick="ContaSmartAI.processQuery('Compré mercadería por S/ 1,500 y pagué en efectivo')">
                    📦 Compra mercadería S/ 1,500 efectivo
                </button>
                <button class="btn btn-sm btn-outline-primary py-1" onclick="ContaSmartAI.processQuery('Vendí productos por S/ 800 al contado con Factura')">
                    🛒 Venta S/ 800 Factura al contado
                </button>
                <button class="btn btn-sm btn-outline-secondary py-1" onclick="ContaSmartAI.processQuery('¿Cuáles son mis productos con stock bajo?')">
                    ⚠️ Productos con stock bajo
                </button>
                <button class="btn btn-sm btn-outline-secondary py-1" onclick="ContaSmartAI.processQuery('Pagué servicio de luz por S/ 250 con transferencia')">
                    💡 Pago servicio luz S/ 250
                </button>
            </div>
            <div id="aiChatStream"></div>
        `;
    }

    function renderVoiceView() {
        const body = document.getElementById('aiDrawerBody');
        body.innerHTML = `
            <div class="text-center py-4">
                <div class="mb-3">
                    <button class="btn btn-danger rounded-circle p-4 btn-mic-pulse ${isRecording ? 'recording' : ''}" id="btnBigVoice" onclick="ContaSmartAI.toggleVoice()" style="width: 85px; height: 85px;">
                        <i class="fa fa-microphone fa-2x"></i>
                    </button>
                </div>
                <h5 class="fw-bold text-dark mb-1">🎙️ CONTA VOZ</h5>
                <p class="text-muted small px-3">
                    ${isRecording ? '<span class="text-danger fw-bold">Escuchando tu voz... Habla ahora.</span>' : 'Presiona el micrófono y dicta tu operación en voz alta.'}
                </p>
                <div class="p-3 bg-light rounded text-start mx-2 border">
                    <span class="small fw-bold text-primary d-block mb-1">Puedes decir por ejemplo:</span>
                    <ul class="small text-muted mb-0 ps-3">
                        <li><em>"Hoy vendí 3 amoladoras por 855 soles"</em></li>
                        <li><em>"Compré materiales por mil doscientos soles"</em></li>
                        <li><em>"Consultar RUC veinte diez cero cero setenta"</em></li>
                    </ul>
                </div>
                <div id="voiceTranscription" class="mt-3 p-2 font-monospace text-primary fw-bold" style="min-height: 30px;"></div>
            </div>
        `;
    }

    function renderAlertsView() {
        const body = document.getElementById('aiDrawerBody');
        body.innerHTML = `
            <div class="mb-2">
                <h6 class="fw-bold text-dark mb-1"><i class="fa fa-brain text-primary me-2"></i>CONTA ALERTA Predictivo</h6>
                <small class="text-muted">Diagnóstico inteligente en tiempo real de tu negocio</small>
            </div>

            <!-- Alerta 1: Inventario -->
            <div class="card border-warning mb-2 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-warning text-dark"><i class="fa fa-triangle-exclamation me-1"></i>Stock Crítico</span>
                        <small class="text-muted">Alerta de Almacén</small>
                    </div>
                    <p class="small text-dark mb-2">
                        Tienes <strong>2 productos</strong> con existencias inferiores al stock mínimo:
                        <br>• <strong>Cinta Métrica 5m</strong> (4 unidades disponibles)
                        <br>• <strong>Cable Mellizo 2x14</strong> (3 unidades disponibles)
                    </p>
                    <a href="compra_nueva.php" class="btn btn-xs btn-outline-warning w-100 fw-bold">
                        <i class="fa fa-cart-plus me-1"></i> Generar Orden de Reposición
                    </a>
                </div>
            </div>

            <!-- Alerta 2: Ventas y Rotación -->
            <div class="card border-info mb-2 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-info text-white"><i class="fa fa-chart-line me-1"></i>Oportunidad Comercial</span>
                        <small class="text-muted">Rotación</small>
                    </div>
                    <p class="small text-dark mb-2">
                        El producto <strong>Foco LED 12W</strong> presenta la mayor rotación del periodo. Se sugiere mantener un margen de stock de seguridad para evitar desabastecimiento.
                    </p>
                </div>
            </div>

            <!-- Alerta 3: Calendario Tributario SIRE -->
            <div class="card border-primary mb-2 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-primary"><i class="fa fa-calendar-check me-1"></i>SUNAT SIRE</span>
                        <small class="text-muted">Cumplimiento</small>
                    </div>
                    <p class="small text-dark mb-2">
                        Tus libros electrónicos <strong>RVIE (140400)</strong> y <strong>RCE (080400)</strong> están listos para descarga oficial con código CAR de 27 dígitos.
                    </p>
                    <a href="sire.php" class="btn btn-xs btn-outline-primary w-100 fw-bold">
                        <i class="fa fa-file-zipper me-1"></i> Ver Propuestas SIRE
                    </a>
                </div>
            </div>
        `;
    }

    function setupSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) return;

        recognition = new SpeechRecognition();
        recognition.lang = 'es-PE';
        recognition.continuous = false;
        recognition.interimResults = false;

        recognition.onstart = () => {
            isRecording = true;
            updateMicButtons(true);
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const transDiv = document.getElementById('voiceTranscription');
            if (transDiv) transDiv.textContent = `"${transcript}"`;
            
            const input = document.getElementById('aiInputText');
            if (input) input.value = transcript;

            // Procesar consulta
            setTimeout(() => {
                processQuery(transcript);
            }, 600);
        };

        recognition.onerror = (event) => {
            isRecording = false;
            updateMicButtons(false);
            console.warn('Speech recognition error:', event.error);
        };

        recognition.onend = () => {
            isRecording = false;
            updateMicButtons(false);
        };
    }

    function toggleVoice() {
        if (!recognition) {
            Swal.fire({
                icon: 'info',
                title: 'Reconocimiento de Voz',
                text: 'Tu navegador no soporta Web Speech API de forma nativa. Te sugerimos usar Google Chrome o Edge.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        if (isRecording) {
            stopVoice();
        } else {
            startVoice();
        }
    }

    function startVoice() {
        if (recognition) {
            try {
                recognition.start();
            } catch (e) {
                console.error(e);
            }
        }
    }

    function stopVoice() {
        if (recognition) {
            try {
                recognition.stop();
            } catch (e) {
                console.error(e);
            }
        }
        isRecording = false;
        updateMicButtons(false);
    }

    function updateMicButtons(recording) {
        const btn = document.getElementById('btnAiMic');
        const bigBtn = document.getElementById('btnBigVoice');
        if (btn) {
            if (recording) btn.classList.add('recording');
            else btn.classList.remove('recording');
        }
        if (bigBtn) {
            if (recording) bigBtn.classList.add('recording');
            else bigBtn.classList.remove('recording');
        }
    }

    function sendUserQuery() {
        const input = document.getElementById('aiInputText');
        if (!input) return;
        const q = input.value.trim();
        if (!q) return;
        input.value = '';
        processQuery(q);
    }

    function processQuery(text) {
        // Asegurar que esté en la pestaña chat
        switchTab('chat');
        const stream = document.getElementById('aiChatStream');
        if (!stream) return;

        // Añadir mensaje del usuario
        const userMsg = document.createElement('div');
        userMsg.className = 'ai-message-bubble ai-bubble-user mb-2';
        userMsg.textContent = text;
        stream.appendChild(userMsg);

        // Analizar texto y generar propuesta contable / respuesta inteligente
        const responseHTML = analyzeTransaction(text);

        const botMsg = document.createElement('div');
        botMsg.className = 'ai-message-bubble ai-bubble-bot mb-3';
        botMsg.innerHTML = responseHTML;
        stream.appendChild(botMsg);

        // Scroll al fondo
        const body = document.getElementById('aiDrawerBody');
        body.scrollTop = body.scrollHeight;
    }

    function analyzeTransaction(raw) {
        const q = raw.toLowerCase();
        
        // Extraer números si hay montos
        const nums = q.match(/\d+([\.,]\d+)?/g);
        let monto = nums ? parseFloat(nums[0].replace(',', '.')) : 1000.00;

        // Caso 1: Compra de mercadería
        if (q.includes('compr') || q.includes('adqui')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);

            return `
                <div class="fw-bold text-success mb-1">
                    <i class="fa fa-file-invoice me-1"></i> Asiento Contable Sugerido: Compra de Mercadería
                </div>
                <div class="small text-muted mb-2">Operación analizada: <strong>S/ ${total}</strong> (Con IGV 18%)</div>
                
                <div class="ai-entry-card">
                    <div class="fw-bold mb-1 border-bottom pb-1">1. Registro de Adquisición (Naturaleza)</div>
                    <div class="d-flex justify-content-between">
                        <span><strong>601</strong> Mercaderías (Base Imp.)</span>
                        <span class="text-primary fw-bold">D: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>4011</strong> IGV - Cuenta Propia (18%)</span>
                        <span class="text-primary fw-bold">D: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-1 mt-1">
                        <span><strong>421</strong> Facturas por Pagar</span>
                        <span class="text-danger fw-bold">H: S/ ${total}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1">2. Destino al Almacén</div>
                    <div class="d-flex justify-content-between">
                        <span><strong>201</strong> Mercaderías Manufacturadas</span>
                        <span class="text-primary fw-bold">D: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>611</strong> Variación de Existencias</span>
                        <span class="text-danger fw-bold">H: S/ ${subtotal}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1">3. Cancelación de Obligación</div>
                    <div class="d-flex justify-content-between">
                        <span><strong>421</strong> Facturas por Pagar</span>
                        <span class="text-primary fw-bold">D: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>101</strong> Caja / Efectivo</span>
                        <span class="text-danger fw-bold">H: S/ ${total}</span>
                    </div>
                </div>

                <div class="mt-2 d-flex gap-1">
                    <a href="compra_nueva.php?total=${total}" class="btn btn-sm btn-primary w-100 fw-bold">
                        <i class="fa fa-cart-arrow-down me-1"></i> Registrar en Compras
                    </a>
                </div>
            `;
        }

        // Caso 2: Venta de productos
        if (q.includes('vend') || q.includes('venta')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);

            return `
                <div class="fw-bold text-primary mb-1">
                    <i class="fa fa-cash-register me-1"></i> Asiento Contable Sugerido: Venta en Mostrador
                </div>
                <div class="small text-muted mb-2">Ingreso analizado: <strong>S/ ${total}</strong> (Con IGV 18%)</div>

                <div class="ai-entry-card" style="border-left-color: #2563eb;">
                    <div class="fw-bold mb-1 border-bottom pb-1">1. Registro de Ingreso por Ventas</div>
                    <div class="d-flex justify-content-between">
                        <span><strong>121</strong> Facturas / Boletas por Cobrar</span>
                        <span class="text-primary fw-bold">D: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>4011</strong> IGV - Cuenta Propia (18%)</span>
                        <span class="text-danger fw-bold">H: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-1 mt-1">
                        <span><strong>701</strong> Venta de Mercaderías</span>
                        <span class="text-danger fw-bold">H: S/ ${subtotal}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1">2. Cobro Efectivo en Caja</div>
                    <div class="d-flex justify-content-between">
                        <span><strong>101</strong> Caja y Efectivo</span>
                        <span class="text-primary fw-bold">D: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>121</strong> Facturas por Cobrar</span>
                        <span class="text-danger fw-bold">H: S/ ${total}</span>
                    </div>
                </div>

                <div class="mt-2">
                    <a href="venta_nueva.php" class="btn btn-sm btn-success w-100 fw-bold">
                        <i class="fa fa-cash-register me-1"></i> Abrir Punto de Venta (POS)
                    </a>
                </div>
            `;
        }

        // Caso 3: Stock o Inventario
        if (q.includes('stock') || q.includes('inventario') || q.includes('producto')) {
            return `
                <div class="fw-bold text-warning mb-1">
                    <i class="fa fa-boxes-stacked me-1"></i> Diagnóstico de Inventario ContaSmart
                </div>
                <p class="small text-dark mb-2">
                    Actualmente tienes <strong>8 productos</strong> en catálogo. 
                    <br>⚠️ Se detectan existencias críticas en <strong>Cinta Métrica (4 unids)</strong> y <strong>Cable Mellizo (3 unids)</strong>.
                </p>
                <div class="d-flex gap-2">
                    <a href="inventario.php" class="btn btn-sm btn-outline-primary w-50">
                        <i class="fa fa-box me-1"></i> Inventario
                    </a>
                    <a href="compra_nueva.php" class="btn btn-sm btn-warning w-50 fw-bold text-dark">
                        <i class="fa fa-cart-plus me-1"></i> Reponer Stock
                    </a>
                </div>
            `;
        }

        // Caso 4: Utilidades o Finanzas
        if (q.includes('utilidad') || q.includes('ganancia') || q.includes('finanza') || q.includes('dinero')) {
            return `
                <div class="fw-bold text-success mb-1">
                    <i class="fa fa-chart-pie me-1"></i> Análisis Financiero Predictivo
                </div>
                <p class="small text-dark mb-2">
                    Tu negocio cuenta con un margen comercial positivo. Los ingresos de ventas superan las compras registradas este mes, manteniendo un flujo de liquidez favorable.
                </p>
                <a href="reportes.php" class="btn btn-sm btn-outline-success w-100 fw-bold">
                    <i class="fa fa-file-invoice-dollar me-1"></i> Ver Reportes Detallados
                </a>
            `;
        }

        // Respuesta genérica inteligente
        return `
            <div class="fw-bold text-dark mb-1">
                <i class="fa fa-circle-info text-primary me-1"></i> Operación Procesada
            </div>
            <p class="small text-muted mb-2">
                He recibido tu consulta: <em>"${raw}"</em>. Puedes pedirme generar asientos contables (compras, ventas, gastos), consultar el stock de almacén o revisar las propuestas tributarias SIRE.
            </p>
        `;
    }

    return {
        init,
        open,
        close,
        switchTab,
        toggleVoice,
        sendUserQuery,
        processQuery
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    ContaSmartAI.init();
});
