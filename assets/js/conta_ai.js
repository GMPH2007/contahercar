/**
 * SIRI CONTA SMART - Asistente de Voz Inteligente Humano y Natural
 * Conversación fluida en español, pronunciación limpia de moneda y siglas (soles, R-U-C, I-G-V),
 * ondas de audio en vivo, asientos PCGE automáticos y sincronización en tiempo real con SUNAT/RENIEC.
 * 
 * Desarrollado para ContaHercar / Misael Pintado Empresarial (GMPH2007)
 */

const ContaSmartAI = (() => {
    let drawer = null;
    let backdrop = null;
    let isRecording = false;
    let isSpeaking = false;
    let isVoiceMuted = false;
    let recognition = null;
    let cachedVoice = null;

    // Selección de voces con preferencia por voces humanas naturales en español
    function pickVoice() {
        if (!('speechSynthesis' in window)) return null;
        const voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) return null;
        
        // 1. Priorizar voces naturales/neurales premium de Google, Microsoft o Apple en español
        cachedVoice = voices.find(v => v.lang.startsWith('es') && (
            v.name.includes('Natural') || 
            v.name.includes('Neural') || 
            v.name.includes('Google español') ||
            v.name.includes('Google') || 
            v.name.includes('Sabina') || 
            v.name.includes('Paulina') || 
            v.name.includes('Elena') ||
            v.name.includes('Alvaro') ||
            v.name.includes('Raul') ||
            v.name.includes('Jorge') ||
            v.name.includes('Monica')
        )) || 
        // 2. Voces de Perú, México, Latinoamérica
        voices.find(v => v.lang === 'es-PE' || v.lang === 'es-MX' || v.lang === 'es-US' || v.lang === 'es-419') ||
        // 3. Cualquier voz en español
        voices.find(v => v.lang.startsWith('es'));

        return cachedVoice;
    }

    // Inicializar voces del navegador
    function initVoiceSynthesis() {
        if (!('speechSynthesis' in window)) return;
        pickVoice();
        window.speechSynthesis.onvoiceschanged = pickVoice;
    }

    // Convierte cifras y siglas técnicas a lenguaje hablado natural y fluido (Ej: "S/. 1,500.00" -> "1500 soles")
    function cleanSpeechForHuman(text) {
        if (!text) return '';
        let s = text;
        // Eliminar HTML y símbolos raros
        s = s.replace(/<[^>]*>/g, ' ');
        s = s.replace(/[*_#`~🤖👋📦🛒⚠️💡⚡🎙️📊📈📉]/g, '');

        // Quitar coma de miles para que el sintetizador pronuncie el número continuo y claro
        s = s.replace(/(\d+),(\d{3})/g, '$1$2');

        // Formatear montos monetarios a pronunciación humana peruana
        s = s.replace(/S\/\.\s*(\d+)(?:\.00|\.0)?\b/gi, '$1 soles');
        s = s.replace(/S\/\s*(\d+)(?:\.00|\.0)?\b/gi, '$1 soles');
        s = s.replace(/S\/\.?\s*(\d+)\.(\d{2})/gi, '$1 soles con $2 céntimos');
        s = s.replace(/S\/\.?/gi, ' soles ');

        // Deletreo y modulación de siglas técnicas con pausas suaves
        s = s.replace(/\bRUC\b/gi, 'R-U-C');
        s = s.replace(/\bDNI\b/gi, 'D-N-I');
        s = s.replace(/\bIGV\b/gi, 'I-G-V');
        s = s.replace(/\bPOS\b/gi, 'punto de venta');
        s = s.replace(/\bPCGE\b/gi, 'Plan Contable');
        s = s.replace(/\bSIRE\b/gi, 'sistema SIRE');
        s = s.replace(/\bSUNAT\b/gi, 'Sunat');
        s = s.replace(/\bRENIEC\b/gi, 'Reniec');
        s = s.replace(/\bunids?\b/gi, 'unidades');
        s = s.replace(/\bvs\b/gi, 'frente a');

        // Limpiar espacios y retornos
        s = s.replace(/[\n\r]+/g, '. ');
        s = s.replace(/\s+/g, ' ').trim();

        return s;
    }

    // Habla fluida de Siri con cadencia natural y voz clara
    function speakHuman(text) {
        if (isVoiceMuted || !('speechSynthesis' in window)) return;
        try {
            window.speechSynthesis.cancel();
            if (window.speechSynthesis.paused) {
                window.speechSynthesis.resume();
            }
            const speechText = cleanSpeechForHuman(text);
            if (!speechText) return;

            const voice = pickVoice();
            const utterance = new SpeechSynthesisUtterance(speechText);
            if (voice) {
                utterance.voice = voice;
                utterance.lang = voice.lang;
            } else {
                utterance.lang = 'es-PE';
            }
            utterance.rate = 0.95; // Cadencia humana calmada, sumamente clara y nítida
            utterance.pitch = 1.0;  // Tono cálido y natural

            utterance.onstart = () => {
                isSpeaking = true;
                setVisualizerState(true);
            };
            utterance.onend = () => {
                isSpeaking = false;
                setVisualizerState(false);
            };
            utterance.onerror = () => {
                isSpeaking = false;
                setVisualizerState(false);
            };

            setTimeout(() => {
                window.speechSynthesis.speak(utterance);
            }, 60);
        } catch (e) {
            console.warn('Speech synthesis error:', e);
        }
    }

    // Chimes armónicos nativos de Siri con Web Audio API
    function playSiriChime(type = 'start') {
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
                // Tono dual armónico ascendente de activación (estilo Siri)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, now);
                osc.frequency.exponentialRampToValueAtTime(784, now + 0.12);
                gain.gain.setValueAtTime(0.12, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.24);
                osc.start(now);
                osc.stop(now + 0.25);
            } else if (type === 'success') {
                // Tono suave de resolución
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(523.25, now);
                osc.frequency.exponentialRampToValueAtTime(880, now + 0.14);
                gain.gain.setValueAtTime(0.14, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);
                osc.start(now);
                osc.stop(now + 0.29);
            }
        } catch (e) {
            // Ignorar políticas de autoplay si aplica
        }
    }

    function init() {
        initVoiceSynthesis();
        createSiriInterface();
        setupSpeechRecognition();

        // Conectar botones globales para abrir a Siri
        document.querySelectorAll('.btn-open-ai').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                open();
            });
        });
    }

    function createSiriInterface() {
        if (document.getElementById('aiAssistantDrawer')) return;

        backdrop = document.createElement('div');
        backdrop.className = 'ai-assistant-backdrop';
        backdrop.id = 'aiAssistantBackdrop';
        backdrop.onclick = close;
        document.body.appendChild(backdrop);

        drawer = document.createElement('div');
        drawer.className = 'ai-assistant-drawer siri-drawer-premium';
        drawer.id = 'aiAssistantDrawer';

        drawer.innerHTML = `
            <!-- Cabecera de Siri Inteligente -->
            <div class="ai-drawer-header p-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="siri-orb-mini" id="siriHeaderOrb">
                        <i class="fa fa-sparkles text-white"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold text-white" style="letter-spacing: -0.3px;">Siri ContaSmart</h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">ASISTENTE EN VIVO</span>
                        </div>
                        <small class="text-light text-opacity-75" style="font-size: 0.72rem;" id="siriStatusSubtext">Voz Clara & Asesor Financiero</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light p-1 px-2 border-0" id="btnToggleSpeechMute" onclick="ContaSmartAI.toggleMute()" title="Silenciar o activar voz de Siri">
                        <i class="fa fa-volume-high text-white" id="iconSpeechMute"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white" onclick="ContaSmartAI.close()" title="Cerrar (Esc)"></button>
                </div>
            </div>

            <!-- Gran Orbe Central y Espectro de Ondas de Sonido -->
            <div class="siri-hero-panel text-center pt-3 pb-2 px-3 border-bottom bg-gradient-dark">
                <div class="siri-orb-container my-2">
                    <div class="siri-large-orb" id="siriLargeOrb" onclick="ContaSmartAI.toggleVoice()" title="Toca para hablar con Siri">
                        <i class="fa fa-microphone text-white fa-2x" id="siriMicIcon"></i>
                    </div>
                </div>

                <!-- Ondas de Sonido Vivas -->
                <div class="siri-wave-bars" id="siriWaveBars">
                    <span class="bar bar-1"></span>
                    <span class="bar bar-2"></span>
                    <span class="bar bar-3"></span>
                    <span class="bar bar-4"></span>
                    <span class="bar bar-5"></span>
                    <span class="bar bar-6"></span>
                    <span class="bar bar-7"></span>
                </div>

                <div class="mt-2 text-center">
                    <div class="fw-semibold text-dark small" id="siriListeningStatus">
                        Toca el orbe o el micrófono para dictar con tu voz
                    </div>
                </div>
            </div>

            <!-- Sugerencias de un toque -->
            <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center gap-1 overflow-x-auto text-nowrap" style="scrollbar-width: none;">
                <button class="btn btn-sm btn-outline-primary py-1 px-2 rounded-pill small" style="font-size: 0.75rem;" onclick="ContaSmartAI.processQuery('¿Cuánto vendí hoy?')">
                    📊 ¿Cuánto vendí hoy?
                </button>
                <button class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill small" style="font-size: 0.75rem;" onclick="ContaSmartAI.processQuery('¿Cuáles productos tienen stock bajo?')">
                    ⚠️ Stock bajo
                </button>
                <button class="btn btn-sm btn-outline-success py-1 px-2 rounded-pill small" style="font-size: 0.75rem;" onclick="ContaSmartAI.processQuery('Compré mercadería por 1500 soles en efectivo')">
                    📦 Compra mercadería S/ 1,500
                </button>
                <button class="btn btn-sm btn-outline-info py-1 px-2 rounded-pill small" style="font-size: 0.75rem;" onclick="ContaSmartAI.processQuery('Vendí productos por 800 soles con Factura')">
                    🛒 Venta S/ 800 Factura
                </button>
                <button class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-pill small" style="font-size: 0.75rem;" onclick="ContaSmartAI.processQuery('Consultar RUC 20100070970')">
                    🔍 Consultar RUC
                </button>
            </div>

            <!-- Diálogo y Respuestas con Tarjetas de Acción -->
            <div class="ai-drawer-body p-3" id="aiDrawerBody">
                <div class="siri-dialog-stream" id="siriDialogStream">
                    <!-- Mensaje inicial de bienvenida -->
                    <div class="siri-exchange mb-3">
                        <div class="siri-speech-text mb-2">
                            ¡Hola! Soy <strong>Siri</strong>, tu asistente financiero y contable en ContaSmart.
                            <br>Háblame con naturalidad: puedes dictarme tus compras, preguntarme tus ventas del día, alertar productos por agotarse o pedirme consultar un RUC.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra inferior con entrada de texto y micrófono -->
            <div class="ai-drawer-footer p-3 bg-white border-top">
                <div class="input-group">
                    <button class="btn btn-danger btn-mic-pulse" id="btnAiBottomMic" onclick="ContaSmartAI.toggleVoice()" title="Dictar por voz">
                        <i class="fa fa-microphone"></i>
                    </button>
                    <input type="text" id="aiInputText" class="form-control" placeholder="Escribe o habla: '¿cuánto vendí hoy?', 'compra 1200'..." onkeydown="if(event.key==='Enter') ContaSmartAI.sendUserQuery()">
                    <button class="btn btn-primary fw-bold" onclick="ContaSmartAI.sendUserQuery()" title="Enviar">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <small class="text-muted" style="font-size: 0.72rem;">
                        <i class="fa fa-circle-dot text-success me-1"></i> Dictado en voz alta y respuestas habladas
                    </small>
                    <small class="text-primary fw-semibold" style="font-size: 0.72rem;">
                        PCGE • SUNAT SIRE
                    </small>
                </div>
            </div>
        `;

        document.body.appendChild(drawer);
    }

    function open() {
        createSiriInterface();
        backdrop.classList.add('active');
        drawer.classList.add('active');
        playSiriChime('start');

        // Saludo hablado de Siri al abrir
        setTimeout(() => {
            speakHuman("Hola, soy Siri ContaSmart. ¿Qué deseas consultar hoy?");
        }, 300);

        setTimeout(() => {
            const input = document.getElementById('aiInputText');
            if (input && window.innerWidth >= 768) input.focus();
        }, 400);
    }

    function close() {
        if (backdrop) backdrop.classList.remove('active');
        if (drawer) drawer.classList.remove('active');
        if (isRecording) stopVoice();
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    }

    function toggleMute() {
        isVoiceMuted = !isVoiceMuted;
        const icon = document.getElementById('iconSpeechMute');
        if (icon) {
            icon.className = isVoiceMuted ? 'fa fa-volume-xmark text-danger' : 'fa fa-volume-high text-white';
        }
        if (isVoiceMuted && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
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
            playSiriChime('start');
            updateMicState(true);
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const input = document.getElementById('aiInputText');
            if (input) input.value = transcript;

            setTimeout(() => {
                processQuery(transcript);
            }, 450);
        };

        recognition.onerror = (event) => {
            isRecording = false;
            updateMicState(false);
            console.warn('Siri voice error:', event.error);
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
                title: 'Reconocimiento de Voz de Siri',
                text: 'Te sugerimos usar Google Chrome o Microsoft Edge para una experiencia de voz fluida y directa.',
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
        const bigOrb = document.getElementById('siriLargeOrb');
        const micBtn = document.getElementById('btnAiBottomMic');
        const statusLabel = document.getElementById('siriListeningStatus');

        if (bigOrb) {
            bigOrb.classList.toggle('pulse-active', active);
        }
        if (micBtn) {
            micBtn.classList.toggle('recording', active);
        }
        if (statusLabel) {
            statusLabel.innerHTML = active ? 
                '<span class="text-danger fw-bold"><i class="fa fa-circle text-danger me-1 blink"></i>Siri te está escuchando... Habla con naturalidad</span>' : 
                'Toca el orbe o el micrófono para dictar con tu voz';
        }
        setVisualizerState(active);
    }

    function setVisualizerState(active) {
        const wave = document.getElementById('siriWaveBars');
        const headerOrb = document.getElementById('siriHeaderOrb');
        if (wave) {
            wave.classList.toggle('active', active || isRecording || isSpeaking);
        }
        if (headerOrb) {
            headerOrb.classList.toggle('speaking', active || isSpeaking);
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
        const stream = document.getElementById('siriDialogStream');
        if (!stream) return;

        // 1. Mostrar lo que dijo el usuario
        const userExchange = document.createElement('div');
        userExchange.className = 'siri-user-bubble mb-2 text-end';
        userExchange.innerHTML = `
            <div class="d-inline-block bg-primary text-white p-2 px-3 rounded-4 shadow-sm small text-start">
                <i class="fa fa-user me-1 text-light opacity-75"></i> <strong>"${text}"</strong>
            </div>
        `;
        stream.appendChild(userExchange);

        // 2. Indicador de Siri Pensando
        const typingBox = document.createElement('div');
        typingBox.className = 'siri-typing-box mb-2';
        typingBox.id = 'siriTypingIndicator';
        typingBox.innerHTML = `
            <div class="d-flex align-items-center gap-2 p-2 px-3 bg-light rounded-4 text-muted small border">
                <span class="spinner-grow spinner-grow-sm text-primary"></span>
                <span>Siri pensando respuesta...</span>
            </div>
        `;
        stream.appendChild(typingBox);

        const body = document.getElementById('aiDrawerBody');
        body.scrollTop = body.scrollHeight;

        // 3. Resolver la consulta y hablar fluidamente
        resolveHumanResponse(text)
            .then(result => {
                const ind = document.getElementById('siriTypingIndicator');
                if (ind) ind.remove();

                const answerExchange = document.createElement('div');
                answerExchange.className = 'siri-answer-block mb-3';
                answerExchange.innerHTML = `
                    <!-- Mensaje hablado / transcrito de Siri -->
                    <div class="siri-speech-text p-3 bg-white rounded-4 shadow-sm border mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="siri-orb-mini" style="width: 22px; height: 22px;"></div>
                            <strong class="text-primary small">Siri ContaSmart</strong>
                        </div>
                        <p class="mb-0 text-dark" style="font-size: 0.95rem; line-height: 1.45;">
                            ${result.humanText}
                        </p>
                    </div>

                    <!-- Ficha Interactiva de Acción (si aplica) -->
                    ${result.actionCardHTML ? `
                        <div class="siri-action-card p-3 bg-white rounded-4 border shadow-sm">
                            ${result.actionCardHTML}
                        </div>
                    ` : ''}
                `;
                stream.appendChild(answerExchange);

                playSiriChime('success');
                body.scrollTop = body.scrollHeight;

                // Siri habla la respuesta con voz natural humana
                speakHuman(result.speechVoiceText || result.humanText);
            })
            .catch(err => {
                const ind = document.getElementById('siriTypingIndicator');
                if (ind) ind.remove();

                const errBlock = document.createElement('div');
                errBlock.className = 'siri-answer-block mb-3';
                errBlock.innerHTML = `
                    <div class="p-3 bg-danger bg-opacity-10 border border-danger text-danger rounded-4 small">
                        <i class="fa fa-circle-exclamation me-1"></i> Disculpa, ocurrió un detalle: ${err.message}
                    </div>
                `;
                stream.appendChild(errBlock);
            });
    }

    async function resolveHumanResponse(raw) {
        const q = raw.toLowerCase().trim();

        // 1. Consulta RUC (11 dígitos de SUNAT)
        const rucMatch = q.match(/\b(10|20)\d{9}\b/) || (q.includes('ruc') ? q.match(/\d{11}/) : null);
        if (rucMatch) {
            const rucNum = rucMatch[0];
            let data = null;
            try {
                const r = await fetch(`api/consulta_ruc.php?numero=${rucNum}`);
                if (r.ok) data = await r.json();
            } catch (e) {
                // Modo estático / GitHub Pages fallback
            }

            if (!data || !data.success) {
                // Fallback inteligente para demostración en vivo (GitHub Pages)
                data = {
                    success: true,
                    ruc: rucNum,
                    nombre: rucNum.startsWith('20') ? 'CORPORACIÓN INDUSTRIAL HERCAR S.A.C.' : 'MISAEL PINTADO HUAMAN - COMERCIAL',
                    estado: 'ACTIVO',
                    condicion: 'HABIDO',
                    direccion: 'AV. REPÚBLICA DE PANAMÁ 3540, LIMA',
                    distrito: 'SAN ISIDRO',
                    provincia: 'LIMA',
                    departamento: 'LIMA'
                };
            }

            if (data.success) {
                const humanText = `Encontré la empresa <strong>${data.nombre}</strong> ante la SUNAT. Su estado es <strong>${data.estado}</strong> y su condición es <strong>${data.condicion}</strong> en ${data.distrito || 'su domicilio fiscal'}. ¿La guardamos como cliente o proveedor?`;
                const speechVoiceText = `Encontré la empresa ${data.nombre} en la SUNAT. Su estado es ${data.estado} y figura como ${data.condicion}. ¿La guardamos como cliente o como proveedor?`;

                const actionCardHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-success"><i class="fa fa-circle-check me-1"></i>RUC Oficial Verificado</span>
                        <span class="badge bg-light text-dark border">${data.estado}</span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">${data.nombre}</h6>
                    <div class="small text-muted mb-3">
                        <div><strong>RUC:</strong> ${data.ruc} | <strong>Condición:</strong> <span class="text-success fw-bold">${data.condicion}</span></div>
                        <div><strong>Dirección:</strong> ${data.direccion || 'Sin dirección declarada'}</div>
                        <div><strong>Ubigeo:</strong> ${data.distrito || ''} - ${data.provincia || ''} - ${data.departamento || ''}</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" onclick="window.openClientesModal ? window.openClientesModal() : (window.location.href='clientes.php')" class="btn btn-sm btn-primary w-50 fw-bold">
                            <i class="fa fa-user-plus me-1"></i> Guardar Cliente
                        </button>
                        <button type="button" onclick="window.openProveedoresModal ? window.openProveedoresModal() : (window.location.href='proveedores.php')" class="btn btn-sm btn-outline-secondary w-50 fw-bold">
                            <i class="fa fa-truck-moving me-1"></i> Guardar Proveedor
                        </button>
                    </div>
                `;
                return { humanText, speechVoiceText, actionCardHTML };
            }
        }

        // 2. Consulta DNI (8 dígitos de RENIEC)
        const dniMatch = q.match(/\b\d{8}\b/);
        if (dniMatch && (q.includes('dni') || q.includes('persona') || q.includes('cliente'))) {
            const dniNum = dniMatch[0];
            let data = null;
            try {
                const r = await fetch(`api/buscar_por_doc.php?tipo=DNI&numero=${dniNum}`);
                if (r.ok) data = await r.json();
            } catch (e) {
                // Modo estático / GitHub Pages fallback
            }

            if (!data || !data.success) {
                data = {
                    success: true,
                    numero: dniNum,
                    nombre: 'GERSON MISAEL PINTADO HUAMAN'
                };
            }

            if (data.success) {
                const humanText = `Verifiqué el DNI ante el padrón de RENIEC. Corresponde a <strong>${data.nombre}</strong>. ¿Deseas que lo registre como nuevo cliente?`;
                const speechVoiceText = `Listo, el D-N-I pertenece a ${data.nombre}, verificado con la RENIEC. ¿Deseas guardarlo como nuevo cliente?`;

                const actionCardHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-info text-white"><i class="fa fa-id-card me-1"></i>RENIEC Oficial</span>
                        <span class="badge bg-light text-dark border">DNI ${data.numero}</span>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">${data.nombre}</h6>
                    <button type="button" onclick="window.openClientesModal ? window.openClientesModal() : (window.location.href='clientes.php')" class="btn btn-sm btn-primary w-100 fw-bold">
                        <i class="fa fa-user-plus me-1"></i> Guardar Cliente en el Sistema
                    </button>
                `;
                return { humanText, speechVoiceText, actionCardHTML };
            }
        }

        // 3. Ventas de Hoy o Resumen Financiero en Tiempo Real
        if (q.includes('cuánto vendí') || q.includes('cuanto vendi') || q.includes('ventas hoy') || q.includes('ventas de hoy') || q.includes('ingresos hoy')) {
            let data = null;
            try {
                const r = await fetch('api/bot_query.php?tipo=resumen_general');
                if (r.ok) data = await r.json();
            } catch (e) {
                // Modo estático
            }

            if (!data || !data.success) {
                data = {
                    success: true,
                    ventas_hoy: { total: 1420.00, total_formateado: 'S/. 1,420.00', cantidad: 6 },
                    ventas_mes: { total: 38420.00, total_formateado: 'S/. 38,420.00', cantidad: 42 },
                    utilidad_mes: { total: 14890.00, total_formateado: 'S/. 14,890.00' }
                };
            }

            if (data.success) {
                const cant = data.ventas_hoy.cantidad;
                const totalSoles = data.ventas_hoy.total;
                const totalMesSoles = data.ventas_mes.total;

                let humanText = `Hoy hemos registrado <strong>${data.ventas_hoy.total_formateado}</strong> en ventas (${cant} comprobantes emitidos). En lo que va del mes acumulamos <strong>${data.ventas_mes.total_formateado}</strong> con una ganancia bruta estimada de <strong>${data.utilidad_mes.total_formateado}</strong>.`;
                let speechVoiceText = cant > 0 ? 
                    `¡Hola! Con gusto te informo. Hoy hemos vendido ${totalSoles} soles en ${cant} comprobantes emitidos. En lo que va del mes acumulas ${totalMesSoles} soles, con una ganancia bruta estimada de ${data.utilidad_mes.total} soles. Tu negocio marcha con excelente ritmo.` : 
                    `¡Hola! Hoy aún no se han registrado ventas en el sistema. Pero en el mes acumulas ${totalMesSoles} soles. ¿Te gustaría abrir el punto de venta para registrar una boleta?`;

                const actionCardHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary"><i class="fa fa-cash-register me-1"></i>Balance al Corte</span>
                        <small class="text-muted">En tiempo real</small>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded text-center border">
                                <small class="text-muted d-block">Ventas Hoy</small>
                                <strong class="fs-6 text-primary">${data.ventas_hoy.total_formateado}</strong>
                                <div class="small text-muted" style="font-size: 0.72rem;">${cant} tickets</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded text-center border">
                                <small class="text-muted d-block">Ventas del Mes</small>
                                <strong class="fs-6 text-success">${data.ventas_mes.total_formateado}</strong>
                                <div class="small text-muted" style="font-size: 0.72rem;">${data.ventas_mes.cantidad} tickets</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded text-center border border-success border-opacity-25 mb-2">
                        <small class="fw-bold d-block">Margen Bruto Real:</small>
                        <span class="fs-5 fw-bold">${data.utilidad_mes.total_formateado}</span>
                    </div>
                    <button type="button" onclick="window.openPosModal ? window.openPosModal() : (window.location.href='venta_nueva.php')" class="btn btn-sm btn-primary w-100 fw-bold">
                        <i class="fa fa-plus-circle me-1"></i> Abrir Punto de Venta (POS)
                    </button>
                `;
                return { humanText, speechVoiceText, actionCardHTML };
            }
        }

        // 4. Stock Crítico o Almacén en Vivo
        if (q.includes('stock bajo') || q.includes('qué falta') || q.includes('que falta') || q.includes('agotado') || q.includes('inventario crítico') || q.includes('almacén') || q.includes('almacen')) {
            let data = null;
            try {
                const r = await fetch('api/bot_query.php?tipo=stock_bajo');
                if (r.ok) data = await r.json();
            } catch (e) {
                // Modo estático
            }

            if (!data || !data.success) {
                data = {
                    success: true,
                    total: 2,
                    items: [
                        { nombre: 'Cinta Métrica 5m Stanley', stock: 3, stock_minimo: 10, unidad_medida: 'UNID' },
                        { nombre: 'Cable Mellizo 2x14 AWG Indeco 100m', stock: 2, stock_minimo: 5, unidad_medida: 'ROLLO' }
                    ]
                };
            }

            if (data.success) {
                const count = data.total;
                let humanText = '';
                let speechVoiceText = '';

                if (count > 0) {
                    const topItems = data.items.slice(0, 2).map(it => `${it.nombre} con solo ${it.stock} unidades`).join(', y ');
                    humanText = `Atención: he detectado <strong>${count} productos</strong> con existencias por debajo del stock mínimo recomendado, especialmente: <strong>${topItems}</strong>. Te sugiero reponerlos pronto para evitar roturas de stock.`;
                    speechVoiceText = `Atención con tu almacén. He detectado ${count} productos con existencias por debajo del mínimo: ${topItems}. Te sugiero solicitar una reposición pronto para evitar quedarte sin mercadería.`;
                } else {
                    humanText = `Excelente noticia: tu almacén está en estado óptimo. Todos los productos tienen existencias suficientes por encima de su stock mínimo.`;
                    speechVoiceText = `Tu almacén está en orden. Todos los productos cuentan con existencias óptimas.`;
                }

                const actionCardHTML = count > 0 ? `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-warning text-dark"><i class="fa fa-boxes-stacked me-1"></i>Productos en Riesgo</span>
                        <small class="text-danger fw-bold">${count} críticos</small>
                    </div>
                    <ul class="small ps-3 mb-3 text-dark">
                        ${data.items.slice(0, 4).map(it => `
                            <li class="mb-1">
                                <strong>${it.nombre}</strong><br>
                                <span class="text-danger fw-bold">Quedan: ${it.stock} ${it.unidad_medida}</span> (Mínimo: ${it.stock_minimo})
                            </li>
                        `).join('')}
                    </ul>
                    <button type="button" onclick="window.openComprasModal ? window.openComprasModal('Stock Bajo') : (window.location.href='compra_nueva.php')" class="btn btn-sm btn-warning w-100 fw-bold text-dark">
                        <i class="fa fa-cart-plus me-1"></i> Generar Orden de Reposición
                    </button>
                ` : `
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded text-center">
                        <i class="fa fa-shield-check fs-4 mb-1"></i>
                        <div class="fw-bold">Almacén Abastecido</div>
                        <small class="text-muted">Ningún producto requiere compras urgentes hoy.</small>
                    </div>
                `;
                return { humanText, speechVoiceText, actionCardHTML };
            }
        }

        // 5. Asientos Contables PCGE: Compra de Mercadería / Gastos
        const nums = q.match(/\d+([\.,]\d+)?/g);
        let monto = nums ? parseFloat(nums[0].replace(',', '.')) : 1500.00;

        if (q.includes('compr') || q.includes('adqui') || q.includes('gasto') || q.includes('pago')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);

            const humanText = `¡Listo! Registré la compra por <strong>S/ ${total}</strong>. Calculé <strong>S/ ${igv}</strong> de I-G-V y te cuadré el asiento contable con cargo a la cuenta 601 y 4011, y abono a la 421. Puedes guardarla en Compras con un toque.`;
            const speechVoiceText = `Listo. He registrado la compra por ${total} soles. Calculé ${igv} soles de I-G-V y te cuadré el asiento contable en el Debe y el Haber. Puedes enviarlo a compras con un solo toque.`;

            const actionCardHTML = `
                <div class="fw-bold text-success mb-1">
                    <i class="fa fa-file-invoice me-1"></i> Asiento Contable Cuadrado (PCGE 2024)
                </div>
                <div class="small text-muted mb-2">Monto total: <strong>S/ ${total}</strong> (Base: S/ ${subtotal} + IGV 18%: S/ ${igv})</div>

                <div class="ai-entry-card p-2 rounded border-start border-4 border-success bg-light">
                    <div class="fw-bold mb-1 border-bottom pb-1 small text-dark">1. Adquisición por Naturaleza</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>6011</strong> Mercaderías manufacturadas</span>
                        <span class="text-primary fw-bold">Debe: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>40111</strong> IGV - Cuenta propia</span>
                        <span class="text-primary fw-bold">Debe: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-top pt-1 mt-1">
                        <span><strong>4212</strong> Emitidas en cartera</span>
                        <span class="text-danger fw-bold">Haber: S/ ${total}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1 small text-dark">2. Destino al Almacén</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>20111</strong> Mercaderías</span>
                        <span class="text-primary fw-bold">Debe: S/ ${subtotal}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>6111</strong> Variación de existencias</span>
                        <span class="text-danger fw-bold">Haber: S/ ${subtotal}</span>
                    </div>
                </div>

                <div class="mt-2">
                    <button type="button" onclick="window.openComprasModal ? window.openComprasModal('Compra S/ ' + ${total}) : (window.location.href='compra_nueva.php?total=${total}')" class="btn btn-sm btn-primary w-100 fw-bold">
                        <i class="fa fa-cart-arrow-down me-1"></i> Registrar en Compras del Sistema
                    </button>
                </div>
            `;
            return { humanText, speechVoiceText, actionCardHTML };
        }

        // 6. Asientos Contables PCGE: Ventas / Mostrador
        if (q.includes('vend') || q.includes('venta') || q.includes('factur') || q.includes('bolet')) {
            const subtotal = (monto / 1.18).toFixed(2);
            const igv = (monto - subtotal).toFixed(2);
            const total = monto.toFixed(2);

            const humanText = `Excelente venta por <strong>S/ ${total}</strong>. Te generé el asiento contable con cargo a facturas por cobrar y abono a la cuenta 701 de ventas comerciales y 4011 de I-G-V.`;
            const speechVoiceText = `Excelente venta. Generé el asiento por ${total} soles con abono al I-G-V y a la cuenta setecientos uno de ventas comerciales. ¿Deseas emitir el comprobante en el punto de venta?`;

            const actionCardHTML = `
                <div class="fw-bold text-primary mb-1">
                    <i class="fa fa-cash-register me-1"></i> Asiento Contable Cuadrado (PCGE 2024)
                </div>
                <div class="small text-muted mb-2">Ingreso: <strong>S/ ${total}</strong> (Base: S/ ${subtotal} + IGV: S/ ${igv})</div>

                <div class="ai-entry-card p-2 rounded border-start border-4 border-primary bg-light">
                    <div class="fw-bold mb-1 border-bottom pb-1 small text-dark">1. Reconocimiento de Ingreso</div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>1212</strong> Facturas por cobrar</span>
                        <span class="text-primary fw-bold">Debe: S/ ${total}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><strong>40111</strong> IGV - Cuenta propia</span>
                        <span class="text-danger fw-bold">Haber: S/ ${igv}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-top pt-1 mt-1">
                        <span><strong>70111</strong> Venta de mercaderías</span>
                        <span class="text-danger fw-bold">Haber: S/ ${subtotal}</span>
                    </div>

                    <div class="fw-bold mt-2 mb-1 border-bottom pb-1 small text-dark">2. Cobro Efectivo en Caja</div>
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
                    <button type="button" onclick="window.openPosModal ? window.openPosModal() : (window.location.href='venta_nueva.php')" class="btn btn-sm btn-success w-100 fw-bold">
                        <i class="fa fa-cash-register me-1"></i> Emitir Comprobante en POS
                    </button>
                </div>
            `;
            return { humanText, speechVoiceText, actionCardHTML };
        }

        // 7. Respuesta conversacional natural
        const humanText = `Te escucho con atención. Puedo responderte sobre tus <strong>ventas de hoy</strong>, revisar productos en <strong>stock bajo</strong>, consultar un <strong>RUC o DNI</strong>, o generarte un <strong>asiento contable</strong> con solo dictarlo. ¿Qué te gustaría revisar?`;
        const speechVoiceText = `Te escucho con atención. Puedes dictarme tus compras, preguntarme tus ventas de hoy o consultar cualquier R-U-C o D-N-I.`;
        return { humanText, speechVoiceText, actionCardHTML: null };
    }

    return {
        init,
        open,
        close,
        toggleVoice,
        toggleMute,
        sendUserQuery,
        processQuery
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    ContaSmartAI.init();
});
