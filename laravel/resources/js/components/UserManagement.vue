<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>User Management</h2>

      <div class="pull-right" style="margin-bottom: 15px;">
        <select
          id="status"
          v-model="userStatus"
          @change="fetchUsers"
          class="form-control"
          style="display: inline-block; width: auto;"
        >
          <option value="active">Show active users</option>
          <option value="archived">Show archived users</option>
          <option value="all">Show all users</option>
        </select>
      </div>

      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new user
        </button>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="users.length === 0">
        <p>No users found.</p>
      </div>

      <div v-else>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray header">
              <th>Username</th>
              <th>Full Name</th>
              <th>Access Flags</th>
              <th>Email Notifications</th>
              <th>Company Id</th>
              <th>Options</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.idUser">
              <td>{{ user.username }}</td>
              <td>{{ user.fullName || '-' }}</td>
              <td>
                <span v-for="(level, index) in user.accessLevels" :key="index">
                  {{ level }}<br v-if="index < user.accessLevels.length - 1" />
                </span>
                <span v-if="user.accessLevels.length === 0">-</span>
              </td>
              <td>
                <span v-for="(notif, index) in user.emailNotifications" :key="index">
                  {{ notif }}<br v-if="index < user.emailNotifications.length - 1" />
                </span>
                <span v-if="user.emailNotifications.length === 0">-</span>
              </td>
              <td>{{ user.companyName || '-' }}</td>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-primary btn-xs"
                  @click="openEditModal(user)"
                >
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Add User Modal -->
      <div
        class="modal fade"
        id="newUserModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newUserModalTitle"
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
              <h4 class="modal-title" id="newUserModalTitle">
                Add a new user
              </h4>
            </div>
            <div class="modal-body">
              <form id="newUserForm" @submit.prevent="handleAddUser">
                <div class="form-group">
                  <label for="newUsername">Username</label>
                  <input
                    id="newUsername"
                    v-model="newUser.username"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter username (alphanumeric only)"
                  />
                </div>
                <div class="form-group">
                  <label for="newPassword">Password (8 chars minimum)</label>
                  <input
                    id="newPassword"
                    v-model="newUser.password"
                    type="text"
                    class="form-control"
                    required
                    :placeholder="generatedPassword"
                    minlength="8"
                  />
                  <button
                    type="button"
                    class="btn btn-sm btn-default"
                    @click="generatePassword"
                    style="margin-top: 5px;"
                  >
                    Generate Password
                  </button>
                </div>
                <div class="form-group">
                  <label for="newFullName">Full Name</label>
                  <input
                    id="newFullName"
                    v-model="newUser.fullName"
                    type="text"
                    class="form-control"
                    placeholder="Enter full name (optional)"
                  />
                </div>
                <div class="form-group">
                  <label for="newEmail">Email Address</label>
                  <input
                    id="newEmail"
                    v-model="newUser.email"
                    type="email"
                    class="form-control"
                    placeholder="Enter email address (optional)"
                  />
                </div>
                <div class="form-group">
                  <label>Access Flags</label>
                  <div v-for="(label, bit) in accessBits" :key="bit" class="checkbox pl-2">
                    <label>
                      <input
                        type="checkbox"
                        :value="bit"
                        v-model="newUser.accessBits"
                      />
                      {{ label }}
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Email Notifications</label>
                  <div v-for="(label, bit) in emailBits" :key="bit" class="checkbox pl-2">
                    <label>
                      <input
                        type="checkbox"
                        :value="bit"
                        v-model="newUser.emailBits"
                      />
                      {{ label }}
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label for="newIdCompany">Company Access</label>
                  <select
                    id="newIdCompany"
                    v-model="newUser.idCompany"
                    class="form-control"
                  >
                    <option value="">Select a company (optional)</option>
                    <option
                      v-for="company in companies"
                      :key="company.idCompany"
                      :value="company.idCompany"
                    >
                      {{ company.name }}
                    </option>
                  </select>
                </div>
                <div v-if="addError" class="alert alert-danger">
                  {{ addError }}
                </div>
              </form>
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
                @click="handleAddUser"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add User' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit User Modal -->
      <div
        class="modal fade"
        id="editUserModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editUserModalTitle"
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
              <h4 class="modal-title" id="editUserModalTitle">
                Edit a user
              </h4>
            </div>
            <div class="modal-body">
              <form id="editUserForm" @submit.prevent="handleUpdateUser">
                <div class="form-group">
                  <label>Username</label>
                  <input
                    type="text"
                    class="form-control"
                    :value="editingUser.username"
                    disabled
                  />
                </div>
                <div class="form-group">
                  <label for="editPassword">Password (8 chars minimum)</label>
                  <input
                    id="editPassword"
                    v-model="editingUser.password"
                    type="text"
                    class="form-control"
                    placeholder="Leave blank to keep current password"
                    minlength="8"
                  />
                </div>
                <div class="form-group">
                  <label for="editFullName">Full Name</label>
                  <input
                    id="editFullName"
                    v-model="editingUser.fullName"
                    type="text"
                    class="form-control"
                    placeholder="Enter full name (optional)"
                  />
                </div>
                <div class="form-group">
                  <label for="editEmail">Email Address</label>
                  <input
                    id="editEmail"
                    v-model="editingUser.email"
                    type="email"
                    class="form-control"
                    placeholder="Enter email address (optional)"
                  />
                </div>
                <div class="form-group">
                  <label>Access Flags</label>
                  <div v-for="(label, bit) in accessBits" :key="bit" class="checkbox">
                    <label>
                      <input
                        type="checkbox"
                        :value="bit"
                        v-model="editingUser.accessBits"
                      />
                      {{ label }}
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Email Notifications</label>
                  <div v-for="(label, bit) in emailBits" :key="bit" class="checkbox">
                    <label>
                      <input
                        type="checkbox"
                        :value="bit"
                        v-model="editingUser.emailBits"
                      />
                      {{ label }}
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label for="editIdCompany">Company Access</label>
                  <select
                    id="editIdCompany"
                    v-model="editingUser.idCompany"
                    class="form-control"
                  >
                    <option value="">Select a company (optional)</option>
                    <option
                      v-for="company in companies"
                      :key="company.idCompany"
                      :value="company.idCompany"
                    >
                      {{ company.name }}
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <div class="checkbox">
                    <label>
                      <input
                        type="checkbox"
                        v-model="editingUser.isArchived"
                      />
                      Archive this user
                    </label>
                  </div>
                </div>
                <div v-if="editError" class="alert alert-danger">
                  {{ editError }}
                </div>
              </form>
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
                @click="handleUpdateUser"
                :disabled="updating"
              >
                {{ updating ? 'Saving...' : 'Save changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';

export default {
  name: 'UserManagement',
  components: {
    Navigation,
  },
  setup() {
    const users = ref([]);
    const companies = ref([]);
    const accessBits = ref({});
    const emailBits = ref({});
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');
    const userStatus = ref('active');
    const generatedPassword = ref('');

    const newUser = ref({
      username: '',
      password: '',
      fullName: '',
      email: '',
      accessBits: [],
      emailBits: [],
      idCompany: '',
    });

    const editingUser = ref({
      idUser: null,
      username: '',
      password: '',
      fullName: '',
      email: '',
      accessBits: [],
      emailBits: [],
      idCompany: '',
      isArchived: false,
    });

    const generatePassword = () => {
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let password = '';
      for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      newUser.value.password = password;
      generatedPassword.value = password;
    };

    const fetchUsers = async () => {
      loading.value = true;
      try {
        const response = await axios.get('/api/users', {
          params: { status: userStatus.value },
        });
        if (response.data.status === 1) {
          users.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching users:', error);
        users.value = [];
      } finally {
        loading.value = false;
      }
    };

    const fetchCompanies = async () => {
      try {
        const response = await axios.get('/api/companies');
        if (response.data.status === 1) {
          companies.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching companies:', error);
      }
    };

    const fetchBits = async () => {
      try {
        const response = await axios.get('/api/users/bits');
        if (response.data.status === 1) {
          accessBits.value = response.data.data.accessBits || {};
          emailBits.value = response.data.data.emailBits || {};
        }
      } catch (error) {
        console.error('Error fetching bits:', error);
      }
    };

    const openAddModal = async () => {
      newUser.value = {
        username: '',
        password: '',
        fullName: '',
        email: '',
        accessBits: [],
        emailBits: [],
        idCompany: '',
      };
      addError.value = '';
      generatePassword();
      
      await nextTick();
      if (window.$ && window.$('#newUserModal')) {
        window.$('#newUserModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newUserModal')) {
        window.$('#newUserModal').modal('hide');
      }
    };

    const openEditModal = async (user) => {
      try {
        const response = await axios.get(`/api/users/${user.idUser}`);
        if (response.data.status === 1) {
          const userData = response.data.data;
          
          // Convert accessBits and emailBits to arrays
          const selectedAccessBits = [];
          const selectedEmailBits = [];
          
          Object.keys(accessBits.value).forEach((bit) => {
            if ((userData.accessBits & parseInt(bit)) === parseInt(bit)) {
              selectedAccessBits.push(bit);
            }
          });
          
          Object.keys(emailBits.value).forEach((bit) => {
            if ((userData.emailBits & parseInt(bit)) === parseInt(bit)) {
              selectedEmailBits.push(bit);
            }
          });
          
          editingUser.value = {
            idUser: userData.idUser,
            username: userData.username,
            password: '',
            fullName: userData.fullName || '',
            email: userData.email || '',
            accessBits: selectedAccessBits,
            emailBits: selectedEmailBits,
            idCompany: userData.idCompany || '',
            isArchived: userData.isArchived || false,
          };
          editError.value = '';
          
          await nextTick();
          if (window.$ && window.$('#editUserModal')) {
            window.$('#editUserModal').modal('show');
          }
        }
      } catch (error) {
        console.error('Error fetching user:', error);
        editError.value = 'Unable to load user data.';
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      if (window.$ && window.$('#editUserModal')) {
        window.$('#editUserModal').modal('hide');
      }
    };

    const handleAddUser = async () => {
      if (!newUser.value.username.trim() || !newUser.value.password.trim()) {
        addError.value = 'Please fill in all required fields.';
        return;
      }

      if (newUser.value.password.length < 8) {
        addError.value = 'Password must be at least 8 characters.';
        return;
      }

      if (newUser.value.accessBits.length === 0) {
        addError.value = 'Please select at least one access flag.';
        return;
      }

      adding.value = true;
      addError.value = '';

      try {
        const payload = {
          username: newUser.value.username.trim(),
          password: newUser.value.password.trim(),
          fullName: newUser.value.fullName.trim() || null,
          email: newUser.value.email.trim() || null,
          accessBits: newUser.value.accessBits.map((b) => parseInt(b)),
          emailBits: newUser.value.emailBits.map((b) => parseInt(b)),
          idCompany: newUser.value.idCompany ? parseInt(newUser.value.idCompany) : null,
        };

        const response = await axios.post('/api/users', payload);

        if (response.data.status === 1) {
          closeAddModal();
          await fetchUsers();
        } else {
          addError.value = response.data.error || 'Failed to add user.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add user.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateUser = async () => {
      if (editingUser.value.password && editingUser.value.password.length < 8) {
        editError.value = 'Password must be at least 8 characters.';
        return;
      }

      if (editingUser.value.accessBits.length === 0 && !editingUser.value.isArchived) {
        editError.value = 'Please select at least one access flag.';
        return;
      }

      updating.value = true;
      editError.value = '';

      try {
        const payload = {
          password: editingUser.value.password.trim() || null,
          fullName: editingUser.value.fullName.trim() || null,
          email: editingUser.value.email.trim() || null,
          accessBits: editingUser.value.accessBits.map((b) => parseInt(b)),
          emailBits: editingUser.value.emailBits.map((b) => parseInt(b)),
          idCompany: editingUser.value.idCompany ? parseInt(editingUser.value.idCompany) : null,
          isArchived: editingUser.value.isArchived,
        };

        const response = await axios.put(
          `/api/users/${editingUser.value.idUser}`,
          payload
        );

        if (response.data.status === 1) {
          closeEditModal();
          await fetchUsers();
        } else {
          editError.value = response.data.error || 'Failed to update user.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update user.';
      } finally {
        updating.value = false;
      }
    };

    onMounted(async () => {
      await fetchBits();
      await fetchCompanies();
      await fetchUsers();
    });

    return {
      users,
      companies,
      accessBits,
      emailBits,
      loading,
      adding,
      updating,
      addError,
      editError,
      userStatus,
      generatedPassword,
      newUser,
      editingUser,
      generatePassword,
      fetchUsers,
      openAddModal,
      closeAddModal,
      openEditModal,
      closeEditModal,
      handleAddUser,
      handleUpdateUser,
    };
  },
};
</script>

<style scoped>
.table th {
  background-color: #072f5f !important;
  color: #fff !important;
  text-align: center;
  vertical-align: middle;
}

.pl-2 {
  padding-left: 20px;
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
</style>
