/**
 * CONTA SMART - Tour Interactivo Guiado para Nuevos Usuarios
 * Inspirado en onboarding profesional paso a paso con spotlight dinámico.
 */

const ContaSmartTour = (() => {
    let currentStep = 0;
    let steps = [];
    let spotlight = null;
    let popover = null;

    function init() {
        createTourElements();

        // Si el usuario hace clic en el botón de la barra superior
        const triggerBtns = document.querySelectorAll('.btn-start-tour');
        triggerBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                startTour(true);
            });
        });

        // Comprobar si es primera visita
        const tourDone = localStorage.getItem('contasmart_tour_seen');
        if (!tourDone && window.location.pathname.includes('index.php')) {
            setTimeout(() => {
                startTour(false);
            }, 1000);
        }
    }

    function createTourElements() {
        if (!spotlight) {
            spotlight = document.createElement('div');
            spotlight.className = 'tour-spotlight';
            spotlight.id = 'tourSpotlight';
            document.body.appendChild(spotlight);
        }

        if (!popover) {
            popover = document.createElement('div');
            popover.className = 'tour-popover';
            popover.id = 'tourPopover';
            document.body.appendChild(popover);
        }
    }

    function defineSteps() {
        return [
            {
                element: '#tourHeaderGreeting',
                title: '👋 Bienvenido a ContaSmart',
                desc: 'Tu sistema integral de gestión contable, inventario inteligente y registros electrónicos SUNAT SIRE adaptado para MYPES.',
                pos: 'bottom'
            },
            {
                element: '#tourKpiCards',
                title: '📊 Métricas Inteligentes en Tiempo Real',
                desc: 'Visualiza tus ventas del mes, gastos operativos, utilidad neta real y alertas preventivas de productos por agotarse.',
                pos: 'bottom'
            },
            {
                element: '#tourQuickActions',
                title: '⚡ Acciones Rápidas (Estilo Stock Mate)',
                desc: 'Accede en 1 solo clic al Punto de Venta (POS Móvil), registro de compras, nuevos clientes, inventario o activa el Asistente IA.',
                pos: 'bottom'
            },
            {
                element: '#tourChartsSection',
                title: '📈 Flujo de Caja & Salud de Inventario',
                desc: 'Analiza la evolución de tus ingresos vs compras mes a mes y supervisa la proporción de productos en stock óptimo o crítico.',
                pos: 'top'
            },
            {
                element: '#tourAiAssistantBtn',
                title: '🤖 ContaSmart IA & ContaVoz',
                desc: '¡La gran innovación! Usa comandos de voz para dictar ventas y compras, o escribe transacciones para que la IA genere el asiento contable (Debe/Haber).',
                pos: 'left'
            }
        ];
    }

    function startTour(force = false) {
        steps = defineSteps().filter(s => document.querySelector(s.element) !== null);
        if (steps.length === 0) return;
        currentStep = 0;
        showStep(currentStep);
    }

    function showStep(index) {
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

        // Scroll suave al elemento
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(() => {
            const rect = target.getBoundingClientRect();
            const padding = 8;

            // Actualizar Spotlight
            spotlight.style.display = 'block';
            spotlight.style.top = `${rect.top - padding}px`;
            spotlight.style.left = `${rect.left - padding}px`;
            spotlight.style.width = `${rect.width + padding * 2}px`;
            spotlight.style.height = `${rect.height + padding * 2}px`;

            // Construir contenido del Popover
            const isLast = (index === steps.length - 1);
            const isFirst = (index === 0);

            popover.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="tour-step-badge">Paso ${index + 1} de ${steps.length}</span>
                    <button type="button" class="btn-close btn-sm" onclick="ContaSmartTour.endTour()" aria-label="Cerrar"></button>
                </div>
                <h6 class="fw-bold text-dark mb-1">${step.title}</h6>
                <p class="text-muted small mb-3">${step.desc}</p>
                <div class="d-flex align-items-center justify-content-between">
                    <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none" onclick="ContaSmartTour.endTour()">
                        Saltar Tour
                    </button>
                    <div class="d-flex gap-2">
                        ${!isFirst ? `<button class="btn btn-sm btn-outline-secondary px-3" onclick="ContaSmartTour.prevStep()">Atrás</button>` : ''}
                        <button class="btn btn-sm btn-primary px-3 fw-bold" onclick="ContaSmartTour.nextStep()">
                            ${isLast ? '¡Comenzar! 🚀' : 'Siguiente'}
                        </button>
                    </div>
                </div>
            `;

            popover.style.display = 'block';

            // Posicionar Popover
            const popRect = popover.getBoundingClientRect();
            let popTop = 0;
            let popLeft = 0;

            if (step.pos === 'bottom' || window.innerWidth <= 768) {
                popTop = rect.bottom + padding + 12;
                popLeft = Math.max(16, rect.left + (rect.width / 2) - (popRect.width / 2));
            } else if (step.pos === 'top') {
                popTop = rect.top - popRect.height - padding - 12;
                popLeft = Math.max(16, rect.left + (rect.width / 2) - (popRect.width / 2));
            } else if (step.pos === 'left') {
                popTop = rect.top;
                popLeft = rect.left - popRect.width - padding - 12;
            } else {
                popTop = rect.bottom + padding + 12;
                popLeft = rect.left;
            }

            // Ajustes para no salirse de la pantalla
            if (popLeft + popRect.width > window.innerWidth - 16) {
                popLeft = window.innerWidth - popRect.width - 16;
            }
            if (popTop + popRect.height > window.innerHeight - 16) {
                popTop = window.innerHeight - popRect.height - 16;
            }
            if (popTop < 16) popTop = 16;

            popover.style.top = `${popTop}px`;
            popover.style.left = `${popLeft}px`;
        }, 300);
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
        if (spotlight) spotlight.style.display = 'none';
        if (popover) popover.style.display = 'none';
        localStorage.setItem('contasmart_tour_seen', 'true');
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
