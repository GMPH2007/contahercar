/**
 * CONTA SMART v2.5 - Recorrido Interactivo Guiado Autónomo ("Conoce la Web")
 * Desarrollado para ContaHercar / Misael Pintado Empresarial (GMPH2007)
 * 
 * Características:
 * - 100% Nítido: Ventana de foco transparente con máscara box-shadow exterior de 9999px (cero blur, cero opacidad en el contenido).
 * - Anti-superposición: Algoritmo de posicionamiento inteligente que nunca tapa el elemento enfocado.
 * - Responsivo para celulares: En pantallas móviles se acopla como bottom/top sheet ergonómico.
 * - Autónomo: Avance automático con barra de progreso fluida (6.5s por paso) y pausa al interactuar/hover.
 * - Controles completos: Pausar/Reanudar, Siguiente, Anterior, Saltar, teclado (Esc, Flechas, Espacio).
 */

const ContaSmartTour = (() => {
    let currentStep = 0;
    let steps = [];
    let spotlight = null;
    let popover = null;
    let backdrop = null;
    let autoPlayTimer = null;
    let isPaused = false;
    let activeTarget = null;
    let elapsedMs = 0;
    const STEP_DURATION = 6500; // 6.5 segundos por paso para lectura cómoda

    function init() {
        createTourDOMElements();

        // Botones manuales de inicio de tour en cualquier parte de la web
        document.querySelectorAll('.btn-start-tour').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                startTour(true);
            });
        });

        // Auto-iniciar solo si es primera visita del usuario y está en el Dashboard
        const isCompleted = localStorage.getItem('contasmart_tour_completed');
        const path = window.location.pathname.toLowerCase();
        const isDashboard = path.endsWith('index.php') || 
                            path.endsWith('/contahercar/') ||
                            path.endsWith('/contahercar') ||
                            path === '/';

        if (!isCompleted && isDashboard) {
            setTimeout(() => {
                startTour(false);
            }, 1200);
        }

        // Atajos de teclado: Escape para salir, Flechas para navegar, Espacio para pausar
        document.addEventListener('keydown', handleKeydown);

        // Reposicionar el foco si el usuario cambia el tamaño de la ventana o hace scroll
        window.addEventListener('resize', handleWindowUpdate);
        window.addEventListener('scroll', handleWindowUpdate, { passive: true });
    }

    function createTourDOMElements() {
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'tour-backdrop-overlay';
            backdrop.id = 'tourBackdropOverlay';
            backdrop.onclick = endTour;
            document.body.appendChild(backdrop);
        }

        if (!spotlight) {
            spotlight = document.createElement('div');
            spotlight.className = 'tour-spotlight-active';
            spotlight.id = 'tourSpotlightActive';
            document.body.appendChild(spotlight);
        }

        if (!popover) {
            popover = document.createElement('div');
            popover.className = 'tour-popover-card shadow-lg';
            popover.id = 'tourPopoverCard';
            // Pausar auto-avance al colocar el mouse o interactuar
            popover.addEventListener('mouseenter', () => { isPaused = true; updatePlayPauseIcon(); });
            popover.addEventListener('mouseleave', () => { isPaused = false; updatePlayPauseIcon(); });
            document.body.appendChild(popover);
        }
    }

    function defineSteps() {
        return [
            {
                element: '#tourHeaderGreeting',
                title: '👋 Bienvenido a ContaSmart v2.5',
                desc: 'Tu plataforma inteligente todo-en-uno: Facturación POS, control de inventario con costo real, analítica financiera y conexión con SUNAT SIRE.',
                icon: 'fa-sparkles text-warning',
                pos: 'bottom'
            },
            {
                element: '#tourKpiCards',
                title: '📊 Indicadores Clave en Tiempo Real',
                desc: 'Supervisa tus ventas acumuladas, compras y gastos, tu margen de utilidad bruta real y alertas de existencias críticas al instante.',
                icon: 'fa-chart-pie text-primary',
                pos: 'bottom'
            },
            {
                element: '#tourQuickActions',
                title: '⚡ Acciones Rápidas con 1 Clic',
                desc: 'Emite comprobantes en el Punto de Venta (POS), registra compras de mercadería, abre los libros SIRE o activa el asistente por voz.',
                icon: 'fa-bolt text-warning',
                pos: 'bottom'
            },
            {
                element: '#tourChartsSection',
                title: '📈 Flujo de Caja & Salud de Stock',
                desc: 'Analiza la comparativa mensual de Ventas vs Compras de los últimos 6 meses y el donut de inventario (óptimo, bajo riesgo y agotado).',
                icon: 'fa-chart-column text-success',
                pos: 'top'
            },
            {
                element: '#tourInventoryTable',
                title: '📦 Monitoreo de Almacén & Reposición',
                desc: 'Supervisa productos en alerta de stock, consulta precios unitarios de compra y venta, y genera órdenes de reposición directa.',
                icon: 'fa-boxes-stacked text-info',
                pos: 'top'
            },
            {
                element: '#tourAiAssistantBtn',
                title: '🤖 Asistente ContaSmart IA & ContaVoz',
                desc: '¡Innovación única! Dicta transacciones con tu voz y la IA estructurará la propuesta automática de asientos contables PCGE (Debe/Haber).',
                icon: 'fa-robot text-primary',
                pos: 'bottom'
            }
        ];
    }

    function isTourActive() {
        return popover && popover.style.display === 'block';
    }

    function startTour(manual = false) {
        // Cerrar asistente IA lateral si estuviese abierto para evitar colisiones
        if (typeof ContaSmartAI !== 'undefined' && ContaSmartAI.close) {
            ContaSmartAI.close();
        }

        steps = defineSteps().filter(s => document.querySelector(s.element) !== null);
        if (steps.length === 0) return;

        createTourDOMElements();
        backdrop.style.display = 'block';
        currentStep = 0;
        showStep(currentStep);
    }


    function showStep(index) {
        clearInterval(autoPlayTimer);
        elapsedMs = 0;
        isPaused = false;

        // Limpiar target previo
        if (activeTarget) {
            activeTarget.classList.remove('tour-highlighted-element');
            activeTarget = null;
        }

        if (index < 0 || index >= steps.length) {
            endTour();
            return;
        }

        currentStep = index;
        const step = steps[index];
        const target = document.querySelector(step.element);
        if (!target) {
            nextStep();
            return;
        }

        activeTarget = target;
        activeTarget.classList.add('tour-highlighted-element');

        // Cálculo de Scroll Inteligente con margen de respiración
        const targetRect = target.getBoundingClientRect();
        const pageY = window.pageYOffset || document.documentElement.scrollTop;
        const elemTop = targetRect.top + pageY;
        const elemHeight = targetRect.height;
        const vh = window.innerHeight;
        const vw = window.innerWidth;

        let targetScrollY = elemTop - 85;
        if (vw < 768) {
            // Celular: colocar el elemento en el tercio superior
            targetScrollY = Math.max(0, elemTop - 65);
        } else {
            if (elemHeight > vh * 0.55) {
                // Elemento grande: margen superior para barra
                targetScrollY = Math.max(0, elemTop - 85);
            } else if (step.pos === 'top') {
                // Si el popover va arriba, dejar espacio superior para el popover
                targetScrollY = Math.max(0, elemTop - 250);
            } else {
                // Elemento regular: centrar cómodamente
                targetScrollY = Math.max(0, elemTop - (vh - elemHeight) / 3);
            }
        }

        window.scrollTo({
            top: targetScrollY,
            behavior: 'smooth'
        });

        // Dar tiempo al scroll suave para asentarse y calcular posición exacta
        setTimeout(() => {
            renderSpotlightAndPopover(target, step, index);
            startAutoPlayTimer();
        }, 340);
    }

    function renderSpotlightAndPopover(target, step, index) {
        if (!target || (!isTourActive() && (!backdrop || backdrop.style.display !== 'block'))) return;

        const rect = target.getBoundingClientRect();
        const pad = 8;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // 1. Posicionar Foco Recortado (Spotlight) Nítido con box-shadow exterior 9999px
        spotlight.style.display = 'block';
        spotlight.style.top = `${Math.max(0, rect.top - pad)}px`;
        spotlight.style.left = `${Math.max(0, rect.left - pad)}px`;
        spotlight.style.width = `${rect.width + pad * 2}px`;
        spotlight.style.height = `${rect.height + pad * 2}px`;

        // 2. Contenido de la Tarjeta Popover
        const isLast = (index === steps.length - 1);
        const isFirst = (index === 0);

        popover.innerHTML = `
            <!-- Barra de tiempo de avance automático -->
            <div class="tour-timer-bar-wrap">
                <div class="tour-timer-bar-fill" id="tourTimerBarFill"></div>
            </div>

            <!-- Cabecera con Contador de Pasos y Controles -->
            <div class="d-flex align-items-center justify-content-between mb-2 mt-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="tour-step-counter">
                        <i class="fa fa-sparkles text-primary"></i> Paso ${index + 1} de ${steps.length}
                    </span>
                    <button type="button" class="tour-autoplay-btn" id="tourPlayPauseBtn" onclick="ContaSmartTour.togglePlayPause()" title="Pausar o reanudar auto-avance">
                        <i class="fa fa-pause text-muted me-1"></i><span>Pausar</span>
                    </button>
                </div>
                <button type="button" class="btn-close btn-sm" onclick="ContaSmartTour.endTour()" title="Cerrar recorrido (Esc)"></button>
            </div>

            <!-- Título y Descripción del Paso -->
            <div class="d-flex align-items-start gap-3 my-2">
                <div class="p-2 rounded-3 bg-light border text-center flex-shrink-0" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa ${step.icon} fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${step.title}</h6>
                    <p class="text-secondary small mb-0 lh-sm" style="font-size: 0.82rem;">${step.desc}</p>
                </div>
            </div>

            <!-- Puntos indicadores de avance interactivos -->
            <div class="d-flex align-items-center justify-content-center gap-1 my-2 py-1">
                ${steps.map((s, idx) => `
                    <span class="tour-dot-step ${idx === index ? 'active' : ''}" onclick="ContaSmartTour.goToStep(${idx})" title="Paso ${idx + 1}: ${s.title}" style="cursor: pointer;"></span>
                `).join('')}
            </div>

            <!-- Botones de Navegación -->
            <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-2">
                <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none small" onclick="ContaSmartTour.endTour()">
                    Saltar Tour
                </button>
                <div class="d-flex gap-2 align-items-center">
                    ${!isFirst ? `<button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" onclick="ContaSmartTour.prevStep()"><i class="fa fa-chevron-left me-1"></i>Atrás</button>` : ''}
                    <button type="button" class="btn btn-sm btn-primary px-3 py-1 fw-bold shadow-sm" onclick="ContaSmartTour.nextStep()">
                        ${isLast ? '¡Comenzar a Usar! 🚀' : 'Siguiente <i class="fa fa-chevron-right ms-1"></i>'}
                    </button>
                </div>
            </div>
        `;

        popover.style.display = 'block';

        // 3. Algoritmo Anti-Superposición para calcular coordenadas
        const popRect = popover.getBoundingClientRect();
        const popHeight = popRect.height || 210;
        const popWidth = popRect.width || 380;

        if (vw < 768) {
            // FORMATO CELULAR: acoplado en los bordes para nunca tapar el elemento
            popover.style.left = '10px';
            popover.style.right = '10px';
            popover.style.width = 'calc(100vw - 20px)';

            // Si el elemento está en la parte inferior de la pantalla, fijar popover arriba
            if (rect.top > (vh / 2)) {
                popover.style.top = '72px';
                popover.style.bottom = 'auto';
            } else {
                // Si el elemento está en la parte superior, fijar popover abajo
                popover.style.top = 'auto';
                popover.style.bottom = '16px';
            }
        } else {
            // FORMATO ESCRITORIO / TABLET
            popover.style.right = 'auto';
            popover.style.width = '380px';

            const spaceBelow = vh - (rect.bottom + pad);
            const spaceAbove = rect.top - pad;
            let popTop = 0;
            let popLeft = rect.left + (rect.width / 2) - (popWidth / 2);

            // Determinar si cabe abajo o arriba sin tapar
            if (step.pos === 'top' && spaceAbove >= (popHeight + 80)) {
                popTop = rect.top - pad - popHeight - 12;
            } else if (spaceBelow >= (popHeight + 20)) {
                popTop = rect.bottom + pad + 12;
            } else if (spaceAbove >= (popHeight + 80)) {
                popTop = rect.top - pad - popHeight - 12;
            } else {
                // Elemento muy alto: fijar flotante en esquina inferior derecha para máxima visibilidad
                popTop = vh - popHeight - 20;
                popLeft = vw - popWidth - 20;
            }

            // Clamping horizontal dentro de los márgenes de pantalla
            if (popLeft + popWidth > vw - 20) {
                popLeft = vw - popWidth - 20;
            }
            if (popLeft < 20) {
                popLeft = 20;
            }

            popover.style.top = `${popTop}px`;
            popover.style.bottom = 'auto';
            popover.style.left = `${popLeft}px`;
        }
    }

    function handleWindowUpdate() {
        if (!isTourActive() || !activeTarget) return;
        const step = steps[currentStep];
        if (step) {
            renderSpotlightAndPopover(activeTarget, step, currentStep);
        }
    }

    function startAutoPlayTimer() {
        clearInterval(autoPlayTimer);
        const interval = 50;
        const bar = document.getElementById('tourTimerBarFill');

        autoPlayTimer = setInterval(() => {
            if (!isPaused) {
                elapsedMs += interval;
                if (bar) {
                    const pct = Math.min(100, (elapsedMs / STEP_DURATION) * 100);
                    bar.style.width = `${pct}%`;
                }

                if (elapsedMs >= STEP_DURATION) {
                    clearInterval(autoPlayTimer);
                    nextStep();
                }
            }
        }, interval);
    }

    function togglePlayPause() {
        isPaused = !isPaused;
        updatePlayPauseIcon();
    }

    function updatePlayPauseIcon() {
        const btn = document.getElementById('tourPlayPauseBtn');
        if (!btn) return;
        if (isPaused) {
            btn.innerHTML = `<i class="fa fa-play text-success me-1"></i><span>Reanudar</span>`;
        } else {
            btn.innerHTML = `<i class="fa fa-pause text-muted me-1"></i><span>Pausar</span>`;
        }
    }

    function handleKeydown(e) {
        if (!isTourActive()) return;

        if (e.key === 'Escape') {
            endTour();
        } else if (e.key === 'ArrowRight') {
            nextStep();
        } else if (e.key === 'ArrowLeft') {
            prevStep();
        } else if (e.key === ' ') {
            e.preventDefault();
            togglePlayPause();
        }
    }

    function nextStep() {
        currentStep++;
        if (currentStep >= steps.length) {
            endTour();
        } else {
            showStep(currentStep);
        }
    }

    function prevStep() {
        currentStep--;
        if (currentStep < 0) currentStep = 0;
        showStep(currentStep);
    }

    function endTour() {
        clearInterval(autoPlayTimer);
        if (activeTarget) {
            activeTarget.classList.remove('tour-highlighted-element');
            activeTarget = null;
        }
        if (spotlight) spotlight.style.display = 'none';
        if (popover) popover.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
        localStorage.setItem('contasmart_tour_completed', 'true');
    }

    function resetTour() {
        localStorage.removeItem('contasmart_tour_completed');
        startTour(true);
    }

    function goToStep(idx) {
        if (idx >= 0 && idx < steps.length) {
            showStep(idx);
        }
    }

    return {
        init,
        startTour,
        resetTour,
        goToStep,
        nextStep,
        prevStep,
        togglePlayPause,
        endTour
    };
})();

// Auto-inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    ContaSmartTour.init();
});
