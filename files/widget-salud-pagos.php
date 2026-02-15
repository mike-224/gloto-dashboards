<?php
/**
 * =========================================================================
 * WIDGET: SALUD DE PAGOS
 * Responde: ¿Estoy perdiendo dinero por problemas técnicos?
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget: Salud de Pagos
 * 
 * Este widget detecta:
 * - Tasa de fallos de pago anormal
 * - Método de pago más problemático
 * - Recovery rate (intentos exitosos tras fallo)
 * - Tiempo medio hasta abandono en checkout
 * - Revenue perdido por errores técnicos
 * - Alertas tempranas de problemas en pasarelas
 */
class Glotomania_Payment_Health_Widget {
    
    private $cache_key = 'glt_payment_health_v1';
    private $cache_time = 1800; // 30 minutos (refresh frecuente para detectar problemas rápido)
    
    // Umbrales de normalidad
    private $normal_failure_rate = 10; // 10% es normal
    private $critical_failure_rate = 18; // 18% es crítico
    
    /**
     * Registrar el widget
     */
    public static function register() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget(
                'glt_payment_health_widget',
                '💳 Salud de Pagos',
                [__CLASS__, 'render']
            );
        });
    }
    
    /**
     * Renderizar el widget
     */
    public static function render() {
        $instance = new self();
        
        // Manejar refresh manual
        if (isset($_GET['glt_refresh_payment_health'])) {
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg('glt_refresh_payment_health'));
            exit;
        }
        
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
     * Renderizar contenido del widget
     */
    private function render_content() {
        $metrics = $this->calculate_metrics();
        
        // Determinar estado general de salud
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
            
            <!-- Métrica principal: Tasa de fallos -->
            <div class="glt-payment-health-main">
                <div class="glt-payment-health-metric-large">
                    <div class="glt-payment-health-label">TASA DE FALLOS (30 días)</div>
                    <div class="glt-payment-health-value <?php echo $this->get_failure_rate_class($metrics['failure_rate']); ?>">
                        <?php echo round($metrics['failure_rate'], 1); ?>%
                    </div>
                    <div class="glt-payment-health-benchmark">
                        Normal: 5-10% | Tu promedio histórico: <?php echo round($metrics['historical_avg'], 1); ?>%
                    </div>
                    
                    <!-- Barra visual de tasa de fallos -->
                    <div class="glt-payment-health-bar">
                        <div class="glt-payment-health-bar-fill <?php echo $this->get_failure_rate_class($metrics['failure_rate']); ?>" 
                             style="width: <?php echo min(100, $metrics['failure_rate']); ?>%">
                        </div>
                    </div>
                </div>
                
                <!-- Revenue perdido -->
                <?php if ($metrics['lost_revenue'] > 0): ?>
                    <div class="glt-payment-health-lost-revenue">
                        <span class="glt-payment-health-lost-icon">💸</span>
                        <div>
                            <div class="glt-payment-health-lost-label">Revenue perdido (30 días)</div>
                            <div class="glt-payment-health-lost-amount"><?php echo wc_price($metrics['lost_revenue']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Desglose por método de pago -->
            <div class="glt-payment-health-breakdown">
                <div class="glt-payment-health-section-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    Desglose por Método de Pago
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
                                        <?php echo $method['total_attempts']; ?> intentos
                                    </span>
                                </div>
                                
                                <div class="glt-payment-health-method-stats">
                                    <div class="glt-payment-health-method-row">
                                        <span>Tasa de fallo:</span>
                                        <strong class="<?php echo $this->get_failure_rate_class($method['failure_rate']); ?>">
                                            <?php echo round($method['failure_rate'], 1); ?>%
                                        </strong>
                                    </div>
                                    <div class="glt-payment-health-method-row">
                                        <span>Fallos:</span>
                                        <strong><?php echo $method['failed']; ?></strong>
                                    </div>
                                    <div class="glt-payment-health-method-row">
                                        <span>Exitosos:</span>
                                        <strong class="success"><?php echo $method['successful']; ?></strong>
                                    </div>
                                    <?php if ($method['lost_revenue'] > 0): ?>
                                        <div class="glt-payment-health-method-row lost">
                                            <span>Perdido:</span>
                                            <strong><?php echo wc_price($method['lost_revenue']); ?></strong>
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
                <?php else: ?>
                    <div class="glt-payment-health-no-data">
                        No hay suficientes datos de pagos en los últimos 30 días.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recovery Rate -->
            <?php if ($metrics['recovery_rate'] !== null): ?>
                <div class="glt-payment-health-recovery">
                    <div class="glt-payment-health-section-title">
                        <span class="dashicons dashicons-backup"></span>
                        Tasa de Recuperación
                    </div>
                    <div class="glt-payment-health-recovery-content">
                        <div class="glt-payment-health-recovery-stat">
                            <div class="glt-payment-health-recovery-label">Clientes que reintentaron</div>
                            <div class="glt-payment-health-recovery-value">
                                <?php echo round($metrics['recovery_rate'], 1); ?>%
                            </div>
                            <div class="glt-payment-health-recovery-detail">
                                <?php echo $metrics['recovered_orders']; ?> de <?php echo $metrics['failed_unique_customers']; ?> clientes volvieron a intentar
                            </div>
                        </div>
                        
                        <?php if ($metrics['recovered_revenue'] > 0): ?>
                            <div class="glt-payment-health-recovery-stat">
                                <div class="glt-payment-health-recovery-label">Revenue recuperado</div>
                                <div class="glt-payment-health-recovery-value success">
                                    <?php echo wc_price($metrics['recovered_revenue']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tiempo hasta abandono -->
            <?php if ($metrics['avg_time_to_abandon'] > 0): ?>
                <div class="glt-payment-health-timing">
                    <div class="glt-payment-health-section-title">
                        <span class="dashicons dashicons-clock"></span>
                        Comportamiento en Checkout
                    </div>
                    <div class="glt-payment-health-timing-grid">
                        <div class="glt-payment-health-timing-item">
                            <div class="glt-payment-health-timing-icon">⏱️</div>
                            <div>
                                <div class="glt-payment-health-timing-label">Tiempo medio hasta abandono</div>
                                <div class="glt-payment-health-timing-value">
                                    <?php echo $this->format_duration($metrics['avg_time_to_abandon']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($metrics['checkout_abandonment_rate'] > 0): ?>
                            <div class="glt-payment-health-timing-item">
                                <div class="glt-payment-health-timing-icon">🚪</div>
                                <div>
                                    <div class="glt-payment-health-timing-label">Tasa de abandono en checkout</div>
                                    <div class="glt-payment-health-timing-value">
                                        <?php echo round($metrics['checkout_abandonment_rate'], 1); ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
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
                        <span class="dashicons dashicons-lightbulb"></span>
                        Acciones Recomendadas
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
     * Calcular todas las métricas
     */
    private function calculate_metrics() {
        global $wpdb;
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Obtener todos los pedidos de los últimos 30 días
        $all_orders = wc_get_orders([
            'limit' => -1,
            'date_created' => '>=' . $thirty_days_ago,
            'return' => 'ids'
        ]);
        
        $successful = 0;
        $failed = 0;
        $lost_revenue = 0;
        $payment_methods_data = [];
        $failed_customer_ids = [];
        $recovered_customer_ids = [];
        $recovered_revenue = 0;
        
        $total_checkout_time = 0;
        $abandoned_count = 0;
        
        foreach ($all_orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $status = $order->get_status();
            $payment_method = $order->get_payment_method();
            $payment_title = $order->get_payment_method_title() ?: 'Desconocido';
            $total = $order->get_total();
            $customer_id = $order->get_customer_id();
            
            // Inicializar método de pago si no existe
            if (!isset($payment_methods_data[$payment_method])) {
                $payment_methods_data[$payment_method] = [
                    'method' => $payment_method,
                    'title' => $payment_title,
                    'successful' => 0,
                    'failed' => 0,
                    'lost_revenue' => 0,
                    'total_attempts' => 0
                ];
            }
            
            $payment_methods_data[$payment_method]['total_attempts']++;
            
            // Clasificar pedido
            if (in_array($status, ['completed', 'processing', 'on-hold'])) {
                $successful++;
                $payment_methods_data[$payment_method]['successful']++;
                
                // Verificar si este cliente tuvo un fallo previo (recuperación)
                if ($customer_id > 0 && in_array($customer_id, $failed_customer_ids)) {
                    $recovered_customer_ids[] = $customer_id;
                    $recovered_revenue += $total;
                }
            } elseif (in_array($status, ['failed', 'cancelled', 'pending'])) {
                $failed++;
                $lost_revenue += $total;
                $payment_methods_data[$payment_method]['failed']++;
                $payment_methods_data[$payment_method]['lost_revenue'] += $total;
                
                if ($customer_id > 0) {
                    $failed_customer_ids[] = $customer_id;
                }
                
                // Calcular tiempo hasta abandono (si está disponible)
                if (in_array($status, ['failed', 'cancelled'])) {
                    $created = $order->get_date_created();
                    $modified = $order->get_date_modified();
                    if ($created && $modified) {
                        $diff = $modified->getTimestamp() - $created->getTimestamp();
                        if ($diff > 0 && $diff < 3600) { // Solo contar si es menos de 1 hora
                            $total_checkout_time += $diff;
                            $abandoned_count++;
                        }
                    }
                }
            }
        }
        
        $total_attempts = $successful + $failed;
        $failure_rate = $total_attempts > 0 ? ($failed / $total_attempts) * 100 : 0;
        
        // Calcular promedio histórico (últimos 90 días para comparar)
        $ninety_days_ago = date('Y-m-d H:i:s', strtotime('-90 days'));
        $sixty_days_ago = date('Y-m-d H:i:s', strtotime('-60 days'));
        
        $historical_orders = wc_get_orders([
            'limit' => -1,
            'date_created' => '>=' . $ninety_days_ago,
            'date_created' => '<' . $sixty_days_ago,
            'return' => 'ids'
        ]);
        
        $hist_successful = 0;
        $hist_failed = 0;
        
        foreach ($historical_orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $status = $order->get_status();
            if (in_array($status, ['completed', 'processing', 'on-hold'])) {
                $hist_successful++;
            } elseif (in_array($status, ['failed', 'cancelled', 'pending'])) {
                $hist_failed++;
            }
        }
        
        $hist_total = $hist_successful + $hist_failed;
        $historical_avg = $hist_total > 0 ? ($hist_failed / $hist_total) * 100 : $failure_rate;
        
        // Procesar datos de métodos de pago
        $payment_methods = [];
        foreach ($payment_methods_data as $method_data) {
            $method_total = $method_data['successful'] + $method_data['failed'];
            $method_failure_rate = $method_total > 0 
                ? ($method_data['failed'] / $method_total) * 100 
                : 0;
            
            $has_issue = $method_failure_rate > $this->normal_failure_rate;
            $issue_message = '';
            
            if ($method_failure_rate > $this->critical_failure_rate) {
                $issue_message = 'Tasa crítica - Revisar integración urgente';
            } elseif ($method_failure_rate > $this->normal_failure_rate) {
                $issue_message = 'Tasa elevada - Monitorear de cerca';
            }
            
            $payment_methods[] = array_merge($method_data, [
                'failure_rate' => $method_failure_rate,
                'has_issue' => $has_issue,
                'issue_message' => $issue_message
            ]);
        }
        
        // Ordenar por tasa de fallo (peores primero)
        usort($payment_methods, function($a, $b) {
            return $b['failure_rate'] <=> $a['failure_rate'];
        });
        
        // Recovery rate
        $failed_unique_customers = count(array_unique($failed_customer_ids));
        $recovered_unique = count(array_unique($recovered_customer_ids));
        $recovery_rate = $failed_unique_customers > 0 
            ? ($recovered_unique / $failed_unique_customers) * 100 
            : null;
        
        // Tiempo promedio hasta abandono
        $avg_time_to_abandon = $abandoned_count > 0 
            ? $total_checkout_time / $abandoned_count 
            : 0;
        
        // Tasa de abandono en checkout (estimación)
        $checkout_abandonment_rate = $total_attempts > 0 
            ? ($failed / $total_attempts) * 100 
            : 0;
        
        return [
            'failure_rate' => $failure_rate,
            'historical_avg' => $historical_avg,
            'lost_revenue' => $lost_revenue,
            'successful' => $successful,
            'failed' => $failed,
            'total_attempts' => $total_attempts,
            'payment_methods' => $payment_methods,
            'recovery_rate' => $recovery_rate,
            'failed_unique_customers' => $failed_unique_customers,
            'recovered_orders' => $recovered_unique,
            'recovered_revenue' => $recovered_revenue,
            'avg_time_to_abandon' => $avg_time_to_abandon,
            'checkout_abandonment_rate' => $checkout_abandonment_rate
        ];
    }
    
    /**
     * Obtener estado general de salud
     */
    private function get_health_status($metrics) {
        $failure_rate = $metrics['failure_rate'];
        
        if ($failure_rate < 5) {
            return [
                'class' => 'excellent',
                'icon' => '✅',
                'title' => 'Salud Excelente',
                'subtitle' => 'Sistema de pagos funcionando óptimamente'
            ];
        } elseif ($failure_rate < $this->normal_failure_rate) {
            return [
                'class' => 'good',
                'icon' => '👍',
                'title' => 'Salud Buena',
                'subtitle' => 'Tasa de fallos dentro de lo normal'
            ];
        } elseif ($failure_rate < $this->critical_failure_rate) {
            return [
                'class' => 'warning',
                'icon' => '⚠️',
                'title' => 'Requiere Atención',
                'subtitle' => 'Tasa de fallos por encima de lo normal'
            ];
        } else {
            return [
                'class' => 'critical',
                'icon' => '🚨',
                'title' => 'Situación Crítica',
                'subtitle' => 'Tasa de fallos anormalmente alta - Revisar urgente'
            ];
        }
    }
    
    /**
     * Obtener clase CSS según tasa de fallo
     */
    private function get_failure_rate_class($rate) {
        if ($rate < 5) return 'success';
        if ($rate < $this->normal_failure_rate) return 'good';
        if ($rate < $this->critical_failure_rate) return 'warning';
        return 'critical';
    }
    
    /**
     * Obtener icono según método de pago
     */
    private function get_payment_icon($method) {
        $icons = [
            'bacs' => '🏦',
            'cheque' => '📝',
            'cod' => '💵',
            'paypal' => '💙',
            'stripe' => '💳',
            'redsys' => '🔴',
            'bizum' => '💚',
        ];
        
        return isset($icons[$method]) ? $icons[$method] : '💳';
    }
    
    /**
     * Formatear duración en segundos
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return round($seconds) . ' segundos';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutos';
        } else {
            return round($seconds / 3600, 1) . ' horas';
        }
    }
    
    /**
     * Obtener recomendaciones basadas en métricas
     */
    private function get_recommendations($metrics) {
        $recommendations = [];
        
        // Recomendación por tasa de fallo alta
        if ($metrics['failure_rate'] > $this->critical_failure_rate) {
            $recommendations[] = [
                'priority' => 'critical',
                'icon' => '🚨',
                'title' => 'Revisar pasarela de pagos urgentemente',
                'description' => 'Tu tasa de fallos (' . round($metrics['failure_rate'], 1) . '%) es crítica. Contacta con tu proveedor de pagos para identificar el problema.',
                'action_url' => '',
                'action_label' => ''
            ];
        } elseif ($metrics['failure_rate'] > $this->normal_failure_rate) {
            $recommendations[] = [
                'priority' => 'warning',
                'icon' => '⚠️',
                'title' => 'Monitorear pasarela de pagos',
                'description' => 'La tasa de fallos está por encima de lo normal. Revisa los logs de transacciones.',
                'action_url' => admin_url('admin.php?page=wc-status&tab=logs'),
                'action_label' => 'Ver Logs'
            ];
        }
        
        // Recomendación por método problemático
        foreach ($metrics['payment_methods'] as $method) {
            if ($method['failure_rate'] > 25 && $method['failed'] > 5) {
                $recommendations[] = [
                    'priority' => 'high',
                    'icon' => '🔧',
                    'title' => 'Problema con ' . $method['title'],
                    'description' => 'Este método de pago tiene ' . round($method['failure_rate'], 1) . '% de fallos. Considera deshabilitarlo temporalmente o revisar su configuración.',
                    'action_url' => admin_url('admin.php?page=wc-settings&tab=checkout'),
                    'action_label' => 'Configurar Pagos'
                ];
                break; // Solo mostrar el peor
            }
        }
        
        // Recomendación por revenue perdido alto
        if ($metrics['lost_revenue'] > 1000) {
            $recommendations[] = [
                'priority' => 'high',
                'icon' => '💰',
                'title' => 'Recuperar pedidos fallidos',
                'description' => 'Has perdido ' . wc_price($metrics['lost_revenue']) . ' en los últimos 30 días. Contacta a estos clientes con un enlace de pago o cupón de descuento.',
                'action_url' => admin_url('edit.php?post_type=shop_order&post_status=wc-failed'),
                'action_label' => 'Ver Pedidos Fallidos'
            ];
        }
        
        // Recomendación por baja recovery rate
        if ($metrics['recovery_rate'] !== null && $metrics['recovery_rate'] < 20) {
            $recommendations[] = [
                'priority' => 'medium',
                'icon' => '📧',
                'title' => 'Mejorar recuperación de pagos fallidos',
                'description' => 'Solo el ' . round($metrics['recovery_rate'], 1) . '% de clientes con pagos fallidos vuelven a intentar. Implementa emails automáticos de recuperación.',
                'action_url' => '',
                'action_label' => ''
            ];
        }
        
        // Recomendación por abandono rápido
        if ($metrics['avg_time_to_abandon'] > 0 && $metrics['avg_time_to_abandon'] < 120) {
            $recommendations[] = [
                'priority' => 'medium',
                'icon' => '⚡',
                'title' => 'Optimizar velocidad de checkout',
                'description' => 'Los clientes abandonan muy rápido (promedio: ' . $this->format_duration($metrics['avg_time_to_abandon']) . '). El checkout puede ser confuso o lento.',
                'action_url' => '',
                'action_label' => ''
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Renderizar estilos del widget
     */
    private function render_styles() {
        ?>
        <style>
            .glt-payment-health-widget {
                margin: -12px;
            }
            
            /* Header */
            .glt-payment-health-header {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px 20px;
                border-left: 5px solid;
            }
            
            .glt-payment-health-header.excellent {
                background: #d4edda;
                border-color: #28a745;
            }
            
            .glt-payment-health-header.good {
                background: #d1ecf1;
                border-color: #17a2b8;
            }
            
            .glt-payment-health-header.warning {
                background: #fff3cd;
                border-color: #ffc107;
            }
            
            .glt-payment-health-header.critical {
                background: #f8d7da;
                border-color: #dc3545;
            }
            
            .glt-payment-health-status-icon {
                font-size: 32px;
                line-height: 1;
            }
            
            .glt-payment-health-status-title {
                font-size: 16px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 2px;
            }
            
            .glt-payment-health-status-subtitle {
                font-size: 12px;
                color: #666;
            }
            
            /* Métrica principal */
            .glt-payment-health-main {
                padding: 20px;
                background: white;
            }
            
            .glt-payment-health-metric-large {
                text-align: center;
                margin-bottom: 15px;
            }
            
            .glt-payment-health-label {
                font-size: 11px;
                font-weight: 700;
                color: #666;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }
            
            .glt-payment-health-value {
                font-size: 48px;
                font-weight: 800;
                line-height: 1;
                margin: 10px 0;
            }
            
            .glt-payment-health-value.success {
                color: #28a745;
            }
            
            .glt-payment-health-value.good {
                color: #17a2b8;
            }
            
            .glt-payment-health-value.warning {
                color: #ffc107;
            }
            
            .glt-payment-health-value.critical {
                color: #dc3545;
            }
            
            .glt-payment-health-benchmark {
                font-size: 11px;
                color: #999;
                margin-bottom: 15px;
            }
            
            .glt-payment-health-bar {
                background: #e0e0e0;
                height: 10px;
                border-radius: 10px;
                overflow: hidden;
            }
            
            .glt-payment-health-bar-fill {
                height: 100%;
                transition: width 0.5s ease;
            }
            
            .glt-payment-health-bar-fill.success {
                background: linear-gradient(90deg, #28a745, #20c997);
            }
            
            .glt-payment-health-bar-fill.good {
                background: linear-gradient(90deg, #17a2b8, #00bcd4);
            }
            
            .glt-payment-health-bar-fill.warning {
                background: linear-gradient(90deg, #ffc107, #ff9800);
            }
            
            .glt-payment-health-bar-fill.critical {
                background: linear-gradient(90deg, #dc3545, #c82333);
            }
            
            /* Revenue perdido */
            .glt-payment-health-lost-revenue {
                display: flex;
                align-items: center;
                gap: 12px;
                background: #fff5f5;
                border: 1px solid #ffcdd2;
                border-left: 4px solid #dc3545;
                padding: 12px 15px;
                border-radius: 4px;
                margin-top: 15px;
            }
            
            .glt-payment-health-lost-icon {
                font-size: 24px;
            }
            
            .glt-payment-health-lost-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                font-weight: 600;
            }
            
            .glt-payment-health-lost-amount {
                font-size: 20px;
                font-weight: 700;
                color: #dc3545;
            }
            
            /* Secciones */
            .glt-payment-health-breakdown,
            .glt-payment-health-recovery,
            .glt-payment-health-timing,
            .glt-payment-health-recommendations {
                padding: 15px 20px;
                border-top: 1px solid #e0e0e0;
                background: #f9f9f9;
            }
            
            .glt-payment-health-section-title {
                font-size: 13px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .glt-payment-health-section-title .dashicons {
                color: #666;
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            
            /* Métodos de pago */
            .glt-payment-health-methods {
                display: grid;
                gap: 10px;
            }
            
            .glt-payment-health-method-card {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                padding: 12px;
                transition: box-shadow 0.2s;
            }
            
            .glt-payment-health-method-card:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .glt-payment-health-method-card.has-issue {
                border-left: 4px solid #ffc107;
            }
            
            .glt-payment-health-method-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .glt-payment-health-method-name {
                font-weight: 600;
                font-size: 13px;
                color: #2c3e50;
            }
            
            .glt-payment-health-method-count {
                font-size: 11px;
                color: #999;
            }
            
            .glt-payment-health-method-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }
            
            .glt-payment-health-method-row {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
                padding: 4px 0;
            }
            
            .glt-payment-health-method-row span {
                color: #666;
            }
            
            .glt-payment-health-method-row strong {
                color: #2c3e50;
            }
            
            .glt-payment-health-method-row strong.success {
                color: #28a745;
            }
            
            .glt-payment-health-method-row.lost {
                grid-column: 1 / -1;
                background: #fff5f5;
                padding: 6px 8px;
                margin-top: 4px;
                border-radius: 3px;
            }
            
            .glt-payment-health-method-alert {
                margin-top: 8px;
                padding: 8px;
                background: #fff3cd;
                border-radius: 4px;
                font-size: 11px;
                color: #856404;
            }
            
            /* Recovery */
            .glt-payment-health-recovery-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .glt-payment-health-recovery-stat {
                background: white;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
            }
            
            .glt-payment-health-recovery-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                font-weight: 600;
                margin-bottom: 8px;
            }
            
            .glt-payment-health-recovery-value {
                font-size: 28px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 6px;
            }
            
            .glt-payment-health-recovery-value.success {
                color: #28a745;
            }
            
            .glt-payment-health-recovery-detail {
                font-size: 11px;
                color: #999;
            }
            
            /* Timing */
            .glt-payment-health-timing-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .glt-payment-health-timing-item {
                background: white;
                padding: 12px;
                border-radius: 6px;
                display: flex;
                gap: 12px;
                align-items: center;
            }
            
            .glt-payment-health-timing-icon {
                font-size: 24px;
            }
            
            .glt-payment-health-timing-label {
                font-size: 11px;
                color: #666;
                margin-bottom: 4px;
            }
            
            .glt-payment-health-timing-value {
                font-size: 16px;
                font-weight: 700;
                color: #2c3e50;
            }
            
            /* Recommendations */
            .glt-payment-health-recommendations-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            
            .glt-payment-health-recommendation-item {
                display: flex;
                gap: 12px;
                padding: 12px;
                background: white;
                border-radius: 6px;
                margin-bottom: 10px;
                border-left: 4px solid;
            }
            
            .glt-payment-health-recommendation-item.critical {
                border-color: #dc3545;
                background: #fff5f5;
            }
            
            .glt-payment-health-recommendation-item.high {
                border-color: #ffc107;
                background: #fffef5;
            }
            
            .glt-payment-health-recommendation-item.warning {
                border-color: #ff9800;
            }
            
            .glt-payment-health-recommendation-item.medium {
                border-color: #17a2b8;
            }
            
            .glt-payment-health-recommendation-icon {
                font-size: 20px;
                line-height: 1;
            }
            
            .glt-payment-health-recommendation-content {
                flex: 1;
            }
            
            .glt-payment-health-recommendation-content strong {
                display: block;
                font-size: 13px;
                color: #2c3e50;
                margin-bottom: 4px;
            }
            
            .glt-payment-health-recommendation-content p {
                font-size: 12px;
                color: #666;
                margin: 0 0 8px 0;
                line-height: 1.4;
            }
            
            /* No data */
            .glt-payment-health-no-data {
                padding: 20px;
                text-align: center;
                color: #999;
                font-size: 12px;
                background: white;
                border-radius: 6px;
            }
            
            /* Refresh Button */
            .glt-refresh-link {
                display: block;
                text-align: right;
                padding: 10px 15px;
                border-top: 1px solid #e0e0e0;
                font-size: 11px;
                color: #999;
                text-decoration: none;
                background: white;
            }
            
            .glt-refresh-link:hover {
                color: #007cba;
            }
            
            /* Responsive */
            @media (max-width: 1600px) {
                .glt-payment-health-method-stats,
                .glt-payment-health-recovery-content,
                .glt-payment-health-timing-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar botón de actualización
     */
    private function render_refresh_button() {
        $url = add_query_arg('glt_refresh_payment_health', '1');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar datos
        </a>
        <?php
    }
}

// Registrar el widget
Glotomania_Payment_Health_Widget::register();
