<template>
  <div>
    <Navigation />
    <div class="container-fluid" style="padding: 0;">
      <div class="dashboard-new-header">Dashboard</div>

      <!-- Quick Jump and Date Range -->
      <div class="date-filter-container">
        <QuickJump
          :start="filters.statsStart"
          :end="filters.statsEnd"
          @update:start="filters.statsStart = $event"
          @update:end="filters.statsEnd = $event"
          @change="fetchDashboardData"
        />
      </div>

      <div class="dashboard-table-scroll">
        <table class="dashboard-new-table">
        <thead>
          <tr>
            <th colspan="2">Company</th>
            <th>Purchase Count</th>
            <th>Lead Expense</th>
            <th>RT Sale Count</th>
            <th>MP Sale Count</th>
            <th>Lead Sales</th>
            <th>Avg Sale</th>
            <th>Profit</th>
            <th>Profit%</th>
            <th>Avg Score MP Sales</th>
            <th v-for="sp in salespersons" :key="sp.idUser">{{ sp.fullName || 'Unknown' }}</th>
          </tr>
        </thead>
        <tbody>
          <!-- Individual Company Rows (by company) -->
          <template v-for="(row, index) in companyData" :key="index">
            <tr
              class="data-row"
              :class="index % 2 === 0 ? 'data-row-dark' : 'data-row-light'"
            >
              <td class="expand-cell">
                <button
                  v-if="(row.feeds || []).length > 0"
                  type="button"
                  class="expand-btn"
                  :class="{ expanded: expandedRows[index] }"
                  :aria-label="expandedRows[index] ? 'Collapse' : 'Expand'"
                  @click="toggleExpand(index)"
                >
                  {{ expandedRows[index] ? '−' : '+' }}
                </button>
                <span v-else></span>
              </td>
              <td>{{ row.company_name || 'Unknown' }}</td>
            <td class="text-right">{{ formatNumber(row.purchase_count || 0) }}</td>
            <td class="text-right">${{ formatNumber(row.lead_expense || 0, 2) }}</td>
            <td class="text-right">{{ formatNumber(row.rt_sale_count || 0) }}</td>
            <td class="text-right">{{ formatNumber(row.mp_sale_count || 0) }}</td>
            <td class="text-right">${{ formatNumber(row.lead_sales || 0, 2) }}</td>
            <td class="text-right">${{ formatNumber(row.avg_sale || 0, 2) }}</td>
            <td
              class="text-right"
              :class="{ 'negative-profit': (row.profit || 0) < 0 }"
            >
              ${{ formatNumber(row.profit || 0, 2) }}
            </td>
            <td
              class="text-right"
              :class="{ 'negative-profit': (row.profit_percent || 0) < 0 }"
            >
              {{ formatNumber(row.profit_percent || 0, 1) }}%
            </td>
            <td class="text-right">${{ formatNumber(row.avg_score_mp_sales || 0, 2) }}</td>
            <td
              v-for="sp in salespersons"
              :key="sp.idUser"
              class="text-right"
            >
              {{ formatNumber((row.purchase_by_salesperson || {})[sp.idUser] || 0) }}
            </td>
          </tr>

          <!-- Feed-level rows (shown when expanded) -->
          <tr
            v-for="feed in (expandedRows[index] ? (row.feeds || []) : [])"
            :key="feed.idFeedIn"
            class="feed-row"
            :class="index % 2 === 0 ? 'data-row-dark' : 'data-row-light'"
          >
            <td></td>
            <td class="feed-name-cell">{{ feed.feed_name || 'Unknown' }}</td>
            <td class="text-right">{{ formatNumber(feed.purchase_count || 0) }}</td>
            <td class="text-right">${{ formatNumber(feed.lead_expense || 0, 2) }}</td>
            <td class="text-right">{{ formatNumber(feed.rt_sale_count || 0) }}</td>
            <td class="text-right">{{ formatNumber(feed.mp_sale_count || 0) }}</td>
            <td class="text-right">${{ formatNumber(feed.lead_sales || 0, 2) }}</td>
            <td class="text-right">${{ formatNumber(feed.avg_sale || 0, 2) }}</td>
            <td
              class="text-right"
              :class="{ 'negative-profit': (feed.profit || 0) < 0 }"
            >
              ${{ formatNumber(feed.profit || 0, 2) }}
            </td>
            <td
              class="text-right"
              :class="{ 'negative-profit': (feed.profit_percent || 0) < 0 }"
            >
              {{ formatNumber(feed.profit_percent || 0, 1) }}%
            </td>
            <td class="text-right">${{ formatNumber(feed.avg_score_mp_sales || 0, 2) }}</td>
            <td
              v-for="sp in salespersons"
              :key="sp.idUser"
              class="text-right"
            >
              {{ formatNumber((feed.purchase_by_salesperson || {})[sp.idUser] || 0) }}
            </td>
          </tr>
          </template>

          <!-- Grand Total Row -->
          <tr class="total-row">
            <td colspan="2">GRAND TOTAL</td>
            <td class="text-right">{{ formatNumber(grandTotal.purchaseCount) }}</td>
            <td class="text-right">${{ formatNumber(grandTotal.leadExpense, 2) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.rtSaleCount) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.mpSaleCount) }}</td>
            <td class="text-right">${{ formatNumber(grandTotal.leadSales, 2) }}</td>
            <td class="text-right">${{ formatNumber(grandTotal.avgSale, 2) }}</td>
            <td
              class="text-right"
              :class="{ 'negative-profit': grandTotal.profit < 0 }"
            >
              ${{ formatNumber(grandTotal.profit, 2) }}
            </td>
            <td
              class="text-right"
              :class="{ 'negative-profit': grandTotal.profitPercent < 0 }"
            >
              {{ formatNumber(grandTotal.profitPercent, 1) }}%
            </td>
            <td class="text-right">${{ formatNumber(grandTotal.avgScoreMpSales, 2) }}</td>
            <td
              v-for="sp in salespersons"
              :key="sp.idUser"
              class="text-right"
            >
              {{ formatNumber(grandTotal.purchaseBySalesperson[sp.idUser] || 0) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <p v-if="companyData.length === 0" class="no-data-message">
        No data available for {{ selectedDateDisplay }}
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, reactive } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';
import QuickJump from './QuickJump.vue';

export default {
  name: 'Dashboard',
  components: {
    Navigation,
    QuickJump,
  },
  setup() {
    const filters = reactive({
      statsStart: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      statsEnd: new Date().toISOString().split('T')[0],
    });
    const companyData = ref([]);
    const salespersons = ref([]);
    const loading = ref(false);
    const expandedRows = ref({});

    const toggleExpand = (index) => {
      expandedRows.value[index] = !expandedRows.value[index];
    };

    const selectedDateDisplay = computed(() => {
      const start = new Date(filters.statsStart);
      const end = new Date(filters.statsEnd);
      const fmt = (d) => d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
      return filters.statsStart === filters.statsEnd ? fmt(start) : `${fmt(start)} – ${fmt(end)}`;
    });

    const grandTotal = computed(() => {
      let purchaseCount = 0;
      let leadExpense = 0;
      let mpSaleCount = 0;
      let rtSaleCount = 0;
      let leadSales = 0;
      const purchaseBySalesperson = {};

      companyData.value.forEach((row) => {
        purchaseCount += parseFloat(row.purchase_count || 0);
        leadExpense += parseFloat(row.lead_expense || 0);
        mpSaleCount += parseFloat(row.mp_sale_count || 0);
        rtSaleCount += parseFloat(row.rt_sale_count || 0);
        leadSales += parseFloat(row.lead_sales || 0);
        Object.entries(row.purchase_by_salesperson || {}).forEach(([id, cnt]) => {
          purchaseBySalesperson[id] = (purchaseBySalesperson[id] || 0) + parseFloat(cnt || 0);
        });
      });

      const profit = leadSales - leadExpense;
      const profitPercent = leadExpense > 0 ? (profit / leadExpense) * 100 : 0;
      const avgSale = purchaseCount > 0 ? leadSales / purchaseCount : 0;
      const avgScoreMpSales = mpSaleCount > 0 ? leadSales / mpSaleCount : 0;

      return {
        purchaseCount,
        leadExpense,
        mpSaleCount,
        rtSaleCount,
        leadSales,
        profit,
        profitPercent,
        avgSale,
        avgScoreMpSales,
        purchaseBySalesperson,
      };
    });

    function formatNumber(value, decimals = 0) {
      const num = parseFloat(value || 0);
      return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });
    }

    const fetchDashboardData = async () => {
      loading.value = true;
      try {
        const response = await axios.get('/api/dashboard/life-leads', {
          params: { start: filters.statsStart, end: filters.statsEnd },
        });
        companyData.value = response.data.data || [];
        salespersons.value = response.data.salespersons || [];
        expandedRows.value = {};
        
        // Calculate derived fields for each row
        // Avg Sale = Lead Sales / Purchase Count, Profit = Lead Sales - Lead Expense, Profit% = profit percent
        companyData.value = companyData.value.map((row) => {
          const purchaseCount = parseFloat(row.purchase_count || 0);
          const leadSales = parseFloat(row.lead_sales || 0);
          const leadExpense = parseFloat(row.lead_expense || 0);
          
          const avgSale = purchaseCount > 0 ? leadSales / purchaseCount : 0;
          const profit = leadSales - leadExpense;
          const profitPercent = leadExpense > 0 ? (profit / leadExpense) * 100 : 0;
          
          return {
            ...row,
            avg_sale: avgSale,
            profit: profit,
            profit_percent: profitPercent,
          };
        });
      } catch (error) {
        console.error('Error fetching dashboard data:', error);
        companyData.value = [];
        salespersons.value = [];
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      fetchDashboardData();
    });

    return {
      filters,
      selectedDateDisplay,
      companyData,
      salespersons,
      loading,
      grandTotal,
      formatNumber,
      fetchDashboardData,
      expandedRows,
      toggleExpand,
    };
  },
};
</script>

<style scoped>
.dashboard-new-header {
  color: black;
  padding: 15px;
  margin: 0;
  margin-bottom: 0;
  font-weight: bold;
  font-size: 24px;
  width: 100%;
  display: block;
}

.dashboard-table-scroll {
  overflow-x: auto;
  margin-bottom: 20px;
  -webkit-overflow-scrolling: touch;
}

.dashboard-new-table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
  margin-bottom: 0;
  margin-top: 0;
}

.dashboard-new-table th {
  background-color: #072f5f;
  color: white;
  padding: 10px 8px;
  text-align: left;
  font-weight: bold;
  border: 1px solid #e6e6e6;
}

.dashboard-new-table td {
  padding: 10px 8px;
  border: 1px solid #e6e6e6;
}

.dashboard-new-table tr.total-row {
  background-color: #6a8ab1;
  color: white;
  font-weight: bold;
}

.dashboard-new-table tr.total-row-light {
  background-color: #8aaad1;
  color: white;
  font-weight: bold;
}

.dashboard-new-table tbody tr.data-row-dark {
  background-color: #d4d4d4;
  color: black;
}

.dashboard-new-table tbody tr.data-row-light {
  background-color: #e6e6e6;
  color: black;
}

.dashboard-new-table .text-right {
  text-align: right;
}

.dashboard-new-table .text-center {
  text-align: center;
}

.negative-profit {
  color: #ffcccc;
}

.no-data-message {
  text-align: center;
  padding: 20px;
  color: #333;
  font-size: 14px;
}

.date-filter-container {
  padding: 15px;
  background-color: #f5f5f5;
  border-bottom: 1px solid #ddd;
  margin-bottom: 0;
}

.date-filter-container .form-inline {
  margin: 0;
}

.date-filter-container label {
  margin-right: 10px;
  font-weight: bold;
  color: #333;
}

.date-filter-container input[type="date"] {
  margin-right: 10px;
  padding: 5px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.expand-cell {
  width: 36px;
  padding: 6px 8px !important;
  vertical-align: middle;
}

.expand-btn {
  width: 24px;
  height: 24px;
  min-width: 24px;
  padding: 0;
  border: 1px solid #072f5f;
  background: white;
  color: #072f5f;
  font-size: 16px;
  font-weight: bold;
  line-height: 1;
  cursor: pointer;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, color 0.2s;
}

.expand-btn:hover {
  background: #072f5f;
  color: white;
}

.expand-btn.expanded {
  background: #072f5f;
  color: white;
}

.feed-row .feed-name-cell {
  padding-left: 24px;
  font-style: italic;
  color: #444;
}
</style>
