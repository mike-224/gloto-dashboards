<?php
/**
 * =========================================================================
 * EJEMPLO DE IMPLEMENTACIÓN: WIDGET "URGENCIAS DEL DÍA"
 * Uno de los widgets más valiosos de la propuesta
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget: Urgencias del Día
 * 
 * Este widget responde: "¿Qué requiere mi atención INMEDIATA?"
 * Es un dashboard de alerta temprana que previene pérdidas
 */
class Glotomania_Urgency_Widget {
    
    private $cache_key = 'glt_urgencies_v1';
    private $cache_time = 1800; // 30 minutos (refresh frecuente)
    
    /**
     * Registrar el widget
     */
    public static function register() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget(
                'glt_urgency_widget',
                '🔥 Urgencias del Día',
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
        if (isset($_GET['glt_refresh_urgency'])) {
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg('glt_refresh_urgency'));
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
        $urgencies = $this->collect_urgencies();
        $total_urgencies = array_sum(array_column($urgencies, 'count'));
        
        ?>
        <div class="glt-urgency-widget">
            <?php if ($total_urgencies > 0): ?>
                <div class="glt-urgency-header">
                    <span class="glt-urgency-badge"><?php echo $total_urgencies; ?></span>
                    <span>alertas requieren atención</span>
                </div>
                
                <div class="glt-urgency-list">
                    <?php foreach ($urgencies as $urgency): ?>
                        <?php if ($urgency['count'] > 0): ?>
                            <div class="glt-urgency-item glt-severity-<?php echo esc_attr($urgency['severity']); ?>">
                                <div class="glt-urgency-icon"><?php echo $urgency['icon']; ?></div>
                                <div class="glt-urgency-content">
                                    <div class="glt-urgency-title">
                                        <strong><?php echo esc_html($urgency['title']); ?></strong>
                                        <span class="glt-urgency-count"><?php echo $urgency['count']; ?></span>
                                    </div>
                                    <div class="glt-urgency-description">
                                        <?php echo esc_html($urgency['description']); ?>
                                    </div>
                                    <?php if (!empty($urgency['action_url'])): ?>
                                        <a href="<?php echo esc_url($urgency['action_url']); ?>" 
                                           class="glt-urgency-action button button-small">
                                            <?php echo esc_html($urgency['action_label']); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($urgency['revenue_impact'])): ?>
                                        <div class="glt-urgency-impact">
                                            💰 Impacto estimado: <?php echo wc_price($urgency['revenue_impact']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glt-urgency-empty">
                    <div class="glt-urgency-icon-large">✅</div>
                    <h3>¡Todo en orden!</h3>
                    <p>No hay urgencias que requieran atención inmediata.</p>
                    <p class="description">Última actualización: <?php echo date('H:i'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php $this->render_styles(); ?>
            <?php $this->render_refresh_button(); ?>
        </div>
        <?php
    }
    
    /**
     * Recopilar todas las urgencias
     */
    private function collect_urgencies() {
        return [
            $this->check_pending_orders_24h(),
            $this->check_out_of_stock_with_demand(),
            $this->check_failed_payments_recoverable(),
            $this->check_unanswered_reviews(),
            $this->check_low_stock_bestsellers(),
        ];
    }
    
    /**
     * Urgencia 1: Pedidos pendientes >24h
     */
    private function check_pending_orders_24h() {
        $threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => 'pending',
            'date_created' => '<' . $threshold,
            'return' => 'ids'
        ]);
        
        $count = count($orders);
        $revenue_at_risk = 0;
        
        if ($count > 0) {
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $revenue_at_risk += $order->get_total();
                }
            }
        }
        
        return [
            'icon' => '⏰',
            'title' => 'Pedidos pendientes >24h',
            'description' => 'Pedidos sin procesar que pueden abandonarse',
            'count' => $count,
            'severity' => $count > 10 ? 'critical' : ($count > 5 ? 'high' : 'medium'),
            'action_url' => admin_url('edit.php?post_type=shop_order&post_status=wc-pending'),
            'action_label' => 'Revisar pedidos',
            'revenue_impact' => $revenue_at_risk
        ];
    }
    
    /**
     * Urgencia 2: Productos sin stock con demanda activa
     */
    private function check_out_of_stock_with_demand() {
        global $wpdb;
        
        // Productos agotados
        $oos_products = wc_get_products([
            'limit' => -1,
            'stock_status' => 'outofstock',
            'return' => 'ids'
        ]);
        
        $count = 0;
        $lost_revenue = 0;
        
        if (!empty($oos_products)) {
            // Verificar si tienen pedidos pendientes o ventas recientes
            foreach ($oos_products as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                // Verificar ventas en últimos 7 días
                $recent_sales = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT order_id) 
                    FROM {$wpdb->prefix}woocommerce_order_items oi
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                    JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
                    WHERE oim.meta_key = '_product_id' 
                    AND oim.meta_value = %d
                    AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND p.post_status IN ('wc-completed', 'wc-processing')",
                    $product_id
                ));
                
                if ($recent_sales > 0) {
                    $count++;
                    // Estimar pérdida: ventas recientes * precio promedio * días sin stock
                    $avg_price = $product->get_price();
                    $lost_revenue += ($recent_sales / 7) * $avg_price * 7; // Pérdida semanal estimada
                }
            }
        }
        
        return [
            'icon' => '📦',
            'title' => 'Productos agotados con demanda',
            'description' => 'Productos sin stock que siguen generando interés',
            'count' => $count,
            'severity' => $count > 5 ? 'critical' : ($count > 2 ? 'high' : 'medium'),
            'action_url' => admin_url('edit.php?post_type=product&stock_status=outofstock'),
            'action_label' => 'Gestionar stock',
            'revenue_impact' => $lost_revenue
        ];
    }
    
    /**
     * Urgencia 3: Pagos fallidos recuperables (últimas 6h)
     */
    private function check_failed_payments_recoverable() {
        $threshold = date('Y-m-d H:i:s', strtotime('-6 hours'));
        
        $failed_orders = wc_get_orders([
            'limit' => -1,
            'status' => 'failed',
            'date_created' => '>=' . $threshold,
            'return' => 'ids'
        ]);
        
        $count = count($failed_orders);
        $recoverable_revenue = 0;
        
        if ($count > 0) {
            foreach ($failed_orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $recoverable_revenue += $order->get_total();
                }
            }
        }
        
        return [
            'icon' => '💳',
            'title' => 'Pagos fallidos recientes',
            'description' => 'Intentos de pago fallidos en las últimas 6 horas',
            'count' => $count,
            'severity' => $count > 5 ? 'high' : 'medium',
            'action_url' => admin_url('edit.php?post_type=shop_order&post_status=wc-failed'),
            'action_label' => 'Contactar clientes',
            'revenue_impact' => $recoverable_revenue
        ];
    }
    
    /**
     * Urgencia 4: Reviews pendientes de respuesta
     */
    private function check_unanswered_reviews() {
        global $wpdb;
        
        // Buscar comentarios de productos sin respuesta (últimos 7 días)
        $unanswered = $wpdb->get_var(
            "SELECT COUNT(c.comment_ID) 
            FROM {$wpdb->comments} c
            LEFT JOIN {$wpdb->comments} r ON r.comment_parent = c.comment_ID
            WHERE c.comment_type = 'review'
            AND c.comment_approved = '1'
            AND c.comment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND r.comment_ID IS NULL"
        );
        
        $count = intval($unanswered);
        
        return [
            'icon' => '💬',
            'title' => 'Reviews sin responder',
            'description' => 'Valoraciones de clientes esperando respuesta',
            'count' => $count,
            'severity' => $count > 10 ? 'high' : 'low',
            'action_url' => admin_url('edit-comments.php?comment_type=review'),
            'action_label' => 'Responder reviews',
            'revenue_impact' => 0 // Impacto indirecto en reputación
        ];
    }
    
    /**
     * Urgencia 5: Bestsellers con stock crítico
     */
    private function check_low_stock_bestsellers() {
        // Top 20 productos por ventas
        $bestsellers = wc_get_products([
            'limit' => 20,
            'orderby' => 'total_sales',
            'order' => 'DESC',
            'return' => 'ids'
        ]);
        
        $count = 0;
        $potential_loss = 0;
        
        foreach ($bestsellers as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || !$product->managing_stock()) continue;
            
            $stock = $product->get_stock_quantity();
            $sales = $product->get_total_sales();
            
            // Stock crítico: menos de 3 días de inventario
            $daily_sales = $sales / 30; // Promedio últimos 30 días (aproximado)
            $days_of_stock = $daily_sales > 0 ? $stock / $daily_sales : 999;
            
            if ($days_of_stock < 3 && $stock > 0) {
                $count++;
                $potential_loss += $product->get_price() * $daily_sales * 7; // Pérdida semanal si se agota
            }
        }
        
        return [
            'icon' => '⚡',
            'title' => 'Bestsellers con stock crítico',
            'description' => 'Productos populares que se agotarán pronto',
            'count' => $count,
            'severity' => $count > 3 ? 'critical' : ($count > 1 ? 'high' : 'low'),
            'action_url' => admin_url('admin.php?page=wc-reports&tab=stock&report=low_in_stock'),
            'action_label' => 'Reabastecer urgente',
            'revenue_impact' => $potential_loss
        ];
    }
    
    /**
     * Renderizar estilos del widget
     */
    private function render_styles() {
        ?>
        <style>
            .glt-urgency-widget {
                margin: -12px -12px 0;
            }
            
            .glt-urgency-header {
                background: #fff3cd;
                border-left: 4px solid #f39c12;
                padding: 12px 15px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 13px;
                font-weight: 600;
            }
            
            .glt-urgency-badge {
                background: #f39c12;
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 700;
            }
            
            .glt-urgency-list {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .glt-urgency-item {
                display: flex;
                gap: 12px;
                padding: 12px 15px;
                border-bottom: 1px solid #f0f0f0;
                transition: background 0.2s;
            }
            
            .glt-urgency-item:hover {
                background: #f9f9f9;
            }
            
            .glt-urgency-item:last-child {
                border-bottom: none;
            }
            
            .glt-urgency-icon {
                font-size: 24px;
                line-height: 1;
            }
            
            .glt-urgency-content {
                flex: 1;
            }
            
            .glt-urgency-title {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 4px;
            }
            
            .glt-urgency-count {
                background: #e74c3c;
                color: white;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 700;
            }
            
            .glt-urgency-description {
                font-size: 12px;
                color: #666;
                margin-bottom: 8px;
            }
            
            .glt-urgency-action {
                margin-top: 8px;
            }
            
            .glt-urgency-impact {
                font-size: 11px;
                color: #c0392b;
                font-weight: 600;
                margin-top: 6px;
                padding: 4px 8px;
                background: #fff5f5;
                border-radius: 3px;
                display: inline-block;
            }
            
            /* Niveles de severidad */
            .glt-severity-critical {
                border-left: 3px solid #e74c3c;
            }
            
            .glt-severity-high {
                border-left: 3px solid #f39c12;
            }
            
            .glt-severity-medium {
                border-left: 3px solid #3498db;
            }
            
            .glt-severity-low {
                border-left: 3px solid #95a5a6;
            }
            
            /* Estado vacío */
            .glt-urgency-empty {
                text-align: center;
                padding: 40px 20px;
            }
            
            .glt-urgency-icon-large {
                font-size: 48px;
                margin-bottom: 10px;
            }
            
            .glt-urgency-empty h3 {
                color: #27ae60;
                margin: 10px 0;
            }
            
            .glt-urgency-empty p {
                color: #666;
                margin: 5px 0;
            }
            
            /* Botón de refresh */
            .glt-refresh-link {
                display: block;
                text-align: right;
                padding: 10px 15px;
                border-top: 1px solid #f0f0f0;
                font-size: 11px;
                color: #999;
                text-decoration: none;
            }
            
            .glt-refresh-link:hover {
                color: #007cba;
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar botón de actualización
     */
    private function render_refresh_button() {
        $url = add_query_arg('glt_refresh_urgency', '1');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar urgencias
        </a>
        <?php
    }
}

// Registrar el widget
Glotomania_Urgency_Widget::register();
