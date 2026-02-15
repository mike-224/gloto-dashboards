<div class="gloto-dashboard-wrap">
    <header class="gloto-header">
        <h1 class="gloto-title">Gloto Dashboards</h1>
        <div class="gloto-controls">
            <select id="gloto-range-filter" class="gloto-select">
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </select>
            <select id="gloto-compare-filter" class="gloto-select">
                <option value="period">vs Periodo Anterior</option>
                <option value="year">vs Año Anterior</option>
            </select>
            <button id="gloto-refresh-all" class="button button-primary">
                <span class="dashicons dashicons-update"></span> Actualizar Todo
            </button>
        </div>
    </header>

    <div class="gloto-widgets-grid" id="gloto-widgets-container">
        <!-- Widgets loaded via JS sequential waterfall -->
        <div class="gloto-loading" style="text-align:center;padding:40px;color:#666;">
            <span class="spinner is-active" style="float:none;margin:0 10px 0 0;"></span>
            Cargando métricas...
        </div>
    </div>
</div>