<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Vertical Management</h2>

      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new vertical
        </button>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="divisions.length === 0">
        <p>No divisions exist in the database.</p>
      </div>

      <div v-else>
        <div v-for="division in divisions" :key="division.divisionId" class="division-section">
          <h3>{{ division.name }}</h3>
          <table class="table table-bordered table-condensed table-striped">
            <thead>
              <tr class="bgGray header">
                <th>Vertical Name</th>
                <th>Options</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="division.verticals.length === 0">
                <td colspan="2" class="text-center">No verticals in this division</td>
              </tr>
              <tr
                v-for="vertical in division.verticals"
                :key="vertical.verticalId"
              >
                <td>{{ vertical.name }}</td>
                <td class="text-center">
                  <button
                    type="button"
                    class="btn btn-primary btn-xs"
                    @click="openEditModal(vertical)"
                  >
                    Edit
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Vertical Modal -->
      <div
        class="modal fade"
        id="newVerticalModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newVerticalModalTitle"
      >
        <div class="modal-dialog" role="document">
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
              <h4 class="modal-title" id="newVerticalModalTitle">
                Add a new vertical
              </h4>
            </div>
            <div class="modal-body">
              <form id="newVerticalForm" @submit.prevent="handleAddVertical">
                <div class="form-group">
                  <label for="newDivisionId">
                    Division
                    <button
                      type="button"
                      class="btn btn-link btn-sm"
                      style="padding: 0; margin-left: 5px; font-size: 12px;"
                      @click="openAddDivisionModal"
                    >
                      + Add Division
                    </button>
                  </label>
                  <select
                    id="newDivisionId"
                    v-model="newVertical.divisionId"
                    class="form-control"
                    required
                  >
                    <option value="">Select a division</option>
                    <option
                      v-for="div in allDivisions"
                      :key="div.divisionId"
                      :value="div.divisionId"
                    >
                      {{ div.name }}
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
                @click="handleAddVertical"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add Vertical' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Vertical Modal -->
      <div
        class="modal fade"
        id="editVerticalModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editVerticalModalTitle"
      >
        <div class="modal-dialog" role="document">
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
              <h4 class="modal-title" id="editVerticalModalTitle">
                Edit a vertical
              </h4>
            </div>
            <div class="modal-body">
              <form id="editVerticalForm" @submit.prevent="handleUpdateVertical">
                <div class="form-group">
                  <label>Division Name</label>
                  <input
                    type="text"
                    class="form-control"
                    :value="editingVertical.divisionName"
                    disabled
                  />
                </div>
                <div class="form-group">
                  <label for="editVerticalName">Vertical Name</label>
                  <input
                    id="editVerticalName"
                    v-model="editingVertical.name"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter vertical name"
                  />
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
                @click="handleUpdateVertical"
                :disabled="updating"
              >
                {{ updating ? 'Saving...' : 'Save changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Division Modal -->
      <div
        class="modal fade"
        id="addDivisionModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="addDivisionModalTitle"
        data-backdrop="static"
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
              <form id="addDivisionForm" @submit.prevent="handleAddDivision">
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
                <div v-if="divisionError" class="alert alert-danger">
                  {{ divisionError }}
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
    </div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';

export default {
  name: 'VerticalManagement',
  components: {
    Navigation,
  },
  setup() {
    const divisions = ref([]);
    const allDivisions = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');
    const addingDivision = ref(false);
    const divisionError = ref('');

    const newVertical = ref({
      divisionId: '',
      name: '',
    });

    const editingVertical = ref({
      verticalId: null,
      name: '',
      divisionId: null,
      divisionName: '',
    });

    const newDivision = ref({
      name: '',
    });

    const fetchVerticals = async () => {
      loading.value = true;
      try {
        const response = await axios.get('/api/verticals');
        if (response.data.status === 1) {
          divisions.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching verticals:', error);
        divisions.value = [];
      } finally {
        loading.value = false;
      }
    };

    const fetchDivisions = async () => {
      try {
        const response = await axios.get('/api/verticals/divisions');
        if (response.data.status === 1) {
          allDivisions.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching divisions:', error);
      }
    };

    const openAddModal = async () => {
      newVertical.value = {
        divisionId: '',
        name: '',
      };
      addError.value = '';
      
      await nextTick();
      // Use Bootstrap's modal API
      if (window.$ && window.$('#newVerticalModal')) {
        window.$('#newVerticalModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newVerticalModal')) {
        window.$('#newVerticalModal').modal('hide');
      }
    };

    const openEditModal = async (vertical) => {
      try {
        const response = await axios.get(`/api/verticals/${vertical.verticalId}`);
        if (response.data.status === 1) {
          editingVertical.value = {
            verticalId: response.data.data.verticalId,
            name: response.data.data.name,
            divisionId: response.data.data.divisionId,
            divisionName: response.data.data.divisionName,
          };
          editError.value = '';
          
          await nextTick();
          // Use Bootstrap's modal API
          if (window.$ && window.$('#editVerticalModal')) {
            window.$('#editVerticalModal').modal('show');
          }
        }
      } catch (error) {
        console.error('Error fetching vertical:', error);
        editError.value = 'Unable to load vertical data.';
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      if (window.$ && window.$('#editVerticalModal')) {
        window.$('#editVerticalModal').modal('hide');
      }
    };

    const openAddDivisionModal = async () => {
      newDivision.value = {
        name: '',
      };
      divisionError.value = '';
      
      await nextTick();
      // Open the add division modal (it will overlay the add vertical modal)
      if (window.$ && window.$('#addDivisionModal')) {
        window.$('#addDivisionModal').modal('show');
      }
    };

    const closeAddDivisionModal = () => {
      divisionError.value = '';
      if (window.$ && window.$('#addDivisionModal')) {
        window.$('#addDivisionModal').modal('hide');
      }
    };

    const handleAddDivision = async () => {
      if (!newDivision.value.name.trim()) {
        divisionError.value = 'Division name cannot be blank.';
        return;
      }

      addingDivision.value = true;
      divisionError.value = '';

      try {
        const response = await axios.post('/api/divisions', {
          name: newDivision.value.name.trim(),
        });

        if (response.data.status === 1) {
          // Refresh divisions list
          await fetchDivisions();
          
          // Auto-select the newly added division
          await nextTick();
          newVertical.value.divisionId = response.data.data.divisionId;
          
          // Close the add division modal
          closeAddDivisionModal();
        } else {
          divisionError.value = response.data.error || 'Failed to add division.';
        }
      } catch (error) {
        divisionError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add division.';
      } finally {
        addingDivision.value = false;
      }
    };

    const handleAddVertical = async () => {
      if (!newVertical.value.divisionId || !newVertical.value.name.trim()) {
        addError.value = 'Please fill in all required fields.';
        return;
      }

      adding.value = true;
      addError.value = '';

      try {
        const response = await axios.post('/api/verticals', {
          name: newVertical.value.name.trim(),
          divisionId: parseInt(newVertical.value.divisionId),
        });

        if (response.data.status === 1) {
          // Close modal
          closeAddModal();
          // Refresh data
          await fetchVerticals();
        } else {
          addError.value = response.data.error || 'Failed to add vertical.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add vertical.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateVertical = async () => {
      if (!editingVertical.value.name.trim()) {
        editError.value = 'Vertical name cannot be blank.';
        return;
      }

      updating.value = true;
      editError.value = '';

      try {
        const response = await axios.put(
          `/api/verticals/${editingVertical.value.verticalId}`,
          {
            name: editingVertical.value.name.trim(),
          }
        );

        if (response.data.status === 1) {
          // Close modal
          closeEditModal();
          // Refresh data
          await fetchVerticals();
        } else {
          editError.value = response.data.error || 'Failed to update vertical.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update vertical.';
      } finally {
        updating.value = false;
      }
    };

    onMounted(async () => {
      await fetchDivisions();
      await fetchVerticals();
    });

    return {
      divisions,
      allDivisions,
      loading,
      adding,
      updating,
      addError,
      editError,
      newVertical,
      editingVertical,
      newDivision,
      addingDivision,
      divisionError,
      openAddModal,
      closeAddModal,
      openEditModal,
      closeEditModal,
      openAddDivisionModal,
      closeAddDivisionModal,
      handleAddDivision,
      handleAddVertical,
      handleUpdateVertical,
    };
  },
};
</script>

<style scoped>
.division-section {
  margin-bottom: 30px;
}

.division-section h3 {
  margin-top: 20px;
  margin-bottom: 10px;
  font-weight: bold;
  color: #333;
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

.btn-link {
  color: #429038;
  text-decoration: none;
}

.btn-link:hover,
.btn-link:focus {
  color: #357a2e;
  text-decoration: underline;
}

</style>
