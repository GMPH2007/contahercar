window.STATIC_DB_VENTAS = {
    "4": {
        "success": true,
        "venta": {
            "id": 4,
            "tipo_comprobante": "Boleta",
            "serie": "B001",
            "correlativo": "000002",
            "fecha_venta": "07\/09\/2026 13:14",
            "cliente_nombre": "CLIENTE VARIOS \/ GENERAL",
            "cliente_doc": "00000000",
            "metodo_pago": "Efectivo",
            "estado": "COMPLETADA",
            "subtotal": 241.53,
            "subtotal_fmt": "S\/. 241.53",
            "impuesto": 43.47,
            "impuesto_fmt": "S\/. 43.47",
            "impuesto_nombre": "IGV",
            "total": 285,
            "total_fmt": "S\/. 285.00"
        },
        "items": [
            {
                "producto_id": 2,
                "producto_nombre": "Amoladora Angular 4-1\/2 pulg 850W Dewalt",
                "codigo_barra": "77501002",
                "unidad_medida": "UNID",
                "cantidad": 1,
                "precio_unitario": 285,
                "precio_unitario_fmt": "S\/. 285.00",
                "subtotal": 285,
                "subtotal_fmt": "S\/. 285.00"
            }
        ]
    },
    "2": {
        "success": true,
        "venta": {
            "id": 2,
            "tipo_comprobante": "Boleta",
            "serie": "B001",
            "correlativo": "000001",
            "fecha_venta": "07\/09\/2026 12:53",
            "cliente_nombre": "CLIENTE VARIOS \/ GENERAL",
            "cliente_doc": "00000000",
            "metodo_pago": "Efectivo",
            "estado": "COMPLETADA",
            "subtotal": 17.8,
            "subtotal_fmt": "S\/. 17.80",
            "impuesto": 3.2,
            "impuesto_fmt": "S\/. 3.20",
            "impuesto_nombre": "IGV",
            "total": 21,
            "total_fmt": "S\/. 21.00"
        },
        "items": [
            {
                "producto_id": 6,
                "producto_nombre": "Foco LED 12W Luz Blanca E27 Philips",
                "codigo_barra": "77501006",
                "unidad_medida": "UNID",
                "cantidad": 2,
                "precio_unitario": 10.5,
                "precio_unitario_fmt": "S\/. 10.50",
                "subtotal": 21,
                "subtotal_fmt": "S\/. 21.00"
            }
        ]
    }
};
window.STATIC_DB_COMPRAS = {
    "2": {
        "success": true,
        "compra": {
            "id": 2,
            "tipo_comprobante": "Factura",
            "serie_numero": "F001-000124",
            "fecha_compra": "08\/09\/2026",
            "proveedor_nombre": "CORPORACION DISTRIBUIDORA FERRETERA S.A.C.",
            "proveedor_ruc": "20100070970",
            "estado": "COMPLETADA",
            "subtotal": 350,
            "subtotal_fmt": "S\/. 350.00",
            "impuesto": 63,
            "impuesto_fmt": "S\/. 63.00",
            "impuesto_nombre": "IGV",
            "total": 413,
            "total_fmt": "S\/. 413.00",
            "observaciones": "Compra inicial de mercadería"
        },
        "items": [
            {
                "producto_id": 1,
                "producto_nombre": "Taladro Percutor 1\/2 pulg 650W Bosch",
                "codigo_barra": "77501001",
                "unidad_medida": "UNID",
                "cantidad": 2,
                "precio_unitario": 175,
                "precio_unitario_fmt": "S\/. 175.00",
                "subtotal": 350,
                "subtotal_fmt": "S\/. 350.00"
            }
        ]
    }
};
window.STATIC_DB_KARDEX = {
    "1": {
        "success": true,
        "producto": {
            "id": 1,
            "nombre": "Taladro Percutor 1\/2 pulg 650W Bosch",
            "codigo_barra": "77501001",
            "stock": 18,
            "precio_compra": "180.00",
            "precio_venta": "245.00",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 1,
                "producto_id": 1,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 18,
                "stock_anterior": 0,
                "stock_nuevo": 18,
                "precio_unitario": "180.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "2": {
        "success": true,
        "producto": {
            "id": 2,
            "nombre": "Amoladora Angular 4-1\/2 pulg 850W Dewalt",
            "codigo_barra": "77501002",
            "stock": 11,
            "precio_compra": "210.00",
            "precio_venta": "285.00",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 13,
                "producto_id": 2,
                "tipo_movimiento": "VENTA",
                "referencia_id": 4,
                "cantidad": 1,
                "stock_anterior": 12,
                "stock_nuevo": 11,
                "precio_unitario": "285.00",
                "motivo": "Venta Boleta B001-000002",
                "fecha": "2026-09-07 13:14:00"
            },
            {
                "id": 2,
                "producto_id": 2,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 12,
                "stock_anterior": 0,
                "stock_nuevo": 12,
                "precio_unitario": "210.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "3": {
        "success": true,
        "producto": {
            "id": 3,
            "nombre": "Juego de Llaves Combinadas 8-24mm Stanley (14 pzs)",
            "codigo_barra": "77501003",
            "stock": 25,
            "precio_compra": "75.00",
            "precio_venta": "115.00",
            "unidad_medida": "JGO"
        },
        "movimientos": [
            {
                "id": 3,
                "producto_id": 3,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 25,
                "stock_anterior": 0,
                "stock_nuevo": 25,
                "precio_unitario": "75.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "4": {
        "success": true,
        "producto": {
            "id": 4,
            "nombre": "Martillo Carpintero 16oz Mango Fibra Truper",
            "codigo_barra": "77501004",
            "stock": 30,
            "precio_compra": "22.00",
            "precio_venta": "36.00",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 4,
                "producto_id": 4,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 30,
                "stock_anterior": 0,
                "stock_nuevo": 30,
                "precio_unitario": "22.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "5": {
        "success": true,
        "producto": {
            "id": 5,
            "nombre": "Cinta Métrica 5m \/ 16ft Global Plus Stanley",
            "codigo_barra": "77501005",
            "stock": 4,
            "precio_compra": "14.50",
            "precio_venta": "24.00",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 5,
                "producto_id": 5,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 4,
                "stock_anterior": 0,
                "stock_nuevo": 4,
                "precio_unitario": "14.50",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "6": {
        "success": true,
        "producto": {
            "id": 6,
            "nombre": "Foco LED 12W Luz Blanca E27 Philips",
            "codigo_barra": "77501006",
            "stock": 58,
            "precio_compra": "6.20",
            "precio_venta": "10.50",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 11,
                "producto_id": 6,
                "tipo_movimiento": "VENTA",
                "referencia_id": 2,
                "cantidad": 2,
                "stock_anterior": 60,
                "stock_nuevo": 58,
                "precio_unitario": "10.50",
                "motivo": "Venta Boleta B001-000001",
                "fecha": "2026-09-07 12:53:09"
            },
            {
                "id": 6,
                "producto_id": 6,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 60,
                "stock_anterior": 0,
                "stock_nuevo": 60,
                "precio_unitario": "6.20",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "7": {
        "success": true,
        "producto": {
            "id": 7,
            "nombre": "Cable Mellizo 2x14 AWG Indeco (Rollo 100m)",
            "codigo_barra": "77501007",
            "stock": 3,
            "precio_compra": "115.00",
            "precio_venta": "160.00",
            "unidad_medida": "ROLLO"
        },
        "movimientos": [
            {
                "id": 7,
                "producto_id": 7,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 3,
                "stock_anterior": 0,
                "stock_nuevo": 3,
                "precio_unitario": "115.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    },
    "8": {
        "success": true,
        "producto": {
            "id": 8,
            "nombre": "Silicona Multiuso Transparente 280ml Tekbond",
            "codigo_barra": "77501008",
            "stock": 45,
            "precio_compra": "11.00",
            "precio_venta": "18.00",
            "unidad_medida": "UNID"
        },
        "movimientos": [
            {
                "id": 8,
                "producto_id": 8,
                "tipo_movimiento": "INVENTARIO_INICIAL",
                "referencia_id": null,
                "cantidad": 45,
                "stock_anterior": 0,
                "stock_nuevo": 45,
                "precio_unitario": "11.00",
                "motivo": "Carga inicial de inventario",
                "fecha": "2026-09-07 12:35:43"
            }
        ]
    }
};
