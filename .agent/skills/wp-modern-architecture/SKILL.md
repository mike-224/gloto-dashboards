---
name: WordPress Modern Architecture
description: Forzar el uso de PHP 8.2+, Namespaces y patrones de diseño modernos.
version: 1.0.0
---
# Estándares
- Namespaces: PSR-4 obligatorio (Vendor\PluginName).
- Patrón Singleton: Clase principal con get_instance().
- Separación de Lógica: Lógica en clases, vistas en carpetas /templates.
- Strict Types: Siempre declarar strict_types=1.
