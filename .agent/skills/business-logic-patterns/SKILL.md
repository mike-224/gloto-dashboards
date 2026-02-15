---
name: Business Logic Patterns
description: Algoritmos específicos para crecimiento (Growth), optimización y comportamiento de usuario.
version: 1.0.0
---
# Algoritmos de Crecimiento
- **Análisis de Combos**: Detectar pares de productos frecuentes (Apriori simplificado).
    - *Insight*: "Comprados juntos frecuentemente".
- **Ladders (Escaleras)**: Identificar flujos de upgrade (Producto A → Producto B donde Precio B > Precio A).
    - *Acción*: Sugerir upgrade tras X días.
- **Segmentación RFM**: Clasificar clientes por Recencia, Frecuencia y Monetización (usando percentiles).
    - *Segmentos*: VIPs, Alto Valor, Riesgo de Churn.
- **Detección de Anomalías**: Stock bajo en top ventas, caídas bruscas de AOV.
