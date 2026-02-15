<?php
/**
 * =========================================================================
 * WIDGET: CUSTOMER LIFETIME VALUE (LTV)
 * Responde: ¿Cuánto vale cada cliente a lo largo del tiempo?
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget: Customer Lifetime Value
 * 
 * Este widget calcula:
 * - LTV promedio a 30, 90 y 365 días
 * - Evolución del LTV a lo largo del tiempo
 * - Segmentación de clientes por valor
 * - Tasa de retención por periodo
 * - Valor proyectado anual
 * - Top clientes por LTV
 */
class Glotomania_LTV_Widget {
    
    private $cache_key = 'glt_ltv_analysis_v1';
    private $cache_time = 3600; // 1 hora
    
    /**
     * Registrar el widget
     */
    public static function register() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget(
                'glt_ltv_widget',
                '💎 Customer Lifetime Value',
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
        if (isset($_GET['glt_refresh_ltv'])) {
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg('glt_refresh_ltv'));
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
        $data = $this->calculate_ltv_metrics();
        
        ?>
        <div class="glt-ltv-widget">
            
            <!-- Header con LTV principal -->
            <div class="glt-ltv-hero">
                <div class="glt-ltv-hero-main">
                    <div class="glt-ltv-hero-label">LIFETIME VALUE PROMEDIO (365 DÍAS)</div>
                    <div class="glt-ltv-hero-amount">
                        <?php echo wc_price($data['ltv_365']); ?>
                    </div>
                    <div class="glt-ltv-hero-subtitle">
                        por cliente en su primer año
                    </div>
                </div>
            </div>
            
            <!-- Comparativa de periodos -->
            <div class="glt-ltv-periods">
                <div class="glt-ltv-period-card">
                    <div class="glt-ltv-period-header">
                        <span class="glt-ltv-period-icon">📅</span>
                        <span class="glt-ltv-period-title">30 DÍAS</span>
                    </div>
                    <div class="glt-ltv-period-value"><?php echo wc_price($data['ltv_30']); ?></div>
                    <div class="glt-ltv-period-stats">
                        <div class="glt-ltv-period-stat">
                            <span>Pedidos promedio:</span>
                            <strong><?php echo number_format($data['avg_orders_30'], 1); ?></strong>
                        </div>
                        <div class="glt-ltv-period-stat">
                            <span>Tasa retención:</span>
                            <strong class="<?php echo $data['retention_30'] > 20 ? 'success' : 'warning'; ?>">
                                <?php echo round($data['retention_30'], 1); ?>%
                            </strong>
                        </div>
                    </div>
                </div>
                
                <div class="glt-ltv-period-card">
                    <div class="glt-ltv-period-header">
                        <span class="glt-ltv-period-icon">📊</span>
                        <span class="glt-ltv-period-title">90 DÍAS</span>
                    </div>
                    <div class="glt-ltv-period-value"><?php echo wc_price($data['ltv_90']); ?></div>
                    <div class="glt-ltv-period-stats">
                        <div class="glt-ltv-period-stat">
                            <span>Pedidos promedio:</span>
                            <strong><?php echo number_format($data['avg_orders_90'], 1); ?></strong>
                        </div>
                        <div class="glt-ltv-period-stat">
                            <span>Tasa retención:</span>
                            <strong class="<?php echo $data['retention_90'] > 15 ? 'success' : 'warning'; ?>">
                                <?php echo round($data['retention_90'], 1); ?>%
                            </strong>
                        </div>
                    </div>
                </div>
                
                <div class="glt-ltv-period-card highlight">
                    <div class="glt-ltv-period-header">
                        <span class="glt-ltv-period-icon">💎</span>
                        <span class="glt-ltv-period-title">365 DÍAS</span>
                    </div>
                    <div class="glt-ltv-period-value"><?php echo wc_price($data['ltv_365']); ?></div>
                    <div class="glt-ltv-period-stats">
                        <div class="glt-ltv-period-stat">
                            <span>Pedidos promedio:</span>
                            <strong><?php echo number_format($data['avg_orders_365'], 1); ?></strong>
                        </div>
                        <div class="glt-ltv-period-stat">
                            <span>Tasa retención:</span>
                            <strong class="<?php echo $data['retention_365'] > 10 ? 'success' : 'warning'; ?>">
                                <?php echo round($data['retention_365'], 1); ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Evolución del LTV -->
            <div class="glt-ltv-evolution">
                <div class="glt-ltv-section-title">
                    <span class="dashicons dashicons-chart-line"></span>
                    Evolución del Valor del Cliente
                </div>
                
                <div class="glt-ltv-evolution-chart">
                    <div class="glt-ltv-evolution-bars">
                        <?php 
                        $max_value = max($data['ltv_30'], $data['ltv_90'], $data['ltv_365']);
                        ?>
                        <div class="glt-ltv-evolution-bar-group">
                            <div class="glt-ltv-evolution-bar" 
                                 style="height: <?php echo ($data['ltv_30'] / $max_value) * 100; ?>%">
                                <span class="glt-ltv-evolution-bar-label">
                                    <?php echo wc_price($data['ltv_30']); ?>
                                </span>
                            </div>
                            <div class="glt-ltv-evolution-bar-title">30d</div>
                        </div>
                        <div class="glt-ltv-evolution-bar-group">
                            <div class="glt-ltv-evolution-bar" 
                                 style="height: <?php echo ($data['ltv_90'] / $max_value) * 100; ?>%">
                                <span class="glt-ltv-evolution-bar-label">
                                    <?php echo wc_price($data['ltv_90']); ?>
                                </span>
                            </div>
                            <div class="glt-ltv-evolution-bar-title">90d</div>
                        </div>
                        <div class="glt-ltv-evolution-bar-group">
                            <div class="glt-ltv-evolution-bar highlight" 
                                 style="height: <?php echo ($data['ltv_365'] / $max_value) * 100; ?>%">
                                <span class="glt-ltv-evolution-bar-label">
                                    <?php echo wc_price($data['ltv_365']); ?>
                                </span>
                            </div>
                            <div class="glt-ltv-evolution-bar-title">365d</div>
                        </div>
                    </div>
                    
                    <div class="glt-ltv-evolution-insight">
                        <?php echo $this->get_evolution_insight($data); ?>
                    </div>
                </div>
            </div>
            
            <!-- Segmentación de clientes -->
            <div class="glt-ltv-segmentation">
                <div class="glt-ltv-section-title">
                    <span class="dashicons dashicons-groups"></span>
                    Segmentación por Valor (365 días)
                </div>
                
                <div class="glt-ltv-segments">
                    <?php foreach ($data['segments'] as $segment): ?>
                        <div class="glt-ltv-segment-card">
                            <div class="glt-ltv-segment-header">
                                <span class="glt-ltv-segment-icon"><?php echo $segment['icon']; ?></span>
                                <div class="glt-ltv-segment-info">
                                    <div class="glt-ltv-segment-name"><?php echo $segment['name']; ?></div>
                                    <div class="glt-ltv-segment-count">
                                        <?php echo $segment['count']; ?> clientes 
                                        (<?php echo round($segment['percentage'], 1); ?>%)
                                    </div>
                                </div>
                            </div>
                            <div class="glt-ltv-segment-metrics">
                                <div class="glt-ltv-segment-metric">
                                    <span>LTV promedio:</span>
                                    <strong><?php echo wc_price($segment['avg_ltv']); ?></strong>
                                </div>
                                <div class="glt-ltv-segment-metric">
                                    <span>Revenue total:</span>
                                    <strong class="highlight"><?php echo wc_price($segment['total_revenue']); ?></strong>
                                </div>
                                <div class="glt-ltv-segment-metric">
                                    <span>Pedidos promedio:</span>
                                    <strong><?php echo number_format($segment['avg_orders'], 1); ?></strong>
                                </div>
                            </div>
                            <?php if (!empty($segment['action'])): ?>
                                <div class="glt-ltv-segment-action">
                                    💡 <?php echo $segment['action']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Top Clientes -->
            <div class="glt-ltv-top-customers">
                <div class="glt-ltv-section-title">
                    <span class="dashicons dashicons-star-filled"></span>
                    Top 10 Clientes por LTV (365 días)
                </div>
                
                <?php if (!empty($data['top_customers'])): ?>
                    <div class="glt-ltv-customers-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>LTV</th>
                                    <th>Pedidos</th>
                                    <th>Última compra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['top_customers'] as $index => $customer): ?>
                                    <tr>
                                        <td>
                                            <div class="glt-ltv-customer-cell">
                                                <span class="glt-ltv-customer-rank">#<?php echo $index + 1; ?></span>
                                                <?php echo get_avatar($customer['id'], 24); ?>
                                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $customer['id']); ?>" 
                                                   target="_blank">
                                                    <?php echo esc_html($customer['name']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="glt-ltv-customer-value">
                                                <?php echo wc_price($customer['ltv']); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $customer['orders']; ?></td>
                                        <td>
                                            <span class="glt-ltv-customer-date">
                                                <?php echo human_time_diff($customer['last_order_timestamp'], current_time('timestamp')); ?> ago
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="glt-ltv-empty">
                        No hay suficientes datos de clientes.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Métricas adicionales -->
            <div class="glt-ltv-additional-metrics">
                <div class="glt-ltv-metric-box">
                    <div class="glt-ltv-metric-label">Valor proyectado anual</div>
                    <div class="glt-ltv-metric-value">
                        <?php echo wc_price($data['projected_annual_value']); ?>
                    </div>
                    <div class="glt-ltv-metric-detail">
                        Basado en <?php echo $data['active_customers']; ?> clientes activos
                    </div>
                </div>
                
                <div class="glt-ltv-metric-box">
                    <div class="glt-ltv-metric-label">Tiempo medio entre compras</div>
                    <div class="glt-ltv-metric-value">
                        <?php echo round($data['avg_days_between_purchases']); ?> días
                    </div>
                    <div class="glt-ltv-metric-detail">
                        Para clientes recurrentes
                    </div>
                </div>
                
                <div class="glt-ltv-metric-box">
                    <div class="glt-ltv-metric-label">Tasa de clientes recurrentes</div>
                    <div class="glt-ltv-metric-value">
                        <?php echo round($data['repeat_customer_rate'], 1); ?>%
                    </div>
                    <div class="glt-ltv-metric-detail">
                        <?php echo $data['repeat_customers']; ?> de <?php echo $data['total_customers']; ?> clientes
                    </div>
                </div>
            </div>
            
            <!-- Insights y Recomendaciones -->
            <div class="glt-ltv-insights">
                <div class="glt-ltv-section-title">
                    <span class="dashicons dashicons-lightbulb"></span>
                    Insights y Recomendaciones
                </div>
                
                <div class="glt-ltv-insights-list">
                    <?php foreach ($this->get_recommendations($data) as $recommendation): ?>
                        <div class="glt-ltv-insight-item <?php echo $recommendation['type']; ?>">
                            <div class="glt-ltv-insight-icon"><?php echo $recommendation['icon']; ?></div>
                            <div class="glt-ltv-insight-content">
                                <strong><?php echo $recommendation['title']; ?></strong>
                                <p><?php echo $recommendation['description']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php $this->render_styles(); ?>
            <?php $this->render_refresh_button(); ?>
        </div>
        <?php
    }
    
    /**
     * Calcular métricas de LTV
     */
    private function calculate_ltv_metrics() {
        global $wpdb;
        
        $now = current_time('timestamp');
        
        // Periodos de análisis
        $periods = [
            30 => date('Y-m-d H:i:s', strtotime('-30 days')),
            90 => date('Y-m-d H:i:s', strtotime('-90 days')),
            365 => date('Y-m-d H:i:s', strtotime('-365 days'))
        ];
        
        $ltv_data = [];
        
        // Calcular LTV para cada periodo
        foreach ($periods as $days => $start_date) {
            $customer_data = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    m.meta_value as customer_id,
                    COUNT(p.ID) as order_count,
                    SUM(pm.meta_value) as total_spent
                FROM {$wpdb->prefix}posts p
                JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
                JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date >= %s
                AND m.meta_value > 0
                GROUP BY m.meta_value",
                $start_date
            ));
            
            $total_ltv = 0;
            $total_customers = 0;
            $total_orders = 0;
            $repeat_customers = 0;
            
            foreach ($customer_data as $customer) {
                $total_ltv += (float) $customer->total_spent;
                $total_customers++;
                $total_orders += (int) $customer->order_count;
                
                if ((int) $customer->order_count > 1) {
                    $repeat_customers++;
                }
            }
            
            $avg_ltv = $total_customers > 0 ? $total_ltv / $total_customers : 0;
            $avg_orders = $total_customers > 0 ? $total_orders / $total_customers : 0;
            $retention_rate = $total_customers > 0 ? ($repeat_customers / $total_customers) * 100 : 0;
            
            $ltv_data[$days] = [
                'ltv' => $avg_ltv,
                'avg_orders' => $avg_orders,
                'retention' => $retention_rate,
                'total_customers' => $total_customers,
                'repeat_customers' => $repeat_customers
            ];
        }
        
        // Segmentación de clientes (basado en 365 días)
        $segments = $this->segment_customers($periods[365]);
        
        // Top clientes
        $top_customers = $this->get_top_customers($periods[365]);
        
        // Tiempo medio entre compras
        $avg_days_between = $this->calculate_avg_days_between_purchases();
        
        // Valor proyectado anual
        $active_customers = $ltv_data[365]['total_customers'];
        $projected_annual_value = $ltv_data[365]['ltv'] * $active_customers;
        
        return [
            'ltv_30' => $ltv_data[30]['ltv'],
            'ltv_90' => $ltv_data[90]['ltv'],
            'ltv_365' => $ltv_data[365]['ltv'],
            'avg_orders_30' => $ltv_data[30]['avg_orders'],
            'avg_orders_90' => $ltv_data[90]['avg_orders'],
            'avg_orders_365' => $ltv_data[365]['avg_orders'],
            'retention_30' => $ltv_data[30]['retention'],
            'retention_90' => $ltv_data[90]['retention'],
            'retention_365' => $ltv_data[365]['retention'],
            'segments' => $segments,
            'top_customers' => $top_customers,
            'projected_annual_value' => $projected_annual_value,
            'active_customers' => $active_customers,
            'avg_days_between_purchases' => $avg_days_between,
            'repeat_customer_rate' => $ltv_data[365]['retention'],
            'repeat_customers' => $ltv_data[365]['repeat_customers'],
            'total_customers' => $ltv_data[365]['total_customers']
        ];
    }
    
    /**
     * Segmentar clientes por valor
     */
    private function segment_customers($start_date) {
        global $wpdb;
        
        // Obtener todos los clientes con su LTV
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                m.meta_value as customer_id,
                COUNT(p.ID) as order_count,
                SUM(pm.meta_value) as total_spent
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
            JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND m.meta_value > 0
            GROUP BY m.meta_value
            ORDER BY total_spent DESC",
            $start_date
        ));
        
        if (empty($customers)) {
            return [];
        }
        
        $total_customers = count($customers);
        
        // Calcular percentiles
        $ltvs = array_map(function($c) { return (float) $c->total_spent; }, $customers);
        sort($ltvs);
        
        $p90 = $this->percentile($ltvs, 90);
        $p75 = $this->percentile($ltvs, 75);
        $p50 = $this->percentile($ltvs, 50);
        
        // Segmentar
        $segments = [
            'vip' => ['min' => $p90, 'customers' => [], 'icon' => '👑', 'name' => 'VIP'],
            'high' => ['min' => $p75, 'max' => $p90, 'customers' => [], 'icon' => '💎', 'name' => 'Alto Valor'],
            'medium' => ['min' => $p50, 'max' => $p75, 'customers' => [], 'icon' => '⭐', 'name' => 'Valor Medio'],
            'low' => ['max' => $p50, 'customers' => [], 'icon' => '👤', 'name' => 'Valor Bajo']
        ];
        
        foreach ($customers as $customer) {
            $ltv = (float) $customer->total_spent;
            
            if ($ltv >= $p90) {
                $segments['vip']['customers'][] = $customer;
            } elseif ($ltv >= $p75) {
                $segments['high']['customers'][] = $customer;
            } elseif ($ltv >= $p50) {
                $segments['medium']['customers'][] = $customer;
            } else {
                $segments['low']['customers'][] = $customer;
            }
        }
        
        // Calcular métricas por segmento
        $result = [];
        
        foreach ($segments as $key => $segment) {
            if (empty($segment['customers'])) continue;
            
            $count = count($segment['customers']);
            $total_revenue = array_sum(array_map(function($c) { return (float) $c->total_spent; }, $segment['customers']));
            $total_orders = array_sum(array_map(function($c) { return (int) $c->order_count; }, $segment['customers']));
            $avg_ltv = $total_revenue / $count;
            $avg_orders = $total_orders / $count;
            $percentage = ($count / $total_customers) * 100;
            
            $action = '';
            if ($key === 'vip') {
                $action = 'Prioridad máxima en atención. Programa de fidelización exclusivo.';
            } elseif ($key === 'high') {
                $action = 'Candidatos a VIP. Ofrecer beneficios premium.';
            } elseif ($key === 'medium') {
                $action = 'Potencial de crecimiento. Campañas de upsell.';
            } else {
                $action = 'Aumentar frecuencia de compra con incentivos.';
            }
            
            $result[] = [
                'icon' => $segment['icon'],
                'name' => $segment['name'],
                'count' => $count,
                'percentage' => $percentage,
                'avg_ltv' => $avg_ltv,
                'total_revenue' => $total_revenue,
                'avg_orders' => $avg_orders,
                'action' => $action
            ];
        }
        
        return $result;
    }
    
    /**
     * Obtener top clientes
     */
    private function get_top_customers($start_date) {
        global $wpdb;
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                m.meta_value as customer_id,
                COUNT(p.ID) as order_count,
                SUM(pm.meta_value) as total_spent,
                MAX(p.post_date) as last_order_date
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
            JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND m.meta_value > 0
            GROUP BY m.meta_value
            ORDER BY total_spent DESC
            LIMIT 10",
            $start_date
        ));
        
        $result = [];
        
        foreach ($customers as $customer) {
            $user = get_userdata($customer->customer_id);
            if (!$user) continue;
            
            $name = $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->display_name;
            
            $result[] = [
                'id' => $customer->customer_id,
                'name' => $name,
                'ltv' => (float) $customer->total_spent,
                'orders' => (int) $customer->order_count,
                'last_order_timestamp' => strtotime($customer->last_order_date)
            ];
        }
        
        return $result;
    }
    
    /**
     * Calcular días promedio entre compras
     */
    private function calculate_avg_days_between_purchases() {
        global $wpdb;
        
        // Obtener clientes recurrentes
        $repeat_customers = $wpdb->get_results(
            "SELECT m.meta_value as customer_id
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND m.meta_value > 0
            GROUP BY m.meta_value
            HAVING COUNT(p.ID) >= 2"
        );
        
        $total_days = 0;
        $total_gaps = 0;
        
        foreach ($repeat_customers as $customer) {
            $orders = wc_get_orders([
                'customer_id' => $customer->customer_id,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'ASC',
                'status' => ['completed', 'processing']
            ]);
            
            if (count($orders) < 2) continue;
            
            for ($i = 1; $i < count($orders); $i++) {
                $date1 = $orders[$i - 1]->get_date_created()->getTimestamp();
                $date2 = $orders[$i]->get_date_created()->getTimestamp();
                $days = ($date2 - $date1) / 86400;
                
                $total_days += $days;
                $total_gaps++;
            }
        }
        
        return $total_gaps > 0 ? $total_days / $total_gaps : 0;
    }
    
    /**
     * Calcular percentil
     */
    private function percentile($array, $percentile) {
        if (empty($array)) return 0;
        
        $index = ($percentile / 100) * (count($array) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower == $upper) {
            return $array[$lower];
        }
        
        return $array[$lower] + ($array[$upper] - $array[$lower]) * ($index - $lower);
    }
    
    /**
     * Obtener insight de evolución
     */
    private function get_evolution_insight($data) {
        $growth_30_to_90 = $data['ltv_30'] > 0 ? (($data['ltv_90'] - $data['ltv_30']) / $data['ltv_30']) * 100 : 0;
        $growth_90_to_365 = $data['ltv_90'] > 0 ? (($data['ltv_365'] - $data['ltv_90']) / $data['ltv_90']) * 100 : 0;
        
        if ($data['ltv_365'] > $data['ltv_90'] * 1.5) {
            return '🚀 <strong>Excelente:</strong> El LTV se multiplica significativamente con el tiempo. Los clientes aumentan su valor a largo plazo.';
        } elseif ($data['ltv_365'] > $data['ltv_90'] * 1.2) {
            return '📈 <strong>Bueno:</strong> El valor del cliente crece de forma saludable. Hay fidelización efectiva.';
        } elseif ($data['ltv_365'] > $data['ltv_90']) {
            return '➡️ <strong>Estable:</strong> Crecimiento moderado del LTV. Hay margen para mejorar la retención.';
        } else {
            return '⚠️ <strong>Atención:</strong> El LTV no crece suficiente con el tiempo. Revisa estrategias de fidelización.';
        }
    }
    
    /**
     * Obtener recomendaciones
     */
    private function get_recommendations($data) {
        $recommendations = [];
        
        // Recomendación por LTV bajo
        if ($data['ltv_365'] < 50) {
            $recommendations[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'LTV bajo detectado',
                'description' => 'El LTV anual es muy bajo (' . wc_price($data['ltv_365']) . '). Considera aumentar el ticket medio con upsells o mejorar la frecuencia de compra.'
            ];
        }
        
        // Recomendación por baja retención
        if ($data['retention_365'] < 15) {
            $recommendations[] = [
                'type' => 'critical',
                'icon' => '🚨',
                'title' => 'Tasa de retención crítica',
                'description' => 'Solo el ' . round($data['retention_365'], 1) . '% de clientes vuelve a comprar. Implementa programa de fidelización o emails de reactivación.'
            ];
        }
        
        // Recomendación por gap entre compras alto
        if ($data['avg_days_between_purchases'] > 90) {
            $recommendations[] = [
                'type' => 'medium',
                'icon' => '⏰',
                'title' => 'Ciclo de compra muy largo',
                'description' => 'Los clientes tardan ' . round($data['avg_days_between_purchases']) . ' días entre compras. Activa campañas de recordatorio cada ' . round($data['avg_days_between_purchases'] / 2) . ' días.'
            ];
        }
        
        // Recomendación por buenos números
        if ($data['ltv_365'] > 100 && $data['retention_365'] > 20) {
            $recommendations[] = [
                'type' => 'success',
                'icon' => '✅',
                'title' => 'Métricas saludables',
                'description' => 'LTV de ' . wc_price($data['ltv_365']) . ' y retención del ' . round($data['retention_365'], 1) . '% son buenos indicadores. Enfócate en escalar la adquisición.'
            ];
        }
        
        // Recomendación por oportunidad de VIP
        if (!empty($data['segments'])) {
            $vip_segment = array_filter($data['segments'], function($s) { return $s['name'] === 'VIP'; });
            if (!empty($vip_segment)) {
                $vip = array_values($vip_segment)[0];
                $recommendations[] = [
                    'type' => 'info',
                    'icon' => '👑',
                    'title' => 'Potencia a tus VIP',
                    'description' => 'Tienes ' . $vip['count'] . ' clientes VIP que generan ' . wc_price($vip['total_revenue']) . '. Crea experiencias exclusivas para maximizar su lealtad.'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Renderizar estilos del widget
     */
    private function render_styles() {
        ?>
        <style>
            .glt-ltv-widget {
                margin: -12px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            /* Hero */
            .glt-ltv-hero {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 30px 20px;
                text-align: center;
                color: white;
            }
            
            .glt-ltv-hero-label {
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 1px;
                opacity: 0.9;
                margin-bottom: 10px;
            }
            
            .glt-ltv-hero-amount {
                font-size: 48px;
                font-weight: 800;
                margin: 10px 0;
                text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
            
            .glt-ltv-hero-subtitle {
                font-size: 14px;
                opacity: 0.9;
            }
            
            /* Periods */
            .glt-ltv-periods {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                padding: 20px;
                background: #f9f9f9;
            }
            
            .glt-ltv-period-card {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            
            .glt-ltv-period-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .glt-ltv-period-card.highlight {
                border: 2px solid #667eea;
                background: linear-gradient(135deg, #f5f7ff 0%, #fff 100%);
            }
            
            .glt-ltv-period-header {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .glt-ltv-period-icon {
                font-size: 20px;
            }
            
            .glt-ltv-period-title {
                font-size: 11px;
                font-weight: 700;
                color: #666;
            }
            
            .glt-ltv-period-value {
                font-size: 24px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 10px;
            }
            
            .glt-ltv-period-stats {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .glt-ltv-period-stat {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
            }
            
            .glt-ltv-period-stat span {
                color: #666;
            }
            
            .glt-ltv-period-stat strong {
                color: #2c3e50;
            }
            
            .glt-ltv-period-stat strong.success {
                color: #28a745;
            }
            
            .glt-ltv-period-stat strong.warning {
                color: #ffc107;
            }
            
            /* Evolution */
            .glt-ltv-evolution {
                padding: 20px;
                background: white;
                border-top: 1px solid #e0e0e0;
            }
            
            .glt-ltv-section-title {
                font-size: 14px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .glt-ltv-section-title .dashicons {
                color: #667eea;
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            
            .glt-ltv-evolution-bars {
                display: flex;
                align-items: flex-end;
                justify-content: space-around;
                height: 200px;
                background: linear-gradient(to bottom, #f9f9f9 0%, white 100%);
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .glt-ltv-evolution-bar-group {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .glt-ltv-evolution-bar {
                width: 60px;
                background: linear-gradient(to top, #667eea, #764ba2);
                border-radius: 4px 4px 0 0;
                position: relative;
                transition: height 0.5s ease;
                min-height: 40px;
            }
            
            .glt-ltv-evolution-bar.highlight {
                background: linear-gradient(to top, #28a745, #20c997);
            }
            
            .glt-ltv-evolution-bar-label {
                position: absolute;
                top: -25px;
                left: 50%;
                transform: translateX(-50%);
                font-size: 11px;
                font-weight: 700;
                color: #2c3e50;
                white-space: nowrap;
            }
            
            .glt-ltv-evolution-bar-title {
                font-size: 12px;
                font-weight: 700;
                color: #666;
            }
            
            .glt-ltv-evolution-insight {
                background: #e8f4fd;
                padding: 12px;
                border-radius: 6px;
                font-size: 12px;
                color: #004085;
                border-left: 3px solid #0066cc;
            }
            
            /* Segmentation */
            .glt-ltv-segmentation {
                padding: 20px;
                background: #f9f9f9;
                border-top: 1px solid #e0e0e0;
            }
            
            .glt-ltv-segments {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .glt-ltv-segment-card {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
            }
            
            .glt-ltv-segment-header {
                display: flex;
                gap: 12px;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .glt-ltv-segment-icon {
                font-size: 28px;
            }
            
            .glt-ltv-segment-name {
                font-size: 14px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 2px;
            }
            
            .glt-ltv-segment-count {
                font-size: 11px;
                color: #666;
            }
            
            .glt-ltv-segment-metrics {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-bottom: 10px;
            }
            
            .glt-ltv-segment-metric {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
            }
            
            .glt-ltv-segment-metric span {
                color: #666;
            }
            
            .glt-ltv-segment-metric strong {
                color: #2c3e50;
            }
            
            .glt-ltv-segment-metric strong.highlight {
                color: #667eea;
                font-size: 13px;
            }
            
            .glt-ltv-segment-action {
                background: #fff3cd;
                padding: 8px 10px;
                border-radius: 4px;
                font-size: 11px;
                color: #856404;
                border-left: 3px solid #ffc107;
            }
            
            /* Top Customers */
            .glt-ltv-top-customers {
                padding: 20px;
                background: white;
                border-top: 1px solid #e0e0e0;
            }
            
            .glt-ltv-customers-table table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .glt-ltv-customers-table th {
                text-align: left;
                font-size: 11px;
                color: #666;
                font-weight: 700;
                padding: 8px;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .glt-ltv-customers-table td {
                padding: 10px 8px;
                border-bottom: 1px solid #f0f0f0;
                font-size: 12px;
            }
            
            .glt-ltv-customer-cell {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .glt-ltv-customer-rank {
                font-weight: 700;
                color: #999;
                font-size: 11px;
            }
            
            .glt-ltv-customer-cell img {
                border-radius: 50%;
            }
            
            .glt-ltv-customer-cell a {
                color: #2271b1;
                text-decoration: none;
                font-weight: 600;
            }
            
            .glt-ltv-customer-cell a:hover {
                text-decoration: underline;
            }
            
            .glt-ltv-customer-value {
                color: #28a745;
                font-size: 14px;
            }
            
            .glt-ltv-customer-date {
                font-size: 11px;
                color: #999;
            }
            
            /* Additional Metrics */
            .glt-ltv-additional-metrics {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                padding: 20px;
                background: #f9f9f9;
                border-top: 1px solid #e0e0e0;
            }
            
            .glt-ltv-metric-box {
                background: white;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
                text-align: center;
            }
            
            .glt-ltv-metric-label {
                font-size: 11px;
                color: #666;
                margin-bottom: 8px;
            }
            
            .glt-ltv-metric-value {
                font-size: 24px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 6px;
            }
            
            .glt-ltv-metric-detail {
                font-size: 11px;
                color: #999;
            }
            
            /* Insights */
            .glt-ltv-insights {
                padding: 20px;
                background: white;
                border-top: 1px solid #e0e0e0;
            }
            
            .glt-ltv-insights-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .glt-ltv-insight-item {
                display: flex;
                gap: 12px;
                padding: 12px;
                border-radius: 6px;
                border-left: 4px solid;
            }
            
            .glt-ltv-insight-item.success {
                background: #d4edda;
                border-color: #28a745;
            }
            
            .glt-ltv-insight-item.warning {
                background: #fff3cd;
                border-color: #ffc107;
            }
            
            .glt-ltv-insight-item.critical {
                background: #f8d7da;
                border-color: #dc3545;
            }
            
            .glt-ltv-insight-item.info {
                background: #d1ecf1;
                border-color: #17a2b8;
            }
            
            .glt-ltv-insight-item.medium {
                background: #e2e3e5;
                border-color: #6c757d;
            }
            
            .glt-ltv-insight-icon {
                font-size: 20px;
            }
            
            .glt-ltv-insight-content strong {
                display: block;
                font-size: 13px;
                margin-bottom: 4px;
            }
            
            .glt-ltv-insight-content p {
                margin: 0;
                font-size: 12px;
                line-height: 1.4;
            }
            
            /* Empty state */
            .glt-ltv-empty {
                padding: 30px;
                text-align: center;
                color: #999;
                font-size: 12px;
            }
            
            /* Refresh */
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
                .glt-ltv-periods,
                .glt-ltv-segments,
                .glt-ltv-additional-metrics {
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
        $url = add_query_arg('glt_refresh_ltv', '1');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar análisis
        </a>
        <?php
    }
}

// Registrar el widget
Glotomania_LTV_Widget::register();
