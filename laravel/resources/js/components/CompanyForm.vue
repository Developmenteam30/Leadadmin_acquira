<template>
  <div class="form-input">
    <form class="form-inline" :id="prefix + '_company'">
      <!-- Basic Information -->
      <div class="pnt-form-row">
        <label :for="prefix + 'name'">Company Name <span class="required">*</span></label>
        <input
          :id="prefix + 'name'"
          v-model="company.name"
          type="text"
          class="form-control"
          required
        />
      </div>

      <div v-if="isEdit" class="pnt-form-row">
        <label :for="prefix + 'status'">Status <span class="required">*</span></label>
        <select :id="prefix + 'status'" v-model="company.status" class="form-control" required>
          <option value="active">Active</option>
          <option value="hidden">Hidden</option>
          <option value="retired">Retired</option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'country'">Country</label>
        <select
          :id="prefix + 'country'"
          v-model="company.country"
          class="form-control"
          @change="handleCountryChange"
        >
          <option value="">Select a country</option>
          <option
            v-for="country in countries"
            :key="country.id"
            :value="country.id"
          >
            {{ country.name }}
          </option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'address'">Address</label>
        <input
          :id="prefix + 'address'"
          v-model="company.address"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'city'">City</label>
        <input
          :id="prefix + 'city'"
          v-model="company.city"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'state'">State/Province</label>
        <select
          v-if="company.country == 236"
          :id="prefix + 'state'"
          v-model="company.state"
          class="form-control"
          style="min-height: 40px;"
        >
          <option value="">Select state</option>
          <option
            v-for="(name, code) in usStates"
            :key="code"
            :value="code"
          >
            {{ name }}
          </option>
        </select>
        <input
          v-else
          :id="prefix + 'state'"
          v-model="company.state"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'zipcode'">Zip/Postal Code</label>
        <input
          :id="prefix + 'zipcode'"
          v-model="company.zipcode"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'url'">Web Site</label>
        <input
          :id="prefix + 'url'"
          v-model="company.url"
          type="url"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label>Company Type</label>
        <div class="checkbox-choices">
          <input
            type="checkbox"
            :id="prefix + 'companyType_isPublisher'"
            value="isPublisher"
            v-model="company.companyType"
          />
          <label class="no-formatting" :for="prefix + 'companyType_isPublisher'">Publisher / Affiliate</label>
          <br/>
          <input
            type="checkbox"
            :id="prefix + 'companyType_isAdvertiser'"
            value="isAdvertiser"
            v-model="company.companyType"
          />
          <label class="no-formatting" :for="prefix + 'companyType_isAdvertiser'">Advertiser</label>
          <br/>
          <input
            type="checkbox"
            :id="prefix + 'companyType_isCallCenter'"
            value="isCallCenter"
            v-model="company.companyType"
          />
          <label class="no-formatting" :for="prefix + 'companyType_isCallCenter'">Call Center</label>
        </div>
      </div>

      <div class="pnt-form-row">
        <label>Dialer Reporting View</label>
        <div class="checkbox-choices">
          <input
            type="radio"
            :id="prefix + 'dialer_report_type_billable'"
            value="billable"
            v-model="company.dialer_report_type"
          />
          <label class="no-formatting" :for="prefix + 'dialer_report_type_billable'">Client (Billable)</label>
          &nbsp;&nbsp;
          <input
            type="radio"
            :id="prefix + 'dialer_report_type_payable'"
            value="payable"
            v-model="company.dialer_report_type"
          />
          <label class="no-formatting" :for="prefix + 'dialer_report_type_payable'">Vendor (Payable)</label>
        </div>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'paymentTerms'">Payment Terms</label>
        <select
          :id="prefix + 'paymentTerms'"
          v-model="company.paymentTerms"
          class="form-control"
          style="min-height: 40px;"
        >
          <option value="">Select payment terms</option>
          <option value="prepay">PrePay</option>
          <option value="net7">Net 7</option>
          <option value="net14">Net 14</option>
          <option value="net30">Net 30</option>
          <option value="semimonthly">Semimonthly</option>
          <option value="monthly_net7">Monthly Net 7</option>
          <option value="monthly_net15">Monthly Net 15</option>
          <option value="monthly_net30">Monthly Net 30</option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'costPerLead'">Cost Per Lead</label>
        <input
          :id="prefix + 'costPerLead'"
          v-model="company.costPerLead"
          type="number"
          step="0.0001"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'accountManager'">Account Manager</label>
        <select
          :id="prefix + 'accountManager'"
          v-model="company.accountManager"
          class="form-control"
          style="min-height: 40px;"
        >
          <option value="">Select an account manager</option>
          <option
            v-for="user in staffUsers"
            :key="user.idUser"
            :value="user.idUser"
          >
            {{ user.fullName }}
          </option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'accountOpener'">Account Opener</label>
        <select
          :id="prefix + 'accountOpener'"
          v-model="company.accountOpener"
          class="form-control"
          style="min-height: 40px;"
        >
          <option value="">Select an account opener</option>
          <option
            v-for="user in staffUsers"
            :key="user.idUser"
            :value="user.idUser"
          >
            {{ user.fullName }}
          </option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'salesperson'">Sales Person</label>
        <select
          :id="prefix + 'salesperson'"
          v-model="company.salesperson"
          class="form-control"
          style="min-height: 40px;"
        >
          <option value="">Select a sales person</option>
          <option
            v-for="user in staffUsers"
            :key="user.idUser"
            :value="user.idUser"
          >
            {{ user.fullName }}
          </option>
        </select>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'note'">Notes</label>
        <textarea
          :id="prefix + 'note'"
          v-model="company.note"
          class="form-control"
        ></textarea>
      </div>

      <!-- Divisions -->
      <div class="pnt-form-row">
        <label>Divisions</label>
        <div class="divisions-with-button">
          <div class="checkbox-choices">
            <button
            type="button"
            class="btn btn-link btn-sm"
            @click="openAddDivisionModal"
          >
            + Add division
          </button>

            <div v-for="(name, id) in divisions" :key="id">
              <input
                type="checkbox"
                :id="prefix + 'divisions_' + id"
                :value="id"
                v-model="company.divisions"
              />
              <label class="no-formatting" :for="prefix + 'divisions_' + id">{{ name }}</label>
              <br/>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Division Modal -->
      <div
        class="modal fade"
        :id="prefix + 'AddDivisionModal'"
        tabindex="-1"
        role="dialog"
        aria-labelledby="addDivisionModalTitle"
      >
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeAddDivisionModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="addDivisionModalTitle">
                Add a new division
              </h4>
            </div>
            <div class="modal-body">
              <form @submit.prevent="handleAddDivision">
                <div class="form-group">
                  <label for="newDivisionName">Division Name</label>
                  <input
                    id="newDivisionName"
                    v-model="newDivision.name"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter division name"
                  />
                </div>
                <div v-if="addDivisionError" class="alert alert-danger">
                  {{ addDivisionError }}
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeAddDivisionModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleAddDivision"
                :disabled="addingDivision"
              >
                {{ addingDivision ? 'Adding...' : 'Add Division' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Verticals -->
      <div class="pnt-form-row">
        <label :for="prefix + 'verticals'">Verticals</label>
        <div class="verticals-with-button">
          <button
            type="button"
            class="btn btn-link btn-sm add-vertical-btn"
            style="
                margin-left: -115px;
                padding-top: 5px;
            "
            @click="openAddVerticalModal"
          >
            + Add vertical
          </button>
          <select
            :id="prefix + 'verticals'"
            v-model="company.verticals"
            class="form-control"
            style="width: 150px"
            multiple
          >
            <option
              v-for="vertical in allVerticals"
              :key="vertical.verticalId"
              :value="vertical.verticalId"
            >
              {{ vertical.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Add Vertical Modal -->
      <div
        class="modal fade"
        :id="prefix + 'AddVerticalModal'"
        tabindex="-1"
        role="dialog"
        aria-labelledby="addVerticalModalTitle"
      >
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeAddVerticalModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="addVerticalModalTitle">
                Add a new vertical
              </h4>
            </div>
            <div class="modal-body">
              <form @submit.prevent="handleAddVertical">
                <div class="form-group">
                  <label for="newVerticalDivisionId">Division</label>
                  <select
                    id="newVerticalDivisionId"
                    v-model="newVertical.divisionId"
                    class="form-control"
                    required
                  >
                    <option value="">Select a division</option>
                    <option
                      v-for="(name, id) in divisions"
                      :key="id"
                      :value="id"
                    >
                      {{ name }}
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="newVerticalName">Vertical Name</label>
                  <input
                    id="newVerticalName"
                    v-model="newVertical.name"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter vertical name"
                  />
                </div>
                <div v-if="addVerticalError" class="alert alert-danger">
                  {{ addVerticalError }}
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeAddVerticalModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleAddVertical"
                :disabled="addingVertical"
              >
                {{ addingVertical ? 'Adding...' : 'Add Vertical' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Contact -->
      <hr>
      <div class="pnt-form-row text-center">
        <label class="no-formatting" style="font-size: 24px; width: auto; text-align: left; margin: 10px 0 5px 0;">Main Contact</label>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'main_name'">Name</label>
        <input
          :id="prefix + 'main_name'"
          v-model="company.main_name"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'main_phone'">Phone Number</label>
        <input
          :id="prefix + 'main_phone'"
          v-model="company.main_phone"
          type="tel"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'main_email'">Email Address</label>
        <input
          :id="prefix + 'main_email'"
          v-model="company.main_email"
          type="email"
          class="form-control"
        />
      </div>

      <!-- Accounting Contact -->
      <hr>
      <div class="pnt-form-row text-center">
        <label class="no-formatting" style="font-size: 24px; width: auto; text-align: left; margin: 10px 0 5px 0;">Accounting Contact</label>
      </div>

      <div class="pnt-form-row">
        <label></label>
        <div class="checkbox-choices">
          <input
            type="checkbox"
            :id="prefix + 'acct_copy'"
            @change="handleCopyMain('acct')"
          />
          <label class="no-formatting" :for="prefix + 'acct_copy'">Copy info from Main Contact</label>
        </div>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'acct_name'">Name</label>
        <input
          :id="prefix + 'acct_name'"
          v-model="company.acct_name"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'acct_phone'">Phone Number</label>
        <input
          :id="prefix + 'acct_phone'"
          v-model="company.acct_phone"
          type="tel"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'acct_email'">Email Address</label>
        <input
          :id="prefix + 'acct_email'"
          v-model="company.acct_email"
          type="email"
          class="form-control"
        />
      </div>

      <!-- Technical Contact -->
      <hr>
      <div class="pnt-form-row text-center">
        <label class="no-formatting" style="font-size: 24px; width: auto; text-align: left; margin: 10px 0 5px 0;">Technical Contact</label>
      </div>

      <div class="pnt-form-row">
        <label></label>
        <div class="checkbox-choices">
          <input
            type="checkbox"
            :id="prefix + 'tech_copy'"
            @change="handleCopyMain('tech')"
          />
          <label class="no-formatting" :for="prefix + 'tech_copy'">Copy info from Main Contact</label>
        </div>
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'tech_name'">Name</label>
        <input
          :id="prefix + 'tech_name'"
          v-model="company.tech_name"
          type="text"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'tech_phone'">Phone Number</label>
        <input
          :id="prefix + 'tech_phone'"
          v-model="company.tech_phone"
          type="tel"
          class="form-control"
        />
      </div>

      <div class="pnt-form-row">
        <label :for="prefix + 'tech_email'">Email Address</label>
        <input
          :id="prefix + 'tech_email'"
          v-model="company.tech_email"
          type="email"
          class="form-control"
        />
      </div>
    </form>
  </div>
</template>

<script>
import { computed, ref, reactive } from 'vue';
import axios from 'axios';

export default {
  name: 'CompanyForm',
  props: {
    company: {
      type: Object,
      required: true,
    },
    countries: {
      type: Array,
      default: () => [],
    },
    staffUsers: {
      type: Array,
      default: () => [],
    },
    divisions: {
      type: Object,
      default: () => {},
    },
    verticalsByDivision: {
      type: Object,
      default: () => {},
    },
    allVerticals: {
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
  emits: ['copy-main-contact', 'vertical-added', 'division-added'],
  setup(props, { emit }) {
    const prefix = computed(() => (props.isEdit ? 'edit' : 'new'));
    const addingVertical = ref(false);
    const addVerticalError = ref('');
    const newVertical = reactive({
      divisionId: '',
      name: '',
    });
    const addingDivision = ref(false);
    const addDivisionError = ref('');
    const newDivision = reactive({
      name: '',
    });

    const openAddDivisionModal = () => {
      newDivision.name = '';
      addDivisionError.value = '';
      const modalId = prefix.value + 'AddDivisionModal';
      if (window.$ && window.$('#' + modalId)) {
        window.$('#' + modalId).modal('show');
      }
    };

    const closeAddDivisionModal = () => {
      const modalId = prefix.value + 'AddDivisionModal';
      if (window.$ && window.$('#' + modalId)) {
        window.$('#' + modalId).modal('hide');
      }
    };

    const handleAddDivision = async () => {
      if (!newDivision.name.trim()) {
        addDivisionError.value = 'Please enter a division name.';
        return;
      }

      addingDivision.value = true;
      addDivisionError.value = '';

      try {
        const response = await axios.post('/api/divisions', {
          name: newDivision.name.trim(),
        });

        if (response.data.status === 1) {
          const newDivisionData = response.data.data;
          closeAddDivisionModal();
          emit('division-added');
          // Auto-select the newly added division in the company form
          const divisionId = newDivisionData.divisionId;
          if (!props.company.divisions.includes(divisionId) && !props.company.divisions.includes(String(divisionId))) {
            props.company.divisions.push(divisionId);
          }
        } else {
          addDivisionError.value = response.data.error || 'Failed to add division.';
        }
      } catch (error) {
        addDivisionError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add division.';
      } finally {
        addingDivision.value = false;
      }
    };

    const openAddVerticalModal = () => {
      newVertical.divisionId = '';
      newVertical.name = '';
      addVerticalError.value = '';
      const modalId = prefix.value + 'AddVerticalModal';
      if (window.$ && window.$('#' + modalId)) {
        window.$('#' + modalId).modal('show');
      }
    };

    const closeAddVerticalModal = () => {
      const modalId = prefix.value + 'AddVerticalModal';
      if (window.$ && window.$('#' + modalId)) {
        window.$('#' + modalId).modal('hide');
      }
    };

    const handleAddVertical = async () => {
      if (!newVertical.divisionId || !newVertical.name.trim()) {
        addVerticalError.value = 'Please select a division and enter a vertical name.';
        return;
      }

      addingVertical.value = true;
      addVerticalError.value = '';

      try {
        const response = await axios.post('/api/verticals', {
          name: newVertical.name.trim(),
          divisionId: parseInt(newVertical.divisionId),
        });

        if (response.data.status === 1) {
          const newVerticalData = response.data.data;
          closeAddVerticalModal();
          emit('vertical-added');
          // Auto-select the newly added vertical in the company form
          if (!props.company.verticals.includes(newVerticalData.verticalId)) {
            props.company.verticals.push(newVerticalData.verticalId);
          }
        } else {
          addVerticalError.value = response.data.error || 'Failed to add vertical.';
        }
      } catch (error) {
        addVerticalError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add vertical.';
      } finally {
        addingVertical.value = false;
      }
    };

    const handleCountryChange = () => {
      // If country changes from US to non-US or vice versa, reset state
      // This is handled in the template with conditional rendering
    };

    const handleCopyMain = (target) => {
      emit('copy-main-contact', target);
      if (target === 'acct') {
        props.company.acct_name = props.company.main_name;
        props.company.acct_phone = props.company.main_phone;
        props.company.acct_email = props.company.main_email;
      } else if (target === 'tech') {
        props.company.tech_name = props.company.main_name;
        props.company.tech_phone = props.company.main_phone;
        props.company.tech_email = props.company.main_email;
      }
    };

    return {
      prefix,
      addingVertical,
      addVerticalError,
      newVertical,
      addingDivision,
      addDivisionError,
      newDivision,
      openAddVerticalModal,
      closeAddVerticalModal,
      handleAddVertical,
      openAddDivisionModal,
      closeAddDivisionModal,
      handleAddDivision,
      handleCountryChange,
      handleCopyMain,
    };
  },
};
</script>

<style scoped>
/* Match the original form-input styling */
.form-input {
  font-family: Verdana, Helvetica, sans-serif;
}

.form-input .pnt-form-row {
  margin-bottom: 0.5em;
}

.form-input label {
  display: inline-block;
  width: 150px;
  text-align: right;
  margin: 5px 10px;
  vertical-align: top;
}

.form-input label.no-formatting {
  width: auto;
  text-align: left;
  margin: 0;
  font-weight: normal;
  vertical-align: middle;
}

.form-input .checkbox-choices {
  display: inline-block;
  margin: 5px 0;
}

.form-input .checkbox-choices label.no-formatting {
  padding-left: 5px;
}

.form-input input[type='text'],
.form-input input[type='email'],
.form-input input[type='url'],
.form-input input[type='tel'],
.form-input input[type='number'] {
  width: 375px;
  font-family: Verdana, Helvetica, sans-serif;
}

.form-input select {
  width: 375px;
  font-family: Verdana, Helvetica, sans-serif;
  height: 26px;
}

.form-input textarea {
  width: 375px !important;
  height: 75px !important;
}

.form-input select[multiple] {
  height: auto;
  min-height: 100px;
}

.form-input .divisions-with-button {
  display: inline-flex;
  align-items: flex-start;
  gap: 10px;
}

.form-input .divisions-with-button .checkbox-choices {
  flex: 1;
}

.form-input .add-division-btn {
  flex-shrink: 0;
  margin-top: 0;
}

.form-input .verticals-with-button {
  display: inline-grid;
  align-items: flex-start;
  gap: 10px;
}

.form-input .verticals-with-button select {
  flex: 1;
  min-width: 200px;
}

.form-input .add-vertical-btn {
  flex-shrink: 0;
  margin-top: 0;
}

.form-input .required {
  color: red;
}

.form-input input[type='radio'],
.form-input input[type='checkbox'] {
  margin-right: 5px;
}

@media (max-width: 767px) {
  .form-input label {
    display: block;
    text-align: left;
    width: auto;
    margin: 5px 0;
  }

  .form-input label.no-formatting {
    display: inline-block;
  }

  .form-input textarea,
  .form-input input,
  .form-input select {
    width: 100% !important;
  }
}
</style>
