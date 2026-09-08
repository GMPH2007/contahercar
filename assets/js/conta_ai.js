/**
 * CONTA SMART AI & CONTA VOZ SIRI PRO
 * Asistente Inteligente de Voz, Consultas en Tiempo Real y Generador de Asientos PCGE
 * Compatible con: Móvil, Tablet y Pantalla TV
 * Autor: Gerson Misael Pintado Huaman (GMPH2007)
 */

const ContaSmartAI = (() => {
    let drawer = null;
    let backdrop = null;
    let isRecording = false;
    let isSpeaking = false;
    let isVoiceMuted = false;
    let recognition = null;
    let currentTab = 'chat';

    // Generador de sonidos estilo Siri con Web Audio API (Chimes sintéticos nativos)
    function playSiriTone(type = 'start') {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            const now = ctx.currentTime;
            if (type === 'start') {
                // Tono dual armónico ascendente de activación (estilo Siri / Apple Intelligence)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, now);
                osc.frequency.exponentialRampToValueAtTime(784, now + 0.12);
                gain.gain.setValueAtTime(0.12, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.24);
                osc.start(now);
                osc.stop(now + 0.25);
            } else if (type === 'success') {
                // Tono de éxito / resolución lista
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(523.25, now); // Do
                osc.frequency.exponentialRampToValueAtTime(880, now + 0.15); // La
                gain.gain.setValueAtTime(0.14, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);
                osc.start(now);
                osc.stop(now + 0.29);
            }
        } catch (e) {
            // Ignorar políticas de audio en caso de restricción del navegador
        }
    }

    // Síntesis de voz hablada en español (Siri Voice TTS)
    function speakText(text) {
        if (isVoiceMuted || !('speechSynthesis' in window)) return;
        try {
            window.speechSynthesis.cancel();
            // Limpiar etiquetas HTML y emojis para lectura fluida
            const plain = text.replace(/<[^>]*>/g, ' ').replace(/[🤖👋📦🛒⚠️💡⚡🎙️📊]/g, '').replace(/\s+/g, ' ').trim();
            if (!plain) return;

            const utt = new SpeechSynthesisUtterance(plain);
            utt.lang = 'es-PE';
            utt.rate = 1.05;
            utt.pitch = 1.0;

            utt.onstart = () => {
                isSpeaking = true;
                updateWaveVisualizer(true);
            };
            utt.onend = () => {
                isSpeaking = false;
                updateWaveVisualizer(false);
            };
            utt.onerror = () => {
                isSpeaking = false;
                updateWaveVisualizer(false);
            };

            window.speechSynthesis.speak(utt);
        } catch (e) {
            console.warn('Speech synthesis not available:', e);
        }
    }

    function init() {
        createDrawer();
        setupSpeechRecognition();

        // Botones globales para abrir el asistente
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
            <!-- Cabecera Asistente Siri -->
            <div class="ai-drawer-header d-flex align-items-center justify-content-between p-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="siri-orb-mini" id="siriHeaderOrb">
                        <i class="fa fa-sparkles text-white"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold text-white">ContaSmart Siri IA</h6>
                            <span class="badge bg-info text-dark" style="font-size: 0.65rem;">v3.0</span>
                        </div>
                        <small class="text-info opacity-75" style="font-size: 0.72rem;">Voz Activa & Asesor Contable</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light p-1 px-2" id="btnToggleSpeechMute" onclick="ContaSmartAI.toggleSpeechMute()" title="Silenciar / Activar voz de Siri">
                        <i class="fa fa-volume-high" id="iconSpeechMute"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white" onclick="ContaSmartAI.close()" title="Cerrar (Esc)"></button>
                </div>
            </div>

            <!-- Selector de Modos Pestañas -->
            <div class="bg-white border-bottom px-3 py-2">
                <ul class="nav nav-pills nav-fill" id="aiModesTabs" style="gap: 4px;">
                    <li class="nav-item">
                        <button class="nav-link active py-1 px-2 small" onclick="ContaSmartAI.switchTab('chat')">
                            <i class="fa fa-comments me-1"></i> Siri Asistente
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2 small" onclick="ContaSmartAI.switchTab('voice')">
                            <i class="fa fa-microphone me-1 text-danger"></i> Onda ContaVoz
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2 small" onclick="ContaSmartAI.switchTab('alerts')">
                            <i class="fa fa-bell me-1 text-warning"></i> ContaAlerta
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Cuerpo Dinámico -->
            <div class="ai-drawer-body" id="aiDrawerBody">
                <!-- Se renderiza según pestaña -->
            </div>

            <!-- Footer con Barra de Entrada & Micrófono -->
            <div class="ai-drawer-footer p-3 bg-white border-top" id="aiDrawerFooter">
                <div class="input-group">
                    <button class="btn btn-danger btn-mic-pulse" id="btnAiMic" onclick="ContaSmartAI.toggleVoice()" title="Dictar por voz (ContaVoz Siri)">
                        <i class="fa fa-microphone"></i>
                    </button>
                    <input type="text" id="aiInputText" class="form-control" placeholder="Escribe o dicta: '¿cuánto vendí hoy?', 'stock bajo'..." onkeydown="if(event.key==='Enter') ContaSmartAI.sendUserQuery()">
                    <button class="btn btn-primary fw-bold" onclick="ContaSmartAI.sendUserQuery()" title="Enviar">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <small class="text-muted" style="font-size: 0.72rem;">
                        <i class="fa fa-headset me-1 text-primary"></i> Dicta con tu voz o escribe una operación
                    </small>
                    <small class="text-secondary" style="font-size: 0.7rem;">
                        PCGE 2024 • SIRE SUNAT
                    </small>
                </div>
            </div>
        `;

        document.body.appendChild(drawer);
        renderChatView();
    }

    function open() {
        if (typeof ContaSmartTour !== 'undefined' && ContaSmartTour.endTour) {
            ContaSmartTour.endTour();
        }
        createDrawer();
        backdrop.classList.add('active');
        drawer.classList.add('active');
        playSiriTone('start');
        setTimeout(() => {
            const input = document.getElementById('aiInputText');
            if (input) input.focus();
        }, 300);
    }

    function close() {
        if (backdrop) backdrop.classList.remove('active');
        if (drawer) drawer.classList.remove('active');
        if (isRecording) stopVoice();
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    }

    function toggleSpeechMute() {
        isVoiceMuted = !isVoiceMuted;
        const icon = document.getElementById('iconSpeechMute');
        if (icon) {
            icon.className = isVoiceMuted ? 'fa fa-volume-xmark text-danger' : 'fa fa-volume-high text-white';
        }
        if (isVoiceMuted && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    }

    function switchTab(tab) {
        currentTab = tab;
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
            <div class="ai-message-bubble ai-bubble-bot mb-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary px-2">SIRI CONTA SMART</span>
                    <small class="text-muted">En línea</small>
                </div>
                <strong>¡Hola! Soy Siri ContaSmart 🤖</strong><br>
                Puedo responderte sobre tus <strong>ventas en tiempo real</strong>, alertarte sobre <strong>stock bajo</strong>, generar <strong>asientos contables del PCGE (Debe/Haber)</strong> o consultar <strong>RUC / DNI en SUNAT</strong>.
            </div>

            <!-- Chips de Consulta Rápida -->
            <div class="small fw-semibold text-muted mb-1"><i class="fa fa-bolt text-warning me-1"></i>Consultas Rápidas con 1 Toque:</div>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <button class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.78rem;" onclick="ContaSmartAI.processQuery('¿Cuánto vendí hoy?')">
                    📊 Ventas de Hoy
                </button>
                <button class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.78rem;" onclick="ContaSmartAI.processQuery('¿Cuáles son mis productos con stock bajo?')">
                    ⚠️ Stock Crítico
                </button>
                <button class="btn btn-sm btn-outline-success py-1 px-2" style="font-size: 0.78rem;" onclick="ContaSmartAI.processQuery('Compré mercadería por S/ 1,500 en efectivo')">
                    📦 Compra mercadería S/ 1,500
                </button>
                <button class="btn btn-sm btn-outline-info py-1 px-2" style="font-size: 0.78rem;" onclick="ContaSmartAI.processQuery('Vendí productos por S/ 850 al contado con Factura')">
                    🛒 Venta S/ 850 Factura
                </button>
                <button class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.78rem;" onclick="ContaSmartAI.processQuery('Consultar RUC 20100070970')">
                    🔍 Consultar RUC 20100070970
                </button>
            </div>

            <!-- Flujo de Conversación -->
            <div id="aiChatStream"></div>
        `;
    }

    function renderVoiceView() {
        const body = document.getElementById('aiDrawerBody');
        body.innerHTML = `
            <div class="text-center py-4 px-2">
                <!-- Siri Visualizer Orb & Sound Wave -->
                <div class="siri-voice-center-wrap mb-3">
                    <div class="siri-large-orb ${isRecording ? 'pulse-active' : ''}" id="siriLargeOrb" onclick="ContaSmartAI.toggleVoice()">
                        <i class="fa fa-microphone fa-2x text-white"></i>
                    </div>

                    <!-- Ondas de Audio Siri -->
                    <div class="siri-wave-bars ${isRecording || isSpeaking ? 'active' : ''}" id="siriWaveBars">
                        <span class="bar bar-1"></span>
                        <span class="bar bar-2"></span>
                        <span class="bar bar-3"></span>
                        <span class="bar bar-4"></span>
                        <span class="bar bar-5"></span>
                        <span class="bar bar-6"></span>
                        <span class="bar bar-7"></span>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-1">🎙️ ContaVoz Siri Inteligente</h5>
                <p class="text-muted small px-3 mb-3" id="voiceStatusLabel">
                    ${isRecording ? '<strong class="text-danger"><i class="fa fa-circle text-danger me-1 blink"></i>Escuchando tu voz... Habla ahora.</strong>' : 'Toca el círculo o el micrófono inferior para dictar.'}
                </p>

                <!-- Tarjeta con ejemplos guiados -->
                <div class="p-3 bg-light rounded-4 text-start border shadow-sm mx-auto" style="max-width: 360px;">
                    <span class="small fw-bold text-primary d-flex align-items-center gap-1 mb-2">
                        <i class="fa fa-wand-magic-sparkles"></i> Puedes ordenar por ejemplo:
                    </span>
                    <ul class="small text-muted mb-0 ps-3 lh-base">
                        <li><em>"¿Cuánto vendí hoy?"</em></li>
                        <li><em>"Compré repuestos por 1,200 soles en efectivo"</em></li>
                        <li><em>"Dime qué productos tienen stock bajo"</em></li>
                        <li><em>"Consultar RUC 20100070970"</em></li>
                        <li><em>"Generar propuesta SIRE del mes"</em></li>
                    </ul>
                </div>

                <div id="voiceTranscription" class="mt-3 p-2 font-monospace text-primary fw-bold" style="min-height: 28px;"></div>
            </div>
        `;
    }

    function renderAlertsView() {
        const body = document.getElementById('aiDrawerBody');
        body.innerHTML = `
            <div class="mb-3 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="fa fa-brain text-primary me-2"></i>CONTA ALERTA Predictivo</h6>
                    <small class="text-muted">Diagnóstico en tiempo real sincronizado con tu base de datos</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="ContaSmartAI.loadRealAlerts()" title="Refrescar diagnóstico">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div id="alertsContainer">
                <div class="text-center py-4 text-muted">
                    <i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <p class="small mb-0">Cargando diagnóstico en tiempo real...</p>
                </div>
            </div>
        `;
        loadRealAlerts();
    }

    function loadRealAlerts() {
        const container = document.getElementById('alertsContainer');
        if (!container) return;

        fetch('api/bot_query.php?tipo=resumen_general')
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Error');

                let html = '';
                // 1. Alerta de Stock
                if (data.stock_bajo && data.stock_bajo.total_criticos > 0) {
                    html += `
                        <div class="card border-warning mb-3 shadow-sm rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-warning text-dark"><i class="fa fa-triangle-exclamation me-1"></i>Stock Crítico (${data.stock_bajo.total_criticos} items)</span>
                                    <small class="text-muted">Almacén</small>
                                </div>
                                <p class="small text-dark mb-2">
                                    Los siguientes productos requieren reposición inmediata:
                                </p>
                                <ul class="small mb-2 ps-3 text-dark">
                                    ${data.stock_bajo.items.map(it => `<li><strong>${it.nombre}</strong>: Quedan <span class="text-danger fw-bold">${it.stock}</span> (Mínimo: ${it.stock_minimo})</li>`).join('')}
                                </ul>
                                <a href="compra_nueva.php" class="btn btn-sm btn-warning w-100 fw-bold text-dark">
                                    <i class="fa fa-cart-plus me-1"></i> Generar Orden de Reposición en Compras
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="card border-success mb-3 shadow-sm rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 text-success mb-1">
                                    <i class="fa fa-shield-check fs-5"></i>
                                    <strong>Inventario en Estado Óptimo</strong>
                                </div>
                                <p class="small text-muted mb-0">No se registran roturas de stock ni faltantes en los ${data.total_productos} productos del catálogo.</p>
                            </div>
                        </div>
                    `;
                }

                // 2. Alerta de Flujo Financiero
                html += `
                    <div class="card border-info mb-3 shadow-sm rounded-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-info text-white"><i class="fa fa-sack-dollar me-1"></i>Utilidad Bruta del Periodo</span>
                                <small class="text-muted">Finanzas</small>
                            </div>
                            <h5 class="fw-bold text-success mb-1">${data.utilidad_mes.total_formateado}</h5>
                            <p class="small text-muted mb-2">
                                Ventas del mes: <strong>${data.ventas_mes.total_formateado}</strong> (${data.ventas_mes.cantidad} tickets) vs Compras: <strong>${data.compras_mes.total_formateado}</strong>.
                            </p>
                            <a href="reportes.php" class="btn btn-sm btn-outline-info w-100 fw-semibold">
                                <i class="fa fa-chart-line me-1"></i> Ver Reporte Financiero Completo
                            </a>
                        </div>
                    </div>
                `;

                // 3. Cumplimiento SIRE SUNAT
                html += `
                    <div class="card border-primary mb-3 shadow-sm rounded-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-primary"><i class="fa fa-book-bookmark me-1"></i>SIRE SUNAT</span>
                                <small class="text-muted">Cumplimiento</small>
                            </div>
                            <p class="small text-dark mb-2">
                                Propuestas RVIE y RCE listas para descarga de archivos planos con código CAR oficial de 27 dígitos.
                            </p>
                            <a href="sire.php" class="btn btn-sm btn-primary w-100 fw-bold">
                                <i class="fa fa-file-zipper me-1"></i> Gestionar Libros SIRE
                            </a>
                        </div>
                    </div>
                `;

                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = `<div class="alert alert-danger small p-2">Error al conectar con la base de datos: ${err.message}</div>`;
            });
    }

    function setupSpeechRecognition() {
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRec) return;

        recognition = new SpeechRec();
        recognition.lang = 'es-PE';
        recognition.continuous = false;
        recognition.interimResults = false;

        recognition.onstart = () => {
            isRecording = true;
            playSiriTone('start');
            updateMicState(true);
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const transDiv = document.getElementById('voiceTranscription');
            if (transDiv) transDiv.textContent = `"${transcript}"`;

            const input = document.getElementById('aiInputText');
            if (input) input.value = transcript;

            // Procesar consulta y dar respuesta
            setTimeout(() => {
                processQuery(transcript);
            }, 500);
        };

        recognition.onerror = (event) => {
            isRecording = false;
            updateMicState(false);
            console.warn('Siri speech error:', event.error);
        };

        recognition.onend = () => {
            isRecording = false;
            updateMicState(false);
        };
    }

    function toggleVoice() {
        if (!recognition) {
            Swal.fire({
                icon: 'info',
                title: 'Reconocimiento de Voz ContaVoz',
                text: 'Te recomendamos utilizar Google Chrome o Microsoft Edge para disfrutar de los comandos de voz fluidos.',
                confirmButtonColor: '#2563eb'
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
                console.warn(e);
            }
        }
    }

    function stopVoice() {
        if (recognition) {
            try {
                recognition.stop();
            } catch (e) {
                console.warn(e);
            }
        }
        isRecording = false;
        updateMicState(false);
    }

    function updateMicState(active) {
        const btn = document.getElementById('btnAiMic');
        const bigOrb = document.getElementById('siriLargeOrb');
        const label = document.getElementById('voiceStatusLabel');
        const wave = document.getElementById('siriWaveBars');

        if (btn) {
            if (active) btn.classList.add('recording');
            else btn.classList.remove('recording');
        }
        if (bigOrb) {
            if (active) bigOrb.classList.add('pulse-active');
            else bigOrb.classList.remove('pulse-active');
        }
        if (wave) {
            if (active || isSpeaking) wave.classList.add('active');
            else wave.classList.remove('active');
        }
        if (label) {
            label.innerHTML = active ? 
                '<strong class="text-danger"><i class="fa fa-circle text-danger me-1 blink"></i>Escuchando tu voz... Habla ahora.</strong>' : 
                'Toca el micrófono para dictar con tu voz.';
        }
    }

    function updateWaveVisualizer(active) {
        const wave = document.getElementById('siriWaveBars');
        const headerOrb = document.getElementById('siriHeaderOrb');
        if (wave) {
            if (active || isRecording) wave.classList.add('active');
            else wave.classList.remove('active');
        }
        if (headerOrb) {
            if (active) headerOrb.classList.add('speaking');
            else headerOrb.classList.remove('speaking');
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
        if (currentTab !== 'chat') {
            switchTab('chat');
        }

        const stream = document.getElementById('aiChatStream');
        if (!stream) return;

        // Añadir burbuja de mensaje del usuario
        const userBubble = document.createElement('div');
        userBubble.className = 'ai-message-bubble ai-bubble-user mb-2';
        userBubble.textContent = text;
        stream.appendChild(userBubble);

        // Indicador de "Siri pensando..."
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'ai-message-bubble ai-bubble-bot mb-2 typing-indicator-box';
        typingIndicator.id = 'aiTypingIndicator';
        typingIndicator.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm text-primary"></span>
                <span class="small text-muted">Siri analizando operación...</span>
            </div>
        `;
        stream.appendChild(typingIndicator);

        const body = document.getElementById('aiDrawerBody');
        body.scrollTop = body.scrollHeight;

        // Evaluar la consulta (soporta APIs en tiempo real y PCGE)
        resolveQueryResponse(text)
            .then(result => {
                // Remover typing indicator
                const ind = document.getElementById('aiTypingIndicator');
                if (ind) ind.remove();

                // Crear burbuja de respuesta del bot
                const botBubble = document.createElement('div');
                botBubble.className = 'ai-message-bubble ai-bubble-bot mb-3';
                botBubble.innerHTML = result.html;
                stream.appendChild(botBubble);

                playSiriTone('success');
                body.scrollTop = body.scrollHeight;

                // Siri habla la respuesta por voz
                if (result.voiceText) {
                    speakText(result.voiceText);
                }
            })
            .catch(err => {
                const ind = document.getElementById('aiTypingIndicator');
                if (ind) ind.remove();

                const botBubble = document.createElement('div');
                botBubble.className = 'ai-message-bubble ai-bubble-bot mb-3';
                botBubble.innerHTML = `<div class="text-danger small"><i class="fa fa-circle-exclamation me-1"></i>${err.message}</div>`;
                stream.appendChild(botBubble);
            });
    }

    async function resolveQueryResponse(raw) {
        const q = raw.toLowerCase().trim();

        // 1. Consulta RUC (11 dígitos o comando 'ruc')
        const rucMatch = q.match(/\b(10|20)\d{9}\b/) || (q.includes('ruc') ? q.match(/\d{11}/) : null);
        if (rucMatch) {
            const rucNum = rucMatch[0];
            try {
                const r = await fetch(`api/consulta_ruc.php?numero=${rucNum}`);
                const data = await r.json();
                if (data.success) {
                    const voice = `RUC ${rucNum} encontrado: ${data.nombre}. Su condición es ${data.condicion} y estado ${data.estado}.`;
                    const html = `
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-success"><i class="fa fa-building-flag me-1"></i>SUNAT RUC Oficial</span>
                            <span class="badge bg-light text-dark border">${data.estado}</span>
                        </div>
                        <h6 class="fw-bold text-primary mb-1">${data.nombre}</h6>
                        <div class="small text-muted mb-2">
                            <div><strong>RUC:</strong> ${data.ruc} | <strong>Condición:</strong> <span class="text-success fw-bold">${data.condicion}</span></div>
                            <div><strong>Dirección:</strong> ${data.direccion || 'No registrada'}</div>
                            <div><strong>Ubigeo:</strong> ${data.distrito || ''} - ${data.provincia || ''} - ${data.departamento || ''}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="clientes.php?action=nuevo&num_doc=${data.ruc}&nombre=${encodeURIComponent(data.nombre)}&direccion=${encodeURIComponent(data.direccion)}" class="btn btn-sm btn-primary w-50 fw-bold">
                                <i class="fa fa-user-plus me-1"></i> Guardar Cliente
                            </a>
                            <a href="proveedores.php?action=nuevo&num_doc=${data.ruc}&nombre=${encodeURIComponent(data.nombre)}&direccion=${encodeURIComponent(data.direccion)}" class="btn btn-sm btn-outline-secondary w-50 fw-bold">
                                <i class="fa fa-truck-moving me-1"></i> Guardar Proveedor
                            </a>
                        </div>
                    `;
                    return { html, voiceText: voice };
                }
            } catch (e) {
                // Continuar a otros analizadores si falla
            }
        }

        // 2. Consulta DNI (8 dígitos o comando 'dni')
        const dniMatch = q.match(/\b\d{8}\b/);
        if (dniMatch && (q.includes('dni') || q.includes('persona') || q.includes('cliente'))) {
            const dniNum = dniMatch[0];
            try {
                const r = await fetch(`api/buscar_por_doc.php?tipo=DNI&numero=${dniNum}`);
                const data = await r.json();
                if (data.success) {
                    const voice = `DNI ${dniNum} verificado en RENIEC: ${data.nombre}.`;
                    const html = `
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-info text-white"><i class="fa fa-id-card me-1"></i>RENIEC Oficial</span>
                            <small class="text-muted">${data.numero}</small>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">${data.nombre}</h6>
                        <p class="small text-muted mb-2">Documento validado con el padrón oficial nacional.</p>
                        <a href="clientes.php?action=nuevo&num_doc=${data.numero}&nombre=${encodeURIComponent(data.nombre)}" class="btn btn-sm btn-primary w-100 fw-bold">
                            <i class="fa fa-user-plus me-1"></i> Registrar como Cliente
                        </a>
                    `;
                    return { html, voiceText: voice };
                }
            } catch (e) {
                // Continuar
            }
        }

        // 3. Ventas de Hoy o Resumen Financiero en Vivo
        if (q.includes('cuánto vendí') || q.includes('cuanto vendi') || q.includes('ventas hoy') || q.includes('ventas de hoy') || q.includes('ingresos hoy')) {
            try {
                const r = await fetch('api/bot_query.php?tipo=resumen_general');
                const data = await r.json();
                if (data.success) {
                    const voice = `Hoy tienes ${data.ventas_hoy.total_formateado} en ventas completadas. En el mes acumulas ${data.ventas_mes.total_formateado} con una utilidad bruta estimada de ${data.utilidad_mes.total_formateado}.`;
                    const html = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary"><i class="fa fa-cash-register me-1"></i>Ventas en Vivo</span>
                            <small class="text-muted">Al corte de hoy</small>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center border">
                                    <small class="text-muted d-block">Ventas Hoy</small>
                                    <strong class="fs-6 text-primary">${data.ventas_hoy.total_formateado}</strong>
                                    <div class="small text-muted" style="font-size: 0.7rem;">${data.ventas_hoy.cantidad} tickets</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center border">
                                    <small class="text-muted d-block">Ventas Mes</small>
                                    <strong class="fs-6 text-success">${data.ventas_mes.total_formateado}</strong>
                                    <div class="small text-muted" style="font-size: 0.7rem;">${data.ventas_mes.cantidad} tickets</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-2 bg-success bg-opacity-10 border border-success border-opacity-25 rounded mb-2 text-center">
                            <small class="text-success fw-bold d-block">Utilidad Bruta Real Estimada:</small>
                            <span class="fs-5 fw-bold text-success">${data.utilidad_mes.total_formateado}</span>
                        </div>
                        <a href="venta_nueva.php" class="btn btn-sm btn-primary w-100 fw-bold">
                            <i class="fa fa-plus-circle me-1"></i> Abrir Punto de Venta (POS)
                        </a>
                    `;
                    return { html, voiceText: voice };
                }
            } catch (e) {
                // Continuar
            }
        }

        // 4. Stock Crítico o Almacén en Vivo
        if (q.includes('stock bajo') || q.includes('qué falta') || q.includes('que falta') || q.includes('agotado') || q.includes('inventario crítico') || q.includes('almacén') || q.includes('almacen')) {
            try {
                const r = await fetch('api/bot_query.php?tipo=stock_bajo');
                const data = await r.json();
                if (data.success) {
                    const count = data.total;
                    let voice = count > 0 ? 
                        `Se detectaron ${count} productos con existencias por debajo del mínimo.` : 
                        `Tu inventario está en regla, no hay productos con stock crítico.`;

                    let html = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge ${count > 0 ? 'bg-warning text-dark' : 'bg-success text-white'}">
                                <i class="fa fa-boxes-stacked me-1"></i>Estado de Almacén
                            </span>
                            <small class="text-muted">${count} críticos</small>
                        </div>
                    `;

                    if (count > 0) {
                        html += `
                            <p class="small text-dark mb-2">Productos que requieren reposición:</p>
                            <ul class="small ps-3 mb-3 text-dark">
                                ${data.items.slice(0, 5).map(it => `
                                    <li class="mb-1">
                                        <strong>${it.nombre}</strong><br>
                                        <span class="text-danger fw-bold">Stock actual: ${it.stock}</span> (Mínimo: ${it.stock_minimo})
                                    </li>
                                `).join('')}
                            </ul>
                            <a href="compra_nueva.php" class="btn btn-sm btn-warning w-100 fw-bold text-dark">
                                <i class="fa fa-cart-plus me-1"></i> Crear Orden de Compra
                            </a>
                        `;
                    } else {
                        html += `
                            <div class="p-3 bg-success bg-opacity-10 text-success rounded text-center mb-2">
                                <i class="fa fa-circle-check fs-4 mb-1"></i>
                                <div class="fw-bold">Inventario Saludable</div>
                                <small class="text-muted">Todos los productos tienen existencias suficientes.</small>
                            </div>
                        `;
                    }
                    return { html, voiceText: voice };
                }
            } catch (e) {
                // Continuar
            }
        }

        // 5. Asientos Contables PCGE: Compra de Mercadería / Gastos
        const nums = q.match(/\d+([\.,]\d+)?/g);
        let monto = nums ? parseFloat(nums[0].replace(',', '.')) : 1000.00;

        if (q.includes('compr') || q.includes('adqui') || q.includes('gasto') || q.includes('pago')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);
            const voice = `He estructurado el asiento contable de compra por ${total} soles según el Plan Contable General Empresarial, con cargo a la cuenta 601 y 4011, y abono a la 421.`;

            const html = `
                <div class="fw-bold text-success mb-1">
                    <i class="fa fa-file-invoice me-1"></i> Asiento Contable PCGE: Compra de Mercaderías
                </div>
                <div class="small text-muted mb-2">Operación analizada: <strong>S/ ${total}</strong> (Base: S/ ${subtotal} + IGV 18%: S/ ${igv})</div>

                <div class="ai-entry-card">
                    <div class="fw-bold mb-1 border-bottom pb-1 text-primary">1. Provisión por Naturaleza</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>6011</strong> Mercaderías manufacturadas</span>
                        <span class="text-primary fw-bold">Debe: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>40111</strong> IGV - Cuenta propia (18%)</span>
                        <span class="text-primary fw-bold">Debe: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-top pt-1 mt-1">
                        <span><strong>4212</strong> Emitidas en cartera</span>
                        <span class="text-danger fw-bold">Haber: S/ ${total}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1 text-primary">2. Destino al Almacén</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>20111</strong> Mercaderías manufacturadas</span>
                        <span class="text-primary fw-bold">Debe: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>6111</strong> Variación de mercaderías</span>
                        <span class="text-danger fw-bold">Haber: S/ ${subtotal}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1 text-primary">3. Cancelación de Obligación</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>4212</strong> Facturas por pagar</span>
                        <span class="text-primary fw-bold">Debe: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>101</strong> Caja / Fondos fijos</span>
                        <span class="text-danger fw-bold">Haber: S/ ${total}</span>
                    </div>
                </div>

                <div class="mt-2">
                    <a href="compra_nueva.php?total=${total}" class="btn btn-sm btn-primary w-100 fw-bold">
                        <i class="fa fa-cart-arrow-down me-1"></i> Registrar Compra en el Sistema
                    </a>
                </div>
            `;
            return { html, voiceText: voice };
        }

        // 6. Asientos Contables PCGE: Ventas / Mostrador
        if (q.includes('vend') || q.includes('venta') || q.includes('factur')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);
            const voice = `He generado el asiento contable de venta por ${total} soles, con cargo a la cuenta 121 y abono a la 4011 de IGV y 701 de ingresos comerciales.`;

            const html = `
                <div class="fw-bold text-primary mb-1">
                    <i class="fa fa-cash-register me-1"></i> Asiento Contable PCGE: Venta de Mercaderías
                </div>
                <div class="small text-muted mb-2">Operación analizada: <strong>S/ ${total}</strong> (Base: S/ ${subtotal} + IGV 18%: S/ ${igv})</div>

                <div class="ai-entry-card" style="border-left-color: #2563eb;">
                    <div class="fw-bold mb-1 border-bottom pb-1 text-primary">1. Reconocimiento de Ingreso</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>1212</strong> Emitidas en cartera</span>
                        <span class="text-primary fw-bold">Debe: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>40111</strong> IGV - Cuenta propia (18%)</span>
                        <span class="text-danger fw-bold">Haber: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-top pt-1 mt-1">
                        <span><strong>70111</strong> Venta mercaderías</span>
                        <span class="text-danger fw-bold">Haber: S/ ${subtotal}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1 text-primary">2. Cobranza en Caja Efectivo</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>101</strong> Caja y fondos fijos</span>
                        <span class="text-primary fw-bold">Debe: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>1212</strong> Facturas por cobrar</span>
                        <span class="text-danger fw-bold">Haber: S/ ${total}</span>
                    </div>
                </div>

                <div class="mt-2">
                    <a href="venta_nueva.php" class="btn btn-sm btn-success w-100 fw-bold">
                        <i class="fa fa-cash-register me-1"></i> Emitir Comprobante en POS
                    </a>
                </div>
            `;
            return { html, voiceText: voice };
        }

        // 7. Respuesta genérica inteligente
        const voice = `He recibido tu mensaje: ${raw}. Puedes consultarme sobre tus ventas, alertar productos bajos, o dictar operaciones contables.`;
        const html = `
            <div class="fw-bold text-dark mb-1">
                <i class="fa fa-sparkles text-primary me-1"></i> Asistente Siri ContaSmart
            </div>
            <p class="small text-secondary mb-2">
                Consulta procesada: <em>"${raw}"</em>.
                <br>Prueba ordenándome por voz o texto:
                <br>• <strong>"¿Cuánto vendí hoy?"</strong>
                <br>• <strong>"Compré insumos por 900 soles"</strong>
                <br>• <strong>"Consultar RUC 20100070970"</strong>
            </p>
        `;
        return { html, voiceText: voice };
    }

    return {
        init,
        open,
        close,
        switchTab,
        toggleVoice,
        toggleSpeechMute,
        sendUserQuery,
        processQuery,
        loadRealAlerts
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    ContaSmartAI.init();
});
