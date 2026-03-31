<template>
  <div class="outbound-feed-form">
    <form :id="prefix + '_feedout'">
      <table class="table table-bordered table-condensed table-striped">
        <tr v-if="isEdit">
          <td>Feed ID</td>
          <td><input type="text" :value="feed.idFeedOut" class="form-control" disabled /></td>
        </tr>
        <tr>
          <td><p>Feed Label</p></td>
          <td>
            <p><input type="text" v-model="localFeed.label" class="form-control input-long" maxlength="30" required /></p>
          </td>
        </tr>
        <tr>
          <td><p>Description</p></td>
          <td>
            <p><input type="text" v-model="localFeed.description" class="form-control input-long" maxlength="100" /></p>
          </td>
        </tr>
        <tr>
          <td><p>Company</p></td>
          <td>
            <p>
              <select v-model="localFeed.idCompany" class="form-control" required>
                <option value="">Select a company</option>
                <option v-for="company in companies" :key="company.idCompany" :value="company.idCompany">{{ company.name }}</option>
              </select>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Feed Category</p></td>
          <td>
            <p>Determines which section this feed shows up under on the dashboard.</p>
            <p>
              <label v-for="(label, value) in feedCategories" :key="value" class="radio-label">
                <input type="radio" v-model="localFeed.feedCategory" :value="value" /> {{ label }}
              </label>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Response Type</p></td>
          <td>
            <p>Real-time: buyer responds immediately. Marketplace: buyer responds asynchronously via webhook.</p>
            <p>
              <label class="radio-label"><input type="radio" v-model="localFeed.responseType" value="realtime" /> Real-time</label>
              <label class="radio-label"><input type="radio" v-model="localFeed.responseType" value="marketplace" /> Marketplace (webhook)</label>
            </p>
            <div v-if="localFeed.responseType === 'marketplace'" style="margin-top: 10px;">
              <p><strong>Webhook Secret</strong> (required for webhook auth):</p>
              <p><input type="text" v-model="localFeed.webhookSecret" class="form-control" maxlength="64" placeholder="Token for X-Webhook-Token header" style="max-width: 400px;" /></p>
              <p class="text-muted">Provide this to the marketplace buyer. They must include it in the <code>X-Webhook-Token</code> or <code>Authorization: Bearer</code> header when calling the webhook.</p>
              <p class="text-muted"><strong>Webhook URL:</strong> <code>POST /api/webhooks/outbound</code> — Include <code>leadId</code> (or <code>callbackId</code>) in the request body along with <code>status</code>, <code>reason</code>, and optional <code>cost</code>.</p>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>Timezone</p></td>
          <td>
            <p>Specify what timezone to send the outgoing leads as. Please reference the posting docs or confirm with the client, as this may throw off their acceptance rates.</p>
            <p>
              <select v-model="localFeed.timezone" class="form-control" required>
                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
              </select>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Feed Type</p></td>
          <td>
            <p>
              <select v-model="localFeed.feedType" class="form-control" required>
                <option v-for="(label, value) in feedTypes" :key="value" :value="value">{{ label }}</option>
              </select>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Post URL</p></td>
          <td>
            <p><input type="text" v-model="localFeed.postUrl" class="form-control input-long" maxlength="1000" required /></p>
          </td>
        </tr>
        <tr>
          <td><p>Preping (pre-flight)</p></td>
          <td>
            <p class="text-muted" style="margin-bottom: 8px;">Optional: call a URL before the real post. The send continues only if the response is HTTP 2xx and JSON includes <code>"result":"true"</code> (string). GET uses the same fields as a query string; POST uses the same encoding as Feed Type above.</p>
            <label class="checkbox-label">
              <input type="checkbox" :checked="localFeed.prepingEnabled === '1'" @change="localFeed.prepingEnabled = $event.target.checked ? '1' : '0'" />
              Enable preping
            </label>
            <div v-if="localFeed.prepingEnabled === '1'" style="margin-top: 12px;">
              <p><label>Preping URL</label></p>
              <p><input type="text" v-model="localFeed.prepingUrl" class="form-control input-long" maxlength="1000" placeholder="https://..." /></p>
              <p style="margin-top: 10px;"><label>HTTP method</label></p>
              <p>
                <select v-model="localFeed.prepingHttpMethod" class="form-control" style="max-width: 200px;">
                  <option value="POST">POST</option>
                  <option value="GET">GET</option>
                </select>
              </p>
              <p style="margin-top: 10px;"><label>Authorization</label></p>
              <p>
                <select v-model="localFeed.prepingAuthType" class="form-control" style="max-width: 280px;">
                  <option value="none">None</option>
                  <option value="bearer">Bearer</option>
                  <option value="basic">Basic (user:password)</option>
                </select>
              </p>
              <p v-if="localFeed.prepingAuthType !== 'none'" style="margin-top: 10px;">
                <label>Credential</label>
                <input type="text" v-model="localFeed.prepingAuthValue" class="form-control input-long" autocomplete="off" placeholder="Token, or user:password for Basic" />
              </p>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>Static Fields</p></td>
          <td>
            <p>These are fields that are assigned values specific to this feed, usually provided by the receiving client.</p>
            <p><a href="#" class="dyn-add-link" @click.prevent="addStaticField">Add New Static Field</a></p>
            <div class="field-rows">
              <div v-for="(item, idx) in staticFieldsList" :key="item._id" class="field-row">
                <input type="text" v-model="item.field" class="form-control" placeholder="Field name" /><span> = </span><input type="text" v-model="item.value" class="form-control" placeholder="Value" />
                <a href="#" class="dyn-remove-link" @click.prevent="removeStaticField(idx)">[X]</a>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>Mapped Fields</p></td>
          <td>
            <p>These are fields that are assigned values for each lead. Enter the field name from the receiving client's API spec, and select the lead value to be mapped from the drop-down.</p>
            <p><a href="#" class="dyn-add-link" @click.prevent="addMappedField">Add New Mapped Field</a></p>
            <div class="field-rows">
              <div v-for="(item, idx) in varFieldsList" :key="item._id" class="field-row">
                <span>API Field:</span><input type="text" v-model="item.field" class="form-control" placeholder="API field" /><span> Mapped To:</span><select v-model="item.map" class="form-control">
                  <option value="">Select field</option>
                  <option v-for="f in availableFields" :key="f.fieldName" :value="f.fieldName">{{ f.fieldName }}</option>
                </select>
                <a href="#" class="dyn-remove-link" @click.prevent="removeMappedField(idx)">[X]</a>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>Field Value Translation</p></td>
          <td>
            <p>Send a different field value to the outgoing feed than was received on the inbound feed.</p>
            <p><a href="#" class="dyn-add-link" @click.prevent="addValueTranslation">Add New Value Translation</a></p>
            <div class="field-rows">
              <div v-for="(item, idx) in valueMapList" :key="item._id" class="field-row">
                <span>Field:</span><select v-model="item.field" class="form-control">
                  <option value="">Select field</option>
                  <option v-for="f in availableFields" :key="f.fieldName" :value="f.fieldName">{{ f.fieldName }}</option>
                </select>
                <span> Incoming Value:</span><input type="text" v-model="item.oldValue" class="form-control" placeholder="Incoming" />
                <span> Outgoing Value:</span><input type="text" v-model="item.newValue" class="form-control" placeholder="Outgoing" />
                <a href="#" class="dyn-remove-link" @click.prevent="removeValueTranslation(idx)">[X]</a>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>URL Assignments</p></td>
          <td>
            <p>If you utilize the urlAssign mapped field, when the feed is processing it will populate the mapped field with values according to what you set here, that way you can have multiple unique id's per url within the same feed.</p>
            <p><a href="#" class="dyn-add-link" @click.prevent="addUrlAssignment">Add New URL Assignment</a></p>
            <div class="field-rows">
              <div v-for="(item, idx) in urlassignmentsList" :key="item._id" class="field-row">
                <input type="text" v-model="item.url" class="form-control" placeholder="URL" /><span> = </span><input type="text" v-model="item.id" class="form-control" placeholder="ID" />
                <a href="#" class="dyn-remove-link" @click.prevent="removeUrlAssignment(idx)">[X]</a>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td><p>XML DTD/Schema</p></td>
          <td>
            <p>This is only required for SOAP and XML feeds. Define the XML schema to be sent.</p>
            <p><textarea v-model="localFeed.xmlDTD" class="form-control" rows="10" style="width:100%"></textarea></p>
          </td>
        </tr>
        <tr>
          <td><p>Success String</p></td>
          <td>
            <p>This is the smallest form of the success response from the receiving client's API spec.</p>
            <p><input type="text" v-model="localFeed.successString" class="form-control" maxlength="50" /></p>
          </td>
        </tr>
        <tr>
          <td><p>Daily Feed Limit</p></td>
          <td>
            <p>Leave blank for no limit (default). If a value is supplied here, the feed will stop sending records after the daily limit is reached.</p>
            <p><input type="number" v-model="localFeed.dailyLimit" class="form-control" min="0" /></p>
          </td>
        </tr>
        <tr>
          <td><p>Feed Delay</p></td>
          <td>
            <p>Leave blank for no delay (default). If a value is supplied here, records will sit in the queue for this number of minutes before being processed.</p>
            <p><input type="number" v-model="localFeed.delay" class="form-control" min="0" /></p> Minutes
            <p>
              <label class="radio-label"><input type="radio" v-model="localFeed.delayDump" value="0" /> Trickle dump delayed records based on actual timestamps (default)</label><br/>
              <label class="radio-label"><input type="radio" v-model="localFeed.delayDump" value="1" /> Mass dump all delayed records for the entire day</label>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Threshold Notifications</p></td>
          <td>
            <p>Send an email notification if we have not sent <input type="number" v-model="localFeed.notifyThresholdCount" class="form-control" min="0" style="width:80px;display:inline-block" /> leads by <input type="text" v-model="localFeed.notifyThresholdTime" class="form-control" placeholder="Example: 10:00AM" style="width:120px;display:inline-block" /> on</p>
            <p>
              <label v-for="(dayName, dayNum) in dayNames" :key="dayNum" class="checkbox-label">
                <input type="checkbox" :value="parseInt(dayNum)" v-model="localFeed.notifyThresholdDays" /> {{ dayName }}
              </label>
            </p>
            <p><strong>To disable notifications from being sent, set the lead count to zero or uncheck all day boxes.</strong></p>
          </td>
        </tr>
        <tr>
          <td><p>Revenue and Cost Per Lead (CPL)</p></td>
          <td>
            <p>RPL: <input type="number" v-model="localFeed.revenuePerLead" class="form-control" step="0.0001" min="0" style="width:80px;display:inline-block" /> CPL Override: <input type="number" v-model="localFeed.costPerLeadOverride" class="form-control" step="0.0001" min="0" style="width:80px;display:inline-block" /></p>
            <p>If a value is set for CPL Override (including a 0.00 amount), this will override the CPL set on the incoming feed. To use the default CPL from the incoming feed, leave this field completely blank.</p>
          </td>
        </tr>
        <tr v-if="localFeed.feedCategory === 'phone-preping'">
          <td><p>Cost Key (Ping)</p></td>
          <td>
            <p><input type="text" v-model="localFeed.costKey" class="form-control" maxlength="100" placeholder="e.g. cost, data.cpl, result.price" style="max-width: 300px;" /></p>
            <p>JSON key path in the outgoing ping server response that contains the cost. Use dot notation for nested keys (e.g. <code>data.cost</code>). When set, the lead is accepted only if the returned cost is at least 125% of the incoming feed cost; otherwise rejected. The returned cost is stored in outgoing feeds data.</p>
          </td>
        </tr>
        <tr>
          <td><p>Salesperson Override</p></td>
          <td>
            <p>By default, salesperson revenues are assigned at a company level. Only set this value if you are overriding the company-level salesperson with a feed-level salesperson.</p>
            <p>
              <select v-model="localFeed.salesperson" class="form-control">
                <option value="">Select a salesperson (optional)</option>
                <option v-for="user in staffUsers" :key="user.idUser" :value="user.idUser">{{ user.fullName }}</option>
              </select>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Launch Date</p></td>
          <td>
            <p><input type="date" v-model="localFeed.launchDate" class="form-control" /></p>
          </td>
        </tr>
        <tr>
          <td><p>Feed Status</p></td>
          <td>
            <p>
              <label class="radio-label"><input type="radio" v-model="localFeed.status" value="active" /> Active (Visible)</label><br/>
              <label class="radio-label"><input type="radio" v-model="localFeed.status" value="hidden" /> Active (Hidden)</label><br/>
              <label class="radio-label"><input type="radio" v-model="localFeed.status" value="retired" /> Retired</label>
            </p>
          </td>
        </tr>
        <tr>
          <td><p>Processing Schedule</p></td>
          <td>
            <p>By default, leads are passed to clients 24 hours a day, 7 days a week. To limit which days and/or times leads are passed, check off the days and input the times you would like leads to be passed. If you would like to pass leads for the entire day, you may leave both the start and end times blank, otherwise both must be filled in if restricted to certain times.</p>
            <table class="schedule-table">
              <tr>
                <td v-for="(dayName, dayKey) in scheduleDayNames" :key="dayKey">
                  <p style="text-transform:capitalize;">
                    <label class="checkbox-label">
                      <input type="checkbox" :checked="localFeed.processingSchedule[dayKey]?.enabled" @change="toggleScheduleDay(dayKey, $event.target.checked)" /> {{ dayName }}
                    </label>
                  </p>
                  <p>
                    <input type="text" v-model="localFeed.processingSchedule[dayKey].startTime" :disabled="!localFeed.processingSchedule[dayKey]?.enabled" placeholder="Start Time" class="form-control" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" />
                    <input type="text" v-model="localFeed.processingSchedule[dayKey].endTime" :disabled="!localFeed.processingSchedule[dayKey]?.enabled" placeholder="End Time" class="form-control" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" />
                  </p>
                </td>
              </tr>
            </table>
            <p>Enter start/end time in 24 hour format (HH:MM).</p>
          </td>
        </tr>
        <tr>
          <td><p>Cron (Pushing)</p></td>
          <td>
            <label class="checkbox-label">
              <input type="checkbox" :checked="localFeed.cron === '1'" @change="localFeed.cron = $event.target.checked ? '1' : '0'" />
              Enable cron/pushing
            </label>
            <div v-if="localFeed.cron === '1'" style="margin-top: 10px;">
              <label>Cron Timing (minutes):</label>
              <input type="number" v-model="localFeed.cronTiming" class="form-control" min="1" style="display: inline-block; width: 100px; margin-left: 10px;" />
            </div>
          </td>
        </tr>
        <tr>
          <td><p>Throttle</p></td>
          <td>
            <input type="number" v-model="localFeed.throttle" class="form-control" min="1" />
            <small>Number of leads to process per batch</small>
          </td>
        </tr>
      </table>
    </form>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';

let _uid = 0;
const nextId = () => `dyn-${Date.now()}-${++_uid}`;

export default {
  name: 'OutboundFeedForm',
  props: {
    feed: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    availableFields: { type: Array, default: () => [] },
    feedCategories: { type: Object, default: () => ({ email: 'Email', phone: 'Phone' }) },
    feedTypes: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    staffUsers: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
  },
  emits: ['update:feed'],
  setup(props, { emit }) {
    const prefix = computed(() => (props.isEdit ? 'edit' : 'new'));

    const defaultProcessingSchedule = () => ({
      sun: { enabled: true, startTime: '', endTime: '' },
      mon: { enabled: true, startTime: '', endTime: '' },
      tue: { enabled: true, startTime: '', endTime: '' },
      wed: { enabled: true, startTime: '', endTime: '' },
      thu: { enabled: true, startTime: '', endTime: '' },
      fri: { enabled: true, startTime: '', endTime: '' },
      sat: { enabled: true, startTime: '', endTime: '' },
    });

    const ensureId = (item) => ({ ...item, _id: item._id || nextId() });
    const normalizeArray = (arr, defaults) => (arr || []).map((x) => ensureId({ ...defaults, ...x }));

    const dayNames = { 0: 'Sun', 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat' };
    const scheduleDayNames = { sun: 'Sun', mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat' };

    const localFeed = ref({
      label: props.feed.label || '',
      description: props.feed.description || '',
      idCompany: props.feed.idCompany || '',
      feedType: props.feed.feedType || 'curlPOST',
      postUrl: props.feed.postUrl || '',
      timezone: props.feed.timezone || 'America/New_York',
      feedCategory: props.feed.feedCategory || 'email',
      responseType: props.feed.responseType || 'realtime',
      webhookSecret: props.feed.webhookSecret || '',
      status: props.feed.status || 'active',
      cron: props.feed.cron === '1' || props.feed.cron === true ? '1' : '0',
      cronTiming: props.feed.cronTiming || 1,
      successString: props.feed.successString || '',
      throttle: props.feed.throttle || 100,
      dailyLimit: props.feed.dailyLimit ?? '',
      delay: props.feed.delay ?? '',
      delayDump: props.feed.delayDump ? '1' : '0',
      notifyThresholdCount: props.feed.notifyThresholdCount ?? '0',
      notifyThresholdTime: props.feed.notifyThresholdTime || '',
      notifyThresholdDays: props.feed.notifyThresholdDays ? (Array.isArray(props.feed.notifyThresholdDays) ? [...props.feed.notifyThresholdDays] : props.feed.notifyThresholdDays.split(',').map(Number)) : [],
      revenuePerLead: props.feed.revenuePerLead ?? '',
      launchDate: props.feed.launchDate || '',
      costPerLeadOverride: props.feed.costPerLeadOverride ?? '',
      costKey: props.feed.costKey ?? '',
      salesperson: props.feed.salesperson ?? '',
      leadStatus: props.feed.leadStatus || '',
      staticFields: normalizeArray(props.feed.staticFields, { field: '', value: '' }),
      varFields: normalizeArray(props.feed.varFields, { field: '', map: '' }),
      valueMap: normalizeArray(props.feed.valueMap, { field: '', oldValue: '', newValue: '' }),
      urlassignments: normalizeArray(props.feed.urlassignments, { url: '', id: '' }),
      xmlDTD: props.feed.xmlDTD || '',
      processingSchedule: props.feed.processingSchedule ? { ...props.feed.processingSchedule } : defaultProcessingSchedule(),
      prepingEnabled: props.feed.prepingEnabled === true || props.feed.prepingEnabled === 1 || props.feed.prepingEnabled === '1' ? '1' : '0',
      prepingUrl: props.feed.prepingUrl || '',
      prepingHttpMethod: props.feed.prepingHttpMethod === 'GET' ? 'GET' : 'POST',
      prepingAuthType: ['none', 'bearer', 'basic'].includes(props.feed.prepingAuthType) ? props.feed.prepingAuthType : 'none',
      prepingAuthValue: props.feed.prepingAuthValue || '',
    });

    const staticFieldsList = computed(() => localFeed.value.staticFields || []);
    const varFieldsList = computed(() => localFeed.value.varFields || []);
    const valueMapList = computed(() => localFeed.value.valueMap || []);
    const urlassignmentsList = computed(() => localFeed.value.urlassignments || []);

    const isEmitting = ref(false);

    const emitUpdate = () => {
      isEmitting.value = true;
      emit('update:feed', { ...localFeed.value });
      setTimeout(() => { isEmitting.value = false; }, 0);
    };

    const addStaticField = () => {
      if (!localFeed.value.staticFields) localFeed.value.staticFields = [];
      localFeed.value.staticFields = [...localFeed.value.staticFields, ensureId({ field: '', value: '' })];
    };
    const removeStaticField = (idx) => {
      localFeed.value.staticFields = (localFeed.value.staticFields || []).filter((_, i) => i !== idx);
    };
    const addMappedField = () => {
      if (!localFeed.value.varFields) localFeed.value.varFields = [];
      localFeed.value.varFields = [...localFeed.value.varFields, ensureId({ field: '', map: '' })];
    };
    const removeMappedField = (idx) => {
      localFeed.value.varFields = (localFeed.value.varFields || []).filter((_, i) => i !== idx);
    };
    const addValueTranslation = () => {
      if (!localFeed.value.valueMap) localFeed.value.valueMap = [];
      localFeed.value.valueMap = [...localFeed.value.valueMap, ensureId({ field: '', oldValue: '', newValue: '' })];
    };
    const removeValueTranslation = (idx) => {
      localFeed.value.valueMap = (localFeed.value.valueMap || []).filter((_, i) => i !== idx);
    };
    const addUrlAssignment = () => {
      if (!localFeed.value.urlassignments) localFeed.value.urlassignments = [];
      localFeed.value.urlassignments = [...localFeed.value.urlassignments, ensureId({ url: '', id: '' })];
    };
    const removeUrlAssignment = (idx) => {
      localFeed.value.urlassignments = (localFeed.value.urlassignments || []).filter((_, i) => i !== idx);
    };
    const toggleScheduleDay = (dayKey, enabled) => {
      if (!localFeed.value.processingSchedule[dayKey]) {
        localFeed.value.processingSchedule[dayKey] = { enabled: true, startTime: '', endTime: '' };
      }
      localFeed.value.processingSchedule[dayKey].enabled = enabled;
    };

    watch(localFeed, () => {
      if (!isEmitting.value) emitUpdate();
    }, { deep: true });

    watch(() => props.feed, (newFeed) => {
      if (isEmitting.value) return;
      if (newFeed && Object.keys(newFeed).length > 0) {
        localFeed.value.label = newFeed.label || '';
        localFeed.value.description = newFeed.description || '';
        localFeed.value.idCompany = newFeed.idCompany || '';
        localFeed.value.feedType = newFeed.feedType || 'curlPOST';
        localFeed.value.postUrl = newFeed.postUrl || '';
        localFeed.value.timezone = newFeed.timezone || 'UTC';
        localFeed.value.feedCategory = newFeed.feedCategory || 'email';
        localFeed.value.responseType = newFeed.responseType || 'realtime';
        localFeed.value.webhookSecret = newFeed.webhookSecret || '';
        localFeed.value.status = newFeed.status || 'active';
        localFeed.value.cron = newFeed.cron === '1' || newFeed.cron === 1 || newFeed.cron === true ? '1' : '0';
        localFeed.value.cronTiming = newFeed.cronTiming || 1;
        localFeed.value.successString = newFeed.successString || '';
        localFeed.value.throttle = newFeed.throttle || 100;
        localFeed.value.dailyLimit = newFeed.dailyLimit != null ? String(newFeed.dailyLimit) : '';
        localFeed.value.delay = newFeed.delay != null ? String(newFeed.delay) : '';
        localFeed.value.delayDump = newFeed.delayDump ? '1' : '0';
        localFeed.value.notifyThresholdCount = newFeed.notifyThresholdCount != null ? String(newFeed.notifyThresholdCount) : '0';
        localFeed.value.notifyThresholdTime = newFeed.notifyThresholdTime || '';
        localFeed.value.notifyThresholdDays = newFeed.notifyThresholdDays ? (Array.isArray(newFeed.notifyThresholdDays) ? [...newFeed.notifyThresholdDays] : newFeed.notifyThresholdDays.split(',').map(Number)) : [];
        localFeed.value.revenuePerLead = newFeed.revenuePerLead != null ? String(newFeed.revenuePerLead) : '';
        localFeed.value.launchDate = newFeed.launchDate || '';
        localFeed.value.costPerLeadOverride = newFeed.costPerLeadOverride != null ? String(newFeed.costPerLeadOverride) : '';
        localFeed.value.costKey = newFeed.costKey ?? '';
        localFeed.value.salesperson = newFeed.salesperson != null ? String(newFeed.salesperson) : '';
        localFeed.value.leadStatus = newFeed.leadStatus || '';
        localFeed.value.staticFields = normalizeArray(newFeed.staticFields, { field: '', value: '' });
        localFeed.value.varFields = normalizeArray(newFeed.varFields, { field: '', map: '' });
        localFeed.value.valueMap = normalizeArray(newFeed.valueMap, { field: '', oldValue: '', newValue: '' });
        localFeed.value.urlassignments = normalizeArray(newFeed.urlassignments, { url: '', id: '' });
        localFeed.value.xmlDTD = newFeed.xmlDTD || '';
        localFeed.value.processingSchedule = newFeed.processingSchedule ? { ...newFeed.processingSchedule } : defaultProcessingSchedule();
        localFeed.value.prepingEnabled = newFeed.prepingEnabled === true || newFeed.prepingEnabled === 1 || newFeed.prepingEnabled === '1' ? '1' : '0';
        localFeed.value.prepingUrl = newFeed.prepingUrl || '';
        localFeed.value.prepingHttpMethod = newFeed.prepingHttpMethod === 'GET' ? 'GET' : 'POST';
        localFeed.value.prepingAuthType = ['none', 'bearer', 'basic'].includes(newFeed.prepingAuthType) ? newFeed.prepingAuthType : 'none';
        localFeed.value.prepingAuthValue = newFeed.prepingAuthValue || '';
      }
    }, { deep: true, immediate: true });

    return {
      prefix,
      localFeed,
      staticFieldsList,
      varFieldsList,
      valueMapList,
      urlassignmentsList,
      dayNames,
      scheduleDayNames,
      addStaticField,
      removeStaticField,
      addMappedField,
      removeMappedField,
      addValueTranslation,
      removeValueTranslation,
      addUrlAssignment,
      removeUrlAssignment,
      toggleScheduleDay,
    };
  },
};
</script>

<style scoped>
.outbound-feed-form table { width: 100%; }
.outbound-feed-form table td { padding: 8px; vertical-align: top; }
.outbound-feed-form table td:first-child { width: 180px; font-weight: bold; }
.input-long { width: 100%; max-width: 500px; }
.checkbox-label, .radio-label { display: inline-block; margin-right: 15px; margin-bottom: 5px; font-weight: normal; }
.checkbox-label input, .radio-label input { margin-right: 5px; }
.form-control { display: inline-block; }
small { display: block; color: #666; margin-top: 5px; }
.nonLink { color: #337ab7; cursor: pointer; text-decoration: none; }
.nonLink:hover { text-decoration: underline; }
.dyn-add-link { color: #337ab7; cursor: pointer; text-decoration: none; }
.dyn-add-link:hover { text-decoration: underline; }
.dyn-remove-link { color: #d9534f; cursor: pointer; text-decoration: none; margin-left: 8px; white-space: nowrap; flex-shrink: 0; }
.dyn-remove-link:hover { text-decoration: underline; }
.field-row {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  margin-bottom: 8px;
  gap: 6px;
}
.field-row input,
.field-row select {
  flex: 0 1 auto;
  min-width: 80px;
  max-width: 220px;
}
.field-row select { max-width: 240px; }
.field-row .form-control { display: inline-block; }
.field-row span { white-space: nowrap; flex-shrink: 0; }
.field-rows { overflow-x: auto; }
.schedule-table { width: 100%; }
.schedule-table td { width: 14%; padding: 5px; }
.schedule-table input { width: 100%; margin-bottom: 4px; }
</style>
