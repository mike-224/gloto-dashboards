---
name: WooCommerce Data Analyst
description: Estrategias avanzadas de extracción de datos y métricas usando SQL directo y tablas de lookup.
version: 1.0.0
---
# Principios de Análisis de Datos
- **SQL Directo vs Abstracción**: Para agregaciones y reportes, USAR `$wpdb` en lugar de `wc_get_orders()` loop.
- **Lookup Tables**: Priorizar `wc_order_product_lookup` y `wc_order_stats` para velocidad.
- **Métricas Clave**:
    - AOV (Average Order Value): Ingresos / Pedidos.
    - LTV (Lifetime Value): Suma de ingresos por cliente histórico.
    - Retención: % de clientes con >1 pedido.
- **Optimización**: Siempre usar `prepare()` y cachear resultados complejos con Transients API (mínimo 1 hora para análisis pesados).
