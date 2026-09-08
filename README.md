# ContaHerCar 🚀
### Sistema Inteligente de Gestión Contable, POS Móvil y SIRE SUNAT

[![Live Demo](https://img.shields.io/badge/DEMO%20EN%20VIVO-GitHub%20Pages-success?style=for-the-badge&logo=githubpages&logoColor=white)](https://gmph2007.github.io/contahercar/)
[![Run in Codespaces](https://img.shields.io/badge/EJECUTAR%20EN%20LA%20NUBE-GitHub%20Codespaces-blue?style=for-the-badge&logo=github)](https://github.com/codespaces/new?repo=GMPH2007/contahercar)
![Versión](https://img.shields.io/badge/Versi%C3%B3n-3.0.0%20ContaSmart-blueviolet?style=for-the-badge)
![SUNAT SIRE](https://img.shields.io/badge/SUNAT-SIRE%20RVIE%20%2F%20RCE-green?style=for-the-badge)
![Autor](https://img.shields.io/badge/Autor-GMPH2007%20%2F%20Misael%20Pintado-orange?style=for-the-badge&logo=github)

---

## 🌐 Enlaces Rápidos de Ejecución

* 🚀 **Probar la Web en Vivo (GitHub Pages):**  
  👉 **[https://gmph2007.github.io/contahercar/](https://gmph2007.github.io/contahercar/)**  
  *(Funciona directamente en tu celular, tablet o PC sin instalar nada: Siri de voz, POS rápido, gráficos y tour guiado).*

* ☁️ **Ejecutar el Servidor Completo en la Nube (Codespaces):**  
  👉 **[Abrir en GitHub Codespaces](https://github.com/codespaces/new?repo=GMPH2007/contahercar)**  
  *(Levanta Apache, PHP 8 y MySQL en 1 clic de forma autónoma).*

* 📁 **Repositorio Oficial:**  
  👉 **[https://github.com/GMPH2007/contahercar](https://github.com/GMPH2007/contahercar)**

---

## 📌 Marca de Agua y Créditos Oficiales

> **Desarrollado por:** **Gerson Misael Pintado Huaman**  
> **GitHub:** [@GMPH2007](https://github.com/GMPH2007)  
> **Organización / Marca:** **Misael Pintado Empresarial**  
> **Proyecto:** ContaHerCar - Sistema Integral de Contabilidad, Facturación, POS Móvil y Libros Electrónicos SUNAT SIRE.  
> **Todos los derechos reservados © 2026 Gerson Misael Pintado Huaman (GMPH2007).**

---

## 🌟 Descripción General

**ContaHerCar / CONTA SMART** es un sistema inteligente de gestión contable, comercial y financiera diseñado para micro, pequeñas y medianas empresas en el Perú. No solo registra datos, sino que **analiza, alerta y orienta al usuario** mediante un asistente inteligente con reconocimiento de voz (**CONTA VOZ**), alertas predictivas de negocio (**CONTA ALERTA**), propuestas automáticas de asientos contables según el PCGE, un **Tour Interactivo Guiado** de bienvenida y la emisión contable oficial exigida por la **SUNAT** (SIRE RVIE/RCE).

---

## ⚡ Módulos y Funcionalidades Destacadas

### 1. 🤖 CONTA SMART: Asistente Contable IA & Comandos de Voz (CONTA VOZ)
* **Reconocimiento de Voz en Tiempo Real (CONTA VOZ):**
  * Dictado de operaciones comerciales mediante el micrófono: *"Hoy vendí 3 amoladoras por 855 soles"* o *"Compré mercadería por 1500 en efectivo"*.
  * Procesamiento en lenguaje natural y cálculo automático de Base Imponible e IGV (18%).
* **Generación Automática de Asientos Contables (PCGE):**
  * Propuesta detallada de cuentas contables (Cuenta 601 Mercaderías, 4011 IGV, 421 Facturas por Pagar, 121 Cobranzas, 101 Caja y asientos de destino 201/611).
* **CONTA ALERTA (Sistema Predictivo):**
  * Detección preventiva de stock crítico y productos sin rotación.
  * Supervisión de liquidez y márgenes comerciales brutos.
  * Alertas de cumplimiento de cronograma tributario SIRE SUNAT.

### 2. 💡 Tour Interactivo Guiado para Nuevos Usuarios (Onboarding Tour)
* Guía interactiva paso a paso con efecto *spotlight* animado que resalta y enseña cada función clave:
  * Saludo y control ejecutivo.
  * Tarjetas KPI inteligentes con tendencias de variación.
  * Cuadrícula de acciones rápidas para operar en 1 clic (estilo *Stock Mate*).
  * Flujo de caja y gráfico Donut de salud de inventario (estilo *Invento*).
  * Asistente IA y comandos de voz.
* Botón siempre accesible en la cabecera: `[ 💡 Tour ]` para reiniciar el recorrido cuando se desee.

### 3. 🏛️ Módulo SIRE SUNAT (Libros Electrónicos Oficiales)
* **RVIE (Registro de Ventas e Ingresos Electrónico - Libro 140400)**:
  * Generación de la propuesta local con estructura oficial de 33 campos delimitados por barra (`|`).
  * Generación y validación del **CAR (Código de Anotación de Registro)** de 27 dígitos.
  * Exportación en archivo plano `.txt` y archivo comprimido `.zip` con la nomenclatura oficial:  
    `LE{RUC}{YYYYMM}00140400021111_1.zip`
* **RCE (Registro de Compras Electrónico - Libro 080400)**:
  * Resumen y detalle de adquisiciones gravadas destinadas a operaciones gravadas y no gravadas.
  * Estructura completa de 38 campos con desglose de Base Imponible, IGV, No Gravado e Impuestos.
  * Exportación oficial: `LE{RUC}{YYYYMM}00080400021111_1.zip`.
* **Conexión API SIRE SUNAT**:
  * Configuración de credenciales Clave SOL y Client ID / Secret desde el panel de control.
  * Autenticación OAuth2 contra los servidores de SUNAT (`https://api-seguridad.sunat.gob.pe`).
* **Enlaces Oficiales en Nueva Pestaña (target="_blank"):**
  * Accesos directos integrados a los portales oficiales de SUNAT SIRE, Consulta RUC SUNAT y RENIEC.

### 4. 📱 Formato y Experiencia Móvil Adaptativa (Smartphone / Tablet)
* **Punto de Venta (POS) Táctil**:
  * Alternador de vistas móviles `[Catálogo de Productos]` y `[Carrito de Compra (N)]`.
  * Barra de cobro flotante inferior (`.pos-mobile-bar`) con total en soles y acceso rápido a pagar.
  * Teclados numéricos directos (`inputmode="numeric"`) en cantidad, búsqueda de comprobantes y pagos para agilizar el despacho en teléfonos.
* **Menú Lateral Móvil**:
  * Botón de cierre `(X)` integrado y fondo oscuro difuminado (*backdrop*) que previene toques no deseados y se oculta al navegar.
* **Tablas Responsivas**:
  * Desplazamiento horizontal fluido con scroll táctil sin deformar datos ni botones de acción.

### 3. 🔍 Consulta RUC y DNI SUNAT / RENIEC en Vivo
* Búsqueda instantánea de RUC de 11 dígitos y DNI de 8 dígitos.
* Autocompletado inmediato de Razón Social / Nombres, Condición de Domicilio (HABIDO / NO HABIDO), Estado de Contribuyente (ACTIVO / BAJA) y Ubigeo completo.
* Conexión resiliente a API Decolecta con fallback seguro.
* Botones de consulta rápida integrados en los formularios de Clientes, Proveedores y Compras.

### 4. 🛒 Punto de Venta (POS) y Facturación
* Registro de ventas por Boleta, Factura o Ticket de venta interna.
* Búsqueda dinámica de productos por nombre, código de barras o categoría.
* Control de stock en tiempo real con validación preventiva de sobreventa.
* Emisión e impresión de tickets térmicos formateados en 80mm / 58mm.

### 5. 📦 Inventario y Compras
* Catálogo de productos con precios de costo, precios de venta, stock actual y stock mínimo.
* Registro de facturas y comprobantes de compra que alimentan automáticamente el RCE del SIRE.
* Actualización inmediata del stock al registrar adquisiciones.

### 6. 📊 Reportes y Dashboard Ejecutivo
* Gráficos interactivos de ventas mensuales, productos más vendidos y márgenes brutos.
* KPIs en tiempo real de ingresos del día, compras, cuentas por cobrar y stock bajo.

---

## 🛠️ Requisitos del Sistema

* **Servidor Web:** Apache (XAMPP, WampServer, Laragon o Linux LAMP)
* **PHP:** Versión 8.0 o superior
* **Extensiones PHP requeridas:**
  * `pdo_mysql`
  * `curl`
  * `zip`
  * `mbstring`
  * `json`
* **Base de Datos:** MySQL 5.7+ o MariaDB 10.4+

---

## 📥 Instalación y Puesta en Marcha

### 1. Clonar o Descargar el Repositorio
```bash
git clone https://github.com/GMPH2007/contahercar.git
```
O descargue y copie los archivos en su directorio web:
`C:\xampp\htdocs\contahercar\`

### 2. Crear y Restaurar la Base de Datos
1. Inicie **Apache** y **MySQL** desde el Panel de Control de XAMPP.
2. Abra **phpMyAdmin** en `http://localhost/phpmyadmin/`.
3. Cree una base de datos llamada `contahercar_db` con cotejamiento `utf8mb4_unicode_ci`.
4. Importe el archivo `database.sql` incluido en la raíz del proyecto.

### 3. Configuración de Conexión
Verifique los datos de conexión en el archivo `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'contahercar_db');
```

### 4. Acceder a la Aplicación
Abra su navegador web favorito e ingrese a:
`http://localhost/contahercar/`

---

## 📂 Estructura del Proyecto

```
contahercar/
├── api/
│   ├── buscar_por_doc.php     # Endpoint de consulta DNI/RUC con Decolecta API
│   ├── compra_detalle.php     # Detalle modal de adquisiciones
│   ├── consulta_ruc.php       # Búsqueda rápida de RUC SUNAT
│   ├── kardex_info.php        # Movimientos de kardex y stock
│   ├── productos_search.php   # Búsqueda en vivo de catálogo
│   ├── sire_api.php           # Conector OAuth2 y gestor del SIRE SUNAT
│   ├── sire_export.php        # Generador de libros RVIE/RCE (TXT y ZIP)
│   ├── tipo_cambio.php        # Consulta de tipo de cambio SBS/SUNAT
│   └── venta_detalle.php      # Detalle modal de ventas
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos personalizados, modo oscuro y responsive móvil
│   └── js/
│       └── main.js            # Lógica global, backdrop móvil y listeners
├── config/
│   ├── app.php                # Constantes globales y buffer de salida ob_start()
│   └── db.php                 # Conexión PDO segura y auto-instalación
├── includes/
│   ├── footer.php             # Pie de página y scripts
│   ├── header.php             # Barra superior, botón hamburguesa y estilos
│   └── sidebar.php            # Navegación principal y botón de cierre móvil
├── clientes.php               # Gestión y búsqueda de clientes
├── compras.php                # Listado de compras registradas
├── compra_nueva.php           # Registro de compras con proveedor y comprobante
├── configuracion.php          # Datos de empresa y tokens de API
├── consulta_sunat.php         # Interfaz dedicada de consulta RUC/DNI
├── database.sql               # Respaldo completo de la estructura y datos
├── index.php                  # Dashboard interactivo y métricas
├── inventario.php             # Control de catálogo y existencias
├── proveedores.php            # Registro y búsqueda de proveedores
├── reportes.php               # Análisis contable y gráficos financieros
├── sire.php                   # Módulo oficial SIRE SUNAT (RVIE y RCE)
├── ticket.php                 # Formato de impresión térmica de ventas
├── ventas.php                 # Historial de ventas emitidas
└── venta_nueva.php            # Punto de Venta (POS) responsivo móvil/desktop
```

---

## 👤 Autor y Marca de Agua

* **Autor:** Gerson Misael Pintado Huaman
* **Perfil de GitHub:** [@GMPH2007](https://github.com/GMPH2007)
* **Empresa:** Misael Pintado Empresarial
* **Repositorio:** [https://github.com/GMPH2007/contahercar](https://github.com/GMPH2007/contahercar)

---
*Desarrollado con dedicación por GMPH2007.*
