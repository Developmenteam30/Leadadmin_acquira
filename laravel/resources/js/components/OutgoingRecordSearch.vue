<template>
  <div>
    <div
      v-if="toast.show"
      class="alert"
      :class="toast.type === 'success' ? 'alert-success' : 'alert-danger'"
      style="position: fixed; top: 16px; right: 16px; z-index: 2000; min-width: 280px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"
      role="alert"
    >
      {{ toast.message }}
    </div>
    <Navigation />
    <div class="container-fluid">
      <h2>Outgoing Feeds Record Search</h2>
      <p>Search outgoing feed results (data sent to buyers). Fill out any or all of the fields below to perform an "AND" search.</p>
      <p>Results are limited to the first 500 matching entries and are sorted with the most recent entries on top.</p>

      <form class="form-horizontal" @submit.prevent="doSearch">
        <div class="form-group">
          <label class="col-sm-2 control-label">Start Date</label>
          <div class="col-sm-2">
            <input v-model="filters.startDate" type="date" class="form-control" />
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">End Date</label>
          <div class="col-sm-2">
            <input v-model="filters.endDate" type="date" class="form-control" />
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">Record Status</label>
          <div class="col-sm-4">
            <label class="radio-inline">
              <input v-model="filters.status" type="radio" value="all" /> All Records
            </label>
            <label class="radio-inline">
              <input v-model="filters.status" type="radio" value="accepted" /> Accepted
            </label>
            <label class="radio-inline">
              <input v-model="filters.status" type="radio" value="rejected" /> Rejected
            </label>
            <label class="radio-inline">
              <input v-model="filters.status" type="radio" value="pending" /> Pending
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">Outgoing Feed</label>
          <div class="col-sm-4">
            <select v-model="filters.idFeedOut" class="form-control">
              <option value="">-- Select a feed --</option>
              <optgroup
                v-for="(feeds, companyName) in feedChoices"
                :key="companyName"
                :label="companyName"
              >
                <option
                  v-for="f in feeds"
                  :key="f.idFeedOut"
                  :value="f.idFeedOut"
                >
                  {{ f.label }}
                </option>
              </optgroup>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">Email</label>
          <div class="col-sm-4">
            <input v-model="filters.email" type="email" class="form-control" placeholder="Email address" />
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">Phone</label>
          <div class="col-sm-4">
            <input v-model="filters.phone" type="text" class="form-control" placeholder="Phone number" />
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">URL</label>
          <div class="col-sm-4">
            <input v-model="filters.url" type="text" class="form-control" placeholder="URL" />
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">IP Address</label>
          <div class="col-sm-4">
            <input v-model="filters.ip" type="text" class="form-control" placeholder="IP address" />
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-2 col-sm-4">
            <button type="submit" class="btn btn-primary" :disabled="searching">
              {{ searching ? 'Searching...' : 'Search' }}
            </button>
          </div>
        </div>
      </form>

      <div v-if="searchError" class="alert alert-danger">
        {{ searchError }}
      </div>

      <div v-if="searched && !searching">
        <p v-if="records.length === 0">No records found.</p>
        <div v-else>
          <p><strong>{{ records.length }}</strong> record(s) found.</p>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-condensed">
              <thead>
                <tr>
                  <th>Outgoing Feed</th>
                  <th>Status</th>
                  <th>Sent Count</th>
                  <th>Timestamp</th>
                  <th>Result / Response</th>
                  <th>Cost</th>
                  <th>Email</th>
                  <th>First Name</th>
                  <th>Last Name</th>
                  <th>Phone</th>
                  <th>Incoming Feed</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="record in records" :key="record.idRecord + '-' + record.idFeedOut">
                  <td>{{ record.outboundCompanyName }} - ({{ record.idFeedOut }}) {{ record.outboundLabel }}</td>
                  <td>
                    <span :class="record.processed === 0 ? 'text-warning' : (record.accepted ? 'text-success' : 'text-danger')" style="font-weight: bold;">
                      {{ record.processed === 0 ? 'Pending' : (record.accepted ? 'Accepted' : 'Rejected') }}
                    </span>
                  </td>
                  <td>{{ record.sentCount || 0 }}</td>
                  <td>{{ record.timestampConverted || record.timestamp }}</td>
                  <td style="max-width: 300px; word-break: break-word;">
                    {{ record.result || (record.accepted ? 'Success' : '-') }}
                  </td>
                  <td>{{ record.cost != null ? formatCost(record.cost) : '' }}</td>
                  <td>{{ record.email }}</td>
                  <td>{{ record.fname || '-' }}</td>
                  <td>{{ record.lname || '-' }}</td>
                  <td>{{ record.cellphone || record.landline || '-' }}</td>
                  <td>{{ record.inboundCompanyName }} - {{ record.inboundLabel }}</td>
                  <td class="text-center">
                    <button
                      v-if="canManualConfirm(record)"
                      type="button"
                      class="btn btn-success btn-sm"
                      style="margin-right: 6px;"
                      @click="confirmMarketplace(record)"
                    >
                      Confirm
                    </button>
                    <button
                      v-if="canResend(record)"
                      type="button"
                      class="btn btn-warning btn-sm"
                      style="margin-right: 6px;"
                      @click="resendRecord(record)"
                    >
                      Resend
                    </button>
                    <button
                      type="button"
                      class="btn btn-primary btn-sm"
                      @click="openDetailsModal(record)"
                    >
                      View Details
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Record Details Modal -->
      <div
        v-if="detailsRecord"
        class="modal fade"
        :id="'outboundRecordDetails' + detailsRecord.idRecord + '-' + detailsRecord.idFeedOut"
        tabindex="-1"
        role="dialog"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" @click="closeDetailsModal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title">Outgoing Record Details</h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <h5>Outgoing Response</h5>
              <table class="table table-bordered table-condensed">
                <tbody>
                  <tr><td><strong>Outgoing Feed</strong></td><td>{{ detailsRecord.outboundCompanyName }} - {{ detailsRecord.outboundLabel }} (#{{ detailsRecord.idFeedOut }})</td></tr>
                  <tr><td><strong>Status</strong></td><td><span :class="detailsRecord.processed === 0 ? 'text-warning' : (detailsRecord.accepted ? 'text-success' : 'text-danger')">{{ detailsRecord.processed === 0 ? 'Pending' : (detailsRecord.accepted ? 'Accepted' : 'Rejected') }}</span></td></tr>
                  <tr><td><strong>Timestamp</strong></td><td>{{ detailsRecord.timestampConverted || detailsRecord.timestamp }}</td></tr>
                  <tr><td><strong>Outgoing Result / Response</strong></td><td style="word-break: break-word;">{{ detailsRecord.result || '-' }}</td></tr>
                  <tr>
                    <td><strong>Buyer response (raw)</strong></td>
                    <td style="word-break: break-word;">
                      <template v-if="buyerRawStored(detailsRecord)">{{ detailsRecord.buyer_response_raw }}</template>
                      <span v-else class="text-muted">Not stored for this row (processed before buyer raw was saved, or migration not applied on the server). After deploy, use Resend to capture the buyer HTTP body.</span>
                    </td>
                  </tr>
                  <tr><td><strong>Inbound lead result</strong></td><td style="word-break: break-word;">{{ detailsRecord.inboundResult || '-' }}</td></tr>
                  <tr><td><strong>Cost</strong></td><td>{{ detailsRecord.cost != null ? formatCost(detailsRecord.cost) : '-' }}</td></tr>
                  <tr><td><strong>Incoming Feed</strong></td><td>{{ detailsRecord.inboundCompanyName }} - {{ detailsRecord.inboundLabel }}</td></tr>
                </tbody>
              </table>
              <h5>Lead Data</h5>
              <table class="table table-bordered table-condensed">
                <tbody>
                  <tr><td><strong>Email</strong></td><td>{{ detailsRecord.email }}</td></tr>
                  <tr><td><strong>First Name</strong></td><td>{{ detailsRecord.fname || '-' }}</td></tr>
                  <tr><td><strong>Last Name</strong></td><td>{{ detailsRecord.lname || '-' }}</td></tr>
                  <tr><td><strong>Address</strong></td><td>{{ detailsRecord.addr }} {{ detailsRecord.addr2 }}</td></tr>
                  <tr><td><strong>City</strong></td><td>{{ detailsRecord.city || '-' }}</td></tr>
                  <tr><td><strong>State</strong></td><td>{{ detailsRecord.state || '-' }}</td></tr>
                  <tr><td><strong>Zip</strong></td><td>{{ detailsRecord.zip || '-' }}</td></tr>
                  <tr><td><strong>Phone</strong></td><td>{{ detailsRecord.cellphone || detailsRecord.landline || '-' }}</td></tr>
                  <tr><td><strong>Lead Stamp</strong></td><td>{{ detailsRecord.leadstamp }}</td></tr>
                </tbody>
              </table>
              <template v-if="detailsRecord.rawData">
                <h5>Raw Data (JSON)</h5>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ formatRawData(detailsRecord.rawData) }}</pre>
              </template>
              <h5>Raw Data Sent to Buyer (JSON)</h5>
              <p v-if="buyerPayloadLoading" class="text-muted">Loading buyer payload...</p>
              <p v-else-if="buyerPayloadError" class="text-danger">{{ buyerPayloadError }}</p>
              <pre v-else style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ formatRawData(detailsRecord.outboundRawData || {}) }}</pre>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" @click="closeDetailsModal">Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import Navigation from './Navigation.vue';

export default {
  name: 'OutgoingRecordSearch',
  components: { Navigation },
  data() {
    const today = new Date().toISOString().slice(0, 10);
    const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    return {
      feedChoices: {},
      filters: {
        startDate: weekAgo,
        endDate: today,
        status: 'all',
        idFeedOut: '',
        idFeedIn: '',
        idCompany: '',
        email: '',
        phone: '',
        url: '',
        ip: '',
      },
      records: [],
      searched: false,
      searching: false,
      searchError: '',
      detailsRecord: null,
      buyerPayloadLoading: false,
      buyerPayloadError: '',
      resendingKey: '',
      toast: {
        show: false,
        message: '',
        type: 'success',
      },
      toastTimer: null,
    };
  },
  mounted() {
    this.fetchFeeds();
    this.applyRouteParams();
  },
  watch: {
    $route() {
      this.applyRouteParams();
    },
  },
  methods: {
    async fetchFeeds() {
      try {
        const r = await axios.get('/api/record-search/outbound-feeds');
        if (r.data.status === 1) {
          this.feedChoices = r.data.data || {};
        }
      } catch (e) {
        console.error('Failed to fetch outgoing feeds:', e);
      }
    },
    applyRouteParams() {
      const q = this.$route.query;
      if (q.startDate) this.filters.startDate = q.startDate;
      if (q.endDate) this.filters.endDate = q.endDate;
      if (q.status) this.filters.status = q.status;
      if (q.idCompany) this.filters.idCompany = q.idCompany;
      if (q.idFeedOut) this.filters.idFeedOut = q.idFeedOut;
      if (q.idFeedIn) this.filters.idFeedIn = q.idFeedIn;
      if (q.idCompany || q.idFeedOut || q.idFeedIn) this.doSearch();
    },
    async doSearch(forceRefresh = false) {
      const hasFilter = this.filters.idFeedOut || this.filters.idFeedIn || this.filters.idCompany || this.filters.email || this.filters.phone || this.filters.url || this.filters.ip;
      if (!hasFilter) {
        this.searchError = 'You must select an outgoing feed/incoming feed/company OR fill out at least one of: email, phone, URL, or IP.';
        return;
      }
      this.searchError = '';
      this.searching = true;
      this.searched = true;
      try {
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(this.filters)) {
          if (v != null && v !== '') params.set(k, v);
        }
        if (forceRefresh) {
          params.set('_ts', String(Date.now()));
        }
        const r = await axios.get('/api/record-search/outbound?' + params.toString());
        if (r.data.status === 1) {
          this.records = r.data.data || [];
        } else {
          this.searchError = r.data.error || 'Search failed';
          this.records = [];
        }
      } catch (e) {
        this.searchError = e.response?.data?.error || e.message || 'Search failed';
        this.records = [];
      } finally {
        this.searching = false;
      }
    },
    openDetailsModal(record) {
      this.detailsRecord = { ...record, outboundRawData: null };
      this.buyerPayloadLoading = true;
      this.buyerPayloadError = '';
      this.$nextTick(() => {
        const $modal = $('#outboundRecordDetails' + record.idRecord + '-' + record.idFeedOut);
        $modal.one('hidden.bs.modal', () => {
          this.detailsRecord = null;
          this.buyerPayloadLoading = false;
          this.buyerPayloadError = '';
        });
        $modal.modal('show');
      });
      this.fetchBuyerPayload(record);
    },
    closeDetailsModal() {
      if (this.detailsRecord) {
        $('#outboundRecordDetails' + this.detailsRecord.idRecord + '-' + this.detailsRecord.idFeedOut).modal('hide');
        this.detailsRecord = null;
        this.buyerPayloadLoading = false;
        this.buyerPayloadError = '';
      }
    },
    canResend(record) {
      if (!record) return false;
      const isPending = Number(record.processed) === 0;
      const isRejected = Number(record.processed) === 1 && Number(record.accepted) === 0;
      return isPending || isRejected;
    },
    async resendRecord(record) {
      if (!this.canResend(record)) return;
      const rowKey = `${record.idRecord}-${record.idFeedOut}`;
      if (this.resendingKey === rowKey) return;
      if (!window.confirm('Resend this record to buyer?')) return;
      try {
        this.resendingKey = rowKey;
        const r = await axios.post(`/api/record-search/outbound/${record.idRecord}/${record.idFeedOut}/resend`, {});
        this.showToast(r.data?.message || 'Record resent successfully.', 'success');
        await this.doSearch(true);
      } catch (e) {
        this.searchError = e.response?.data?.error || e.message || 'Failed to resend record';
        this.showToast(this.searchError, 'error');
      } finally {
        this.resendingKey = '';
      }
    },
    showToast(message, type = 'success') {
      if (this.toastTimer) {
        clearTimeout(this.toastTimer);
      }
      this.toast.message = message;
      this.toast.type = type;
      this.toast.show = true;
      this.toastTimer = setTimeout(() => {
        this.toast.show = false;
      }, 2500);
    },
    async fetchBuyerPayload(record) {
      try {
        const r = await axios.get(`/api/record-search/outbound/${record.idRecord}/${record.idFeedOut}/buyer-payload`);
        if (r.data?.status === 1 && this.detailsRecord) {
          this.detailsRecord.outboundRawData = r.data.data || {};
        } else if (this.detailsRecord) {
          this.buyerPayloadError = r.data?.error || 'Failed to load buyer payload';
        }
      } catch (e) {
        if (this.detailsRecord) {
          this.buyerPayloadError = e.response?.data?.error || e.message || 'Failed to load buyer payload';
        }
      } finally {
        this.buyerPayloadLoading = false;
      }
    },
    canManualConfirm(record) {
      if (!record) return false;
      const isMarketplace = (record.responseType || '').toLowerCase() === 'marketplace';
      const isPending = Number(record.processed) === 0;
      const resultText = String(record.result || '');
      return isMarketplace && isPending && resultText.includes('Marketplace success received, but price is missing or zero');
    },
    async confirmMarketplace(record) {
      if (!this.canManualConfirm(record)) return;
      if (!window.confirm('Mark this pending marketplace record as accepted?')) return;
      try {
        await axios.post(`/api/record-search/outbound/${record.idRecord}/${record.idFeedOut}/confirm-marketplace`, {});
        await this.doSearch();
      } catch (e) {
        this.searchError = e.response?.data?.error || e.message || 'Failed to confirm marketplace record';
      }
    },
    buyerRawStored(record) {
      const v = record?.buyer_response_raw;
      return v != null && String(v).trim() !== '';
    },
    formatCost(val) {
      const n = parseFloat(val);
      return isNaN(n) ? '' : n.toFixed(2);
    },
    formatRawData(raw) {
      if (typeof raw === 'string') {
        try {
          return JSON.stringify(JSON.parse(raw), null, 2);
        } catch (_) {
          return raw;
        }
      }
      return JSON.stringify(raw, null, 2);
    },
  },
};
</script>

<style scoped>
.form-horizontal .form-group {
  margin-left: 0;
  margin-right: 0;
}
.table th {
  background: #f5f5f5;
  font-size: 12px;
}
.text-success { color: #3c763d; }
.text-danger { color: #a94442; }
.text-warning { color: #8a6d3b; }
</style>
