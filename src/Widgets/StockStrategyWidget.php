<?php
/**
 * Stock Strategy Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class StockStrategyWidget
 */
class StockStrategyWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'stock_strategy_widget';
    }

    public function get_title()
    {
        return '📦 Estrategia de Stock';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_stock_data();

        ob_start();
?>
<div class="gloto-widget-card" id="<?php echo esc_attr($this->get_id()); ?>">
    <div class="gloto-widget-header">
        <h3 class="gloto-widget-title">
            <?php echo esc_html($this->get_title()); ?>
        </h3>
        <button class="gloto-widget-refresh" data-widget="<?php echo esc_attr($this->get_id()); ?>">
            <span class="dashicons dashicons-update"></span>
        </button>
    </div>

    <div class="gloto-stock-summary">
        <div class="gloto-stock-kpi">
            <span class="gloto-kpi-val">
                <?php echo $data['total_value']; ?>
            </span>
            <span class="gloto-kpi-lbl">Valor Inventario</span>
        </div>
        <div class="gloto-stock-kpi">
            <span class="gloto-kpi-val">
                <?php echo $data['low_stock_count']; ?>
            </span>
            <span class="gloto-kpi-lbl">Bajo Stock</span>
        </div>
    </div>

    <h4 class="gloto-section-title" style="margin-top:15px">Movimiento Lento (Dead Stock)</h4>
    <div class="gloto-list-rows">
        <?php if (empty($data['dead_stock'])): ?>
        <div class="gloto-empty-state">Inventario saludable.</div>
        <?php
        else: ?>
        <?php foreach ($data['dead_stock'] as $prod): ?>
        <div class="gloto-list-row">
            <span class="gloto-prod-name">
                <?php echo esc_html($prod['name']); ?>
            </span>
            <span class="gloto-stock-val">
                <?php echo $prod['stock']; ?> un.
            </span>
        </div>
        <?php
            endforeach; ?>
        <?php
        endif; ?>
    </div>
</div>
<style>
    .gloto-stock-summary {
        display: flex;
        gap: 15px;
        background: var(--gloto-bg);
        padding: 15px;
        border-radius: 8px;
    }

    .gloto-stock-kpi {
        flex: 1;
        text-align: center;
    }

    .gloto-kpi-val {
        font-size: 16px;
        font-weight: bold;
        display: block;
    }

    .gloto-kpi-lbl {
        font-size: 11px;
        color: var(--gloto-text-muted);
    }
</style>
<?php
        return ob_get_clean();
    }

    private function get_stock_data()
    {
        global $wpdb;

        // 1. Low Stock: products with stock <= low_stock_amount threshold
        // WooCommerce stock_status values: 'instock', 'outofstock', 'onbackorder'
        // There's no 'lowofstock' value. We check _stock <= woocommerce_notify_low_stock_amount
        $low_threshold = (int)get_option('woocommerce_notify_low_stock_amount', 2);
        $low_stock = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(p.ID)
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            JOIN {$wpdb->prefix}postmeta pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_stock_status'
            WHERE p.post_type = 'product' AND p.post_status = 'publish'
            AND pm_status.meta_value = 'instock'
            AND CAST(pm_stock.meta_value AS SIGNED) > 0
            AND CAST(pm_stock.meta_value AS SIGNED) <= %d
        ", $low_threshold));

        // 2. Total Inventory Value (Price * Stock)
        $total_value = $wpdb->get_var("
            SELECT SUM( CAST(pm_price.meta_value AS DECIMAL(10,2)) * CAST(pm_stock.meta_value AS SIGNED) )
            FROM {$wpdb->prefix}posts p
            JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            WHERE p.post_type = 'product' AND p.post_status = 'publish'
            AND CAST(pm_stock.meta_value AS SIGNED) > 0
        ");

        // 3. Dead Stock placeholder
        $dead_stock = [];

        return [
            'low_stock_count' => $low_stock ?: 0,
            'total_value' => wc_price($total_value ?: 0),
            'dead_stock' => $dead_stock
        ];
    }
}