<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Record Search</h2>
      <p>Fill out any or all of the fields below to perform an "AND" search against all of the fields that are filled in.</p>
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
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label">Incoming Feed</label>
          <div class="col-sm-4">
            <select v-model="filters.idFeedIn" class="form-control">
              <option value="">-- Select a feed --</option>
              <optgroup
                v-for="(feeds, companyName) in feedChoices"
                :key="companyName"
                :label="companyName"
              >
                <option
                  v-for="f in feeds"
                  :key="f.idFeedIn"
                  :value="f.idFeedIn"
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
          <label class="col-sm-2 control-label">View Type</label>
          <div class="col-sm-4">
            <label class="radio-inline">
              <input v-model="filters.viewType" type="radio" value="expanded" /> Show outbound results
            </label>
            <label class="radio-inline">
              <input v-model="filters.viewType" type="radio" value="condensed" /> Hide outbound results
            </label>
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
                  <th>Incoming Feed</th>
                  <th>Email</th>
                  <th>Timestamp</th>
                  <th>URL</th>
                  <th>First Name</th>
                  <th>Last Name</th>
                  <th>Lead Stamp</th>
                  <th>IP</th>
                  <th>DOB</th>
                  <th>Cost</th>
                  <th>Actions</th>
                </tr>
                <tr>
                  <th>Address 1</th>
                  <th>Address 2</th>
                  <th>City</th>
                  <th>State</th>
                  <th>Zip</th>
                  <th>Country</th>
                  <th>Landline</th>
                  <th>Cellphone</th>
                  <th>Gender</th>
                  <th></th>
                  <th></th>
                </tr>
                <tr>
                  <th colspan="10">{{ filters.viewType === 'expanded' ? 'Incoming and Outgoing Responses' : 'Incoming Response' }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="record in records" :key="record.idRecord">
                  <tr>
                    <td>{{ record.companyName }} - ({{ record.idFeedIn }}) {{ record.label }} [{{ record.description || '' }}]</td>
                    <td>{{ record.email }}</td>
                    <td>{{ record.timestampConverted || record.timestamp }}</td>
                    <td>{{ record.url }}</td>
                    <td>{{ record.fname }}</td>
                    <td>{{ record.lname }}</td>
                    <td>{{ record.leadstamp }}</td>
                    <td>{{ record.ip }}</td>
                    <td>{{ record.dob }}</td>
                    <td>{{ record.cost != null ? formatCost(record.cost) : '' }}</td>
                    <td rowspan="3" class="text-center">
                      <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="openDetailsModal(record)"
                      >
                        View Details
                      </button>
                    </td>
                  </tr>
                  <tr>
                    <td>{{ record.addr }}</td>
                    <td>{{ record.addr2 }}</td>
                    <td>{{ record.city }}</td>
                    <td>{{ record.state }}</td>
                    <td>{{ record.zip }}</td>
                    <td>{{ record.country }}</td>
                    <td>{{ record.landline }}</td>
                    <td>{{ record.cellphone }}</td>
                    <td>{{ record.gender }}</td>
                    <td></td>
                  </tr>
                  <tr>
                    <td colspan="10">
                      <p v-if="filters.viewType === 'expanded'">
                        <strong>Incoming Response</strong>:
                        <span :style="{ color: record.result ? 'red' : 'green', fontWeight: 'bold' }">
                          {{ record.result || 'Success' }}
                        </span>
                      </p>
                      <template v-if="filters.viewType === 'expanded' && record.outboundRecords && record.outboundRecords.length">
                        <p><strong>Outgoing Responses:</strong></p>
                        <ul>
                          <li v-for="(ob, idx) in record.outboundRecords" :key="idx">
                            {{ ob.timestampConverted || ob.timestamp }}: {{ ob.companyName }} - {{ ob.label }} (#{{ ob.idFeedOut }}) Response: {{ ob.result || 'Success' }}
                          </li>
                        </ul>
                      </template>
                      <p v-else-if="filters.viewType === 'expanded' && (!record.outboundRecords || !record.outboundRecords.length)">
                        No outgoing records found.
                      </p>
                      <span v-else :style="{ color: record.result ? 'red' : 'green', fontWeight: 'bold' }">
                        {{ record.result || 'Success' }}
                      </span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Record Details Modal -->
      <div
        v-if="detailsRecord"
        class="modal fade"
        :id="'recordDetails' + detailsRecord.idRecord"
        tabindex="-1"
        role="dialog"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" @click="closeDetailsModal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title">Record Details</h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <template v-if="detailsRecord.rawData">
                <h4>Raw Data (JSON)</h4>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ formatRawData(detailsRecord.rawData) }}</pre>
              </template>
              <h4>Database Fields</h4>
              <table class="table table-bordered table-condensed">
                <tbody>
                  <tr v-for="(val, key) in detailsRecord" :key="key">
                    <td class="col-sm-4"><strong>{{ key }}</strong></td>
                    <td>{{ val }}</td>
                  </tr>
                </tbody>
              </table>
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
  name: 'RecordSearch',
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
        idFeedIn: '',
        idCompany: '',
        email: '',
        phone: '',
        url: '',
        ip: '',
        viewType: 'condensed',
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
        const r = await axios.get('/api/record-search/feeds');
        if (r.data.status === 1) {
          this.feedChoices = r.data.data || {};
        }
      } catch (e) {
        console.error('Failed to fetch feeds:', e);
      }
    },
    applyRouteParams() {
      const q = this.$route.query;
      if (q.startDate) this.filters.startDate = q.startDate;
      if (q.endDate) this.filters.endDate = q.endDate;
      if (q.status) this.filters.status = q.status;
      if (q.idCompany) this.filters.idCompany = q.idCompany;
      if (q.idFeedIn) this.filters.idFeedIn = q.idFeedIn;
      if (q.idCompany || q.idFeedIn) this.doSearch();
    },
    async doSearch() {
      const hasFilter = this.filters.idFeedIn || this.filters.idCompany || this.filters.email || this.filters.phone || this.filters.url || this.filters.ip;
      if (!hasFilter) {
        this.searchError = 'You must select a feed/company OR fill out at least one of: email, phone, URL, or IP.';
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
        const r = await axios.get('/api/record-search?' + params.toString());
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
        const $modal = $('#recordDetails' + record.idRecord);
        $modal.one('hidden.bs.modal', () => {
          this.detailsRecord = null;
        });
        $modal.modal('show');
      });
    },
    closeDetailsModal() {
      if (this.detailsRecord) {
        $('#recordDetails' + this.detailsRecord.idRecord).modal('hide');
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
</style>
