<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Companies</h2>

      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new company
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="resetFilters"
          style="margin-left: 10px;"
        >
          Reset All Filters
        </button>
      </p>

      <!-- Search Filters -->
      <div class="row" style="margin-bottom: 15px;">
        <div class="col-md-4">
          <div class="form-group">
            <label>Text Search</label>
            <input
              v-model="filters.searchText"
              type="text"
              class="form-control"
              placeholder="Search by name, note, or URL"
              @input="debounceSearch"
            />
          </div>
          <div class="form-group">
            <label>Account Manager</label>
            <select v-model="filters.searchAccountManager" class="form-control" @change="fetchCompanies">
              <option value="">All</option>
              <option
                v-for="user in staffUsers"
                :key="user.idUser"
                :value="user.idUser"
              >
                {{ user.fullName }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Division(s)</label>
            <div v-for="(name, id) in divisions" :key="id" class="checkbox pl-2">
              <label>
                <input
                  type="checkbox"
                  :value="id"
                  v-model="filters.searchDivisions"
                  @change="fetchCompanies"
                />
                {{ name }}
              </label>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Status</label>
            <select v-model="filters.searchStatus" class="form-control" @change="fetchCompanies">
              <option value="">All</option>
              <option value="active">Active companies</option>
              <option value="hidden">Hidden companies</option>
              <option value="retired">Retired companies</option>
            </select>
          </div>
          <div class="form-group">
            <label>Account Opener</label>
            <select v-model="filters.searchAccountOpener" class="form-control" @change="fetchCompanies">
              <option value="">All</option>
              <option
                v-for="user in staffUsers"
                :key="user.idUser"
                :value="user.idUser"
              >
                {{ user.fullName }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Verticals</label>
            <select
              v-model="filters.searchVerticals"
              class="form-control"
              multiple
              @change="fetchCompanies"
              style="height: 100px;"
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
        <div class="col-md-4">
          <div class="form-group">
            <label>Company Type</label>
            <select v-model="filters.searchCompanyType" class="form-control" @change="fetchCompanies">
              <option value="">All</option>
              <option value="isPublisher">Publisher / Affiliate</option>
              <option value="isAdvertiser">Advertiser</option>
              <option value="isCallCenter">Call Center</option>
            </select>
          </div>
          <div class="form-group">
            <label>Sales Person</label>
            <select v-model="filters.searchSalesperson" class="form-control" @change="fetchCompanies">
              <option value="">All</option>
              <option
                v-for="user in staffUsers"
                :key="user.idUser"
                :value="user.idUser"
              >
                {{ user.fullName }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <button
              type="button"
              class="btn btn-success"
              @click="fetchCompanies"
            >
              Search
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="companies.length === 0">
        <p>No companies found.</p>
      </div>

      <div v-else>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray header">
              <th>ID</th>
              <th>Name</th>
              <th class="hidden-xs">Note</th>
              <th>Options</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="company in companies" :key="company.idCompany">
              <td>{{ company.idCompany }}</td>
              <td>{{ company.name }}</td>
              <td class="hidden-xs">{{ company.note || '-' }}</td>
              <td class="text-center">
                <div class="btn-group">
                  <button
                    type="button"
                    class="btn btn-primary btn-xs"
                    @click="openEditModal(company)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary btn-xs dropdown-toggle"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                  >
                    <span class="caret"></span>
                    <span class="sr-only">Toggle Dropdown</span>
                  </button>
                  <ul class="dropdown-menu">
                    <li>
                      <a href="#" @click.prevent="openNotesModal(company)">Notes</a>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Add Company Modal -->
      <div
        class="modal fade"
        id="newCompanyModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newCompanyModalTitle"
      >
        <div class="modal-dialog modal-md" role="document">
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
              <h4 class="modal-title" id="newCompanyModalTitle">
                Add a new company
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <CompanyForm
                ref="addCompanyForm"
                :company="newCompany"
                :countries="countries"
                :staffUsers="staffUsers"
                :divisions="divisions"
                :verticalsByDivision="verticalsByDivision"
                :allVerticals="allVerticals"
                :usStates="usStates"
                @copy-main-contact="handleCopyMainContact"
                @vertical-added="handleVerticalAdded"
                @division-added="handleDivisionAdded"
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
                @click="handleAddCompany"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add Company' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Company Modal -->
      <div
        class="modal fade"
        id="editCompanyModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editCompanyModalTitle"
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
              <h4 class="modal-title" id="editCompanyModalTitle">
                Edit a company
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <CompanyForm
                ref="editCompanyForm"
                :company="editingCompany"
                :countries="countries"
                :staffUsers="staffUsers"
                :divisions="divisions"
                :verticalsByDivision="verticalsByDivision"
                :allVerticals="allVerticals"
                :usStates="usStates"
                :isEdit="true"
                @copy-main-contact="handleCopyMainContact"
                @vertical-added="handleVerticalAdded"
                @division-added="handleDivisionAdded"
              />
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
                @click="handleUpdateCompany"
                :disabled="updating"
              >
                {{ updating ? 'Saving...' : 'Save changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Company Notes Modal -->
      <div
        class="modal fade"
        id="companyNotesModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="companyNotesModalTitle"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeNotesModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="companyNotesModalTitle">
                Company Notes
              </h4>
            </div>
            <div class="modal-body">
              <div v-if="notesLoading">
                <p>Loading notes...</p>
              </div>
              <div v-else-if="companyNotes.length === 0">
                <p>No notes found for this company.</p>
              </div>
              <div v-else>
                <div
                  v-for="note in companyNotes"
                  :key="note.noteId"
                  style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd;"
                >
                  <p>
                    On <strong>{{ formatDate(note.timestamp) }}</strong> at
                    {{ formatTime(note.timestamp) }}, <strong>{{ note.fullName }}</strong> wrote:
                  </p>
                  <p style="white-space: pre-wrap;">{{ note.note }}</p>
                </div>
              </div>
              <div class="form-group" style="margin-top: 20px;">
                <label for="newNote">Add a new note</label>
                <textarea
                  id="newNote"
                  v-model="newNote"
                  class="form-control"
                  rows="4"
                  placeholder="Enter your note here"
                ></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeNotesModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleAddNote"
                :disabled="addingNote || !newNote.trim()"
              >
                {{ addingNote ? 'Adding...' : 'Add A New Note' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick, reactive } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';
import CompanyForm from './CompanyForm.vue';

export default {
  name: 'CompanyManagement',
  components: {
    Navigation,
    CompanyForm,
  },
  setup() {
    const companies = ref([]);
    const countries = ref([]);
    const staffUsers = ref([]);
    const divisions = ref({});
    const allVerticals = ref([]);
    const verticalsByDivision = ref({});
    const companyNotes = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const notesLoading = ref(false);
    const addingNote = ref(false);
    const addError = ref('');
    const editError = ref('');
    const newNote = ref('');
    const currentCompanyId = ref(null);
    const searchTimeout = ref(null);

    const filters = reactive({
      searchText: '',
      searchStatus: '',
      searchAccountManager: '',
      searchAccountOpener: '',
      searchCompanyType: '',
      searchSalesperson: '',
      searchDivisions: [],
      searchVerticals: [],
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

    const newCompany = reactive({
      name: '',
      url: '',
      note: '',
      address: '',
      city: '',
      state: '',
      zipcode: '',
      country: 236,
      companyType: [],
      dialer_report_type: 'billable',
      paymentTerms: '',
      costPerLead: '',
      accountManager: '',
      accountOpener: '',
      salesperson: '',
      divisions: [],
      verticals: [],
      main_name: '',
      main_phone: '',
      main_email: '',
      returns_name: '',
      returns_phone: '',
      returns_email: '',
      acct_name: '',
      acct_phone: '',
      acct_email: '',
      tech_name: '',
      tech_phone: '',
      tech_email: '',
    });

    const editingCompany = reactive({
      idCompany: null,
      name: '',
      url: '',
      note: '',
      address: '',
      city: '',
      state: '',
      zipcode: '',
      country: 236,
      status: 'active',
      companyType: [],
      dialer_report_type: 'billable',
      paymentTerms: '',
      costPerLead: '',
      accountManager: '',
      accountOpener: '',
      salesperson: '',
      divisions: [],
      verticals: [],
      main_name: '',
      main_phone: '',
      main_email: '',
      returns_name: '',
      returns_phone: '',
      returns_email: '',
      acct_name: '',
      acct_phone: '',
      acct_email: '',
      tech_name: '',
      tech_phone: '',
      tech_email: '',
    });

    const formatDate = (dateString) => {
      const date = new Date(dateString);
      const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return `${days[date.getDay()]}, ${months[date.getMonth()]} ${date.getDate()}${getOrdinal(date.getDate())}, ${date.getFullYear()}`;
    };

    const formatTime = (dateString) => {
      const date = new Date(dateString);
      let hours = date.getHours();
      const minutes = date.getMinutes();
      const ampm = hours >= 12 ? 'pm' : 'am';
      hours = hours % 12;
      hours = hours ? hours : 12;
      const minutesStr = minutes < 10 ? '0' + minutes : minutes;
      return `${hours}:${minutesStr}${ampm}`;
    };

    const getOrdinal = (n) => {
      const s = ['th', 'st', 'nd', 'rd'];
      const v = n % 100;
      return s[(v - 20) % 10] || s[v] || s[0];
    };

    const debounceSearch = () => {
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }
      searchTimeout.value = setTimeout(() => {
        fetchCompanies();
      }, 500);
    };

    const fetchCompanies = async () => {
      loading.value = true;
      try {
        const params = {};
        if (filters.searchText) params.searchText = filters.searchText;
        if (filters.searchStatus) params.status = filters.searchStatus;
        if (filters.searchAccountManager) params.searchAccountManager = filters.searchAccountManager;
        if (filters.searchAccountOpener) params.searchAccountOpener = filters.searchAccountOpener;
        if (filters.searchCompanyType) params.searchCompanyType = filters.searchCompanyType;
        if (filters.searchSalesperson) params.searchSalesperson = filters.searchSalesperson;
        if (filters.searchDivisions.length > 0) params.searchDivisions = filters.searchDivisions;
        if (filters.searchVerticals.length > 0) params.searchVerticals = filters.searchVerticals;

        const response = await axios.get('/api/companies', { params });
        if (response.data.status === 1) {
          companies.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching companies:', error);
        companies.value = [];
      } finally {
        loading.value = false;
      }
    };

    const fetchCountries = async () => {
      try {
        const response = await axios.get('/api/companies/countries');
        if (response.data.status === 1) {
          countries.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching countries:', error);
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

    const fetchDivisions = async () => {
      try {
        const response = await axios.get('/api/verticals/divisions');
        if (response.data.status === 1) {
          const divs = {};
          response.data.data.forEach((div) => {
            divs[div.divisionId] = div.name;
          });
          divisions.value = divs;
        }
      } catch (error) {
        console.error('Error fetching divisions:', error);
      }
    };

    const fetchVerticals = async () => {
      try {
        const response = await axios.get('/api/verticals');
        if (response.data.status === 1) {
          const verticalsByDiv = {};
          const allVerts = [];
          response.data.data.forEach((division) => {
            verticalsByDiv[division.name] = division.verticals || [];
            division.verticals.forEach((v) => {
              allVerts.push({
                verticalId: v.verticalId,
                name: v.name,
              });
            });
          });
          verticalsByDivision.value = verticalsByDiv;
          allVerticals.value = allVerts;
        }
      } catch (error) {
        console.error('Error fetching verticals:', error);
      }
    };

    const fetchCompanyNotes = async (companyId) => {
      notesLoading.value = true;
      try {
        const response = await axios.get(`/api/companies/${companyId}/notes`);
        if (response.data.status === 1) {
          companyNotes.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching company notes:', error);
        companyNotes.value = [];
      } finally {
        notesLoading.value = false;
      }
    };

    const resetFilters = () => {
      filters.searchText = '';
      filters.searchStatus = '';
      filters.searchAccountManager = '';
      filters.searchAccountOpener = '';
      filters.searchCompanyType = '';
      filters.searchSalesperson = '';
      filters.searchDivisions = [];
      filters.searchVerticals = [];
      fetchCompanies();
    };

    const openAddModal = async () => {
      Object.assign(newCompany, {
        name: '',
        url: '',
        note: '',
        address: '',
        city: '',
        state: '',
        zipcode: '',
        country: 236,
        companyType: [],
        dialer_report_type: 'billable',
        paymentTerms: '',
        costPerLead: '',
        accountManager: '',
        accountOpener: '',
        salesperson: '',
        divisions: [],
        verticals: [],
        main_name: '',
        main_phone: '',
        main_email: '',
        returns_name: '',
        returns_phone: '',
        returns_email: '',
        acct_name: '',
        acct_phone: '',
        acct_email: '',
        tech_name: '',
        tech_phone: '',
        tech_email: '',
      });
      addError.value = '';
      
      await nextTick();
      if (window.$ && window.$('#newCompanyModal')) {
        window.$('#newCompanyModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newCompanyModal')) {
        window.$('#newCompanyModal').modal('hide');
      }
    };

    const openEditModal = async (company) => {
      try {
        const response = await axios.get(`/api/companies/${company.idCompany}`);
        if (response.data.status === 1) {
          const data = response.data.data;
          
          // Determine company types
          const companyType = [];
          if (data.isPublisher) companyType.push('isPublisher');
          if (data.isAdvertiser) companyType.push('isAdvertiser');
          if (data.isCallCenter) companyType.push('isCallCenter');
          
          Object.assign(editingCompany, {
            idCompany: data.idCompany,
            name: data.name || '',
            url: data.url || '',
            note: data.note || '',
            address: data.address || '',
            city: data.city || '',
            state: data.state || '',
            zipcode: data.zipcode || '',
            country: data.country || 236,
            status: data.status || 'active',
            companyType: companyType,
            dialer_report_type: data.dialer_report_type || 'billable',
            paymentTerms: data.paymentTerms || '',
            costPerLead: data.costPerLead || '',
            accountManager: data.accountManager || '',
            accountOpener: data.accountOpener || '',
            salesperson: data.salesperson || '',
            divisions: data.divisions || [],
            verticals: data.verticals || [],
            main_name: data.main_name || '',
            main_phone: data.main_phone || '',
            main_email: data.main_email || '',
            returns_name: data.returns_name || '',
            returns_phone: data.returns_phone || '',
            returns_email: data.returns_email || '',
            acct_name: data.acct_name || '',
            acct_phone: data.acct_phone || '',
            acct_email: data.acct_email || '',
            tech_name: data.tech_name || '',
            tech_phone: data.tech_phone || '',
            tech_email: data.tech_email || '',
          });
          editError.value = '';
          
          await nextTick();
          if (window.$ && window.$('#editCompanyModal')) {
            window.$('#editCompanyModal').modal('show');
          }
        }
      } catch (error) {
        console.error('Error fetching company:', error);
        editError.value = 'Unable to load company data.';
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      if (window.$ && window.$('#editCompanyModal')) {
        window.$('#editCompanyModal').modal('hide');
      }
    };

    const openNotesModal = async (company) => {
      currentCompanyId.value = company.idCompany;
      newNote.value = '';
      await fetchCompanyNotes(company.idCompany);
      
      await nextTick();
      if (window.$ && window.$('#companyNotesModal')) {
        window.$('#companyNotesModal').modal('show');
      }
    };

    const closeNotesModal = () => {
      if (window.$ && window.$('#companyNotesModal')) {
        window.$('#companyNotesModal').modal('hide');
      }
    };

    const handleVerticalAdded = async () => {
      await fetchVerticals();
    };

    const handleDivisionAdded = async () => {
      await fetchDivisions();
    };

    const handleCopyMainContact = (target) => {
      if (target === 'returns') {
        newCompany.returns_name = newCompany.main_name;
        newCompany.returns_phone = newCompany.main_phone;
        newCompany.returns_email = newCompany.main_email;
      } else if (target === 'acct') {
        newCompany.acct_name = newCompany.main_name;
        newCompany.acct_phone = newCompany.main_phone;
        newCompany.acct_email = newCompany.main_email;
      } else if (target === 'tech') {
        newCompany.tech_name = newCompany.main_name;
        newCompany.tech_phone = newCompany.main_phone;
        newCompany.tech_email = newCompany.main_email;
      }
    };

    const handleAddCompany = async () => {
      if (!newCompany.name.trim()) {
        addError.value = 'Company name cannot be blank.';
        return;
      }

      adding.value = true;
      addError.value = '';

      try {
        const payload = {
          name: newCompany.name.trim(),
          url: newCompany.url.trim() || null,
          note: newCompany.note.trim() || null,
          address: newCompany.address.trim() || null,
          city: newCompany.city.trim() || null,
          state: newCompany.state.trim() || null,
          zipcode: newCompany.zipcode.trim() || null,
          country: newCompany.country,
          companyType: newCompany.companyType,
          dialer_report_type: newCompany.dialer_report_type,
          paymentTerms: newCompany.paymentTerms.trim() || null,
          costPerLead: newCompany.costPerLead ? parseFloat(newCompany.costPerLead) : null,
          accountManager: newCompany.accountManager ? parseInt(newCompany.accountManager) : null,
          accountOpener: newCompany.accountOpener ? parseInt(newCompany.accountOpener) : null,
          salesperson: newCompany.salesperson ? parseInt(newCompany.salesperson) : null,
          divisions: newCompany.divisions.map((d) => parseInt(d)),
          verticals: newCompany.verticals.map((v) => parseInt(v)),
          main_name: newCompany.main_name.trim() || null,
          main_phone: newCompany.main_phone.trim() || null,
          main_email: newCompany.main_email.trim() || null,
          returns_name: newCompany.returns_name.trim() || null,
          returns_phone: newCompany.returns_phone.trim() || null,
          returns_email: newCompany.returns_email.trim() || null,
          acct_name: newCompany.acct_name.trim() || null,
          acct_phone: newCompany.acct_phone.trim() || null,
          acct_email: newCompany.acct_email.trim() || null,
          tech_name: newCompany.tech_name.trim() || null,
          tech_phone: newCompany.tech_phone.trim() || null,
          tech_email: newCompany.tech_email.trim() || null,
        };

        const response = await axios.post('/api/companies', payload);

        if (response.data.status === 1) {
          closeAddModal();
          await fetchCompanies();
        } else {
          addError.value = response.data.error || 'Failed to add company.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add company.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateCompany = async () => {
      if (!editingCompany.name.trim()) {
        editError.value = 'Company name cannot be blank.';
        return;
      }

      updating.value = true;
      editError.value = '';

      try {
        const payload = {
          name: editingCompany.name.trim(),
          url: editingCompany.url.trim() || null,
          note: editingCompany.note.trim() || null,
          address: editingCompany.address.trim() || null,
          city: editingCompany.city.trim() || null,
          state: editingCompany.state.trim() || null,
          zipcode: editingCompany.zipcode.trim() || null,
          country: editingCompany.country,
          status: editingCompany.status,
          companyType: editingCompany.companyType,
          dialer_report_type: editingCompany.dialer_report_type,
          paymentTerms: editingCompany.paymentTerms.trim() || null,
          costPerLead: editingCompany.costPerLead ? parseFloat(editingCompany.costPerLead) : null,
          accountManager: editingCompany.accountManager ? parseInt(editingCompany.accountManager) : null,
          accountOpener: editingCompany.accountOpener ? parseInt(editingCompany.accountOpener) : null,
          salesperson: editingCompany.salesperson ? parseInt(editingCompany.salesperson) : null,
          divisions: editingCompany.divisions.map((d) => parseInt(d)),
          verticals: editingCompany.verticals.map((v) => parseInt(v)),
          main_name: editingCompany.main_name.trim() || null,
          main_phone: editingCompany.main_phone.trim() || null,
          main_email: editingCompany.main_email.trim() || null,
          returns_name: editingCompany.returns_name.trim() || null,
          returns_phone: editingCompany.returns_phone.trim() || null,
          returns_email: editingCompany.returns_email.trim() || null,
          acct_name: editingCompany.acct_name.trim() || null,
          acct_phone: editingCompany.acct_phone.trim() || null,
          acct_email: editingCompany.acct_email.trim() || null,
          tech_name: editingCompany.tech_name.trim() || null,
          tech_phone: editingCompany.tech_phone.trim() || null,
          tech_email: editingCompany.tech_email.trim() || null,
        };

        const response = await axios.put(
          `/api/companies/${editingCompany.idCompany}`,
          payload
        );

        if (response.data.status === 1) {
          closeEditModal();
          await fetchCompanies();
        } else {
          editError.value = response.data.error || 'Failed to update company.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update company.';
      } finally {
        updating.value = false;
      }
    };

    const handleAddNote = async () => {
      if (!newNote.value.trim() || !currentCompanyId.value) {
        return;
      }

      addingNote.value = true;
      try {
        const response = await axios.post(`/api/companies/${currentCompanyId.value}/notes`, {
          note: newNote.value.trim(),
        });

        if (response.data.status === 1) {
          newNote.value = '';
          await fetchCompanyNotes(currentCompanyId.value);
        }
      } catch (error) {
        console.error('Error adding note:', error);
      } finally {
        addingNote.value = false;
      }
    };

    onMounted(async () => {
      await fetchCountries();
      await fetchStaffUsers();
      await fetchDivisions();
      await fetchVerticals();
      await fetchCompanies();
    });

    return {
      companies,
      countries,
      staffUsers,
      divisions,
      allVerticals,
      verticalsByDivision,
      companyNotes,
      loading,
      adding,
      updating,
      notesLoading,
      addingNote,
      addError,
      editError,
      newNote,
      currentCompanyId,
      filters,
      usStates,
      newCompany,
      editingCompany,
      formatDate,
      formatTime,
      debounceSearch,
      resetFilters,
      fetchCompanies,
      openAddModal,
      closeAddModal,
      openEditModal,
      closeEditModal,
      openNotesModal,
      closeNotesModal,
      handleVerticalAdded,
      handleDivisionAdded,
      handleCopyMainContact,
      handleAddCompany,
      handleUpdateCompany,
      handleAddNote,
    };
  },
};
</script>

<style scoped>
.pl-2 {
  padding-left: 20px;
}
.table th {
  background-color: #072f5f !important;
  color: #fff !important;
  text-align: center;
  vertical-align: middle;
}

.table.table-bordered {
  border: 1px solid #ddd;
}

.table.table-striped > tbody > tr:nth-of-type(odd) {
  background-color: #f9f9f9;
}

.bgGray.header {
  background-color: #072f5f !important;
  color: #fff !important;
}

.btn-primary {
  background-color: #429038;
  border-color: #429038;
}

.btn-primary:hover,
.btn-primary:focus {
  background-color: #357a2e;
  border-color: #357a2e;
}

.btn-success {
  background-color: #429038;
  border-color: #429038;
}

.btn-success:hover,
.btn-success:focus {
  background-color: #357a2e;
  border-color: #357a2e;
}

.modal-header {
  background: #072f5f;
  color: #fff;
}

.modal-header .close {
  color: #fff;
  opacity: 0.6;
}

.modal-header .close:hover {
  opacity: 1;
}

.checkbox {
  margin-top: 0;
  margin-bottom: 5px;
}

.checkbox label {
  font-weight: normal;
  padding-left: 5px;
}

@media (max-width: 767px) {
  .hidden-xs {
    display: none !important;
  }
}
</style>
