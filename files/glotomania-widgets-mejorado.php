<?php
/**
 * =========================================================================
 * GLOTOMANIA WIDGETS - VERSIÓN MEJORADA
 * Sistema modular de widgets de analytics para WooCommerce
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal de gestión de widgets
 */
class Glotomania_Dashboard_Widgets {
    
    private static $instance = null;
    private $cache_prefix = 'glt_cache_';
    private $cache_version = '100'; // Incrementar para forzar regeneración
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('admin_head', [$this, 'render_global_styles']);
        add_action('wp_dashboard_setup', [$this, 'register_all_widgets']);
        add_action('admin_init', [$this, 'handle_cache_refresh']);
    }
    
    /**
     * Estilos globales
     */
    public function render_global_styles() {
        ?>
        <style>
            /* Responsive grid fix */
            @media (max-width: 1600px) {
                .glt-grid-responsive,
                div[style*="grid-template-columns: 1fr 1fr"] {
                    grid-template-columns: 1fr !important;
                    gap: 10px !important;
                }
            }
            
            /* Componentes base */
            .glt-card {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 10px;
            }
            
            .glt-list-row {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                margin-bottom: 6px;
                font-size: 13px;
                gap: 5px;
                border-bottom: 1px solid #eee;
                padding: 6px 0;
            }
            
            .glt-list-row:last-child {
                border-bottom: none;
            }
            
            .glt-big-num {
                font-size: 28px;
                font-weight: 700;
                color: #2c3e50;
                line-height: 1;
            }
            
            .glt-col-header {
                font-size: 11px;
                text-transform: uppercase;
                margin-bottom: 8px;
                font-weight: 700;
                border-bottom: 2px solid #eee;
                padding-bottom: 5px;
            }
            
            .glt-refresh-link {
                display: block;
                text-align: right;
                margin-top: 10px;
                padding-top: 5px;
                border-top: 1px solid #eee;
                font-size: 11px;
                color: #999;
                text-decoration: none;
            }
            
            .glt-refresh-link:hover {
                color: #007cba;
            }
            
            .glt-refresh-link .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
                vertical-align: middle;
            }
            
            /* Loading state */
            .glt-loading {
                padding: 20px;
                text-align: center;
                color: #999;
            }
        </style>
        <?php
    }
    
    /**
     * Registrar todos los widgets
     */
    public function register_all_widgets() {
        $widgets = [
            'growth' => [
                'id' => 'glt_widget_growth',
                'title' => '🚀 Glotomania: Crecimiento (30 días)',
                'callback' => [$this, 'render_growth_widget']
            ],
            'abandoned' => [
                'id' => 'glt_widget_abandoned',
                'title' => '🛒 Dinero en Mesa (30 días)',
                'callback' => [$this, 'render_abandoned_widget']
            ],
            'pulse' => [
                'id' => 'glt_widget_pulse',
                'title' => '💰 Pulso de Ventas',
                'callback' => [$this, 'render_pulse_widget']
            ],
            'strategy' => [
                'id' => 'glt_widget_strategy',
                'title' => '🧠 Ticket Medio & Stock',
                'callback' => [$this, 'render_strategy_widget']
            ],
            'pareto' => [
                'id' => 'glt_widget_pareto',
                'title' => '🏆 Facturación & Roturas',
                'callback' => [$this, 'render_pareto_widget']
            ],
            'whales' => [
                'id' => 'glt_widget_whales',
                'title' => '🐳 Clientes VIP',
                'callback' => [$this, 'render_whales_widget']
            ]
        ];
        
        foreach ($widgets as $widget) {
            wp_add_dashboard_widget(
                $widget['id'],
                $widget['title'],
                $widget['callback']
            );
        }
    }
    
    /**
     * Manejar refresh de caché
     */
    public function handle_cache_refresh() {
        if (isset($_GET['glt_force_refresh']) && current_user_can('manage_options')) {
            $key = sanitize_text_field($_GET['glt_force_refresh']);
            delete_transient($key);
            
            // Nonce check sería ideal aquí
            wp_safe_redirect(remove_query_arg('glt_force_refresh'));
            exit;
        }
    }
    
    /**
     * Helper: Obtener caché o ejecutar callback
     */
    private function get_cached_or_render($key, $ttl, $callback) {
        $cache_key = $this->cache_prefix . $key . '_v' . $this->cache_version;
        
        if (false === ($output = get_transient($cache_key))) {
            ob_start();
            call_user_func($callback);
            $output = ob_get_clean();
            set_transient($cache_key, $output, $ttl);
        }
        
        echo $output;
    }
    
    /**
     * Helper: Botón de refresh
     */
    private function render_refresh_button($key) {
        $url = add_query_arg(
            'glt_force_refresh',
            $this->cache_prefix . $key . '_v' . $this->cache_version,
            remove_query_arg('glt_force_refresh')
        );
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link" title="Borrar caché y recalcular">
            <span class="dashicons dashicons-update"></span> Actualizar datos
        </a>
        <?php
    }
    
    // =========================================================================
    // WIDGETS INDIVIDUALES
    // =========================================================================
    
    /**
     * Widget: Crecimiento
     */
    public function render_growth_widget() {
        $this->get_cached_or_render('growth', 3600, function() {
            $days = 30;
            $start = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
            
            // Obtener pedidos
            $orders = wc_get_orders([
                'limit' => -1,
                'date_created' => '>=' . $start,
                'status' => ['wc-completed', 'wc-processing', 'wc-on-hold'],
                'return' => 'ids'
            ]);
            $total_orders = count($orders);
            
            // Obtener nuevos usuarios
            $new_users = get_users([
                'role__in' => ['customer', 'subscriber'],
                'date_query' => [[
                    'after' => $start,
                    'inclusive' => true
                ]],
                'fields' => 'ID'
            ]);
            $total_users = count($new_users);
            
            // Calcular conversión
            $success = 0;
            $idle = 0;
            
            if ($total_users > 0) {
                foreach ($new_users as $uid) {
                    $user_orders = wc_get_orders([
                        'customer_id' => $uid,
                        'limit' => 1,
                        'return' => 'ids',
                        'status' => ['wc-completed', 'wc-processing']
                    ]);
                    
                    if (!empty($user_orders)) {
                        $success++;
                    } else {
                        $idle++;
                    }
                }
            }
            
            $conversion_rate = $total_users > 0 ? round(($success / $total_users) * 100, 1) : 0;
            
            // Calcular pedidos de nuevos vs recurrentes
            $new_client_orders = 0;
            $recurring_orders = 0;
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;
                
                if (in_array($order->get_user_id(), $new_users)) {
                    $new_client_orders++;
                } else {
                    $recurring_orders++;
                }
            }
            
            $pct_new = $total_orders > 0 ? ($new_client_orders / $total_orders) * 100 : 0;
            
            // Renderizar
            ?>
            <div class="glt-grid-responsive" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <div class="glt-card" style="border-left:4px solid #3498db">
                        <div class="glt-col-header" style="border:none;margin:0;color:#7f8c8d;">
                            👥 Nuevos Usuarios
                        </div>
                        <div class="glt-big-num"><?php echo number_format($total_users); ?></div>
                    </div>
                    <div class="glt-list-row">
                        <span>✅ Compradores</span>
                        <strong><?php echo $success; ?> (<?php echo $conversion_rate; ?>%)</strong>
                    </div>
                    <div class="glt-list-row">
                        <span>💤 Sin compra</span>
                        <strong><?php echo $idle; ?></strong>
                    </div>
                </div>
                
                <div>
                    <div class="glt-card" style="border-left:4px solid #2ecc71">
                        <div class="glt-col-header" style="border:none;margin:0;color:#7f8c8d;">
                            📦 Pedidos
                        </div>
                        <div class="glt-big-num"><?php echo number_format($total_orders); ?></div>
                    </div>
                    <div class="glt-list-row">
                        <span>🆕 Nuevos clientes</span>
                        <strong><?php echo $new_client_orders; ?></strong>
                    </div>
                    <div style="height:4px;background:#eee;margin-bottom:5px;">
                        <div style="height:100%;width:<?php echo $pct_new; ?>%;background:#3498db;"></div>
                    </div>
                    <div class="glt-list-row">
                        <span>💎 Recurrentes</span>
                        <strong><?php echo $recurring_orders; ?></strong>
                    </div>
                </div>
            </div>
            <?php
            
            $this->render_refresh_button('growth');
        });
    }
    
    /**
     * Widget: Carritos Abandonados
     */
    public function render_abandoned_widget() {
        $this->get_cached_or_render('abandoned', 3900, function() {
            $start = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            $orders = wc_get_orders([
                'limit' => -1,
                'date_created' => '>=' . $start,
                'status' => ['wc-pending', 'wc-failed', 'wc-cancelled']
            ]);
            
            $lost_revenue = 0;
            $count = 0;
            
            foreach ($orders as $order) {
                $lost_revenue += $order->get_total();
                $count++;
            }
            
            ?>
            <div style="display:flex;gap:15px;align-items:center;">
                <div style="flex:1;background:#fff5f5;border-left:4px solid #e74c3c;padding:15px;">
                    <div style="font-size:10px;color:#7f8c8d">TOTAL PERDIDO</div>
                    <div style="font-size:24px;font-weight:800;color:#c0392b">
                        <?php echo wc_price($lost_revenue); ?>
                    </div>
                </div>
                <div style="flex:1;">
                    <div style="font-size:12px;margin-bottom:5px;">
                        <strong><?php echo $count; ?></strong> Intentos fallidos
                    </div>
                    <?php if ($count > 0): ?>
                        <a href="<?php echo admin_url('edit.php?post_type=shop_order&post_status=wc-pending,wc-failed,wc-cancelled'); ?>" 
                           class="button button-small">
                            Recuperar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            
            $this->render_refresh_button('abandoned');
        });
    }
    
    /**
     * Widget: Pulso de Ventas
     */
    public function render_pulse_widget() {
        $this->get_cached_or_render('pulse', 4200, function() {
            global $wpdb;
            
            // Helper para obtener revenue
            $get_revenue = function($start_date, $end_date) use ($wpdb) {
                return (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(pm.meta_value) 
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
            
            $now = current_time('timestamp');
            
            // Hoy vs Ayer
            $today = $get_revenue(
                date('Y-m-d 00:00:00', $now),
                date('Y-m-d 23:59:59', $now)
            );
            $yesterday = $get_revenue(
                date('Y-m-d 00:00:00', strtotime('-1 day', $now)),
                date('Y-m-d 23:59:59', strtotime('-1 day', $now))
            );
            
            // Este mes vs Mes anterior
            $this_month = $get_revenue(
                date('Y-m-01 00:00:00', $now),
                date('Y-m-d 23:59:59', $now)
            );
            $last_month = $get_revenue(
                date('Y-m-01 00:00:00', strtotime('last month', $now)),
                date('Y-m-t 23:59:59', strtotime('last month', $now))
            );
            
            // Función para renderizar una caja
            $render_box = function($label, $current, $previous) {
                $diff = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);
                $color = $diff >= 0 ? '#27ae60' : '#c0392b';
                $arrow = $diff >= 0 ? '↑' : '↓';
                ?>
                <div style="background:#fff;border:1px solid #eee;padding:10px;border-left:3px solid <?php echo $color; ?>">
                    <div style="font-size:10px;color:#999"><?php echo esc_html($label); ?></div>
                    <div style="font-size:18px;font-weight:700">
                        <?php echo wc_price($current); ?>
                    </div>
                    <div style="font-size:10px;color:<?php echo $color; ?>">
                        <?php echo $arrow; ?> <?php echo abs(round($diff, 1)); ?>% vs anterior
                    </div>
                </div>
                <?php
            };
            
            ?>
            <div class="glt-grid-responsive" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <?php $render_box('HOY', $today, $yesterday); ?>
                <?php $render_box('ESTE MES', $this_month, $last_month); ?>
            </div>
            <?php
            
            $this->render_refresh_button('pulse');
        });
    }
    
    /**
     * Widget: Estrategia (Ticket Medio & Stock)
     */
    public function render_strategy_widget() {
        $this->get_cached_or_render('strategy', 4500, function() {
            global $wpdb;
            $start = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            // Calcular AOV (Average Order Value)
            $aov_data = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(pm.meta_value) as revenue, COUNT(p.ID) as count 
                FROM {$wpdb->prefix}posts p 
                JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'shop_order' 
                AND p.post_status IN ('wc-completed', 'wc-processing') 
                AND p.post_date >= %s 
                AND pm.meta_key = '_order_total'",
                $start
            ));
            
            $aov = ($aov_data && $aov_data->count > 0) ? $aov_data->revenue / $aov_data->count : 0;
            
            // Productos con stock bajo
            $low_stock = $wpdb->get_var(
                "SELECT COUNT(p.ID) 
                FROM {$wpdb->prefix}posts p 
                JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
                WHERE p.post_type IN ('product', 'product_variation') 
                AND p.post_status = 'publish' 
                AND pm.meta_key = '_stock' 
                AND CAST(pm.meta_value AS SIGNED) <= 3 
                AND CAST(pm.meta_value AS SIGNED) > 0"
            );
            
            ?>
            <div class="glt-grid-responsive" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div style="background:#fff;border:1px solid #ddd;padding:15px;border-left:4px solid #3498db;border-radius:4px">
                    <div style="font-size:10px;color:#888;">TICKET MEDIO (30 días)</div>
                    <div style="font-size:22px;font-weight:700;color:#2c3e50">
                        <?php echo wc_price($aov); ?>
                    </div>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=wc-reports&tab=stock&report=low_in_stock'); ?>" 
                   style="display:block;text-decoration:none;background:#fff;border:1px solid #ddd;padding:15px;border-left:4px solid #f39c12;border-radius:4px">
                    <div style="font-size:10px;color:#888;">STOCK BAJO (≤3 unidades)</div>
                    <div style="font-size:22px;font-weight:700;color:#2c3e50">
                        <?php echo number_format($low_stock); ?> <span style="font-size:12px">productos</span>
                    </div>
                </a>
            </div>
            <?php
            
            $this->render_refresh_button('strategy');
        });
    }
    
    /**
     * Widget: Pareto (Top Facturación & Roturas)
     */
    public function render_pareto_widget() {
        $this->get_cached_or_render('pareto', 4800, function() {
            global $wpdb;
            
            // Top 5 productos por facturación
            $top_revenue = $wpdb->get_results(
                "SELECT product_id, SUM(product_net_revenue) as total 
                FROM {$wpdb->prefix}wc_order_product_lookup 
                GROUP BY product_id 
                ORDER BY total DESC 
                LIMIT 5"
            );
            
            // Productos agotados con ventas históricas (VIP)
            $out_of_stock = wc_get_products([
                'limit' => 20,
                'status' => 'publish',
                'return' => 'ids',
                'stock_status' => 'outofstock',
                'manage_stock' => true,
                'orderby' => 'total_sales',
                'order' => 'DESC'
            ]);
            
            ?>
            <div class="glt-grid-responsive" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Columna 1: Top Ingresos -->
                <div>
                    <div class="glt-col-header" style="color:#27ae60;border-color:#27ae60;">
                        💶 Top Ingresos
                    </div>
                    <?php if ($top_revenue): ?>
                        <?php foreach ($top_revenue as $item): ?>
                            <?php 
                            $product = wc_get_product($item->product_id);
                            if (!$product) continue;
                            ?>
                            <div class="glt-list-row">
                                <a href="<?php echo get_edit_post_link($item->product_id); ?>" 
                                   style="text-decoration:none;font-weight:500;color:#2271b1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;">
                                    <?php echo esc_html($product->get_name()); ?>
                                </a>
                                <strong><?php echo wc_price($item->total); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size:11px;color:#999;padding:10px;background:#eee;">
                            Sin datos. 
                            <a href="<?php echo admin_url('admin.php?page=wc-admin&path=/analytics/settings'); ?>">
                                Importar histórico
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Columna 2: Agotados VIP -->
                <div>
                    <div class="glt-col-header" style="color:#c0392b;border-color:#c0392b;">
                        🚨 Agotados VIP
                    </div>
                    <?php 
                    $count = 0;
                    if ($out_of_stock):
                        foreach ($out_of_stock as $product_id):
                            if ($count >= 5) break;
                            
                            $product = wc_get_product($product_id);
                            if (!$product) continue;
                            
                            $sales = $product->get_total_sales();
                            if ($sales < 1) continue; // Solo mostrar productos con ventas
                            
                            $count++;
                            ?>
                            <div class="glt-list-row">
                                <a href="<?php echo get_edit_post_link($product_id); ?>" 
                                   style="color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;">
                                    <?php echo esc_html($product->get_name()); ?>
                                </a>
                                <div style="text-align:right;line-height:1.1">
                                    <span style="color:#c0392b;font-weight:bold;font-size:10px;">SIN STOCK</span><br>
                                    <span style="font-size:9px;color:#999">Hist: <?php echo $sales; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if ($count == 0): ?>
                        <div style="font-size:11px;color:#27ae60">✅ Todo OK</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            
            $this->render_refresh_button('pareto');
        });
    }
    
    /**
     * Widget: Ballenas (Clientes VIP)
     */
    public function render_whales_widget() {
        $this->get_cached_or_render('whales', 5100, function() {
            global $wpdb;
            
            $whales = $wpdb->get_results(
                "SELECT m.meta_value as user_id, SUM(pm.meta_value) as total 
                FROM {$wpdb->prefix}posts p 
                JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total' 
                JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user' 
                WHERE p.post_type = 'shop_order' 
                AND p.post_status IN ('wc-completed', 'wc-processing') 
                AND m.meta_value > 0 
                GROUP BY m.meta_value 
                ORDER BY total DESC 
                LIMIT 5"
            );
            
            ?>
            <div style="margin-top:5px;">
                <?php if ($whales): ?>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr style="text-align:left;color:#999;font-size:10px;">
                            <th>CLIENTE</th>
                            <th style="text-align:right;">LTV</th>
                        </tr>
                        <?php foreach ($whales as $whale): ?>
                            <?php 
                            $user = get_userdata($whale->user_id);
                            if (!$user) continue;
                            
                            $name = $user->first_name ? $user->first_name : $user->display_name;
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:6px 0;font-size:12px;display:flex;align-items:center;gap:8px;">
                                    <?php echo get_avatar($whale->user_id, 20); ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $whale->user_id); ?>" 
                                       style="text-decoration:none;color:#333;font-weight:600;">
                                        <?php echo esc_html($name); ?>
                                    </a>
                                </td>
                                <td style="text-align:right;color:#27ae60;font-weight:bold;">
                                    <?php echo wc_price($whale->total); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div style="font-size:11px;color:#999;">Sin datos disponibles</div>
                <?php endif; ?>
            </div>
            <?php
            
            $this->render_refresh_button('whales');
        });
    }
}

// Inicializar
Glotomania_Dashboard_Widgets::get_instance();
