<?php
/**
 * =========================================================================
 * WIDGET: VELOCIDAD DE CRECIMIENTO
 * Responde: ¿El negocio acelera o desacelera?
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget: Velocidad de Crecimiento
 * 
 * Este widget muestra:
 * - MoM Revenue Growth (mes actual vs anterior)
 * - WoW momentum (semana actual vs anterior)
 * - Proyección de fin de mes
 * - Días estimados para alcanzar objetivo
 * - Indicadores visuales de aceleración/desaceleración
 */
class Glotomania_Growth_Velocity_Widget {
    
    private $cache_key = 'glt_growth_velocity_v1';
    private $cache_time = 3600; // 60 minutos
    
    // Configuración de objetivos (pueden ser opciones guardadas en DB)
    private $monthly_target = 25000; // Objetivo mensual en euros
    
    /**
     * Registrar el widget
     */
    public static function register() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget(
                'glt_growth_velocity_widget',
                '📈 Velocidad de Crecimiento',
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
        if (isset($_GET['glt_refresh_velocity'])) {
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg('glt_refresh_velocity'));
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
        
        ?>
        <div class="glt-velocity-widget">
            
            <!-- Métrica principal: Este Mes -->
            <div class="glt-velocity-hero">
                <div class="glt-velocity-main-metric">
                    <div class="glt-velocity-label">ESTE MES</div>
                    <div class="glt-velocity-amount">
                        <?php echo wc_price($metrics['current_month']['revenue']); ?>
                    </div>
                    <div class="glt-velocity-change <?php echo $metrics['current_month']['growth'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $this->render_trend_arrow($metrics['current_month']['growth']); ?>
                        <?php echo abs(round($metrics['current_month']['growth'], 1)); ?>% vs mes anterior
                    </div>
                </div>
                
                <!-- Barra de progreso hacia objetivo -->
                <?php if ($this->monthly_target > 0): ?>
                    <div class="glt-velocity-progress-container">
                        <div class="glt-velocity-progress-bar">
                            <div class="glt-velocity-progress-fill" 
                                 style="width: <?php echo min(100, $metrics['current_month']['progress_pct']); ?>%">
                            </div>
                        </div>
                        <div class="glt-velocity-progress-label">
                            <?php echo round($metrics['current_month']['progress_pct']); ?>% del objetivo 
                            (<?php echo wc_price($this->monthly_target); ?>)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grid de métricas secundarias -->
            <div class="glt-velocity-grid">
                
                <!-- Esta Semana -->
                <div class="glt-velocity-card">
                    <div class="glt-velocity-card-icon">📅</div>
                    <div class="glt-velocity-card-content">
                        <div class="glt-velocity-card-label">Esta Semana</div>
                        <div class="glt-velocity-card-value">
                            <?php echo wc_price($metrics['current_week']['revenue']); ?>
                        </div>
                        <div class="glt-velocity-card-change <?php echo $metrics['current_week']['growth'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $this->render_trend_arrow($metrics['current_week']['growth']); ?>
                            <?php echo abs(round($metrics['current_week']['growth'], 1)); ?>% vs anterior
                        </div>
                    </div>
                </div>
                
                <!-- Hoy -->
                <div class="glt-velocity-card">
                    <div class="glt-velocity-card-icon">⚡</div>
                    <div class="glt-velocity-card-content">
                        <div class="glt-velocity-card-label">Hoy</div>
                        <div class="glt-velocity-card-value">
                            <?php echo wc_price($metrics['today']['revenue']); ?>
                        </div>
                        <div class="glt-velocity-card-change <?php echo $metrics['today']['growth'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $this->render_trend_arrow($metrics['today']['growth']); ?>
                            <?php echo abs(round($metrics['today']['growth'], 1)); ?>% vs ayer
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Proyección y Análisis -->
            <div class="glt-velocity-projection">
                <div class="glt-velocity-projection-header">
                    <span class="dashicons dashicons-chart-line"></span>
                    Proyección Fin de Mes
                </div>
                
                <div class="glt-velocity-projection-content">
                    <div class="glt-velocity-projection-amount">
                        <?php echo wc_price($metrics['projection']['end_of_month']); ?>
                    </div>
                    
                    <?php if ($this->monthly_target > 0): ?>
                        <div class="glt-velocity-projection-details">
                            <div class="glt-velocity-projection-row">
                                <span>Falta para objetivo:</span>
                                <strong><?php echo wc_price($metrics['projection']['gap']); ?></strong>
                            </div>
                            <div class="glt-velocity-projection-row">
                                <span>Días restantes:</span>
                                <strong><?php echo $metrics['projection']['days_remaining']; ?> días</strong>
                            </div>
                            <div class="glt-velocity-projection-row">
                                <span>Revenue diario necesario:</span>
                                <strong><?php echo wc_price($metrics['projection']['daily_needed']); ?>/día</strong>
                            </div>
                        </div>
                        
                        <!-- Indicador de viabilidad -->
                        <div class="glt-velocity-projection-status">
                            <?php echo $this->render_viability_status($metrics['projection']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Análisis de Momentum -->
            <div class="glt-velocity-momentum">
                <div class="glt-velocity-momentum-header">
                    🎯 Análisis de Momentum
                </div>
                <div class="glt-velocity-momentum-content">
                    <?php echo $this->render_momentum_analysis($metrics); ?>
                </div>
            </div>
            
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
        
        $now = current_time('timestamp');
        
        // Función helper para obtener revenue
        $get_revenue = function($start_date, $end_date) use ($wpdb) {
            return (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(pm.meta_value), 0)
                FROM {$wpdb->prefix}posts p 
                JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'shop_order' 
                AND p.post_status IN ('wc-completed', 'wc-processing') 
                AND p.post_date >= %s 
                AND p.post_date <= %s 
                AND pm.meta_key = '_order_total'",
                $start_date,
                $end_date
            ));
        };
        
        // HOY vs AYER
        $today_revenue = $get_revenue(
            date('Y-m-d 00:00:00', $now),
            date('Y-m-d 23:59:59', $now)
        );
        
        $yesterday_revenue = $get_revenue(
            date('Y-m-d 00:00:00', strtotime('-1 day', $now)),
            date('Y-m-d 23:59:59', strtotime('-1 day', $now))
        );
        
        $today_growth = $yesterday_revenue > 0 
            ? (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100 
            : ($today_revenue > 0 ? 100 : 0);
        
        // ESTA SEMANA vs SEMANA ANTERIOR
        $week_start = strtotime('monday this week', $now);
        $week_end = $now;
        
        $current_week_revenue = $get_revenue(
            date('Y-m-d 00:00:00', $week_start),
            date('Y-m-d 23:59:59', $week_end)
        );
        
        $last_week_revenue = $get_revenue(
            date('Y-m-d 00:00:00', strtotime('-7 days', $week_start)),
            date('Y-m-d 23:59:59', strtotime('-7 days', $week_end))
        );
        
        $week_growth = $last_week_revenue > 0 
            ? (($current_week_revenue - $last_week_revenue) / $last_week_revenue) * 100 
            : ($current_week_revenue > 0 ? 100 : 0);
        
        // ESTE MES vs MES ANTERIOR
        $month_start = strtotime('first day of this month', $now);
        $month_end = $now;
        
        $current_month_revenue = $get_revenue(
            date('Y-m-d 00:00:00', $month_start),
            date('Y-m-d 23:59:59', $month_end)
        );
        
        // Para comparar con mes anterior completo
        $last_month_start = strtotime('first day of last month', $now);
        $last_month_end = strtotime('last day of last month', $now);
        
        $last_month_revenue = $get_revenue(
            date('Y-m-d 00:00:00', $last_month_start),
            date('Y-m-d 23:59:59', $last_month_end)
        );
        
        // Calcular growth ajustado por días transcurridos
        $days_in_last_month = date('t', $last_month_start);
        $days_in_current_month = date('j', $now); // Días transcurridos
        
        $last_month_daily_avg = $last_month_revenue / $days_in_last_month;
        $last_month_comparable = $last_month_daily_avg * $days_in_current_month;
        
        $month_growth = $last_month_comparable > 0 
            ? (($current_month_revenue - $last_month_comparable) / $last_month_comparable) * 100 
            : ($current_month_revenue > 0 ? 100 : 0);
        
        // PROYECCIÓN FIN DE MES
        $days_in_month = date('t', $now);
        $days_elapsed = date('j', $now);
        $days_remaining = $days_in_month - $days_elapsed;
        
        $daily_average = $days_elapsed > 0 ? $current_month_revenue / $days_elapsed : 0;
        $projected_month_end = $current_month_revenue + ($daily_average * $days_remaining);
        
        $gap = $this->monthly_target - $projected_month_end;
        $daily_needed = $days_remaining > 0 ? $gap / $days_remaining : 0;
        
        $progress_pct = $this->monthly_target > 0 
            ? ($current_month_revenue / $this->monthly_target) * 100 
            : 0;
        
        // Calcular velocidad actual vs necesaria
        $current_daily_avg = $daily_average;
        $required_daily_avg = $days_remaining > 0 
            ? ($this->monthly_target - $current_month_revenue) / $days_remaining 
            : 0;
        
        return [
            'today' => [
                'revenue' => $today_revenue,
                'growth' => $today_growth
            ],
            'current_week' => [
                'revenue' => $current_week_revenue,
                'growth' => $week_growth
            ],
            'current_month' => [
                'revenue' => $current_month_revenue,
                'growth' => $month_growth,
                'progress_pct' => $progress_pct
            ],
            'projection' => [
                'end_of_month' => $projected_month_end,
                'gap' => $gap,
                'days_remaining' => $days_remaining,
                'daily_needed' => $required_daily_avg,
                'current_daily_avg' => $current_daily_avg,
                'is_on_track' => $projected_month_end >= $this->monthly_target
            ],
            'momentum' => [
                'day' => $today_growth,
                'week' => $week_growth,
                'month' => $month_growth
            ]
        ];
    }
    
    /**
     * Renderizar flecha de tendencia
     */
    private function render_trend_arrow($growth) {
        if ($growth > 0) {
            return '<span class="glt-velocity-arrow up">↑</span>';
        } elseif ($growth < 0) {
            return '<span class="glt-velocity-arrow down">↓</span>';
        } else {
            return '<span class="glt-velocity-arrow neutral">→</span>';
        }
    }
    
    /**
     * Renderizar estado de viabilidad del objetivo
     */
    private function render_viability_status($projection) {
        if ($projection['is_on_track']) {
            $status_class = 'success';
            $status_icon = '✅';
            $status_text = 'Objetivo alcanzable';
            $status_detail = 'Mantén el ritmo actual para superar el objetivo mensual.';
        } else {
            $deficit_pct = ($projection['current_daily_avg'] > 0) 
                ? (($projection['daily_needed'] - $projection['current_daily_avg']) / $projection['current_daily_avg']) * 100 
                : 100;
            
            if ($deficit_pct > 50) {
                $status_class = 'critical';
                $status_icon = '🚨';
                $status_text = 'Requiere acción urgente';
                $status_detail = 'Necesitas aumentar las ventas en ' . round($deficit_pct) . '% para alcanzar el objetivo.';
            } elseif ($deficit_pct > 20) {
                $status_class = 'warning';
                $status_icon = '⚠️';
                $status_text = 'Acelerar necesario';
                $status_detail = 'Aumenta el ritmo en ' . round($deficit_pct) . '% para llegar al objetivo.';
            } else {
                $status_class = 'caution';
                $status_icon = '💡';
                $status_text = 'Cerca del objetivo';
                $status_detail = 'Un pequeño empujón y lo consigues. Necesitas ' . wc_price($projection['gap']) . ' más.';
            }
        }
        
        ob_start();
        ?>
        <div class="glt-velocity-status <?php echo $status_class; ?>">
            <div class="glt-velocity-status-icon"><?php echo $status_icon; ?></div>
            <div class="glt-velocity-status-content">
                <div class="glt-velocity-status-title"><?php echo $status_text; ?></div>
                <div class="glt-velocity-status-detail"><?php echo $status_detail; ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar análisis de momentum
     */
    private function render_momentum_analysis($metrics) {
        $momentum = $metrics['momentum'];
        
        // Determinar tendencia general
        $avg_growth = ($momentum['day'] + $momentum['week'] + $momentum['month']) / 3;
        
        // Detectar aceleración/desaceleración
        $accelerating = $momentum['day'] > $momentum['week'] && $momentum['week'] > $momentum['month'];
        $decelerating = $momentum['day'] < $momentum['week'] && $momentum['week'] < $momentum['month'];
        
        ob_start();
        ?>
        <div class="glt-velocity-momentum-grid">
            <div class="glt-velocity-momentum-item">
                <span class="glt-velocity-momentum-label">Tendencia general:</span>
                <strong class="<?php echo $avg_growth >= 0 ? 'positive' : 'negative'; ?>">
                    <?php 
                    if ($avg_growth > 10) echo 'Crecimiento fuerte 🚀';
                    elseif ($avg_growth > 0) echo 'Crecimiento moderado 📈';
                    elseif ($avg_growth > -10) echo 'Estable ➡️';
                    else echo 'Requiere atención 📉';
                    ?>
                </strong>
            </div>
            
            <?php if ($accelerating): ?>
                <div class="glt-velocity-momentum-item highlight">
                    <span class="glt-velocity-momentum-label">Estado:</span>
                    <strong class="positive">⚡ Acelerando</strong>
                </div>
                <div class="glt-velocity-momentum-message success">
                    ✨ Excelente! El negocio está ganando velocidad. Mantén las acciones actuales.
                </div>
            <?php elseif ($decelerating): ?>
                <div class="glt-velocity-momentum-item highlight">
                    <span class="glt-velocity-momentum-label">Estado:</span>
                    <strong class="negative">⚠️ Desacelerando</strong>
                </div>
                <div class="glt-velocity-momentum-message warning">
                    💡 El ritmo está bajando. Considera activar promociones o campañas de marketing.
                </div>
            <?php else: ?>
                <div class="glt-velocity-momentum-item">
                    <span class="glt-velocity-momentum-label">Estado:</span>
                    <strong>📊 Ritmo constante</strong>
                </div>
            <?php endif; ?>
            
            <!-- Sugerencias basadas en métricas -->
            <?php if ($metrics['projection']['gap'] > 0 && !$metrics['projection']['is_on_track']): ?>
                <div class="glt-velocity-momentum-message info">
                    <strong>💡 Sugerencias para alcanzar el objetivo:</strong>
                    <ul>
                        <li>Lanzar una promoción flash este fin de semana</li>
                        <li>Reactivar clientes inactivos con cupón</li>
                        <li>Hacer upsell en productos complementarios</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar estilos del widget
     */
    private function render_styles() {
        ?>
        <style>
            .glt-velocity-widget {
                margin: -12px;
            }
            
            /* Hero Section */
            .glt-velocity-hero {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                text-align: center;
            }
            
            .glt-velocity-main-metric {
                margin-bottom: 15px;
            }
            
            .glt-velocity-label {
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 1px;
                opacity: 0.9;
                margin-bottom: 5px;
            }
            
            .glt-velocity-amount {
                font-size: 36px;
                font-weight: 800;
                margin: 5px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .glt-velocity-change {
                font-size: 13px;
                font-weight: 600;
            }
            
            .glt-velocity-change.positive {
                color: #a8ff78;
            }
            
            .glt-velocity-change.negative {
                color: #ffb199;
            }
            
            .glt-velocity-arrow {
                font-size: 14px;
                font-weight: bold;
            }
            
            /* Progress Bar */
            .glt-velocity-progress-container {
                margin-top: 15px;
            }
            
            .glt-velocity-progress-bar {
                background: rgba(255,255,255,0.2);
                height: 8px;
                border-radius: 10px;
                overflow: hidden;
                margin-bottom: 8px;
            }
            
            .glt-velocity-progress-fill {
                background: linear-gradient(90deg, #a8ff78, #78ffd6);
                height: 100%;
                border-radius: 10px;
                transition: width 0.5s ease;
            }
            
            .glt-velocity-progress-label {
                font-size: 11px;
                opacity: 0.9;
            }
            
            /* Grid de Cards */
            .glt-velocity-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                padding: 15px;
                background: #f9f9f9;
            }
            
            .glt-velocity-card {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                display: flex;
                gap: 12px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            
            .glt-velocity-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .glt-velocity-card-icon {
                font-size: 28px;
                line-height: 1;
            }
            
            .glt-velocity-card-content {
                flex: 1;
            }
            
            .glt-velocity-card-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .glt-velocity-card-value {
                font-size: 20px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 4px;
            }
            
            .glt-velocity-card-change {
                font-size: 11px;
                font-weight: 600;
            }
            
            .glt-velocity-card-change.positive {
                color: #27ae60;
            }
            
            .glt-velocity-card-change.negative {
                color: #e74c3c;
            }
            
            /* Proyección */
            .glt-velocity-projection {
                background: white;
                border-top: 1px solid #e0e0e0;
                padding: 15px;
            }
            
            .glt-velocity-projection-header {
                font-size: 13px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .glt-velocity-projection-header .dashicons {
                color: #3498db;
            }
            
            .glt-velocity-projection-amount {
                font-size: 28px;
                font-weight: 700;
                color: #3498db;
                margin-bottom: 12px;
            }
            
            .glt-velocity-projection-details {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 12px;
            }
            
            .glt-velocity-projection-row {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                padding: 5px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .glt-velocity-projection-row:last-child {
                border-bottom: none;
            }
            
            .glt-velocity-projection-row span {
                color: #666;
            }
            
            .glt-velocity-projection-row strong {
                color: #2c3e50;
            }
            
            /* Status */
            .glt-velocity-status {
                display: flex;
                gap: 12px;
                padding: 12px;
                border-radius: 6px;
                align-items: flex-start;
            }
            
            .glt-velocity-status.success {
                background: #d4edda;
                border-left: 4px solid #28a745;
            }
            
            .glt-velocity-status.warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
            }
            
            .glt-velocity-status.caution {
                background: #d1ecf1;
                border-left: 4px solid #17a2b8;
            }
            
            .glt-velocity-status.critical {
                background: #f8d7da;
                border-left: 4px solid #dc3545;
            }
            
            .glt-velocity-status-icon {
                font-size: 20px;
                line-height: 1;
            }
            
            .glt-velocity-status-title {
                font-size: 13px;
                font-weight: 700;
                margin-bottom: 4px;
                color: #2c3e50;
            }
            
            .glt-velocity-status-detail {
                font-size: 11px;
                color: #666;
                line-height: 1.4;
            }
            
            /* Momentum */
            .glt-velocity-momentum {
                background: #f9f9f9;
                border-top: 1px solid #e0e0e0;
                padding: 15px;
            }
            
            .glt-velocity-momentum-header {
                font-size: 13px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 12px;
            }
            
            .glt-velocity-momentum-grid {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .glt-velocity-momentum-item {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                padding: 8px;
                background: white;
                border-radius: 4px;
            }
            
            .glt-velocity-momentum-item.highlight {
                border-left: 3px solid #3498db;
            }
            
            .glt-velocity-momentum-label {
                color: #666;
            }
            
            .glt-velocity-momentum-item strong.positive {
                color: #27ae60;
            }
            
            .glt-velocity-momentum-item strong.negative {
                color: #e74c3c;
            }
            
            .glt-velocity-momentum-message {
                padding: 10px;
                border-radius: 4px;
                font-size: 11px;
                line-height: 1.5;
            }
            
            .glt-velocity-momentum-message.success {
                background: #d4edda;
                color: #155724;
                border-left: 3px solid #28a745;
            }
            
            .glt-velocity-momentum-message.warning {
                background: #fff3cd;
                color: #856404;
                border-left: 3px solid #ffc107;
            }
            
            .glt-velocity-momentum-message.info {
                background: #d1ecf1;
                color: #0c5460;
                border-left: 3px solid #17a2b8;
            }
            
            .glt-velocity-momentum-message ul {
                margin: 8px 0 0 18px;
                padding: 0;
            }
            
            .glt-velocity-momentum-message li {
                margin: 4px 0;
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
                .glt-velocity-grid {
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
        $url = add_query_arg('glt_refresh_velocity', '1');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar datos
        </a>
        <?php
    }
}

// Registrar el widget
Glotomania_Growth_Velocity_Widget::register();
