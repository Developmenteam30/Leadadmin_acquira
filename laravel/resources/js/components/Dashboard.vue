<template>
  <div>
    <Navigation />
    <div class="container-fluid" style="padding: 0;">
      <div class="dashboard-new-header">Life Leads</div>

      <!-- Date Filter Form -->
      <div class="date-filter-container">
        <form @submit.prevent="updateDate" class="form-inline">
          <label for="dateFilter">Select Date:</label>
          <input
            type="date"
            id="dateFilter"
            v-model="selectedDate"
            class="form-control"
          />
          <button type="submit" class="btn btn-primary btn-sm">Update</button>
          <button
            type="button"
            class="btn btn-default btn-sm"
            style="margin-left: 5px"
            @click="resetToYesterday"
          >
            Reset to Yesterday
          </button>
        </form>
      </div>

      <table class="dashboard-new-table">
        <thead>
          <tr>
            <th colspan="2">Company</th>
            <th>Purchase Count</th>
            <th>Lead Expense</th>
            <th>MP Sale Count</th>
            <th>RT Sale Count</th>
            <th>Lead Sales</th>
            <th>Avg Sale</th>
            <th>Profit</th>
            <th>Profit%</th>
            <th>Avg Score MP Sales</th>
          </tr>
        </thead>
        <tbody>
          <!-- Grand Total Row -->
          <tr class="total-row">
            <td colspan="2">GRAND TOTAL</td>
            <td class="text-right">{{ formatNumber(grandTotal.purchaseCount) }}</td>
            <td class="text-right">${{ formatNumber(grandTotal.leadExpense, 2) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.mpSaleCount) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.rtSaleCount) }}</td>
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
            <td class="text-center">-</td>
          </tr>

          <!-- Date-Specific Total Row -->
          <tr class="total-row-light">
            <td>{{ selectedDateDisplay }}</td>
            <td>Total</td>
            <td class="text-right">{{ formatNumber(grandTotal.purchaseCount) }}</td>
            <td class="text-right">${{ formatNumber(grandTotal.leadExpense, 2) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.mpSaleCount) }}</td>
            <td class="text-right">{{ formatNumber(grandTotal.rtSaleCount) }}</td>
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
            <td class="text-center">-</td>
          </tr>

          <!-- Individual Company Rows -->
          <tr
            v-for="(row, index) in companyData"
            :key="index"
            class="data-row"
            :class="index % 2 === 0 ? 'data-row-dark' : 'data-row-light'"
          >
            <td></td>
            <td>{{ row.company_name || 'Unknown' }}</td>
            <td class="text-right">{{ formatNumber(row.purchase_count || 0) }}</td>
            <td class="text-right">${{ formatNumber(row.lead_expense || 0, 2) }}</td>
            <td class="text-right">{{ formatNumber(row.mp_sale_count || 0) }}</td>
            <td class="text-right">{{ formatNumber(row.rt_sale_count || 0) }}</td>
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
            <td class="text-center">-</td>
          </tr>
        </tbody>
      </table>

      <p v-if="companyData.length === 0" class="no-data-message">
        No data available for {{ selectedDateDisplay }}
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';

export default {
  name: 'Dashboard',
  components: {
    Navigation,
  },
  setup() {
    const selectedDate = ref(getYesterdayDate());
    const companyData = ref([]);
    const loading = ref(false);

    const selectedDateDisplay = computed(() => {
      const date = new Date(selectedDate.value);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    });

    const grandTotal = computed(() => {
      let purchaseCount = 0;
      let leadExpense = 0;
      let mpSaleCount = 0;
      let rtSaleCount = 0;
      let leadSales = 0;

      companyData.value.forEach((row) => {
        purchaseCount += parseFloat(row.purchase_count || 0);
        leadExpense += parseFloat(row.lead_expense || 0);
        mpSaleCount += parseFloat(row.mp_sale_count || 0);
        rtSaleCount += parseFloat(row.rt_sale_count || 0);
        leadSales += parseFloat(row.lead_sales || 0);
      });

      const profit = leadSales - leadExpense;
      const profitPercent = leadExpense > 0 ? (profit / leadExpense) * 100 : 0;
      const avgSale = mpSaleCount > 0 ? leadSales / mpSaleCount : 0;

      return {
        purchaseCount,
        leadExpense,
        mpSaleCount,
        rtSaleCount,
        leadSales,
        profit,
        profitPercent,
        avgSale,
      };
    });

    function getYesterdayDate() {
      const yesterday = new Date();
      yesterday.setDate(yesterday.getDate() - 1);
      return yesterday.toISOString().split('T')[0];
    }

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
          params: { date: selectedDate.value },
        });
        companyData.value = response.data.data || [];
        
        // Calculate derived fields for each row
        companyData.value = companyData.value.map((row) => {
          const mpSaleCount = parseFloat(row.mp_sale_count || 0);
          const leadSales = parseFloat(row.lead_sales || 0);
          const leadExpense = parseFloat(row.lead_expense || 0);
          
          const avgSale = mpSaleCount > 0 ? leadSales / mpSaleCount : 0;
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
      } finally {
        loading.value = false;
      }
    };

    const updateDate = () => {
      fetchDashboardData();
    };

    const resetToYesterday = () => {
      selectedDate.value = getYesterdayDate();
      fetchDashboardData();
    };

    onMounted(() => {
      fetchDashboardData();
    });

    return {
      selectedDate,
      selectedDateDisplay,
      companyData,
      loading,
      grandTotal,
      formatNumber,
      updateDate,
      resetToYesterday,
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

.dashboard-new-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
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
</style>
