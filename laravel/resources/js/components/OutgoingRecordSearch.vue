<template>
  <div>
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
                  <th>Timestamp</th>
                  <!-- <th>Result / Response</th> -->
                  <th>Cost</th>
                  <th>Email</th>
                  <th>Name</th>
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
                  <td>{{ record.timestampConverted || record.timestamp }}</td>
                  <!-- <td style="max-width: 300px; word-break: break-word;">
                    {{ record.result || (record.accepted ? 'Success' : '-') }}
                  </td> -->
                  <td>{{ record.cost != null ? formatCost(record.cost) : '' }}</td>
                  <td>{{ record.email }}</td>
                  <td>{{ record.fname }} {{ record.lname }}</td>
                  <td>{{ record.cellphone || record.landline || '-' }}</td>
                  <td>{{ record.inboundCompanyName }} - {{ record.inboundLabel }}</td>
                  <td class="text-center">
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
                  <tr><td><strong>Result / Response</strong></td><td style="word-break: break-word;">{{ detailsRecord.result || '-' }}</td></tr>
                  <tr><td><strong>Cost</strong></td><td>{{ detailsRecord.cost != null ? formatCost(detailsRecord.cost) : '-' }}</td></tr>
                  <tr><td><strong>Incoming Feed</strong></td><td>{{ detailsRecord.inboundCompanyName }} - {{ detailsRecord.inboundLabel }}</td></tr>
                </tbody>
              </table>
              <h5>Lead Data</h5>
              <table class="table table-bordered table-condensed">
                <tbody>
                  <tr><td><strong>Email</strong></td><td>{{ detailsRecord.email }}</td></tr>
                  <tr><td><strong>Name</strong></td><td>{{ detailsRecord.fname }} {{ detailsRecord.lname }}</td></tr>
                  <tr><td><strong>Address</strong></td><td>{{ detailsRecord.addr }} {{ detailsRecord.addr2 }}</td></tr>
                  <tr><td><strong>City/State/Zip</strong></td><td>{{ detailsRecord.city }}, {{ detailsRecord.state }} {{ detailsRecord.zip }}</td></tr>
                  <tr><td><strong>Phone</strong></td><td>{{ detailsRecord.cellphone || detailsRecord.landline || '-' }}</td></tr>
                  <tr><td><strong>Lead Stamp</strong></td><td>{{ detailsRecord.leadstamp }}</td></tr>
                </tbody>
              </table>
              <template v-if="detailsRecord.rawData">
                <h5>Raw Data (JSON)</h5>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ formatRawData(detailsRecord.rawData) }}</pre>
              </template>
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
      if (q.idCompany || q.idFeedOut) this.doSearch();
    },
    async doSearch() {
      const hasFilter = this.filters.idFeedOut || this.filters.idCompany || this.filters.email || this.filters.phone || this.filters.url || this.filters.ip;
      if (!hasFilter) {
        this.searchError = 'You must select an outgoing feed/company OR fill out at least one of: email, phone, URL, or IP.';
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
      this.detailsRecord = record;
      this.$nextTick(() => {
        const $modal = $('#outboundRecordDetails' + record.idRecord + '-' + record.idFeedOut);
        $modal.one('hidden.bs.modal', () => {
          this.detailsRecord = null;
        });
        $modal.modal('show');
      });
    },
    closeDetailsModal() {
      if (this.detailsRecord) {
        $('#outboundRecordDetails' + this.detailsRecord.idRecord + '-' + this.detailsRecord.idFeedOut).modal('hide');
        this.detailsRecord = null;
      }
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
