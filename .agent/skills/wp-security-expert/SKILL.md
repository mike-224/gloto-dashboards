---
name: WordPress Security Expert
description: Auditoría y aplicación de estándares de seguridad críticos para plugins de WP.
version: 1.0.0
---
# Directivas de Seguridad
- Validación de Intención: Verifica Nonces usando check_admin_referer() o wp_verify_nonce().
- Control de Acceso: Comprobación obligatoria de current_user_can().
- Sanitización: Usa sanitize_text_field(), absint(), etc.
- Escapado: Todo output debe pasar por esc_html(), esc_attr() o wp_kses().
