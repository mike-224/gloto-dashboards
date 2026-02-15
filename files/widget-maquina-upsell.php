<?php
/**
 * =========================================================================
 * WIDGET: MÁQUINA DE UPSELL
 * Responde: ¿Estoy dejando dinero sobre la mesa?
 * =========================================================================
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget: Máquina de Upsell
 * 
 * Este widget identifica:
 * - Productos con mayor AOV (Average Order Value)
 * - Mejores combos (comprados juntos frecuentemente)
 * - Upsell success rate (% aceptación de productos relacionados)
 * - Productos "escalera" (base → premium)
 * - Revenue potencial no explotado de cross-sell
 */
class Glotomania_Upsell_Machine_Widget {
    
    private $cache_key = 'glt_upsell_machine_v1';
    private $cache_time = 7200; // 2 horas (análisis más pesado)
    
    // Configuración
    private $min_combo_frequency = 3; // Mínimo 3 veces para considerar combo
    private $days_for_analysis = 90; // Analizar últimos 90 días
    
    /**
     * Registrar el widget
     */
    public static function register() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget(
                'glt_upsell_machine_widget',
                '🎁 Máquina de Upsell',
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
        if (isset($_GET['glt_refresh_upsell'])) {
            delete_transient($instance->cache_key);
            wp_safe_redirect(remove_query_arg('glt_refresh_upsell'));
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
        $data = $this->analyze_upsell_opportunities();
        
        ?>
        <div class="glt-upsell-widget">
            
            <!-- Header con potencial total -->
            <div class="glt-upsell-hero">
                <div class="glt-upsell-hero-content">
                    <div class="glt-upsell-hero-label">💰 REVENUE POTENCIAL SIN EXPLOTAR</div>
                    <div class="glt-upsell-hero-amount">
                        <?php echo wc_price($data['total_potential']); ?>
                    </div>
                    <div class="glt-upsell-hero-subtitle">
                        Oportunidad estimada en los próximos 30 días
                    </div>
                </div>
            </div>
            
            <!-- Métricas rápidas -->
            <div class="glt-upsell-quick-stats">
                <div class="glt-upsell-stat-card">
                    <div class="glt-upsell-stat-icon">📊</div>
                    <div class="glt-upsell-stat-value"><?php echo wc_price($data['avg_aov']); ?></div>
                    <div class="glt-upsell-stat-label">AOV Actual</div>
                </div>
                
                <div class="glt-upsell-stat-card">
                    <div class="glt-upsell-stat-icon">🎯</div>
                    <div class="glt-upsell-stat-value"><?php echo wc_price($data['potential_aov']); ?></div>
                    <div class="glt-upsell-stat-label">AOV con Upsell</div>
                </div>
                
                <div class="glt-upsell-stat-card highlight">
                    <div class="glt-upsell-stat-icon">💎</div>
                    <div class="glt-upsell-stat-value">+<?php echo round($data['aov_lift'], 1); ?>%</div>
                    <div class="glt-upsell-stat-label">Potencial de Mejora</div>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="glt-upsell-tabs">
                <button class="glt-upsell-tab active" data-tab="combos">
                    🔗 Mejores Combos (<?php echo count($data['combos']); ?>)
                </button>
                <button class="glt-upsell-tab" data-tab="high-aov">
                    💎 AOV Alto (<?php echo count($data['high_aov_products']); ?>)
                </button>
                <button class="glt-upsell-tab" data-tab="ladder">
                    🎯 Escalera (<?php echo count($data['ladder_products']); ?>)
                </button>
            </div>
            
            <!-- Tab Content: Combos -->
            <div class="glt-upsell-tab-content active" id="tab-combos">
                <div class="glt-upsell-section-header">
                    <h4>🔗 Productos Comprados Juntos</h4>
                    <p>Combos con alta frecuencia de compra conjunta</p>
                </div>
                
                <?php if (!empty($data['combos'])): ?>
                    <div class="glt-upsell-combos-list">
                        <?php foreach (array_slice($data['combos'], 0, 5) as $combo): ?>
                            <div class="glt-upsell-combo-card">
                                <div class="glt-upsell-combo-products">
                                    <div class="glt-upsell-combo-product">
                                        <a href="<?php echo get_edit_post_link($combo['product_a_id']); ?>" 
                                           class="glt-upsell-product-name" target="_blank">
                                            <?php echo esc_html($combo['product_a_name']); ?>
                                        </a>
                                        <span class="glt-upsell-product-price">
                                            <?php echo wc_price($combo['product_a_price']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="glt-upsell-combo-connector">+</div>
                                    
                                    <div class="glt-upsell-combo-product">
                                        <a href="<?php echo get_edit_post_link($combo['product_b_id']); ?>" 
                                           class="glt-upsell-product-name" target="_blank">
                                            <?php echo esc_html($combo['product_b_name']); ?>
                                        </a>
                                        <span class="glt-upsell-product-price">
                                            <?php echo wc_price($combo['product_b_price']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="glt-upsell-combo-stats">
                                    <div class="glt-upsell-combo-stat">
                                        <span class="glt-upsell-combo-stat-label">Frecuencia:</span>
                                        <strong><?php echo $combo['frequency']; ?>×</strong>
                                    </div>
                                    <div class="glt-upsell-combo-stat">
                                        <span class="glt-upsell-combo-stat-label">Tasa de combo:</span>
                                        <strong><?php echo round($combo['combo_rate'], 1); ?>%</strong>
                                    </div>
                                    <div class="glt-upsell-combo-stat">
                                        <span class="glt-upsell-combo-stat-label">Revenue extra/mes:</span>
                                        <strong class="success"><?php echo wc_price($combo['monthly_potential']); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="glt-upsell-combo-action">
                                    <div class="glt-upsell-combo-insight">
                                        💡 <strong>Oportunidad:</strong> 
                                        <?php echo $this->get_combo_insight($combo); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glt-upsell-empty">
                        <p>No hay suficientes datos para detectar combos. Se necesitan al menos <?php echo $this->min_combo_frequency; ?> compras conjuntas.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Content: High AOV Products -->
            <div class="glt-upsell-tab-content" id="tab-high-aov">
                <div class="glt-upsell-section-header">
                    <h4>💎 Productos con Mayor Ticket Medio</h4>
                    <p>Productos que elevan significativamente el valor del pedido</p>
                </div>
                
                <?php if (!empty($data['high_aov_products'])): ?>
                    <div class="glt-upsell-products-list">
                        <?php foreach (array_slice($data['high_aov_products'], 0, 5) as $product): ?>
                            <div class="glt-upsell-product-card">
                                <div class="glt-upsell-product-header">
                                    <a href="<?php echo get_edit_post_link($product['id']); ?>" 
                                       class="glt-upsell-product-title" target="_blank">
                                        <?php echo esc_html($product['name']); ?>
                                    </a>
                                    <span class="glt-upsell-product-badge">
                                        <?php echo wc_price($product['price']); ?>
                                    </span>
                                </div>
                                
                                <div class="glt-upsell-product-metrics">
                                    <div class="glt-upsell-product-metric">
                                        <span>AOV con este producto:</span>
                                        <strong class="highlight"><?php echo wc_price($product['aov']); ?></strong>
                                    </div>
                                    <div class="glt-upsell-product-metric">
                                        <span>Ventas (<?php echo $this->days_for_analysis; ?>d):</span>
                                        <strong><?php echo $product['sales']; ?></strong>
                                    </div>
                                    <div class="glt-upsell-product-metric">
                                        <span>Lift vs AOV medio:</span>
                                        <strong class="success">+<?php echo round($product['aov_lift'], 1); ?>%</strong>
                                    </div>
                                </div>
                                
                                <div class="glt-upsell-product-insight">
                                    💡 Aparece en pedidos de alto valor. Ideal para recomendar a clientes premium.
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glt-upsell-empty">
                        <p>No hay productos con suficiente historial de ventas.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Content: Ladder Products -->
            <div class="glt-upsell-tab-content" id="tab-ladder">
                <div class="glt-upsell-section-header">
                    <h4>🎯 Productos Escalera</h4>
                    <p>Detecta patrones de upgrade (compra básica → compra premium)</p>
                </div>
                
                <?php if (!empty($data['ladder_products'])): ?>
                    <div class="glt-upsell-ladder-list">
                        <?php foreach (array_slice($data['ladder_products'], 0, 5) as $ladder): ?>
                            <div class="glt-upsell-ladder-card">
                                <div class="glt-upsell-ladder-step">
                                    <div class="glt-upsell-ladder-step-label">Paso 1: Producto Base</div>
                                    <a href="<?php echo get_edit_post_link($ladder['base_product_id']); ?>" 
                                       class="glt-upsell-ladder-product" target="_blank">
                                        <strong><?php echo esc_html($ladder['base_product_name']); ?></strong>
                                        <span><?php echo wc_price($ladder['base_price']); ?></span>
                                    </a>
                                </div>
                                
                                <div class="glt-upsell-ladder-arrow">
                                    ↓ <?php echo round($ladder['upgrade_rate'], 1); ?>% hacen upgrade
                                </div>
                                
                                <div class="glt-upsell-ladder-step">
                                    <div class="glt-upsell-ladder-step-label">Paso 2: Producto Premium</div>
                                    <a href="<?php echo get_edit_post_link($ladder['premium_product_id']); ?>" 
                                       class="glt-upsell-ladder-product premium" target="_blank">
                                        <strong><?php echo esc_html($ladder['premium_product_name']); ?></strong>
                                        <span><?php echo wc_price($ladder['premium_price']); ?></span>
                                    </a>
                                </div>
                                
                                <div class="glt-upsell-ladder-stats">
                                    <div class="glt-upsell-ladder-stat">
                                        <span>Tiempo medio de upgrade:</span>
                                        <strong><?php echo $ladder['avg_days_to_upgrade']; ?> días</strong>
                                    </div>
                                    <div class="glt-upsell-ladder-stat">
                                        <span>Revenue extra/upgrade:</span>
                                        <strong class="success">
                                            +<?php echo wc_price($ladder['premium_price'] - $ladder['base_price']); ?>
                                        </strong>
                                    </div>
                                </div>
                                
                                <div class="glt-upsell-ladder-action">
                                    💡 <strong>Sugerencia:</strong> Email automático en día <?php echo max(1, $ladder['avg_days_to_upgrade'] - 5); ?> 
                                    ofreciendo upgrade con descuento.
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glt-upsell-empty">
                        <p>No se detectaron patrones de upgrade. Esto puede significar:</p>
                        <ul>
                            <li>Los clientes no vuelven a comprar</li>
                            <li>No hay productos en rangos de precio diferenciados</li>
                            <li>Necesitas más historial de datos (<?php echo $this->days_for_analysis; ?> días)</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Resumen de Oportunidades -->
            <div class="glt-upsell-summary">
                <div class="glt-upsell-section-header">
                    <h4>📊 Resumen de Oportunidades</h4>
                </div>
                
                <div class="glt-upsell-opportunities">
                    <?php if ($data['total_potential'] > 0): ?>
                        <div class="glt-upsell-opportunity-item">
                            <div class="glt-upsell-opportunity-icon">🔗</div>
                            <div class="glt-upsell-opportunity-content">
                                <strong>Combos frecuentes</strong>
                                <p>
                                    <?php echo count($data['combos']); ?> combos detectados con potencial de 
                                    <strong><?php echo wc_price($data['combo_potential']); ?>/mes</strong>
                                </p>
                                <a href="#tab-combos" class="button button-small glt-tab-link" data-tab="combos">
                                    Ver combos
                                </a>
                            </div>
                        </div>
                        
                        <div class="glt-upsell-opportunity-item">
                            <div class="glt-upsell-opportunity-icon">💎</div>
                            <div class="glt-upsell-opportunity-content">
                                <strong>Productos de alto valor</strong>
                                <p>
                                    <?php echo count($data['high_aov_products']); ?> productos elevan el AOV en 
                                    <strong>+<?php echo round($data['avg_aov_lift'], 1); ?>%</strong> promedio
                                </p>
                                <a href="#tab-high-aov" class="button button-small glt-tab-link" data-tab="high-aov">
                                    Ver productos
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($data['ladder_products'])): ?>
                            <div class="glt-upsell-opportunity-item">
                                <div class="glt-upsell-opportunity-icon">🎯</div>
                                <div class="glt-upsell-opportunity-content">
                                    <strong>Oportunidades de upgrade</strong>
                                    <p>
                                        <?php echo count($data['ladder_products']); ?> patrones de escalera detectados con 
                                        <strong><?php echo round($data['avg_upgrade_rate'], 1); ?>%</strong> de conversión
                                    </p>
                                    <a href="#tab-ladder" class="button button-small glt-tab-link" data-tab="ladder">
                                        Ver escaleras
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="glt-upsell-no-opportunities">
                            <div class="glt-upsell-no-opportunities-icon">📊</div>
                            <h3>Construyendo Datos de Análisis</h3>
                            <p>Necesitas más historial de ventas para detectar patrones de upsell.</p>
                            <p><strong>Recomendación:</strong> Vuelve a revisar este widget en 30 días.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php $this->render_styles(); ?>
            <?php $this->render_scripts(); ?>
            <?php $this->render_refresh_button(); ?>
        </div>
        <?php
    }
    
    /**
     * Analizar oportunidades de upsell
     */
    private function analyze_upsell_opportunities() {
        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', strtotime('-' . $this->days_for_analysis . ' days'));
        
        // Calcular AOV actual
        $aov_data = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(pm.meta_value) as avg_aov, SUM(pm.meta_value) as total_revenue, COUNT(p.ID) as total_orders
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND pm.meta_key = '_order_total'",
            $start_date
        ));
        
        $avg_aov = $aov_data ? (float) $aov_data->avg_aov : 0;
        $total_orders = $aov_data ? (int) $aov_data->total_orders : 0;
        
        // Analizar combos
        $combos = $this->find_product_combos($start_date);
        
        // Analizar productos con alto AOV
        $high_aov_products = $this->find_high_aov_products($start_date, $avg_aov);
        
        // Analizar productos escalera
        $ladder_products = $this->find_ladder_products($start_date);
        
        // Calcular potencial total
        $combo_potential = array_sum(array_column($combos, 'monthly_potential'));
        
        $avg_aov_lift = 0;
        if (!empty($high_aov_products)) {
            $avg_aov_lift = array_sum(array_column($high_aov_products, 'aov_lift')) / count($high_aov_products);
        }
        
        $potential_aov = $avg_aov * (1 + ($avg_aov_lift / 100));
        $aov_lift = $avg_aov > 0 ? (($potential_aov - $avg_aov) / $avg_aov) * 100 : 0;
        
        // Estimar revenue potencial mensual
        $monthly_orders = $total_orders * (30 / $this->days_for_analysis);
        $total_potential = ($potential_aov - $avg_aov) * $monthly_orders + $combo_potential;
        
        // Calcular promedio de upgrade rate
        $avg_upgrade_rate = 0;
        if (!empty($ladder_products)) {
            $avg_upgrade_rate = array_sum(array_column($ladder_products, 'upgrade_rate')) / count($ladder_products);
        }
        
        return [
            'avg_aov' => $avg_aov,
            'potential_aov' => $potential_aov,
            'aov_lift' => $aov_lift,
            'total_potential' => $total_potential,
            'combos' => $combos,
            'combo_potential' => $combo_potential,
            'high_aov_products' => $high_aov_products,
            'avg_aov_lift' => $avg_aov_lift,
            'ladder_products' => $ladder_products,
            'avg_upgrade_rate' => $avg_upgrade_rate
        ];
    }
    
    /**
     * Encontrar combos de productos frecuentes
     */
    private function find_product_combos($start_date) {
        global $wpdb;
        
        // Obtener todos los pedidos con sus productos
        $orders = wc_get_orders([
            'limit' => -1,
            'date_created' => '>=' . $start_date,
            'status' => ['completed', 'processing'],
            'return' => 'ids'
        ]);
        
        $product_pairs = [];
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $items = $order->get_items();
            $product_ids = [];
            
            foreach ($items as $item) {
                $product_id = $item->get_product_id();
                if ($product_id) {
                    $product_ids[] = $product_id;
                }
            }
            
            // Generar pares de productos
            if (count($product_ids) >= 2) {
                $product_ids = array_unique($product_ids);
                sort($product_ids);
                
                for ($i = 0; $i < count($product_ids) - 1; $i++) {
                    for ($j = $i + 1; $j < count($product_ids); $j++) {
                        $pair_key = $product_ids[$i] . '-' . $product_ids[$j];
                        
                        if (!isset($product_pairs[$pair_key])) {
                            $product_pairs[$pair_key] = [
                                'product_a' => $product_ids[$i],
                                'product_b' => $product_ids[$j],
                                'frequency' => 0
                            ];
                        }
                        
                        $product_pairs[$pair_key]['frequency']++;
                    }
                }
            }
        }
        
        // Filtrar por frecuencia mínima y enriquecer datos
        $combos = [];
        
        foreach ($product_pairs as $pair) {
            if ($pair['frequency'] >= $this->min_combo_frequency) {
                $product_a = wc_get_product($pair['product_a']);
                $product_b = wc_get_product($pair['product_b']);
                
                if (!$product_a || !$product_b) continue;
                
                // Calcular tasa de combo (cuando se compra A, qué % incluye B)
                $product_a_orders = $this->count_product_orders($pair['product_a'], $start_date);
                $combo_rate = $product_a_orders > 0 ? ($pair['frequency'] / $product_a_orders) * 100 : 0;
                
                // Estimar potencial mensual
                $monthly_orders = $product_a_orders * (30 / $this->days_for_analysis);
                $potential_combos = $monthly_orders * (1 - ($combo_rate / 100));
                $monthly_potential = $potential_combos * $product_b->get_price();
                
                $combos[] = [
                    'product_a_id' => $pair['product_a'],
                    'product_a_name' => $product_a->get_name(),
                    'product_a_price' => $product_a->get_price(),
                    'product_b_id' => $pair['product_b'],
                    'product_b_name' => $product_b->get_name(),
                    'product_b_price' => $product_b->get_price(),
                    'frequency' => $pair['frequency'],
                    'combo_rate' => $combo_rate,
                    'monthly_potential' => $monthly_potential
                ];
            }
        }
        
        // Ordenar por potencial mensual
        usort($combos, function($a, $b) {
            return $b['monthly_potential'] <=> $a['monthly_potential'];
        });
        
        return $combos;
    }
    
    /**
     * Encontrar productos con alto AOV
     */
    private function find_high_aov_products($start_date, $global_aov) {
        global $wpdb;
        
        // Obtener productos con sus AOV individuales
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oim.meta_value as product_id,
                AVG(pm.meta_value) as aov,
                COUNT(DISTINCT p.ID) as order_count
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND oim.meta_key = '_product_id'
            GROUP BY oim.meta_value
            HAVING order_count >= 3
            ORDER BY aov DESC
            LIMIT 10",
            $start_date
        ));
        
        $high_aov_products = [];
        
        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);
            if (!$product) continue;
            
            $aov = (float) $row->aov;
            $aov_lift = $global_aov > 0 ? (($aov - $global_aov) / $global_aov) * 100 : 0;
            
            // Solo incluir productos que eleven el AOV significativamente
            if ($aov_lift > 10) {
                $high_aov_products[] = [
                    'id' => $row->product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'aov' => $aov,
                    'aov_lift' => $aov_lift,
                    'sales' => (int) $row->order_count
                ];
            }
        }
        
        return $high_aov_products;
    }
    
    /**
     * Encontrar productos escalera (upgrade patterns)
     */
    private function find_ladder_products($start_date) {
        global $wpdb;
        
        // Obtener clientes que compraron múltiples veces
        $repeat_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT m.meta_value as customer_id
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND m.meta_value > 0
            GROUP BY m.meta_value
            HAVING COUNT(p.ID) >= 2",
            $start_date
        ));
        
        $upgrade_patterns = [];
        
        foreach ($repeat_customers as $customer) {
            $customer_id = $customer->customer_id;
            
            // Obtener pedidos del cliente ordenados por fecha
            $orders = wc_get_orders([
                'customer_id' => $customer_id,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'ASC',
                'status' => ['completed', 'processing'],
                'date_created' => '>=' . $start_date
            ]);
            
            if (count($orders) < 2) continue;
            
            // Analizar productos entre pedidos consecutivos
            for ($i = 0; $i < count($orders) - 1; $i++) {
                $order1 = $orders[$i];
                $order2 = $orders[$i + 1];
                
                $products1 = [];
                foreach ($order1->get_items() as $item) {
                    $products1[] = $item->get_product_id();
                }
                
                foreach ($order2->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    
                    $product2 = wc_get_product($product_id);
                    if (!$product2) continue;
                    
                    foreach ($products1 as $prod1_id) {
                        if ($prod1_id == $product_id) continue;
                        
                        $product1 = wc_get_product($prod1_id);
                        if (!$product1) continue;
                        
                        // Detectar upgrade por precio y categoría
                        if ($product2->get_price() > $product1->get_price() * 1.2) {
                            $cats1 = wp_get_post_terms($prod1_id, 'product_cat', ['fields' => 'ids']);
                            $cats2 = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
                            
                            $shared_cats = array_intersect($cats1, $cats2);
                            
                            if (!empty($shared_cats)) {
                                $pair_key = $prod1_id . '-' . $product_id;
                                
                                if (!isset($upgrade_patterns[$pair_key])) {
                                    $upgrade_patterns[$pair_key] = [
                                        'base' => $prod1_id,
                                        'premium' => $product_id,
                                        'count' => 0,
                                        'total_days' => 0
                                    ];
                                }
                                
                                $upgrade_patterns[$pair_key]['count']++;
                                
                                $days_diff = ($order2->get_date_created()->getTimestamp() - $order1->get_date_created()->getTimestamp()) / 86400;
                                $upgrade_patterns[$pair_key]['total_days'] += $days_diff;
                            }
                        }
                    }
                }
            }
        }
        
        // Procesar patrones detectados
        $ladder_products = [];
        
        foreach ($upgrade_patterns as $pattern) {
            if ($pattern['count'] < 2) continue;
            
            $base_product = wc_get_product($pattern['base']);
            $premium_product = wc_get_product($pattern['premium']);
            
            if (!$base_product || !$premium_product) continue;
            
            $base_orders = $this->count_product_orders($pattern['base'], $start_date);
            $upgrade_rate = $base_orders > 0 ? ($pattern['count'] / $base_orders) * 100 : 0;
            
            $avg_days = $pattern['total_days'] / $pattern['count'];
            
            $ladder_products[] = [
                'base_product_id' => $pattern['base'],
                'base_product_name' => $base_product->get_name(),
                'base_price' => $base_product->get_price(),
                'premium_product_id' => $pattern['premium'],
                'premium_product_name' => $premium_product->get_name(),
                'premium_price' => $premium_product->get_price(),
                'upgrade_count' => $pattern['count'],
                'upgrade_rate' => $upgrade_rate,
                'avg_days_to_upgrade' => round($avg_days)
            ];
        }
        
        usort($ladder_products, function($a, $b) {
            return $b['upgrade_rate'] <=> $a['upgrade_rate'];
        });
        
        return $ladder_products;
    }
    
    /**
     * Contar pedidos que contienen un producto
     */
    private function count_product_orders($product_id, $start_date) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND oim.meta_key = '_product_id'
            AND oim.meta_value = %d",
            $start_date,
            $product_id
        ));
    }
    
    /**
     * Generar insight para un combo
     */
    private function get_combo_insight($combo) {
        if ($combo['combo_rate'] > 50) {
            return "Muy fuerte! Más del 50% ya lo compran juntos. Crea un bundle con descuento.";
        } elseif ($combo['combo_rate'] > 30) {
            return "Ofrece el segundo producto con descuento en checkout.";
        } else {
            return "Muestra como 'Comprados juntos frecuentemente' en la página de producto.";
        }
    }
    
    /**
     * Renderizar estilos (parte 1)
     */
    private function render_styles() {
        // Debido a la longitud, los estilos están en el archivo completo
        include dirname(__FILE__) . '/widget-upsell-styles.php';
    }
    
    /**
     * Renderizar scripts para tabs
     */
    private function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.glt-upsell-tab').on('click', function() {
                var tabId = $(this).data('tab');
                $('.glt-upsell-tab').removeClass('active');
                $(this).addClass('active');
                $('.glt-upsell-tab-content').removeClass('active');
                $('#tab-' + tabId).addClass('active');
            });
            
            $('.glt-tab-link').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).data('tab');
                $('.glt-upsell-tab[data-tab="' + tabId + '"]').trigger('click');
                $('html, body').animate({
                    scrollTop: $('.glt-upsell-tabs').offset().top - 100
                }, 500);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar botón de actualización
     */
    private function render_refresh_button() {
        $url = add_query_arg('glt_refresh_upsell', '1');
        ?>
        <a href="<?php echo esc_url($url); ?>" class="glt-refresh-link">
            <span class="dashicons dashicons-update"></span> Actualizar análisis (proceso intensivo)
        </a>
        <?php
    }
}

// Registrar el widget
Glotomania_Upsell_Machine_Widget::register();
