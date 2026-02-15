<?php
/**
 * =========================================================================
 * WIDGET: SALUD DE PAGOS (Versión Anti-Duplicados)
 * Corrige el problema de contar múltiples intentos del mismo pedido
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Glotomania_Payment_Health_Widget {
    
    private $cache_key = 'glt_payment_health_v3'; // Nueva versión
    private $cache_time = 1800; // 30 minutos
    
    // Umbrales
    private $normal_failure_rate = 10; 
    private $critical_failure_rate = 18; 
    
    /**
     * Iniciar el widget
     */
    public static function init() {
        add_action('wp_dashboard_setup', [__CLASS__, 'register_widget']);
        add_action('admin_init', [__CLASS__, 'handle_refresh']);
    }

    /**
     * Registrar el widget en el dashboard
     */
    public static function register_widget() {
        if (!current_user_can('manage_woocommerce')) return;
        
        wp_add_dashboard_widget(
            'glt_payment_health_widget',
            '💳 Salud de Pagos (Pedidos Únicos)',
            [__CLASS__, 'render']
        );
    }

    /**
     * Manejar la acción de refrescar caché
     */
    public static function handle_refresh() {
        if (isset($_GET['glt_refresh_payment_health']) && check_admin_referer('glt_refresh_nonce', '_glt_nonce')) {
            $instance = new self();
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg(['glt_refresh_payment_health', '_glt_nonce']));
            exit;
        }
    }
    
    /**
     * Renderizar el widget
     */
    public static function render() {
        $instance = new self();
        
        // Obtener datos (con caché)
        if (false === ($output = get_transient($instance->cache_key))) {
            ob_start();
            $instance->render_content();
            $output = ob_get_clean();
            set_transient($instance->cache_key, $output, $instance->cache_time);
        }
        
        echo $output;
    }
    
    /**
     * Renderizar contenido HTML
     */
    private function render_content() {
        // Verificar dependencia
        if (!class_exists('WooCommerce')) {
            echo '<div class="glt-payment-health-no-data">WooCommerce no está activo.</div>';
            return;
        }

        $metrics = $this->calculate_metrics_unique_orders();
        
        // Si no hay datos
        if ($metrics['total_unique_orders'] === 0) {
            $this->render_styles();
            echo '<div class="glt-payment-health-widget">';
            echo '<div class="glt-payment-health-no-data">No hay datos de pagos en los últimos 30 días.</div>';
            $this->render_refresh_button();
            echo '</div>';
            return;
        }

        $health_status = $this->get_health_status($metrics);
        
        ?>
        <div class="glt-payment-health-widget">
            
            <!-- Header con estado general -->
            <div class="glt-payment-health-header <?php echo $health_status['class']; ?>">
                <div class="glt-payment-health-status-icon"><?php echo $health_status['icon']; ?></div>
                <div class="glt-payment-health-status-content">
                    <div class="glt-payment-health-status-title"><?php echo $health_status['title']; ?></div>
                    <div class="glt-payment-health-status-subtitle"><?php echo $health_status['subtitle']; ?></div>
                </div>
            </div>
            
            <!-- Métrica principal -->
            <div class="glt-payment-health-main">
                <div class="glt-payment-health-metric-large">
                    <div class="glt-payment-health-label">TASA DE FALLOS REAL (30 días)</div>
                    <div class="glt-payment-health-value <?php echo $this->get_failure_rate_class($metrics['failure_rate']); ?>">
                        <?php echo round($metrics['failure_rate'], 1); ?>%
                    </div>
                    <div class="glt-payment-health-benchmark">
                        Normal: <10% | Pedidos analizados: <?php echo $metrics['total_unique_orders']; ?>
                        <?php if($metrics['retry_attempts'] > 0): ?>
                        <br><small style="color:#999;">⚡ <?php echo $metrics['retry_attempts']; ?> reintentos detectados (excluidos del cálculo)</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="glt-payment-health-bar">
                        <div class="glt-payment-health-bar-fill <?php echo $this->get_failure_rate_class($metrics['failure_rate']); ?>" 
                             style="width: <?php echo min(100, $metrics['failure_rate']); ?>%">
                        </div>
                    </div>
                </div>
                
                <?php if ($metrics['lost_revenue'] > 0): ?>
                    <div class="glt-payment-health-lost-revenue">
                        <span class="glt-payment-health-lost-icon">💸</span>
                        <div>
                            <div class="glt-payment-health-lost-label">Revenue perdido (pedidos fallidos finales)</div>
                            <div class="glt-payment-health-lost-amount"><?php echo wc_price($metrics['lost_revenue']); ?></div>
                            <div class="glt-payment-health-lost-detail">
                                <?php echo $metrics['failed_orders']; ?> pedidos que no se recuperaron
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Nueva métrica: Tasa de recuperación -->
                <?php if ($metrics['recovery_rate'] > 0): ?>
                    <div class="glt-payment-health-recovery-box">
                        <div class="glt-payment-health-recovery-icon">🔄</div>
                        <div>
                            <div class="glt-payment-health-recovery-label">Tasa de Recuperación</div>
                            <div class="glt-payment-health-recovery-value">
                                <?php echo round($metrics['recovery_rate'], 1); ?>%
                            </div>
                            <div class="glt-payment-health-recovery-detail">
                                <?php echo $metrics['recovered_orders']; ?> de <?php echo $metrics['failed_orders'] + $metrics['recovered_orders']; ?> pedidos se recuperaron tras fallo inicial
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Desglose por método -->
            <div class="glt-payment-health-breakdown">
                <div class="glt-payment-health-section-title">
                    <span class="dashicons dashicons-admin-generic"></span> Desglose por Método de Pago
                </div>
                
                <?php if (!empty($metrics['payment_methods'])): ?>
                    <div class="glt-payment-health-methods">
                        <?php foreach ($metrics['payment_methods'] as $method): ?>
                            <div class="glt-payment-health-method-card <?php echo $method['has_issue'] ? 'has-issue' : ''; ?>">
                                <div class="glt-payment-health-method-header">
                                    <span class="glt-payment-health-method-name">
                                        <?php echo $this->get_payment_icon($method['method']); ?>
                                        <?php echo esc_html($method['title']); ?>
                                    </span>
                                    <span class="glt-payment-health-method-count">
                                        <?php echo $method['total_unique_orders']; ?> pedidos
                                    </span>
                                </div>
                                
                                <div class="glt-payment-health-method-stats">
                                    <div class="glt-payment-health-method-row">
                                        <span>Tasa fallo:</span>
                                        <strong class="<?php echo $this->get_failure_rate_class($method['failure_rate']); ?>">
                                            <?php echo round($method['failure_rate'], 1); ?>%
                                        </strong>
                                    </div>
                                    <div class="glt-payment-health-method-row">
                                        <span>Exitosos:</span>
                                        <strong class="success"><?php echo $method['successful']; ?></strong>
                                    </div>
                                    <div class="glt-payment-health-method-row">
                                        <span>Fallidos:</span>
                                        <strong><?php echo $method['failed']; ?></strong>
                                    </div>
                                    <?php if ($method['retries'] > 0): ?>
                                        <div class="glt-payment-health-method-row">
                                            <span>Reintentos:</span>
                                            <strong style="color:#999;"><?php echo $method['retries']; ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($method['has_issue']): ?>
                                    <div class="glt-payment-health-method-alert">
                                        ⚠️ <?php echo $method['issue_message']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Análisis de Reintentos -->
            <?php if ($metrics['retry_attempts'] > 0): ?>
                <div class="glt-payment-health-retry-analysis">
                    <div class="glt-payment-health-section-title">
                        <span class="dashicons dashicons-backup"></span> Análisis de Reintentos
                    </div>
                    
                    <div class="glt-payment-health-retry-grid">
                        <div class="glt-payment-health-retry-stat">
                            <div class="glt-payment-health-retry-stat-value"><?php echo $metrics['retry_attempts']; ?></div>
                            <div class="glt-payment-health-retry-stat-label">Total de reintentos detectados</div>
                        </div>
                        
                        <div class="glt-payment-health-retry-stat">
                            <div class="glt-payment-health-retry-stat-value"><?php echo round($metrics['avg_retries_per_order'], 1); ?></div>
                            <div class="glt-payment-health-retry-stat-label">Reintentos promedio por pedido</div>
                        </div>
                        
                        <div class="glt-payment-health-retry-stat">
                            <div class="glt-payment-health-retry-stat-value"><?php echo round($metrics['recovery_rate'], 1); ?>%</div>
                            <div class="glt-payment-health-retry-stat-label">Tasa de éxito tras reintentos</div>
                        </div>
                    </div>
                    
                    <div class="glt-payment-health-retry-insight">
                        💡 <strong>Insight:</strong> <?php echo $this->get_retry_insight($metrics); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Recomendaciones -->
            <?php 
            $recommendations = $this->get_recommendations($metrics);
            if (!empty($recommendations)): 
            ?>
                <div class="glt-payment-health-recommendations">
                    <div class="glt-payment-health-section-title">
                        <span class="dashicons dashicons-lightbulb"></span> Acciones Recomendadas
                    </div>
                    <ul class="glt-payment-health-recommendations-list">
                        <?php foreach ($recommendations as $rec): ?>
                            <li class="glt-payment-health-recommendation-item <?php echo $rec['priority']; ?>">
                                <span class="glt-payment-health-recommendation-icon"><?php echo $rec['icon']; ?></span>
                                <div class="glt-payment-health-recommendation-content">
                                    <strong><?php echo $rec['title']; ?></strong>
                                    <p><?php echo $rec['description']; ?></p>
                                    <?php if (!empty($rec['action_url'])): ?>
                                        <a href="<?php echo esc_url($rec['action_url']); ?>" class="button button-small">
                                            <?php echo $rec['action_label']; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php $this->render_styles(); ?>
            <?php $this->render_refresh_button(); ?>
        </div>
        <?php
    }
    
    /**
     * CÁLCULO MEJORADO: Agrupa por pedido único y detecta reintentos
     * 
     * Estrategia:
     * 1. Obtiene todos los pedidos de los últimos 30 días
     * 2. Agrupa por customer_id + order_total + fecha (mismo día) para detectar duplicados
     * 3. Solo cuenta el ÚLTIMO intento de cada grupo como resultado final
     * 4. Los intentos previos se cuentan como "reintentos" pero no afectan la tasa de fallo
     */
    private function calculate_metrics_unique_orders() {
        global $wpdb;

        $date_30_days = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Estados a considerar
        $success_statuses = ['wc-completed', 'wc-processing', 'wc-on-hold'];
        $failed_statuses = ['wc-failed', 'wc-cancelled'];
        
        // Obtener TODOS los pedidos con información clave
        $sql = "
            SELECT 
                p.ID as order_id,
                p.post_status as status,
                p.post_date as date,
                DATE(p.post_date) as date_only,
                pm_customer.meta_value as customer_id,
                pm_total.meta_value as order_total,
                pm_payment.meta_value as payment_method
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_payment_method'
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND p.post_status IN ('" . implode("','", array_merge($success_statuses, $failed_statuses)) . "')
            ORDER BY p.post_date ASC
        ";
        
        $all_orders = $wpdb->get_results($wpdb->prepare($sql, $date_30_days));
        
        if (empty($all_orders)) {
            return [
                'total_unique_orders' => 0,
                'failure_rate' => 0,
                'lost_revenue' => 0,
                'payment_methods' => [],
                'retry_attempts' => 0
            ];
        }
        
        // Agrupar pedidos potencialmente duplicados
        $order_groups = [];
        
        foreach ($all_orders as $order) {
            $customer_id = $order->customer_id ?: 'guest_' . $order->order_id;
            $order_total = round((float)$order->order_total, 2);
            $date_only = $order->date_only;
            
            // Clave única: cliente + monto + fecha
            // Esto agrupa pedidos del mismo cliente, mismo monto, mismo día
            $group_key = $customer_id . '_' . $order_total . '_' . $date_only;
            
            if (!isset($order_groups[$group_key])) {
                $order_groups[$group_key] = [];
            }
            
            $order_groups[$group_key][] = $order;
        }
        
        // Analizar cada grupo y tomar el ÚLTIMO pedido como resultado final
        $unique_orders = [];
        $retry_count = 0;
        
        foreach ($order_groups as $group) {
            $attempts_count = count($group);
            
            // El último pedido del grupo es el resultado final
            $final_order = end($group);
            $final_order->is_retry_success = false;
            
            // Si hay más de 1 intento, contar los anteriores como reintentos
            if ($attempts_count > 1) {
                $retry_count += ($attempts_count - 1);
                
                // Detectar si se recuperó tras fallo
                $had_failure = false;
                foreach ($group as $attempt) {
                    if (in_array($attempt->status, $failed_statuses)) {
                        $had_failure = true;
                        break;
                    }
                }
                
                if ($had_failure && in_array($final_order->status, $success_statuses)) {
                    $final_order->is_retry_success = true;
                }
            }
            
            $unique_orders[] = $final_order;
        }
        
        // Calcular métricas sobre pedidos únicos
        $successful = 0;
        $failed = 0;
        $recovered = 0;
        $lost_revenue = 0;
        $payment_methods_data = [];
        
        foreach ($unique_orders as $order) {
            $method = $order->payment_method ?: 'unknown';
            $is_success = in_array($order->status, $success_statuses);
            $is_failed = in_array($order->status, $failed_statuses);
            
            // Inicializar método si no existe
            if (!isset($payment_methods_data[$method])) {
                $payment_methods_data[$method] = [
                    'method' => $method,
                    'title' => ucfirst($method),
                    'successful' => 0,
                    'failed' => 0,
                    'total_unique_orders' => 0,
                    'lost_revenue' => 0,
                    'retries' => 0
                ];
            }
            
            $payment_methods_data[$method]['total_unique_orders']++;
            
            if ($is_success) {
                $successful++;
                $payment_methods_data[$method]['successful']++;
                
                if (isset($order->is_retry_success) && $order->is_retry_success) {
                    $recovered++;
                }
            } elseif ($is_failed) {
                $failed++;
                $lost_revenue += (float)$order->order_total;
                $payment_methods_data[$method]['failed']++;
                $payment_methods_data[$method]['lost_revenue'] += (float)$order->order_total;
            }
        }
        
        // Calcular tasa de fallo real
        $total_unique = $successful + $failed;
        $failure_rate = $total_unique > 0 ? ($failed / $total_unique) * 100 : 0;
        
        // Tasa de recuperación
        $recovery_rate = $failed > 0 ? ($recovered / ($failed + $recovered)) * 100 : 0;
        
        // Reintentos promedio
        $avg_retries = $total_unique > 0 ? $retry_count / $total_unique : 0;
        
        // Procesar métodos de pago
        $payment_methods = [];
        foreach ($payment_methods_data as $key => $data) {
            // Obtener título legible
            $gateways = WC()->payment_gateways->payment_gateways();
            $gateway = isset($gateways[$key]) ? $gateways[$key] : null;
            $data['title'] = $gateway ? $gateway->get_title() : ucfirst(str_replace('_', ' ', $key));
            
            $m_total = $data['successful'] + $data['failed'];
            $m_rate = $m_total > 0 ? ($data['failed'] / $m_total) * 100 : 0;
            
            $data['failure_rate'] = $m_rate;
            $data['has_issue'] = $m_rate > $this->normal_failure_rate && $data['failed'] > 2;
            $data['issue_message'] = ($m_rate > $this->critical_failure_rate) ? 'Tasa crítica - revisar urgente' : 'Monitorear de cerca';
            
            $payment_methods[] = $data;
        }
        
        // Ordenar por tasa de fallo
        usort($payment_methods, function($a, $b) {
            return $b['failure_rate'] <=> $a['failure_rate'];
        });

        return [
            'failure_rate' => $failure_rate,
            'total_unique_orders' => $total_unique,
            'successful_orders' => $successful,
            'failed_orders' => $failed,
            'recovered_orders' => $recovered,
            'recovery_rate' => $recovery_rate,
            'lost_revenue' => $lost_revenue,
            'payment_methods' => $payment_methods,
            'retry_attempts' => $retry_count,
            'avg_retries_per_order' => $avg_retries
        ];
    }
    
    /**
     * Generar insight sobre reintentos
     */
    private function get_retry_insight($metrics) {
        if ($metrics['recovery_rate'] > 50) {
            return "Excelente! Más del 50% de los fallos se recuperan con reintentos. El sistema de pagos funciona bien.";
        } elseif ($metrics['recovery_rate'] > 30) {
            return "Buen índice de recuperación. Los reintentos están funcionando, pero hay margen de mejora.";
        } elseif ($metrics['recovery_rate'] > 10) {
            return "Recuperación moderada. Considera implementar emails automáticos para animar a reintentar.";
        } else {
            return "Baja recuperación tras fallos. Los clientes abandonan después del primer error. Revisa UX del checkout.";
        }
    }

    private function get_health_status($metrics) {
        $rate = $metrics['failure_rate'];
        if ($rate < 5) return ['class'=>'excellent', 'icon'=>'✅', 'title'=>'Salud Excelente', 'subtitle'=>'Sistema de pagos óptimo'];
        if ($rate < $this->normal_failure_rate) return ['class'=>'good', 'icon'=>'👍', 'title'=>'Salud Buena', 'subtitle'=>'Tasa de fallos dentro de lo normal'];
        if ($rate < $this->critical_failure_rate) return ['class'=>'warning', 'icon'=>'⚠️', 'title'=>'Requiere Atención', 'subtitle'=>'Tasa de fallos elevada'];
        return ['class'=>'critical', 'icon'=>'🚨', 'title'=>'Situación Crítica', 'subtitle'=>'Revisar pasarelas urgentemente'];
    }

    private function get_failure_rate_class($rate) {
        if ($rate < 5) return 'success';
        if ($rate < $this->normal_failure_rate) return 'good';
        if ($rate < $this->critical_failure_rate) return 'warning';
        return 'critical';
    }

    private function get_payment_icon($method) {
        $icons = [
            'stripe' => '💳',
            'paypal' => '💙',
            'redsys' => '🔴',
            'bizum' => '📱',
            'cod' => '💵',
            'bacs' => '🏦',
            'cheque' => '📝'
        ];
        
        foreach ($icons as $key => $icon) {
            if (stripos($method, $key) !== false) return $icon;
        }
        return '💳';
    }

    private function get_recommendations($metrics) {
        $recs = [];
        
        // Recomendación crítica por tasa alta
        if ($metrics['failure_rate'] > $this->critical_failure_rate) {
            $recs[] = [
                'priority' => 'critical',
                'icon' => '🚨',
                'title' => 'Tasa de fallos crítica: ' . round($metrics['failure_rate'], 1) . '%',
                'description' => 'La tasa de fallos está en niveles críticos. Verifica inmediatamente los logs de WooCommerce y contacta con tus proveedores de pago.',
                'action_url' => admin_url('admin.php?page=wc-status&tab=logs'),
                'action_label' => 'Ver Logs'
            ];
        }
        
        // Recomendación por revenue perdido
        if ($metrics['lost_revenue'] > 500) {
            $recs[] = [
                'priority' => 'high',
                'icon' => '💰',
                'title' => 'Recuperar ' . wc_price($metrics['lost_revenue']) . ' en pedidos fallidos',
                'description' => 'Tienes ' . $metrics['failed_orders'] . ' pedidos fallidos. Contacta a estos clientes con un enlace de pago o cupón de descuento.',
                'action_url' => admin_url('edit.php?post_type=shop_order&post_status=wc-failed'),
                'action_label' => 'Ver Pedidos Fallidos'
            ];
        }
        
        // Recomendación por baja recuperación
        if ($metrics['retry_attempts'] > 0 && $metrics['recovery_rate'] < 30) {
            $recs[] = [
                'priority' => 'medium',
                'icon' => '📧',
                'title' => 'Baja tasa de recuperación: ' . round($metrics['recovery_rate'], 1) . '%',
                'description' => 'Los clientes tienen dificultades para completar el pago tras un fallo. Implementa emails automáticos de recuperación y simplifica el proceso de reintento.',
                'action_url' => '',
                'action_label' => ''
            ];
        }
        
        // Recomendación por muchos reintentos
        if ($metrics['avg_retries_per_order'] > 2) {
            $recs[] = [
                'priority' => 'medium',
                'icon' => '🔄',
                'title' => 'Demasiados reintentos por pedido: ' . round($metrics['avg_retries_per_order'], 1),
                'description' => 'Los clientes necesitan múltiples intentos para completar el pago. Esto indica problemas de UX o validación en el checkout. Simplifica el formulario y mejora los mensajes de error.',
                'action_url' => '',
                'action_label' => ''
            ];
        }
        
        // Recomendación positiva
        if ($metrics['failure_rate'] < 5 && $metrics['recovery_rate'] > 40) {
            $recs[] = [
                'priority' => 'success',
                'icon' => '✅',
                'title' => 'Sistema de pagos saludable',
                'description' => 'Tasa de fallos de ' . round($metrics['failure_rate'], 1) . '% y recuperación del ' . round($metrics['recovery_rate'], 1) . '%. Continúa monitoreando para mantener estos niveles.',
                'action_url' => '',
                'action_label' => ''
            ];
        }
        
        return $recs;
    }

    private function render_styles() {
        ?>
        <style>
            .glt-payment-health-widget { margin: -12px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .glt-payment-health-header { display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #ccc; background: #f8f9fa; border-bottom: 1px solid #eee; }
            .glt-payment-health-header.excellent { border-color: #28a745; background: #f0fff4; }
            .glt-payment-health-header.good { border-color: #17a2b8; background: #e7f6f8; }
            .glt-payment-health-header.warning { border-color: #ffc107; background: #fff9e6; }
            .glt-payment-health-header.critical { border-color: #dc3545; background: #fff5f5; }
            .glt-payment-health-status-icon { font-size: 28px; }
            .glt-payment-health-status-title { font-weight: 700; color: #23282d; font-size: 14px; }
            .glt-payment-health-status-subtitle { font-size: 12px; color: #666; margin-top: 2px; }
            
            .glt-payment-health-main { padding: 20px; text-align: center; background: white; }
            .glt-payment-health-label { font-size: 11px; color: #666; font-weight: 600; margin-bottom: 8px; }
            .glt-payment-health-value { font-size: 42px; font-weight: 800; line-height: 1.2; margin: 8px 0; }
            .glt-payment-health-value.critical { color: #dc3545; }
            .glt-payment-health-value.warning { color: #ffc107; }
            .glt-payment-health-value.success { color: #28a745; }
            .glt-payment-health-benchmark { font-size: 11px; color: #999; margin-bottom: 12px; }
            .glt-payment-health-bar { height: 8px; background: #eee; border-radius: 4px; margin: 10px auto; max-width: 300px; overflow: hidden; }
            .glt-payment-health-bar-fill { height: 100%; transition: width 0.5s ease; }
            .glt-payment-health-bar-fill.critical { background: linear-gradient(90deg, #dc3545, #c82333); }
            .glt-payment-health-bar-fill.warning { background: linear-gradient(90deg, #ffc107, #ff9800); }
            .glt-payment-health-bar-fill.success { background: linear-gradient(90deg, #28a745, #20c997); }
            
            .glt-payment-health-lost-revenue { background: #fff5f5; border: 1px solid #ffcdd2; border-left: 4px solid #dc3545; padding: 12px; border-radius: 4px; display: inline-flex; align-items: flex-start; gap: 12px; margin-top: 15px; text-align: left; max-width: 400px; }
            .glt-payment-health-lost-icon { font-size: 24px; }
            .glt-payment-health-lost-label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
            .glt-payment-health-lost-amount { font-weight: bold; color: #c00; font-size: 20px; margin: 4px 0; }
            .glt-payment-health-lost-detail { font-size: 11px; color: #999; }
            
            .glt-payment-health-recovery-box { background: #e8f4fd; border: 1px solid #b3d9f2; border-left: 4px solid #0066cc; padding: 12px; border-radius: 4px; display: inline-flex; align-items: flex-start; gap: 12px; margin-top: 15px; text-align: left; max-width: 400px; }
            .glt-payment-health-recovery-icon { font-size: 24px; }
            .glt-payment-health-recovery-label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
            .glt-payment-health-recovery-value { font-weight: bold; color: #0066cc; font-size: 20px; margin: 4px 0; }
            .glt-payment-health-recovery-detail { font-size: 11px; color: #666; }
            
            .glt-payment-health-breakdown, .glt-payment-health-recommendations, .glt-payment-health-retry-analysis { border-top: 1px solid #eee; padding: 15px; background: #fcfcfc; }
            .glt-payment-health-section-title { font-weight: 600; font-size: 13px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; color: #555; }
            .glt-payment-health-section-title .dashicons { font-size: 16px; width: 16px; height: 16px; }
            
            .glt-payment-health-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
            .glt-payment-health-method-card { background: #fff; border: 1px solid #e2e4e7; padding: 12px; border-radius: 6px; font-size: 12px; transition: box-shadow 0.2s; }
            .glt-payment-health-method-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .glt-payment-health-method-card.has-issue { border-left: 3px solid #ffc107; background: #fffef5; }
            .glt-payment-health-method-header { font-weight: bold; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; }
            .glt-payment-health-method-name { display: flex; align-items: center; gap: 4px; }
            .glt-payment-health-method-count { font-size: 10px; color: #999; font-weight: normal; }
            .glt-payment-health-method-stats { display: flex; flex-direction: column; gap: 4px; }
            .glt-payment-health-method-row { display: flex; justify-content: space-between; color: #666; font-size: 11px; }
            .glt-payment-health-method-row strong.success { color: #28a745; }
            .glt-payment-health-method-alert { margin-top: 8px; padding: 6px 8px; background: #fff3cd; border-radius: 4px; font-size: 10px; color: #856404; }
            
            .glt-payment-health-retry-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 12px; }
            .glt-payment-health-retry-stat { background: white; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #e0e0e0; }
            .glt-payment-health-retry-stat-value { font-size: 28px; font-weight: 700; color: #2c3e50; }
            .glt-payment-health-retry-stat-label { font-size: 11px; color: #666; margin-top: 4px; }
            .glt-payment-health-retry-insight { background: #e8f4fd; padding: 10px 12px; border-radius: 4px; font-size: 12px; color: #004085; border-left: 3px solid #0066cc; }
            
            .glt-payment-health-recommendations-list { margin: 0; padding: 0; list-style: none; }
            .glt-payment-health-recommendation-item { display: flex; gap: 12px; padding: 12px; background: #fff; border: 1px solid #eee; margin-bottom: 8px; border-radius: 6px; }
            .glt-payment-health-recommendation-item.critical { border-left: 4px solid #dc3545; background: #fff5f5; }
            .glt-payment-health-recommendation-item.high { border-left: 4px solid #ffc107; background: #fffef5; }
            .glt-payment-health-recommendation-item.medium { border-left: 4px solid #17a2b8; background: #f0f9fa; }
            .glt-payment-health-recommendation-item.success { border-left: 4px solid #28a745; background: #f0fff4; }
            .glt-payment-health-recommendation-icon { font-size: 20px; line-height: 1; }
            .glt-payment-health-recommendation-content strong { display: block; font-size: 13px; margin-bottom: 4px; color: #23282d; }
            .glt-payment-health-recommendation-content p { margin: 0 0 8px; font-size: 12px; color: #666; line-height: 1.4; }
            
            .glt-payment-health-no-data { padding: 30px; text-align: center; color: #999; font-size: 13px; }
            
            .glt-refresh-link { display: block; text-align: center; padding: 12px; background: #f3f3f3; border-top: 1px solid #ddd; text-decoration: none; font-size: 12px; color: #666; transition: all 0.2s; }
            .glt-refresh-link:hover { background: #e8e8e8; color: #0073aa; }
            .glt-refresh-link .dashicons { vertical-align: middle; }
            
            @media (max-width: 1600px) {
                .glt-payment-health-methods { grid-template-columns: 1fr; }
                .glt-payment-health-retry-grid { grid-template-columns: 1fr; }
            }
        </style>
        <?php
    }

    private function render_refresh_button() {
        $url = wp_nonce_url(add_query_arg('glt_refresh_payment_health', '1'), 'glt_refresh_nonce', '_glt_nonce');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar datos
        </a>
        <?php
    }
}

// Inicializar de forma segura
add_action('plugins_loaded', ['Glotomania_Payment_Health_Widget', 'init']);
