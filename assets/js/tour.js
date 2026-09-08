/**
 * CONTA SMART - Tour Interactivo Guiado Autónomo para Nuevos Usuarios
 * "Solito se mueve, señala y describe cada sección con animación y avance automático"
 */

const ContaSmartTour = (() => {
    let currentStep = 0;
    let steps = [];
    let spotlight = null;
    let popover = null;
    let backdrop = null;
    let autoPlayTimer = null;
    let autoPlayProgress = null;
    let isPaused = false;
    const STEP_DURATION = 6000; // 6 segundos por paso

    function init() {
        createTourDOMElements();

        // Botones manuales de inicio de tour
        document.querySelectorAll('.btn-start-tour').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                startTour(true);
            });
        });

        // Auto-iniciar solo si es nuevo usuario y está en el Dashboard
        const isCompleted = localStorage.getItem('contasmart_tour_completed');
        const isDashboard = window.location.pathname.endsWith('index.php') || 
                            window.location.pathname.endsWith('/contahercar/') ||
                            window.location.pathname.endsWith('/contahercar');

        if (!isCompleted && isDashboard) {
            setTimeout(() => {
                startTour(false);
            }, 1200);
        }

        // Cerrar con tecla Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isTourActive()) {
                endTour();
            }
        });
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
            // Pausar auto-avance al pasar el mouse por encima
            popover.onmouseenter = () => { isPaused = true; };
            popover.onmouseleave = () => { isPaused = false; };
            document.body.appendChild(popover);
        }
    }

    function defineSteps() {
        return [
            {
                element: '#tourHeaderGreeting',
                title: '👋 Bienvenido a ContaSmart',
                desc: 'Tu plataforma inteligente de gestión contable, inventario en tiempo real y registros oficiales SUNAT SIRE.',
                icon: 'fa-sparkles text-warning',
                pos: 'bottom'
            },
            {
                element: '#tourKpiCards',
                title: '📊 Métricas Inteligentes en Tiempo Real',
                desc: 'Supervisa tus ventas del mes, gastos, margen de utilidad bruta real y alertas inmediatas de existencias por agotarse.',
                icon: 'fa-chart-pie text-primary',
                pos: 'bottom'
            },
            {
                element: '#tourQuickActions',
                title: '⚡ Acciones Rápidas con 1 Clic',
                desc: 'Factura rápidamente en el Punto de Venta (POS), registra compras, nuevos clientes o activa el comando de voz.',
                icon: 'fa-bolt text-warning',
                pos: 'bottom'
            },
            {
                element: '#tourChartsSection',
                title: '📈 Flujo de Caja & Salud de Stock',
                desc: 'Gráficos comparativos de ingresos vs egresos de los últimos 6 meses y donut de existencias en tiempo real.',
                icon: 'fa-chart-column text-success',
                pos: 'top'
            },
            {
                element: '#tourInventoryTable',
                title: '📦 Monitoreo Inteligente de Almacén',
                desc: 'Supervisión automática de stock actual vs mínimo, con botón directo de reposición para evitar desabastecimiento.',
                icon: 'fa-boxes-stacked text-info',
                pos: 'top'
            },
            {
                element: '#tourAiAssistantBtn',
                title: '🤖 Asistente ContaSmart IA & ContaVoz',
                desc: '¡Innovación única! Dicta transacciones con tu voz y la IA genera la propuesta de asientos contables del PCGE (Debe/Haber).',
                icon: 'fa-robot text-primary',
                pos: 'bottom'
            }
        ];
    }

    function isTourActive() {
        return popover && popover.style.display === 'block';
    }

    function startTour(manual = false) {
        // Cerrar asistente IA si está abierto para no colisionar
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

        if (index < 0 || index >= steps.length) {
            endTour();
            return;
        }

        const step = steps[index];
        const target = document.querySelector(step.element);
        if (!target) {
            nextStep();
            return;
        }

        // Scroll suave con offset superior para que la barra no tape el elemento
        const elementPosition = target.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - 90;
        window.scrollTo({
            top: Math.max(0, offsetPosition),
            behavior: 'smooth'
        });

        setTimeout(() => {
            const rect = target.getBoundingClientRect();
            const pad = 8;

            // Actualizar Spotlight con halo iluminado
            spotlight.style.display = 'block';
            spotlight.style.top = `${Math.max(0, rect.top - pad)}px`;
            spotlight.style.left = `${Math.max(0, rect.left - pad)}px`;
            spotlight.style.width = `${rect.width + pad * 2}px`;
            spotlight.style.height = `${rect.height + pad * 2}px`;

            // Construir tarjeta Popover
            const isLast = (index === steps.length - 1);
            const isFirst = (index === 0);

            popover.innerHTML = `
                <!-- Barra de progreso automático -->
                <div class="tour-timer-bar-wrap">
                    <div class="tour-timer-bar-fill" id="tourTimerBarFill"></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2 mt-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary text-white fw-bold px-2 py-1" style="font-size: 0.72rem;">
                            Paso ${index + 1} de ${steps.length}
                        </span>
                        <span class="small text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem;" id="tourAutoplayBadge">
                            <i class="fa fa-play text-success"></i> Auto-avance
                        </span>
                    </div>
                    <button type="button" class="btn-close btn-sm" onclick="ContaSmartTour.endTour()" title="Cerrar Tour (Esc)"></button>
                </div>

                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="fa ${step.icon} fs-5 mt-1"></i>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">${step.title}</h6>
                        <p class="text-secondary small mb-0 lh-sm">${step.desc}</p>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-2">
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none small" onclick="ContaSmartTour.endTour()">
                        Saltar Tour
                    </button>
                    <div class="d-flex gap-2 align-items-center">
                        ${!isFirst ? `<button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" onclick="ContaSmartTour.prevStep()">Atrás</button>` : ''}
                        <button type="button" class="btn btn-sm btn-primary px-3 py-1 fw-bold" onclick="ContaSmartTour.nextStep()">
                            ${isLast ? '¡Comenzar! 🚀' : 'Siguiente &rarr;'}
                        </button>
                    </div>
                </div>
            `;

            popover.style.display = 'block';

            // Calcular posición del popover inteligente
            const popRect = popover.getBoundingClientRect();
            let popTop = 0;
            let popLeft = 0;

            if (step.pos === 'bottom' || window.innerWidth <= 768) {
                popTop = rect.bottom + pad + 14;
                popLeft = Math.max(16, rect.left + (rect.width / 2) - (popRect.width / 2));
            } else if (step.pos === 'top') {
                popTop = rect.top - popRect.height - pad - 14;
                popLeft = Math.max(16, rect.left + (rect.width / 2) - (popRect.width / 2));
            } else {
                popTop = rect.bottom + pad + 14;
                popLeft = Math.max(16, rect.left);
            }

            // Asegurar que no se salga de los márgenes de la ventana
            if (popLeft + popRect.width > window.innerWidth - 20) {
                popLeft = window.innerWidth - popRect.width - 20;
            }
            if (popLeft < 20) popLeft = 20;

            if (popTop + popRect.height > window.innerHeight - 20) {
                popTop = rect.top - popRect.height - pad - 14;
            }
            if (popTop < 80) popTop = 80;

            popover.style.top = `${popTop}px`;
            popover.style.left = `${popLeft}px`;

            // Iniciar timer de auto-avance (solito se mueve)
            startAutoPlayTimer();
        }, 320);
    }

    function startAutoPlayTimer() {
        let elapsed = 0;
        const interval = 50;
        const bar = document.getElementById('tourTimerBarFill');

        isPaused = false;
        autoPlayTimer = setInterval(() => {
            if (!isPaused) {
                elapsed += interval;
                if (bar) {
                    const pct = Math.min(100, (elapsed / STEP_DURATION) * 100);
                    bar.style.width = `${pct}%`;
                }

                if (elapsed >= STEP_DURATION) {
                    clearInterval(autoPlayTimer);
                    nextStep();
                }
            }
        }, interval);
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
        if (spotlight) spotlight.style.display = 'none';
        if (popover) popover.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
        localStorage.setItem('contasmart_tour_completed', 'true');
    }

    return {
        init,
        startTour,
        nextStep,
        prevStep,
        endTour
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    ContaSmartTour.init();
});
