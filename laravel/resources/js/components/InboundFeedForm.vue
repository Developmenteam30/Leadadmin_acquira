<template>
  <div class="form-input">
    <form class="form-inline" :id="prefix + '_feedinc'">
      <table class="table table-bordered table-condensed table-striped">
        <tr v-if="isEdit">
          <td>Feed ID</td>
          <td>
            <input
              type="text"
              :value="feed.idFeedIn"
              class="form-control"
              disabled
            />
          </td>
        </tr>
        <tr>
          <td>Feed Label</td>
          <td>
            <input
              type="text"
              name="label"
              id="label"
              v-model="localFeed.label"
              class="input-long form-control"
              required
            />
          </td>
        </tr>
        <tr>
          <td>Description</td>
          <td>
            <input
              type="text"
              name="description"
              id="description"
              v-model="localFeed.description"
              class="input-long form-control"
            />
          </td>
        </tr>
        <tr>
          <td>Company</td>
          <td>
            <select name="idCompany" id="idCompany" v-model="localFeed.idCompany" class="form-control" required>
              <option value="">Select a company</option>
              <option
                v-for="company in companies"
                :key="company.idCompany"
                :value="company.idCompany"
              >
                {{ company.name }}
              </option>
            </select>
          </td>
        </tr>
        <tr>
          <td>Filter State</td>
          <td>
            <p>Use this feature to limit which state(s) leads are allowed to come from.</p>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterState"
                  value="includeOnly"
                  v-model="localFeed.filterState"
                  @change="handleFilterStateChange"
                />
                Include Only
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterState"
                  value="excludeOnly"
                  v-model="localFeed.filterState"
                  @change="handleFilterStateChange"
                />
                Exclude Only
              </label>
            </p>
            <div v-if="localFeed.filterState === 'includeOnly' || localFeed.filterState === 'excludeOnly'">
              <p>Choose which states to include/exclude.</p>
              <p>
                <label
                  v-for="(name, code) in usStates"
                  :key="code"
                  class="checkbox-label"
                >
                  <input
                    type="checkbox"
                    name="filterStateChoice[]"
                    :value="code"
                    v-model="localFeed.filterStateChoice"
                  />
                  &nbsp;{{ name }}
                </label>
              </p>
            </div>
          </td>
        </tr>
        <tr>
          <td>Filter Zip Code</td>
          <td>
            <p>Use this feature to limit which zip code(s) leads are allowed to come from.</p>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterZip"
                  value="includeOnly"
                  v-model="localFeed.filterZip"
                />
                Include Only
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterZip"
                  value="excludeOnly"
                  v-model="localFeed.filterZip"
                />
                Exclude Only
              </label>
            </p>
            <p>There are {{ zipCodeCount }} zip codes on file.</p>
            <div v-if="isEdit && feed.idFeedIn && (localFeed.filterZip === 'includeOnly' || localFeed.filterZip === 'excludeOnly')" class="mt-2">
              <input
                ref="zipFileInput"
                type="file"
                accept=".csv,.txt"
                @change="onZipFileChange"
                style="display: none"
              />
              <button type="button" class="btn btn-sm btn-default" @click="$refs.zipFileInput?.click()">
                {{ zipUploading ? 'Importing...' : 'Import CSV' }}
              </button>
              <span v-if="zipImportMessage" class="ml-2" :class="zipImportSuccess ? 'text-success' : 'text-danger'">{{ zipImportMessage }}</span>
            </div>
          </td>
        </tr>
        <tr>
          <td>Feed Category</td>
          <td>
            <p>Determines which section this feed shows up under on the dashboard.</p>
            <p>
              <label
                v-for="(label, value) in feedCategories"
                :key="value"
                class="radio-label"
              >
                <input
                  type="radio"
                  name="feedCategory"
                  :value="value"
                  v-model="localFeed.feedCategory"
                  @change="handleFeedCategoryChange"
                />
                {{ label }}
              </label>
            </p>
          </td>
        </tr>
        <tr>
          <td>Timezone</td>
          <td>
            <p>Specify what timezone the incoming leads are being sent as.</p>
            <p>
              <select name="timezone" v-model="localFeed.timezone" class="form-control">
                <option
                  v-for="tz in timezones"
                  :key="tz"
                  :value="tz"
                >
                  {{ tz }}
                </option>
              </select>
            </p>
          </td>
        </tr>
        <tr v-if="localFeed.feedCategory === 'phone-preping'" class="preping-row">
          <td>Required PING Fields</td>
          <td>
            <label
              v-for="field in availableFields"
              :key="field.fieldName"
              class="checkbox-label"
            >
              <input
                type="checkbox"
                name="requiredPingFields[]"
                :value="field.fieldName"
                v-model="localFeed.requiredPingFields"
              />
              &nbsp;{{ field.fieldName }}
            </label>
          </td>
        </tr>
        <tr v-if="localFeed.feedCategory === 'phone-preping'" class="preping-row">
          <td>Allowed PING Fields</td>
          <td>
            <label
              v-for="field in availableFields"
              :key="field.fieldName"
              class="checkbox-label"
            >
              <input
                type="checkbox"
                name="allowedPingFields[]"
                :value="field.fieldName"
                v-model="localFeed.allowedPingFields"
              />
              &nbsp;{{ field.fieldName }}
            </label>
          </td>
        </tr>
        <tr>
          <td>Required POST Fields</td>
          <td>
            <label
              v-for="field in availableFields"
              :key="field.fieldName"
              class="checkbox-label"
            >
              <input
                type="checkbox"
                name="required[]"
                :value="field.fieldName"
                v-model="localFeed.required"
                @change="handleRequiredChange(field.fieldName)"
              />
              &nbsp;{{ field.fieldName }}
            </label>
            <br/>
            <label class="checkbox-label">
              <input
                type="checkbox"
                value="phone"
                :checked="localFeed.required.includes('landline') && localFeed.required.includes('cellphone')"
                @change="handlePhoneRequiredChange"
              />
              &nbsp;phone (selects both landline and cellphone)
            </label>
          </td>
        </tr>
        <tr>
          <td>Allowed POST Fields</td>
          <td>
            <label
              v-for="field in availableFields"
              :key="field.fieldName"
              class="checkbox-label"
            >
              <input
                type="checkbox"
                name="allowedFields[]"
                :value="field.fieldName"
                v-model="localFeed.allowedFields"
              />
              &nbsp;{{ field.fieldName }}
            </label>
          </td>
        </tr>
        <tr>
          <td>Legacy Custom Fields</td>
          <td>
            <p>Use this section to store notes about what each custom field is being used for.</p>
            custom1 = <input
              type="text"
              name="custom1Label"
              v-model="localFeed.custom1Label"
              class="input-long form-control"
            /><br/>
            custom2 = <input
              type="text"
              name="custom2Label"
              v-model="localFeed.custom2Label"
              class="input-long form-control"
            /><br/>
            custom3 = <input
              type="text"
              name="custom3Label"
              v-model="localFeed.custom3Label"
              class="input-long form-control"
            /><br/>
            custom4 = <input
              type="text"
              name="custom4Label"
              v-model="localFeed.custom4Label"
              class="input-long form-control"
            /><br/>
            custom5 = <input
              type="text"
              name="custom5Label"
              v-model="localFeed.custom5Label"
              class="input-long form-control"
            /><br/>
            custom6 = <input
              type="text"
              name="custom6Label"
              v-model="localFeed.custom6Label"
              class="input-long form-control"
            />
          </td>
        </tr>
        <tr>
          <td>Duplicate Filters</td>
          <td>
            <label class="checkbox-label">
              <input
                type="checkbox"
                name="dedupeEmail"
                :checked="localFeed.dedupeEmail === '1'"
                @change="localFeed.dedupeEmail = $event.target.checked ? '1' : '0'"
              />
              &nbsp;Reject Duplicate Emails
            </label>
            <label class="checkbox-label">
              <input
                type="checkbox"
                name="dedupeLandline"
                :checked="localFeed.dedupeLandline === '1'"
                @change="localFeed.dedupeLandline = $event.target.checked ? '1' : '0'"
              />
              &nbsp;Reject Duplicate Landline Numbers
            </label>
            <label class="checkbox-label">
              <input
                type="checkbox"
                name="dedupeCellphone"
                :checked="localFeed.dedupeCellphone === '1'"
                @change="localFeed.dedupeCellphone = $event.target.checked ? '1' : '0'"
              />
              &nbsp;Reject Duplicate Cellphone Numbers
            </label>
          </td>
        </tr>
        <tr>
          <td>Duplicate Options</td>
          <td>
            <p>
              DISABLED: <input
                type="radio"
                name="dedupeAcross"
                value="none"
                v-model="localFeed.dedupeAcross"
              />
              Allow duplicate records<br/>
              THIS FEED: <input
                type="radio"
                name="dedupeAcross"
                value="all"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across all records of this feed
              <input
                type="radio"
                name="dedupeAcross"
                value="url"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across same URL of this feed
              <input
                type="radio"
                name="dedupeAcross"
                value="listcode"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across same listcode of this feed<br/>
              ALL FEEDS: <input
                type="radio"
                name="dedupeAcross"
                value="allGlobal"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across all records of all feeds
              <input
                type="radio"
                name="dedupeAcross"
                value="urlGlobal"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across same URL of all feeds
              <!-- <input
                type="radio"
                name="dedupeAcross"
                value="listcodeGlobal"
                v-model="localFeed.dedupeAcross"
              />
              Dedupe across same listcode of all feeds -->
            </p>
            <p style="margin-top: 1em;">Lookback period</p>
            <p>
              <label class="radio-label">
                <input type="radio" name="lookbackPeriod" value="30" v-model="localFeed.lookbackPeriod"/>
                30 days
              </label>
              <br/>
              <label class="radio-label">
                <input type="radio" name="lookbackPeriod" value="60" v-model="localFeed.lookbackPeriod"/>
                60 days
              </label>
              <br/>
              <label class="radio-label">
                <input type="radio" name="lookbackPeriod" value="90" v-model="localFeed.lookbackPeriod"/>
                90 days
              </label>
              <br/>
              <label class="radio-label">
                <input type="radio" name="lookbackPeriod" value="120" v-model="localFeed.lookbackPeriod"/>
                120 days (default)
              </label>
            </p>
          </td>
        </tr>
        <tr>
          <td>URL Filter Options</td>
          <td>
            <p>
              Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into the feed.
              Using the 'Reject' option, all urls will be accepted, except the ones listed here.
            </p>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterTypeUrl"
                  value=""
                  v-model="localFeed.filterTypeUrl"
                  @change="handleFilterUrlChange"
                />
                Disabled
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterTypeUrl"
                  value="accept"
                  v-model="localFeed.filterTypeUrl"
                  @change="handleFilterUrlChange"
                />
                Accept
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="filterTypeUrl"
                  value="reject"
                  v-model="localFeed.filterTypeUrl"
                  @change="handleFilterUrlChange"
                />
                Reject
              </label>
            </p>
            <div v-if="localFeed.filterTypeUrl">
              <p>The following urls:</p>
              <p>
                <a href="#" class="nonLink" @click.prevent="addFilterUrl">Add New URL</a>
              </p>
              <div>
                <div
                  v-for="(url, index) in localFeed.filterUrl"
                  :key="index"
                >
                  <input
                    type="text"
                    name="filterUrl[]"
                    v-model="localFeed.filterUrl[index]"
                    class="form-control"
                    style="display: inline-block; width: 300px;"
                  />
                  <a href="#" class="nonLink" @click.prevent="removeFilterUrl(index)">[X]</a>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td>Lead Rejections</td>
          <td>
            <p>How old are leads allowed to be before we reject them? This should be a text string like "7 Days Ago" or "30 Days Ago".</p>
            <p>
              <input
                type="text"
                name="rejectOldLeadsMaxAge"
                id="rejectOldLeadsMaxAge"
                v-model="localFeed.rejectOldLeadsMaxAge"
                class="input-long form-control"
              />
            </p>
          </td>
        </tr>
        <tr v-if="localFeed.feedCategory === 'phone-preping'" class="preping-row">
          <td>Ping/Post Timeout</td>
          <td>
            <p>Enter the maximum number of <strong>seconds</strong> allowed between a PING and a POST.</p>
            <p>300 seconds = 5 minutes<br/>
               3600 seconds = 1 hour<br/>
               86400 seconds = 1 day
            </p>
            <p>
              <input
                type="text"
                name="pingTimeout"
                id="pingTimeout"
                v-model="localFeed.pingTimeout"
                class="input-long form-control"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Daily Feed Limit</td>
          <td>
            <p>Leave blank for no limit (default). If a value is supplied here, the feed will stop accepting records after the daily limit is reached.</p>
            <p>
              <input
                type="text"
                name="dailyLimit"
                v-model="localFeed.dailyLimit"
                class="form-control"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Cost Per Lead</td>
          <td>
            <p>
              <input
                type="text"
                name="costPerLead"
                v-model="localFeed.costPerLead"
                class="form-control"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Margin Per Lead</td>
          <td>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="revenuePerLeadType"
                  value="fixed"
                  v-model="localFeed.revenuePerLeadType"
                />
                Fixed
              </label>
              <label class="radio-label">
                <input
                  type="radio"
                  name="revenuePerLeadType"
                  value="percent"
                  v-model="localFeed.revenuePerLeadType"
                />
                Percentage
              </label>
            </p>
            <p>
              <input
                type="number"
                name="revenuePerLead"
                v-model="localFeed.revenuePerLead"
                class="form-control"
                placeholder="Value"
                step="0.01"
                min="0"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Salesperson</td>
          <td>
            <p>By default, salesperson revenues are assigned at a company level.</p>
            <p>
              <select name="salesperson" v-model="localFeed.salesperson" class="form-control">
                <option value="">Select a salesperson (optional)</option>
                <option
                  v-for="user in staffUsers"
                  :key="user.idUser"
                  :value="user.idUser"
                >
                  {{ user.fullName }}
                </option>
              </select>
            </p>
          </td>
        </tr>
        <tr>
          <td>Dormant Notifications</td>
          <td>
            <p>Should we send dormant URL notifications for URLs in this feed?</p>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="notifications"
                  value="1"
                  v-model="localFeed.notifications"
                />
                Enabled
              </label>
              <label class="radio-label">
                <input
                  type="radio"
                  name="notifications"
                  value="0"
                  v-model="localFeed.notifications"
                />
                Disabled
              </label>
            </p>
          </td>
        </tr>
        <tr>
          <td>Threshold Notifications</td>
          <td>
            <p>
              Send an email notification if we have not received
              <input
                type="text"
                name="notifyThresholdCount"
                v-model="localFeed.notifyThresholdCount"
                class="form-control"
                style="display: inline-block; width: 80px;"
              />
              leads by
              <input
                type="text"
                name="notifyThresholdTime"
                placeholder="Example: 10:00AM"
                v-model="localFeed.notifyThresholdTime"
                class="form-control"
                style="display: inline-block; width: 120px;"
              />
              on<br/>
              <label
                v-for="(day, index) in daysOfWeek"
                :key="index"
                class="checkbox-label"
              >
                <input
                  type="checkbox"
                  name="notifyThresholdDays[]"
                  :value="index"
                  v-model="localFeed.notifyThresholdDays"
                />
                &nbsp;{{ day }}
              </label>
            </p>
            <p><strong>To disable notifications from being sent, set the lead count to zero or uncheck all day boxes.</strong></p>
          </td>
        </tr>
        <tr>
          <td>Pause Message</td>
          <td>
            <p>If the feed is paused, send this rejection message to the vendor.</p>
            <p>
              <input
                type="text"
                name="pauseMessage"
                v-model="localFeed.pauseMessage"
                class="input-long form-control"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Time Skew</td>
          <td>
            <p>If inbound timestamps on the feed should be manipulated before being saved to the DB, enter the amount of the skew below.</p>
            <p>
              <input
                type="text"
                name="timeskew"
                v-model="localFeed.timeskew"
                class="input-long form-control"
                placeholder="Example: -14 days, +5 hours"
              />
            </p>
          </td>
        </tr>
        <tr>
          <td>Feed Status</td>
          <td>
            <p>
              <label class="radio-label">
                <input
                  type="radio"
                  name="status"
                  value="active"
                  v-model="localFeed.status"
                />
                Active (Visible)
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="status"
                  value="hidden"
                  v-model="localFeed.status"
                />
                Active (Hidden)
              </label>
              <br/>
              <label class="radio-label">
                <input
                  type="radio"
                  name="status"
                  value="retired"
                  v-model="localFeed.status"
                />
                Retired
              </label>
            </p>
          </td>
        </tr>
        <tr>
          <td>Date of Birth Restrictions</td>
          <td>
            <p>If either or both of these values are set, the system will calculate the age of the person based on the DOB passed and reject if the age falls outside these values.</p>
            <p>
              <label for="minimumBirthAge">Minimum Birth Age:</label>
              <input
                type="text"
                name="minimumBirthAge"
                id="minimumBirthAge"
                v-model="localFeed.minimumBirthAge"
                class="form-control"
              />
            </p>
            <p>
              <label for="maximumBirthAge">Maximum Birth Age:</label>
              <input
                type="text"
                name="maximumBirthAge"
                id="maximumBirthAge"
                v-model="localFeed.maximumBirthAge"
                class="form-control"
              />
            </p>
          </td>
        </tr>
      </table>
    </form>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

export default {
  name: 'InboundFeedForm',
  props: {
    feed: {
      type: Object,
      required: true,
    },
    companies: {
      type: Array,
      default: () => [],
    },
    availableFields: {
      type: Array,
      default: () => [],
    },
    feedCategories: {
      type: Object,
      default: () => ({
        email: 'Email',
        phone: 'Phone',
        'phone-preping': 'Phone Pre-Ping',
      }),
    },
    timezones: {
      type: Array,
      default: () => [],
    },
    staffUsers: {
      type: Array,
      default: () => [],
    },
    usStates: {
      type: Object,
      required: true,
    },
    isEdit: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:feed', 'zip-imported'],
  setup(props, { emit }) {
    const prefix = computed(() => (props.isEdit ? 'edit' : 'new'));

    const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    const zipUploading = ref(false);
    const zipImportMessage = ref('');
    const zipImportSuccess = ref(false);
    const zipCodeCount = ref(0);

    const localFeed = ref({
      label: props.feed.label || '',
      description: props.feed.description || '',
      idCompany: props.feed.idCompany || '',
      filterState: (typeof props.feed.filterState === 'object' && props.feed.filterState?.mode) ? props.feed.filterState.mode : (props.feed.filterState || ''),
      filterStateChoice: (typeof props.feed.filterState === 'object' && props.feed.filterState?.states) ? props.feed.filterState.states : (props.feed.filterStateChoice || []),
      filterZip: (typeof props.feed.filterZip === 'object' && props.feed.filterZip?.mode) ? props.feed.filterZip.mode : (props.feed.filterZip || ''),
      filterZipCodes: (typeof props.feed.filterZip === 'object' && props.feed.filterZip?.zipCodes) ? props.feed.filterZip.zipCodes : (props.feed.filterZipCodes || []),
      feedCategory: props.feed.feedCategory || 'phone',
      timezone: props.feed.timezone || 'America/New_York',
      requiredPingFields: props.feed.requiredPingFields || [],
      allowedPingFields: props.feed.allowedPingFields || [],
      required: props.feed.required || ['email', 'ip', 'url', 'stamp'],
      allowedFields: props.feed.allowedFields || [],
      custom1Label: props.feed.custom1Label || '',
      custom2Label: props.feed.custom2Label || '',
      custom3Label: props.feed.custom3Label || '',
      custom4Label: props.feed.custom4Label || '',
      custom5Label: props.feed.custom5Label || '',
      custom6Label: props.feed.custom6Label || '',
      dedupeEmail: props.feed.dedupeEmail === true || props.feed.dedupeEmail === '1' ? '1' : '0',
      dedupeLandline: props.feed.dedupeLandline === true || props.feed.dedupeLandline === '1' ? '1' : '0',
      dedupeCellphone: props.feed.dedupeCellphone === true || props.feed.dedupeCellphone === '1' ? '1' : '0',
      dedupeAcross: props.feed.dedupeAcross || 'urlGlobal',
      lookbackPeriod: props.feed.lookbackPeriod || '120',
      filterTypeUrl: props.feed.filterTypeUrl || '',
      filterUrl: props.feed.filterUrl ? (Array.isArray(props.feed.filterUrl) ? props.feed.filterUrl : props.feed.filterUrl.split(';').filter(Boolean)) : [],
      rejectOldLeadsMaxAge: props.feed.rejectOldLeadsMaxAge || '7 Days Ago',
      pingTimeout: props.feed.pingTimeout || '300',
      dailyLimit: props.feed.dailyLimit || '',
      chokePercent: props.feed.chokePercent || '0',
      costPerLead: props.feed.costPerLead || '',
      revenuePerLeadType: props.feed.revenuePerLeadType || 'fixed',
      revenuePerLead: props.feed.revenuePerLead || '',
      salesperson: props.feed.salesperson || '',
      notifications: props.feed.notifications ? '1' : '1',
      notifyThresholdCount: props.feed.notifyThresholdCount || '0',
      notifyThresholdTime: props.feed.notifyThresholdTime || '',
      notifyThresholdDays: props.feed.notifyThresholdDays ? (Array.isArray(props.feed.notifyThresholdDays) ? props.feed.notifyThresholdDays : props.feed.notifyThresholdDays.split(',').map(Number)) : [],
      pauseMessage: props.feed.pauseMessage || '',
      timeskew: props.feed.timeskew || '',
      status: props.feed.status || 'active',
      minimumBirthAge: props.feed.minimumBirthAge || '',
      maximumBirthAge: props.feed.maximumBirthAge || '',
    });

    const fetchZipCodeCount = async () => {
      if (!props.isEdit || !props.feed.idFeedIn) return;
      try {
        const r = await axios.get(`/api/inbound-feeds/${props.feed.idFeedIn}/filter-zip`);
        if (r.data.status === 1) {
          zipCodeCount.value = r.data.data.count ?? 0;
          localFeed.value.filterZipCodes = r.data.data.zipCodes ?? [];
        }
      } catch (e) {
        zipCodeCount.value = 0;
      }
    };

    onMounted(() => {
      if (props.isEdit && props.feed.idFeedIn) {
        fetchZipCodeCount();
      } else {
        zipCodeCount.value = localFeed.value.filterZipCodes?.length ?? 0;
      }
    });

    watch(
      () => [props.isEdit, props.feed?.idFeedIn],
      ([isEdit, idFeedIn]) => {
        if (isEdit && idFeedIn) {
          fetchZipCodeCount();
        } else {
          zipCodeCount.value = localFeed.value.filterZipCodes?.length ?? 0;
        }
      }
    );

    const onZipFileChange = async (e) => {
      const file = e.target?.files?.[0];
      e.target.value = '';
      if (!file || !props.feed.idFeedIn) return;
      zipUploading.value = true;
      zipImportMessage.value = '';
      try {
        const form = new FormData();
        form.append('file', file);
        const r = await axios.post(`/api/inbound-feeds/${props.feed.idFeedIn}/import-filter-zip`, form, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        zipImportMessage.value = r.data.message || (r.data.status === 1 ? 'Import successful' : r.data.error);
        zipImportSuccess.value = r.data.status === 1;
        if (r.data.status === 1) {
          await fetchZipCodeCount();
          emit('zip-imported', { idFeedIn: props.feed.idFeedIn });
        }
      } catch (err) {
        zipImportMessage.value = err.response?.data?.error || err.message || 'Import failed';
        zipImportSuccess.value = false;
      } finally {
        zipUploading.value = false;
      }
    };

    // Watch for changes and emit to parent
    watch(
      localFeed,
      (newValue) => {
        emit('update:feed', newValue);
      },
      { deep: true }
    );

    // Watch filterZipCodes for updates after zip import (parent refetches)
    watch(
      () => props.feed.filterZipCodes,
      (newVal) => {
        if (Array.isArray(newVal)) {
          localFeed.value.filterZipCodes = newVal;
        }
      },
      { deep: true }
    );

    // Watch for prop changes (when loading feed data for editing)
    watch(
      () => props.feed,
      (newFeed) => {
        if (newFeed && Object.keys(newFeed).length > 0) {
          localFeed.value.label = newFeed.label || '';
          localFeed.value.description = newFeed.description || '';
          localFeed.value.idCompany = newFeed.idCompany || '';
          localFeed.value.filterState = (typeof newFeed.filterState === 'object' && newFeed.filterState?.mode) ? newFeed.filterState.mode : (newFeed.filterState || '');
          localFeed.value.filterStateChoice = (typeof newFeed.filterState === 'object' && newFeed.filterState?.states) ? newFeed.filterState.states : (newFeed.filterStateChoice || []);
          localFeed.value.filterZip = (typeof newFeed.filterZip === 'object' && newFeed.filterZip?.mode) ? newFeed.filterZip.mode : (newFeed.filterZip || '');
          localFeed.value.filterZipCodes = (typeof newFeed.filterZip === 'object' && newFeed.filterZip?.zipCodes) ? newFeed.filterZip.zipCodes : (newFeed.filterZipCodes || []);
          localFeed.value.feedCategory = newFeed.feedCategory || 'phone';
          localFeed.value.timezone = newFeed.timezone || 'America/New_York';
          localFeed.value.requiredPingFields = newFeed.requiredPingFields || [];
          localFeed.value.allowedPingFields = newFeed.allowedPingFields || [];
          localFeed.value.required = newFeed.required || ['email', 'ip', 'url', 'stamp'];
          localFeed.value.allowedFields = newFeed.allowedFields || [];
          localFeed.value.custom1Label = newFeed.custom1Label || '';
          localFeed.value.custom2Label = newFeed.custom2Label || '';
          localFeed.value.custom3Label = newFeed.custom3Label || '';
          localFeed.value.custom4Label = newFeed.custom4Label || '';
          localFeed.value.custom5Label = newFeed.custom5Label || '';
          localFeed.value.custom6Label = newFeed.custom6Label || '';
          localFeed.value.dedupeEmail = newFeed.dedupeEmail === true || newFeed.dedupeEmail === '1' || newFeed.dedupeEmail === 1 ? '1' : '0';
          localFeed.value.dedupeLandline = newFeed.dedupeLandline === true || newFeed.dedupeLandline === '1' || newFeed.dedupeLandline === 1 ? '1' : '0';
          localFeed.value.dedupeCellphone = newFeed.dedupeCellphone === true || newFeed.dedupeCellphone === '1' || newFeed.dedupeCellphone === 1 ? '1' : '0';
          localFeed.value.dedupeAcross = newFeed.dedupeAcross || 'urlGlobal';
          localFeed.value.lookbackPeriod = newFeed.lookbackPeriod ? String(newFeed.lookbackPeriod) : '120';
          localFeed.value.filterTypeUrl = newFeed.filterTypeUrl || '';
          localFeed.value.filterUrl = newFeed.filterUrl ? (Array.isArray(newFeed.filterUrl) ? newFeed.filterUrl : newFeed.filterUrl.split(';').filter(Boolean)) : [];
          localFeed.value.rejectOldLeadsMaxAge = newFeed.rejectOldLeadsMaxAge || '7 Days Ago';
          localFeed.value.pingTimeout = newFeed.pingTimeout ? String(newFeed.pingTimeout) : '300';
          localFeed.value.dailyLimit = newFeed.dailyLimit ? String(newFeed.dailyLimit) : '';
          localFeed.value.chokePercent = newFeed.chokePercent ? String(newFeed.chokePercent) : '0';
          localFeed.value.costPerLead = newFeed.costPerLead ? String(newFeed.costPerLead) : '';
          localFeed.value.revenuePerLeadType = newFeed.revenuePerLeadType || 'fixed';
          localFeed.value.revenuePerLead = newFeed.revenuePerLead ? String(newFeed.revenuePerLead) : '';
          localFeed.value.salesperson = newFeed.salesperson ? String(newFeed.salesperson) : '';
          localFeed.value.notifications = newFeed.notifications === '1' || newFeed.notifications === true || newFeed.notifications === 1 ? '1' : '0';
          localFeed.value.notifyThresholdCount = newFeed.notifyThresholdCount ? String(newFeed.notifyThresholdCount) : '0';
          localFeed.value.notifyThresholdTime = newFeed.notifyThresholdTime || '';
          localFeed.value.notifyThresholdDays = newFeed.notifyThresholdDays ? (Array.isArray(newFeed.notifyThresholdDays) ? newFeed.notifyThresholdDays : newFeed.notifyThresholdDays.split(',').map(Number)) : [];
          localFeed.value.pauseMessage = newFeed.pauseMessage || '';
          localFeed.value.timeskew = newFeed.timeskew || '';
          localFeed.value.status = newFeed.status || 'active';
          localFeed.value.minimumBirthAge = newFeed.minimumBirthAge ? String(newFeed.minimumBirthAge) : '';
          localFeed.value.maximumBirthAge = newFeed.maximumBirthAge ? String(newFeed.maximumBirthAge) : '';
        }
      },
      { deep: true, immediate: true }
    );

    const handleFilterStateChange = () => {
      if (localFeed.value.filterState !== 'includeOnly' && localFeed.value.filterState !== 'excludeOnly') {
        localFeed.value.filterStateChoice = [];
      }
    };

    const handleFeedCategoryChange = () => {
      if (localFeed.value.feedCategory !== 'phone-preping') {
        localFeed.value.requiredPingFields = [];
        localFeed.value.allowedPingFields = [];
      }
    };

    const handleFilterUrlChange = () => {
      if (!localFeed.value.filterTypeUrl) {
        localFeed.value.filterUrl = [];
      } else if (localFeed.value.filterUrl.length === 0) {
        localFeed.value.filterUrl = [''];
      }
    };

    const addFilterUrl = () => {
      if (!localFeed.value.filterUrl) {
        localFeed.value.filterUrl = [];
      }
      localFeed.value.filterUrl.push('');
    };

    const removeFilterUrl = (index) => {
      localFeed.value.filterUrl.splice(index, 1);
    };

    const handlePhoneRequiredChange = (event) => {
      if (event.target.checked) {
        if (!localFeed.value.required.includes('landline')) {
          localFeed.value.required.push('landline');
        }
        if (!localFeed.value.required.includes('cellphone')) {
          localFeed.value.required.push('cellphone');
        }
      } else {
        localFeed.value.required = localFeed.value.required.filter(
          (f) => f !== 'landline' && f !== 'cellphone'
        );
      }
    };

    const handleRequiredChange = (fieldName) => {
      // If phone checkbox is checked but we uncheck landline or cellphone, uncheck phone
      if ((fieldName === 'landline' || fieldName === 'cellphone') && !localFeed.value.required.includes(fieldName)) {
        // Phone is no longer fully selected
      }
    };

    return {
      prefix,
      daysOfWeek,
      localFeed,
      zipUploading,
      zipImportMessage,
      zipImportSuccess,
      zipCodeCount,
      onZipFileChange,
      handleFilterStateChange,
      handleFeedCategoryChange,
      handleFilterUrlChange,
      addFilterUrl,
      removeFilterUrl,
      handlePhoneRequiredChange,
      handleRequiredChange,
    };
  },
};
</script>

<style scoped>
.form-input {
  font-family: Verdana, Helvetica, sans-serif;
}

.form-input table {
  width: 100%;
}

.form-input table td {
  padding: 8px;
  vertical-align: top;
}

.form-input table td:first-child {
  width: 150px;
  font-weight: bold;
}

.input-long {
  width: 375px;
}

.checkbox-label,
.radio-label {
  display: inline-block;
  margin-right: 15px;
  margin-bottom: 5px;
  font-weight: normal;
}

.checkbox-label input[type="checkbox"],
.radio-label input[type="radio"] {
  margin-right: 5px;
}

.preping-row {
  display: table-row;
}

.nonLink {
  color: #337ab7;
  text-decoration: none;
  cursor: pointer;
}

.nonLink:hover {
  text-decoration: underline;
}

.form-control {
  display: inline-block;
}
</style>
