<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Field Management</h2>

      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new custom field
        </button>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray header">
              <th>Name</th>
              <th>Type</th>
              <th>Description</th>
              <th>Definition</th>
              <th>Format</th>
              <th>Options</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="fields.length === 0">
              <td colspan="6" class="text-center">No fields found</td>
            </tr>
            <tr v-for="field in fields" :key="field.fieldId">
              <td>{{ field.fieldName }}</td>
              <td>{{ capitalize(field.fieldType) }}</td>
              <td>{{ field.fieldDescription }}</td>
              <td>{{ field.fieldDefinition }}</td>
              <td>{{ field.fieldFormat || '-' }}</td>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-primary btn-xs"
                  @click="openEditModal(field)"
                >
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Add Field Modal -->
      <div
        class="modal fade"
        id="newFieldModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newFieldModalTitle"
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
              <h4 class="modal-title" id="newFieldModalTitle">
                Add a new custom field
              </h4>
            </div>
            <div class="modal-body">
              <div class="alert alert-info">
                <p><strong>Custom field names are case sensitive.</strong> Your vendor will need to send the field with the exact capitalization you specify here. We recommend using all lowercase letters, but this is not required. Valid characters are lowercase and uppercase letters, numbers, underscores, and dashes.</p>
                <p>Custom field names are automatically prepended with a "c_". So if you name the field "income_level", the API specs will expect the field to come over as "c_income_level".</p>
                <p>Once a custom field is added, the field cannot be removed or renamed since this may affect existing feeds already using that field.</p>
                <p>Custom fields cannot be searched by in the "Record Search" feature. If you think you will need a field to be searchable in the future, please ask for it to be added as a "System" field instead.</p>
              </div>
              <form id="newFieldForm" @submit.prevent="handleAddField">
                <div class="form-group">
                  <label for="newFieldType">Type</label>
                  <input
                    id="newFieldType"
                    type="text"
                    class="form-control"
                    value="Custom"
                    disabled
                  />
                </div>
                <div class="form-group">
                  <label for="newFieldName">Field Name</label>
                  <input
                    id="newFieldName"
                    v-model="newField.fieldName"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter field name (without c_ prefix)"
                  />
                  <small class="help-block">Will be automatically prefixed with "c_"</small>
                </div>
                <div class="form-group">
                  <label for="newFieldDescription">Description</label>
                  <input
                    id="newFieldDescription"
                    v-model="newField.fieldDescription"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter field description"
                  />
                </div>
                <div class="form-group">
                  <label for="newFieldFormat">Format</label>
                  <input
                    id="newFieldFormat"
                    v-model="newField.fieldFormat"
                    type="text"
                    class="form-control"
                    placeholder="Enter field format (optional)"
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
                @click="handleAddField"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add Field' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Field Modal -->
      <div
        class="modal fade"
        id="editFieldModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editFieldModalTitle"
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
              <h4 class="modal-title" id="editFieldModalTitle">
                Edit a field
              </h4>
            </div>
            <div class="modal-body">
              <div class="alert alert-info">
                <p>Once a custom field is added, the field cannot be removed or renamed since this may affect existing feeds already using that field.</p>
              </div>
              <form id="editFieldForm" @submit.prevent="handleUpdateField">
                <div class="form-group">
                  <label>Type</label>
                  <input
                    type="text"
                    class="form-control"
                    :value="capitalize(editingField.fieldType)"
                    disabled
                  />
                </div>
                <div class="form-group">
                  <label>Field Name</label>
                  <input
                    type="text"
                    class="form-control"
                    :value="editingField.fieldName"
                    disabled
                  />
                </div>
                <div class="form-group">
                  <label for="editFieldDescription">Description</label>
                  <input
                    id="editFieldDescription"
                    v-model="editingField.fieldDescription"
                    type="text"
                    class="form-control"
                    required
                    placeholder="Enter field description"
                  />
                </div>
                <div class="form-group">
                  <label for="editFieldFormat">Format</label>
                  <input
                    id="editFieldFormat"
                    v-model="editingField.fieldFormat"
                    type="text"
                    class="form-control"
                    placeholder="Enter field format (optional)"
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
                @click="handleUpdateField"
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
  name: 'FieldManagement',
  components: {
    Navigation,
  },
  setup() {
    const fields = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');

    const newField = ref({
      fieldName: '',
      fieldDescription: '',
      fieldFormat: '',
    });

    const editingField = ref({
      fieldId: null,
      fieldName: '',
      fieldType: '',
      fieldDescription: '',
      fieldFormat: '',
    });

    const capitalize = (str) => {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const fetchFields = async () => {
      loading.value = true;
      try {
        const response = await axios.get('/api/fields');
        if (response.data.status === 1) {
          fields.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching fields:', error);
        fields.value = [];
      } finally {
        loading.value = false;
      }
    };

    const openAddModal = async () => {
      newField.value = {
        fieldName: '',
        fieldDescription: '',
        fieldFormat: '',
      };
      addError.value = '';
      
      await nextTick();
      if (window.$ && window.$('#newFieldModal')) {
        window.$('#newFieldModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newFieldModal')) {
        window.$('#newFieldModal').modal('hide');
      }
    };

    const openEditModal = async (field) => {
      try {
        const response = await axios.get(`/api/fields/${field.fieldId}`);
        if (response.data.status === 1) {
          editingField.value = {
            fieldId: response.data.data.fieldId,
            fieldName: response.data.data.fieldName,
            fieldType: response.data.data.fieldType,
            fieldDescription: response.data.data.fieldDescription,
            fieldFormat: response.data.data.fieldFormat || '',
          };
          editError.value = '';
          
          await nextTick();
          if (window.$ && window.$('#editFieldModal')) {
            window.$('#editFieldModal').modal('show');
          }
        }
      } catch (error) {
        console.error('Error fetching field:', error);
        editError.value = 'Unable to load field data.';
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      if (window.$ && window.$('#editFieldModal')) {
        window.$('#editFieldModal').modal('hide');
      }
    };

    const handleAddField = async () => {
      if (!newField.value.fieldName.trim() || !newField.value.fieldDescription.trim()) {
        addError.value = 'Please fill in all required fields.';
        return;
      }

      adding.value = true;
      addError.value = '';

      try {
        const response = await axios.post('/api/fields', {
          fieldName: newField.value.fieldName.trim(),
          fieldDescription: newField.value.fieldDescription.trim(),
          fieldFormat: newField.value.fieldFormat.trim() || null,
        });

        if (response.data.status === 1) {
          closeAddModal();
          await fetchFields();
        } else {
          addError.value = response.data.error || 'Failed to add field.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add field.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateField = async () => {
      if (!editingField.value.fieldDescription.trim()) {
        editError.value = 'Field description cannot be blank.';
        return;
      }

      updating.value = true;
      editError.value = '';

      try {
        const response = await axios.put(
          `/api/fields/${editingField.value.fieldId}`,
          {
            fieldDescription: editingField.value.fieldDescription.trim(),
            fieldFormat: editingField.value.fieldFormat.trim() || null,
          }
        );

        if (response.data.status === 1) {
          closeEditModal();
          await fetchFields();
        } else {
          editError.value = response.data.error || 'Failed to update field.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update field.';
      } finally {
        updating.value = false;
      }
    };

    onMounted(async () => {
      await fetchFields();
    });

    return {
      fields,
      loading,
      adding,
      updating,
      addError,
      editError,
      newField,
      editingField,
      capitalize,
      openAddModal,
      closeAddModal,
      openEditModal,
      closeEditModal,
      handleAddField,
      handleUpdateField,
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

.alert-info {
  background-color: #d9edf7;
  border-color: #bce8f1;
  color: #31708f;
  font-size: 12px;
}

.alert-info p {
  margin-bottom: 8px;
}

.alert-info p:last-child {
  margin-bottom: 0;
}

.help-block {
  display: block;
  margin-top: 5px;
  margin-bottom: 10px;
  color: #737373;
  font-size: 12px;
}
</style>
