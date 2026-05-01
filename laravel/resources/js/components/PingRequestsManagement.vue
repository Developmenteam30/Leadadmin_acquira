<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Incoming Feeds (Ping)</h2>
      <div class="row">
        <div class="col-md-9">
          <div style="clear: both; margin-bottom: 15px;">
            <div class="row">
              <div class="col-md-10">
                <QuickJump
                  :start="filters.statsStart"
                  :end="filters.statsEnd"
                  @update:start="filters.statsStart = $event"
                  @update:end="filters.statsEnd = $event"
                  @change="fetchFeeds"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="pull-right" style="margin-bottom: 15px;">
            <select v-model="filters.status" class="form-control" @change="fetchFeeds" style="display: inline-block; width: auto;">
              <option value="active">Show active feeds</option>
              <option value="hidden">Show hidden feeds</option>
              <option value="retired">Show retired feeds</option>
              <option value="">Show all feeds</option>
            </select>
          </div>
        </div>
      </div>


      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new ping request
        </button>
        <router-link to="/incoming-feeds" class="btn btn-primary" style="margin-left: 10px;">
          Incoming Feeds
        </router-link>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="companyGroups.length === 0">
        <p>No ping requests found.</p>
      </div>

      <div v-else>
        <h4>Incoming Phone Feeds</h4>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray">
              <th class="incoming-col-large" colspan="2">Company</th>
              <th class="incoming-col-small text-right">Accepted</th>
              <th class="incoming-col-small text-right">Rejected</th>
              <th class="incoming-col-small text-right">Pending</th>
              <th class="incoming-col-small">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(company, index) in companyGroups" :key="company.idCompany">
              <tr class="custom-master">
                <td colspan="2">
                  {{ company.name }} ({{ company.feeds.length }})
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link
                    :to="recordSearchLink(company.idCompany, null, 'accepted')"
                  >
                    {{ formatNumber(company.totalAccepted) }}
                  </router-link>
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link
                    :to="recordSearchLink(company.idCompany, null, 'rejected')"
                  >
                    {{ formatNumber(company.totalRejected) }}
                  </router-link>
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link
                    :to="recordSearchLink(company.idCompany, null, 'pending')"
                  >
                    {{ formatNumber(company.totalPending) }}
                  </router-link>
                </td>
                <td class="text-center">
                  <button
                    class="btn btn-primary btn-xs"
                    type="button"
                    @click="toggleCompanyFeeds(company.idCompany)"
                  >
                    {{ expandedCompanies[company.idCompany] ? 'Hide Feeds' : 'Show Feeds' }}
                  </button>
                </td>
              </tr>
              <template v-if="expandedCompanies[company.idCompany]">
                <tr
                  v-for="feed in company.feeds"
                  :key="feed.idFeedIn"
                  class="bg-gray feed-toggle"
                >
                  <td :class="'status-' + feed.status">
                    {{ feed.idFeedIn }}: {{ feed.label }}
                    <span v-if="feed.description">({{ feed.description }})</span>
                  </td>
                  <td>
                    <label class="switch">
                      <input
                        type="checkbox"
                        :checked="!feed.paused"
                        @change="togglePause(feed.idFeedIn, !feed.paused)"
                      />
                      <span class="slider"></span>
                    </label>
                    <span style="margin-left: 10px;">{{ feed.paused ? 'Paused' : 'Enabled' }}</span>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link
                      :to="recordSearchLink(null, feed.idFeedIn, 'accepted')"
                    >
                      {{ formatNumber(feed.accepted) }}
                    </router-link>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link
                      :to="recordSearchLink(null, feed.idFeedIn, 'rejected')"
                    >
                      {{ formatNumber(feed.rejected) }}
                    </router-link>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link
                      :to="recordSearchLink(null, feed.idFeedIn, 'pending')"
                    >
                      {{ formatNumber(feed.pending) }}
                    </router-link>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" :class="{ open: openDropdownFeedId === feed.idFeedIn }">
                      <button
                        type="button"
                        class="btn btn-primary btn-xs"
                        @click="openEditModal(feed)"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        class="btn btn-primary btn-xs dropdown-toggle"
                        @click.stop="toggleFeedDropdown(feed.idFeedIn)"
                        aria-haspopup="true"
                        :aria-expanded="openDropdownFeedId === feed.idFeedIn"
                      >
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                      </button>
                      <ul
                        v-show="openDropdownFeedId === feed.idFeedIn"
                        class="dropdown-menu dropdown-menu-right"
                        @click="openDropdownFeedId = null"
                      >
                        <li><a href="#" @click.prevent="openPingSpecModal(feed)">Ping Spec</a></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Add Feed Modal -->
      <div
        class="modal fade"
        id="newFeedModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newFeedModalTitle"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeAddModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="newFeedModalTitle">
                Add a new ping request
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <InboundFeedForm
                ref="addFeedForm"
                :feed="newFeed"
                :companies="companies"
                :availableFields="availableFields"
                :feedCategories="feedCategories"
                :timezones="timezones"
                :staffUsers="staffUsers"
                :usStates="usStates"
                @update:feed="updateNewFeed"
              />
              <div v-if="addError" class="alert alert-danger">
                {{ addError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeAddModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleAddFeed"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add ping request' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Feed Modal -->
      <div
        class="modal fade"
        id="editFeedModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editFeedModalTitle"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeEditModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="editFeedModalTitle">
                Edit ping request
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <div v-if="!editingFeed.idFeedIn && !editError" class="text-center" style="padding: 20px;">
                <p>Loading feed data...</p>
              </div>
              <template v-else-if="editingFeed.idFeedIn">
                <InboundFeedForm
                  :key="editingFeed.idFeedIn"
                  ref="editFeedForm"
                  :feed="editingFeed"
                  :companies="companies"
                  :availableFields="availableFields"
                  :feedCategories="feedCategories"
                  :timezones="timezones"
                  :staffUsers="staffUsers"
                  :usStates="usStates"
                  :isEdit="true"
                  @update:feed="updateEditingFeed"
                />
              </template>
              <div v-if="editError" class="alert alert-danger">
                {{ editError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeEditModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleUpdateFeed"
                :disabled="updating"
              >
                {{ updating ? 'Saving...' : 'Save changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Ping Spec Modal -->
      <Teleport to="body">
        <div v-show="pingSpecModal.show" class="ping-feed-modal" tabindex="-1" @click.self="closePingSpecModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Ping Spec – {{ pingSpecModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closePingSpecModal">&times;</button>
              </div>
              <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div v-if="pingSpecLoading">Loading...</div>
                <template v-else-if="pingSpecModal.data">
                  <p><strong>Company:</strong> {{ pingSpecModal.data.company }}</p>
                  <p><strong>Feed:</strong> {{ pingSpecModal.data.label }} (#{{ pingSpecModal.data.feedId }})</p>
                  <p><strong>Password:</strong> {{ pingSpecModal.data.password }}</p>
                  <p v-if="pingSpecModal.data.pingUrl" class="mb-2">
                    <strong>Ping URL:</strong>
                    <code class="d-block mt-1 p-2 bg-light rounded" style="word-break: break-all;">{{ pingSpecModal.data.pingUrl }}</code>
                    <button type="button" class="btn btn-xs btn-default mt-1" @click="copyToClipboard(pingSpecModal.data.pingUrl)" title="Copy">Copy</button>
                  </p>
                  <p v-if="pingSpecModal.data.pingSpecUrl" class="mb-2">
                    <strong>Ping Spec URL (shareable):</strong>
                    <code class="d-block mt-1 p-2 bg-light rounded" style="word-break: break-all;">{{ pingSpecModal.data.pingSpecUrl }}</code>
                    <button type="button" class="btn btn-xs btn-default mt-1" @click="copyToClipboard(pingSpecModal.data.pingSpecUrl)" title="Copy">Copy</button>
                  </p>
                  <h5>Ping Field Definitions</h5>
                  <table class="table table-bordered table-condensed">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Format</th><th>Notes</th><th>Cost</th></tr></thead>
                    <tbody>
                      <tr v-for="f in (pingSpecModal.data.fields || [])" :key="f.fieldName">
                        <td>{{ f.fieldName }}</td>
                        <td>{{ f.fieldDefinition }}</td>
                        <td>{{ f.required }}</td>
                        <td>{{ f.fieldFormat || '-' }}</td>
                        <td>{{ f.fieldDescription }}</td>
                        <td>-</td>
                      </tr>
                      <tr>
                        <td>cost</td>
                        <td>decimal</td>
                        <td>No</td>
                        <td>decimal number (e.g., 1.50, 2.75)</td>
                        <td>The cost associated with this lead submission. Must be a valid decimal number.</td>
                        <td>{{ pingSpecModal.data.costPerLead || '-' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </template>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick, reactive } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';
import InboundFeedForm from './InboundFeedForm.vue';
import QuickJump from './QuickJump.vue';

export default {
  name: 'PingRequestsManagement',
  components: {
    Navigation,
    InboundFeedForm,
    QuickJump,
  },
  setup() {
    const companyGroups = ref([]);
    const companies = ref([]);
    const availableFields = ref([]);
    const feedCategories = ref({});
    const timezones = ref([]);
    const staffUsers = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');
    const expandedCompanies = ref({});
    const openDropdownFeedId = ref(null);

    const pingSpecModal = reactive({ show: false, feedLabel: '', data: null });
    const pingSpecLoading = ref(false);

    const editingFeed = reactive({
      idFeedIn: null,
      label: '',
      description: '',
      idCompany: '',
      filterState: '',
      filterStateChoice: [],
      filterZip: '',
      filterZipCodes: [],
      feedCategory: 'phone-preping',
      timezone: 'America/New_York',
      requiredPingFields: [],
      allowedPingFields: [],
      required: ['email', 'ip', 'url', 'stamp', 'authorization'],
      allowedFields: [],
      custom1Label: '',
      custom2Label: '',
      custom3Label: '',
      custom4Label: '',
      custom5Label: '',
      custom6Label: '',
      dedupeEmail: '0',
      dedupeLandline: '0',
      dedupeCellphone: '0',
      dedupeAcross: 'urlGlobal',
      lookbackPeriod: '120',
      filterTypeUrl: '',
      filterUrl: [],
      rejectOldLeadsMaxAge: '7 Days Ago',
      pingTimeout: '300',
      dailyLimit: '',
      chokePercent: '0',
      costPerLead: '',
      salesperson: '',
      notifications: '1',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      pauseMessage: '',
      timeskew: '',
      status: 'active',
      minimumBirthAge: '',
      maximumBirthAge: '',
    });

    const filters = reactive({
      status: 'active',
      statsStart: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      statsEnd: new Date().toISOString().split('T')[0],
    });

    const usStates = {
      AL: 'Alabama',
      AK: 'Alaska',
      AZ: 'Arizona',
      AR: 'Arkansas',
      CA: 'California',
      CO: 'Colorado',
      CT: 'Connecticut',
      DE: 'Delaware',
      DC: 'District of Columbia',
      FL: 'Florida',
      GA: 'Georgia',
      HI: 'Hawaii',
      ID: 'Idaho',
      IL: 'Illinois',
      IN: 'Indiana',
      IA: 'Iowa',
      KS: 'Kansas',
      KY: 'Kentucky',
      LA: 'Louisiana',
      ME: 'Maine',
      MD: 'Maryland',
      MA: 'Massachusetts',
      MI: 'Michigan',
      MN: 'Minnesota',
      MS: 'Mississippi',
      MO: 'Missouri',
      MT: 'Montana',
      NE: 'Nebraska',
      NV: 'Nevada',
      NH: 'New Hampshire',
      NJ: 'New Jersey',
      NM: 'New Mexico',
      NY: 'New York',
      NC: 'North Carolina',
      ND: 'North Dakota',
      OH: 'Ohio',
      OK: 'Oklahoma',
      OR: 'Oregon',
      PA: 'Pennsylvania',
      RI: 'Rhode Island',
      SC: 'South Carolina',
      SD: 'South Dakota',
      TN: 'Tennessee',
      TX: 'Texas',
      UT: 'Utah',
      VT: 'Vermont',
      VA: 'Virginia',
      WA: 'Washington',
      WV: 'West Virginia',
      WI: 'Wisconsin',
      WY: 'Wyoming',
    };

    const newFeed = reactive({
      label: '',
      description: '',
      idCompany: '',
      filterState: '',
      filterStateChoice: [],
      filterZip: '',
      filterZipCodes: [],
      feedCategory: 'phone-preping', // Default to phone-preping for ping requests
      timezone: 'America/New_York',
      requiredPingFields: [],
      allowedPingFields: [],
      required: ['email', 'ip', 'url', 'stamp', 'authorization'], // authorization is required for ping
      allowedFields: [],
      custom1Label: '',
      custom2Label: '',
      custom3Label: '',
      custom4Label: '',
      custom5Label: '',
      custom6Label: '',
      dedupeEmail: '0',
      dedupeLandline: '0',
      dedupeCellphone: '0',
      dedupeAcross: 'urlGlobal',
      lookbackPeriod: '120',
      filterTypeUrl: '',
      filterUrl: [],
      rejectOldLeadsMaxAge: '7 Days Ago',
      pingTimeout: '300',
      dailyLimit: '',
      chokePercent: '0',
      costPerLead: '',
      salesperson: '',
      notifications: '1',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      pauseMessage: '',
      timeskew: '',
      status: 'active',
      minimumBirthAge: '',
      maximumBirthAge: '',
    });

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    const recordSearchLink = (idCompany, idFeedIn, status) => {
      const query = {
        startDate: filters.statsStart,
        endDate: filters.statsEnd,
        status,
      };
      if (idCompany) query.idCompany = idCompany;
      if (idFeedIn) query.idFeedIn = idFeedIn;
      return { path: '/record-search', query };
    };

    const fetchFeeds = async () => {
      loading.value = true;
      try {
        const params = {
          statsStart: filters.statsStart,
          statsEnd: filters.statsEnd,
        };
        if (filters.status) {
          params.status = filters.status;
        }

        const response = await axios.get('/api/inbound-feeds/ping', { params });
        if (response.data.status === 1) {
          companyGroups.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching ping requests:', error);
        companyGroups.value = [];
      } finally {
        loading.value = false;
      }
    };

    const toggleCompanyFeeds = (companyId) => {
      expandedCompanies.value[companyId] = !expandedCompanies.value[companyId];
    };

    const toggleFeedDropdown = (idFeedIn) => {
      openDropdownFeedId.value = openDropdownFeedId.value === idFeedIn ? null : idFeedIn;
    };

    const openPingSpecModal = async (feed) => {
      pingSpecModal.feedLabel = feed.label;
      pingSpecModal.show = true;
      pingSpecModal.data = null;
      pingSpecLoading.value = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}/ping-spec`);
        if (r.data.status === 1) pingSpecModal.data = r.data.data;
      } catch (e) {
        pingSpecModal.data = { error: e.response?.data?.error || e.message };
      } finally {
        pingSpecLoading.value = false;
      }
    };

    const closePingSpecModal = () => {
      pingSpecModal.show = false;
      pingSpecModal.feedLabel = '';
      pingSpecModal.data = null;
    };

    const copyToClipboard = async (text) => {
      try {
        await navigator.clipboard.writeText(text);
        alert('Copied to clipboard');
      } catch (e) {
        console.error('Copy failed:', e);
      }
    };

    const togglePause = async (idFeedIn, paused) => {
      try {
        const r = await axios.patch(`/api/inbound-feeds/${idFeedIn}/toggle-pause`, {
          paused: paused ? 1 : 0,
        });
        if (r.data.status === 1) {
          await fetchFeeds();
        } else {
          alert(r.data.error || 'Failed to toggle pause');
        }
      } catch (error) {
        console.error('Error toggling pause:', error);
        alert(error.response?.data?.error || error.message || 'Failed to toggle pause');
      }
    };

    const openAddModal = async () => {
      addError.value = '';
      await nextTick();
      if (window.$ && window.$('#newFeedModal')) {
        window.$('#newFeedModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newFeedModal')) {
        window.$('#newFeedModal').modal('hide');
      }
    };

    const updateEditingFeed = (updatedFeed) => {
      Object.assign(editingFeed, updatedFeed);
    };

    const openEditModal = async (feed) => {
      editError.value = '';
      try {
        const response = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}`);
        if (response.data.status === 1) {
          const feedData = response.data.data;
          Object.assign(editingFeed, {
            idFeedIn: feedData.idFeedIn,
            label: feedData.label || '',
            description: feedData.description || '',
            idCompany: feedData.idCompany || '',
            filterState: (typeof feedData.filterState === 'object' && feedData.filterState?.mode) ? feedData.filterState.mode : (feedData.filterState || ''),
            filterStateChoice: (typeof feedData.filterState === 'object' && feedData.filterState?.states) ? feedData.filterState.states : (feedData.filterStateChoice || []),
            filterZip: (typeof feedData.filterZip === 'object' && feedData.filterZip?.mode) ? feedData.filterZip.mode : (feedData.filterZip || ''),
            filterZipCodes: (typeof feedData.filterZip === 'object' && feedData.filterZip?.zipCodes) ? feedData.filterZip.zipCodes : (feedData.filterZipCodes || []),
            feedCategory: feedData.feedCategory || 'phone-preping',
            timezone: feedData.timezone || 'America/New_York',
            requiredPingFields: feedData.requiredPingFields || [],
            allowedPingFields: feedData.allowedPingFields || [],
            required: feedData.required || ['email', 'ip', 'url', 'stamp', 'authorization'],
            allowedFields: feedData.allowedFields || [],
            custom1Label: feedData.custom1Label || '',
            custom2Label: feedData.custom2Label || '',
            custom3Label: feedData.custom3Label || '',
            custom4Label: feedData.custom4Label || '',
            custom5Label: feedData.custom5Label || '',
            custom6Label: feedData.custom6Label || '',
            dedupeEmail: feedData.dedupeEmail === '1' || feedData.dedupeEmail === 1 ? '1' : '0',
            dedupeLandline: feedData.dedupeLandline === '1' || feedData.dedupeLandline === 1 ? '1' : '0',
            dedupeCellphone: feedData.dedupeCellphone === '1' || feedData.dedupeCellphone === 1 ? '1' : '0',
            dedupeAcross: feedData.dedupeAcross || 'urlGlobal',
            lookbackPeriod: feedData.lookbackPeriod ? String(feedData.lookbackPeriod) : '120',
            filterTypeUrl: feedData.filterTypeUrl || '',
            filterUrl: feedData.filterUrl || [],
            rejectOldLeadsMaxAge: feedData.rejectOldLeadsMaxAge || '7 Days Ago',
            pingTimeout: feedData.pingTimeout ? String(feedData.pingTimeout) : '300',
            dailyLimit: feedData.dailyLimit ? String(feedData.dailyLimit) : '',
            chokePercent: feedData.chokePercent ? String(feedData.chokePercent) : '0',
            costPerLead:
              feedData.costPerLead !== null &&
              feedData.costPerLead !== undefined &&
              feedData.costPerLead !== '' &&
              Number.isFinite(Number(feedData.costPerLead))
                ? Number(feedData.costPerLead).toFixed(2)
                : '',
            salesperson: feedData.salesperson ? String(feedData.salesperson) : '',
            notifications: feedData.notifications === '1' || feedData.notifications === 1 ? '1' : '0',
            notifyThresholdCount: feedData.notifyThresholdCount ? String(feedData.notifyThresholdCount) : '0',
            notifyThresholdTime: feedData.notifyThresholdTime || '',
            notifyThresholdDays: feedData.notifyThresholdDays || [],
            pauseMessage: feedData.pauseMessage || '',
            timeskew: feedData.timeskew || '',
            status: feedData.status || 'active',
            minimumBirthAge: feedData.minimumBirthAge || '',
            maximumBirthAge: feedData.maximumBirthAge || '',
          });
          await nextTick();
          await nextTick();
          if (window.$ && window.$('#editFeedModal')) {
            window.$('#editFeedModal').modal('show');
          }
        } else {
          editError.value = 'Failed to load feed data.';
        }
      } catch (error) {
        editError.value = 'Failed to load feed data: ' + (error.response?.data?.error || error.message);
        await nextTick();
        if (window.$ && window.$('#editFeedModal')) {
          window.$('#editFeedModal').modal('show');
        }
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      Object.assign(editingFeed, {
        idFeedIn: null,
        label: '',
        description: '',
        idCompany: '',
        filterState: '',
        filterStateChoice: [],
        filterZip: '',
        filterZipCodes: [],
        feedCategory: 'phone-preping',
        timezone: 'America/New_York',
        requiredPingFields: [],
        allowedPingFields: [],
        required: ['email', 'ip', 'url', 'stamp', 'authorization'],
        allowedFields: [],
        custom1Label: '',
        custom2Label: '',
        custom3Label: '',
        custom4Label: '',
        custom5Label: '',
        custom6Label: '',
        dedupeEmail: '0',
        dedupeLandline: '0',
        dedupeCellphone: '0',
        dedupeAcross: 'urlGlobal',
        lookbackPeriod: '120',
        filterTypeUrl: '',
        filterUrl: [],
        rejectOldLeadsMaxAge: '7 Days Ago',
        pingTimeout: '300',
        dailyLimit: '',
        chokePercent: '0',
        costPerLead: '',
        salesperson: '',
        notifications: '1',
        notifyThresholdCount: '0',
        notifyThresholdTime: '',
        notifyThresholdDays: [],
        pauseMessage: '',
        timeskew: '',
        status: 'active',
        minimumBirthAge: '',
        maximumBirthAge: '',
      });
      if (window.$ && window.$('#editFeedModal')) {
        window.$('#editFeedModal').modal('hide');
      }
    };

    const fetchCompanies = async () => {
      try {
        const response = await axios.get('/api/companies/dropdown', { params: { status: 'active' } });
        if (response.data.status === 1) {
          companies.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching companies:', error);
      }
    };

    const fetchAvailableFields = async () => {
      try {
        const response = await axios.get('/api/inbound-feeds/available-fields');
        if (response.data.status === 1) {
          availableFields.value = response.data.data || [];
          // Set default allowedFields to all fields
          if (availableFields.value.length > 0) {
            newFeed.allowedFields = availableFields.value.map((f) => f.fieldName);
            // Ensure authorization is included for ping requests
            if (!newFeed.allowedFields.includes('authorization')) {
              newFeed.allowedFields.push('authorization');
            }
          }
        }
      } catch (error) {
        console.error('Error fetching available fields:', error);
      }
    };

    const fetchFeedCategories = async () => {
      try {
        const response = await axios.get('/api/inbound-feeds/categories');
        if (response.data.status === 1) {
          feedCategories.value = response.data.data || {};
        }
      } catch (error) {
        console.error('Error fetching feed categories:', error);
      }
    };

    const fetchTimezones = async () => {
      try {
        const response = await axios.get('/api/inbound-feeds/timezones');
        if (response.data.status === 1) {
          timezones.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching timezones:', error);
      }
    };

    const fetchStaffUsers = async () => {
      try {
        const response = await axios.get('/api/companies/staff-users');
        if (response.data.status === 1) {
          staffUsers.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching staff users:', error);
      }
    };

    const updateNewFeed = (updatedFeed) => {
      Object.assign(newFeed, updatedFeed);
    };

    const handleAddFeed = async () => {
      if (!newFeed.label.trim()) {
        addError.value = 'Feed label cannot be empty.';
        return;
      }

      if (!newFeed.idCompany) {
        addError.value = 'Company cannot be empty.';
        return;
      }

      if (newFeed.allowedFields.length === 0) {
        addError.value = 'You must allow at least one field to be processed.';
        return;
      }

      // Ensure feedCategory is phone-preping for ping requests
      if (newFeed.feedCategory !== 'phone-preping') {
        newFeed.feedCategory = 'phone-preping';
      }

      // Ensure authorization is in required and allowed fields
      if (!newFeed.allowedFields.includes('authorization')) {
        newFeed.allowedFields.push('authorization');
      }
      if (!newFeed.required.includes('authorization')) {
        newFeed.required.push('authorization');
      }

      if (newFeed.filterState && (newFeed.filterState === 'includeOnly' || newFeed.filterState === 'excludeOnly')) {
        if (newFeed.filterStateChoice.length === 0) {
          addError.value = 'If using the state filter feature, at least one state must be selected.';
          return;
        }
      }

      adding.value = true;
      addError.value = '';

      try {
        const payload = {
          label: newFeed.label.trim(),
          description: newFeed.description.trim() || null,
          idCompany: parseInt(newFeed.idCompany),
          feedCategory: 'phone-preping', // Force phone-preping
          timezone: newFeed.timezone,
          required: newFeed.required,
          allowedFields: newFeed.allowedFields,
          requiredPingFields: newFeed.requiredPingFields,
          allowedPingFields: newFeed.allowedPingFields,
          custom1Label: newFeed.custom1Label.trim() || null,
          custom2Label: newFeed.custom2Label.trim() || null,
          custom3Label: newFeed.custom3Label.trim() || null,
          custom4Label: newFeed.custom4Label.trim() || null,
          custom5Label: newFeed.custom5Label.trim() || null,
          custom6Label: newFeed.custom6Label.trim() || null,
          dedupeEmail: newFeed.dedupeEmail,
          dedupeLandline: newFeed.dedupeLandline,
          dedupeCellphone: newFeed.dedupeCellphone,
          dedupeAcross: newFeed.dedupeAcross,
          lookbackPeriod: parseInt(newFeed.lookbackPeriod),
          filterTypeUrl: newFeed.filterTypeUrl || null,
          filterUrl: newFeed.filterUrl.filter((url) => url.trim()),
          filterState: newFeed.filterState || null,
          filterStateChoice: newFeed.filterStateChoice,
          filterZip: newFeed.filterZip || null,
          filterZipCodes: newFeed.filterZipCodes,
          rejectOldLeadsMaxAge: newFeed.rejectOldLeadsMaxAge.trim() || null,
          pingTimeout: newFeed.pingTimeout ? parseInt(newFeed.pingTimeout) : null,
          dailyLimit: newFeed.dailyLimit ? parseInt(newFeed.dailyLimit) : null,
          chokePercent: newFeed.chokePercent ? parseInt(newFeed.chokePercent) : 0,
          costPerLead: newFeed.costPerLead ? parseFloat(newFeed.costPerLead) : null,
          salesperson: newFeed.salesperson ? parseInt(newFeed.salesperson) : null,
          notifications: newFeed.notifications,
          notifyThresholdCount: newFeed.notifyThresholdCount ? parseInt(newFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: newFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: newFeed.notifyThresholdDays,
          pauseMessage: newFeed.pauseMessage.trim() || null,
          timeskew: newFeed.timeskew.trim() || null,
          status: newFeed.status,
          minimumBirthAge: newFeed.minimumBirthAge ? parseInt(newFeed.minimumBirthAge) : null,
          maximumBirthAge: newFeed.maximumBirthAge ? parseInt(newFeed.maximumBirthAge) : null,
        };

        const response = await axios.post('/api/inbound-feeds', payload);

        if (response.data.status === 1) {
          closeAddModal();
          // Reset form
          const defaultAllowedFields = availableFields.value.length > 0
            ? availableFields.value.map((f) => f.fieldName)
            : [];
          if (!defaultAllowedFields.includes('authorization')) {
            defaultAllowedFields.push('authorization');
          }
          Object.assign(newFeed, {
            label: '',
            description: '',
            idCompany: '',
            filterState: '',
            filterStateChoice: [],
            filterZip: '',
            filterZipCodes: [],
            feedCategory: 'phone-preping',
            timezone: 'America/New_York',
            requiredPingFields: [],
            allowedPingFields: [],
            required: ['email', 'ip', 'url', 'stamp', 'authorization'],
            allowedFields: defaultAllowedFields,
            custom1Label: '',
            custom2Label: '',
            custom3Label: '',
            custom4Label: '',
            custom5Label: '',
            custom6Label: '',
            dedupeEmail: '0',
            dedupeLandline: '0',
            dedupeCellphone: '0',
            dedupeAcross: 'urlGlobal',
            lookbackPeriod: '120',
            filterTypeUrl: '',
            filterUrl: [],
            rejectOldLeadsMaxAge: '7 Days Ago',
            pingTimeout: '300',
            dailyLimit: '',
            chokePercent: '0',
            costPerLead: '',
            salesperson: '',
            notifications: '1',
            notifyThresholdCount: '0',
            notifyThresholdTime: '',
            notifyThresholdDays: [],
            pauseMessage: '',
            timeskew: '',
            status: 'active',
            minimumBirthAge: '',
            maximumBirthAge: '',
          });
          await fetchFeeds();
        } else {
          addError.value = response.data.error || 'Failed to add ping request.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add ping request.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateFeed = async () => {
      if (!editingFeed.label.trim()) {
        editError.value = 'Feed label cannot be empty.';
        return;
      }

      if (!editingFeed.idCompany) {
        editError.value = 'Company cannot be empty.';
        return;
      }

      if (editingFeed.allowedFields.length === 0) {
        editError.value = 'You must allow at least one field to be processed.';
        return;
      }

      if (editingFeed.filterState && (editingFeed.filterState === 'includeOnly' || editingFeed.filterState === 'excludeOnly')) {
        if (editingFeed.filterStateChoice.length === 0) {
          editError.value = 'If using the state filter feature, at least one state must be selected.';
          return;
        }
      }

      updating.value = true;
      editError.value = '';

      try {
        const payload = {
          label: editingFeed.label.trim(),
          description: editingFeed.description.trim() || null,
          idCompany: parseInt(editingFeed.idCompany),
          feedCategory: 'phone-preping',
          timezone: editingFeed.timezone,
          required: editingFeed.required,
          allowedFields: editingFeed.allowedFields,
          requiredPingFields: editingFeed.requiredPingFields,
          allowedPingFields: editingFeed.allowedPingFields,
          custom1Label: editingFeed.custom1Label.trim() || null,
          custom2Label: editingFeed.custom2Label.trim() || null,
          custom3Label: editingFeed.custom3Label.trim() || null,
          custom4Label: editingFeed.custom4Label.trim() || null,
          custom5Label: editingFeed.custom5Label.trim() || null,
          custom6Label: editingFeed.custom6Label.trim() || null,
          dedupeEmail: editingFeed.dedupeEmail,
          dedupeLandline: editingFeed.dedupeLandline,
          dedupeCellphone: editingFeed.dedupeCellphone,
          dedupeAcross: editingFeed.dedupeAcross,
          lookbackPeriod: parseInt(editingFeed.lookbackPeriod),
          filterTypeUrl: editingFeed.filterTypeUrl || null,
          filterUrl: editingFeed.filterUrl.filter((url) => url.trim()),
          filterState: editingFeed.filterState || null,
          filterStateChoice: editingFeed.filterStateChoice,
          filterZip: editingFeed.filterZip || null,
          filterZipCodes: editingFeed.filterZipCodes,
          rejectOldLeadsMaxAge: editingFeed.rejectOldLeadsMaxAge.trim() || null,
          pingTimeout: editingFeed.pingTimeout ? parseInt(editingFeed.pingTimeout) : null,
          dailyLimit: editingFeed.dailyLimit ? parseInt(editingFeed.dailyLimit) : null,
          chokePercent: editingFeed.chokePercent ? parseInt(editingFeed.chokePercent) : 0,
          costPerLead: editingFeed.costPerLead ? parseFloat(editingFeed.costPerLead) : null,
          salesperson: editingFeed.salesperson ? parseInt(editingFeed.salesperson) : null,
          notifications: editingFeed.notifications,
          notifyThresholdCount: editingFeed.notifyThresholdCount ? parseInt(editingFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: editingFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: editingFeed.notifyThresholdDays,
          pauseMessage: editingFeed.pauseMessage.trim() || null,
          timeskew: editingFeed.timeskew.trim() || null,
          status: editingFeed.status,
          minimumBirthAge: editingFeed.minimumBirthAge ? parseInt(editingFeed.minimumBirthAge) : null,
          maximumBirthAge: editingFeed.maximumBirthAge ? parseInt(editingFeed.maximumBirthAge) : null,
        };

        const response = await axios.put(`/api/inbound-feeds/${editingFeed.idFeedIn}`, payload);

        if (response.data.status === 1) {
          closeEditModal();
          await fetchFeeds();
        } else {
          editError.value = response.data.error || 'Failed to update feed.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update feed.';
      } finally {
        updating.value = false;
      }
    };

    onMounted(async () => {
      document.addEventListener('click', () => {
        openDropdownFeedId.value = null;
      });
      await Promise.all([
        fetchCompanies(),
        fetchAvailableFields(),
        fetchFeedCategories(),
        fetchTimezones(),
        fetchStaffUsers(),
        fetchFeeds(),
      ]);
    });

    return {
      companyGroups,
      companies,
      availableFields,
      feedCategories,
      timezones,
      staffUsers,
      usStates,
      newFeed,
      editingFeed,
      loading,
      adding,
      updating,
      addError,
      editError,
      filters,
      expandedCompanies,
      openDropdownFeedId,
      toggleFeedDropdown,
      pingSpecModal,
      pingSpecLoading,
      openPingSpecModal,
      closePingSpecModal,
      copyToClipboard,
      updateEditingFeed,
      formatNumber,
      recordSearchLink,
      fetchFeeds,
      toggleCompanyFeeds,
      togglePause,
      openAddModal,
      closeAddModal,
      openEditModal,
      closeEditModal,
      updateNewFeed,
      handleAddFeed,
      handleUpdateFeed,
    };
  },
};
</script>

<style scoped>
/* Ping Spec modal - Teleport to body */
.ping-feed-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  overflow-x: hidden;
  overflow-y: auto;
  padding: 20px;
}
.ping-feed-modal .modal-dialog {
  margin: auto;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
  max-width: 90%;
}

.status-active { color: #429038; }
.status-hidden { color: #999; }
.status-retired { color: #d9534f; }

.incoming-col-large { width: 30%; }
.incoming-col-small { width: 10%; }
.text-center { text-align: center; }

.pt-2{
  padding-top:20px;
}
.table th {
  background-color: #072f5f !important;
  color: #fff !important;
  text-align: center;
  vertical-align: middle;
}

.bgGray {
  background-color: #072f5f !important;
  color: #fff !important;
}

.custom-master {
  font-weight: bold;
}

.feed-toggle {
  background-color: #f9f9f9;
}

.bg-gray {
  background-color: #f9f9f9;
}

.status-active {
  color: #429038;
}

.status-hidden {
  color: #999;
}

.status-retired {
  color: #d9534f;
}

.incoming-col-large {
  width: 40%;
}

.incoming-col-small {
  width: 15%;
}

.record-search-link-cell a {
  color: inherit;
  text-decoration: none;
}

.record-search-link-cell a:hover {
  text-decoration: underline;
}

.text-right {
  text-align: right;
}

/* Toggle Switch Styles */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.4s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #429038;
}

input:checked + .slider:before {
  transform: translateX(26px);
}
</style>
